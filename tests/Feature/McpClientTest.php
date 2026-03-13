<?php

use App\Services\Mcp\McpClientService;
use App\Services\Mcp\McpConnectionException;
use App\Services\Mcp\McpError;
use App\Services\Mcp\McpMessage;
use App\Services\Mcp\McpResponse;
use App\Services\Mcp\McpServerManager;
use App\Services\Mcp\McpToolDefinition;
use App\Services\Mcp\McpToolResult;
use App\Services\Mcp\McpTransportInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --- McpMessage tests ---

test('McpMessage creates a request with JSON-RPC 2.0 format', function () {
    $msg = McpMessage::request('tools/list', ['cursor' => null], 42);

    $array = $msg->toArray();

    expect($array)->toMatchArray([
        'jsonrpc' => '2.0',
        'method' => 'tools/list',
        'params' => ['cursor' => null],
        'id' => 42,
    ]);
});

test('McpMessage creates a notification without id', function () {
    $msg = McpMessage::notification('notifications/initialized');

    expect($msg->isNotification())->toBeTrue();

    $array = $msg->toArray();
    expect($array)->toHaveKeys(['jsonrpc', 'method']);
    expect($array)->not->toHaveKey('id');
});

test('McpMessage serializes to JSON', function () {
    $msg = McpMessage::request('ping', [], 1);

    $json = $msg->toJson();
    $decoded = json_decode($json, true);

    expect($decoded['jsonrpc'])->toBe('2.0');
    expect($decoded['method'])->toBe('ping');
    expect($decoded['id'])->toBe(1);
});

test('McpMessage parses successful response', function () {
    $response = McpMessage::parseResponse([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => ['tools' => [['name' => 'read_file', 'description' => 'Read a file', 'inputSchema' => ['type' => 'object']]]],
    ]);

    expect($response)->toBeInstanceOf(McpResponse::class);
    expect($response->isSuccess())->toBeTrue();
    expect($response->isError())->toBeFalse();
    expect($response->result['tools'])->toHaveCount(1);
});

test('McpMessage parses error response', function () {
    $response = McpMessage::parseResponse([
        'jsonrpc' => '2.0',
        'id' => 1,
        'error' => ['code' => -32601, 'message' => 'Method not found'],
    ]);

    expect($response->isError())->toBeTrue();
    expect($response->error)->toBeInstanceOf(McpError::class);
    expect($response->error->code)->toBe(-32601);
    expect($response->error->message)->toBe('Method not found');
});

// --- McpToolDefinition tests ---

test('McpToolDefinition creates from array', function () {
    $tool = McpToolDefinition::fromArray([
        'name' => 'read_file',
        'description' => 'Read contents of a file',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'path' => ['type' => 'string', 'description' => 'File path'],
            ],
            'required' => ['path'],
        ],
    ]);

    expect($tool->name)->toBe('read_file');
    expect($tool->description)->toBe('Read contents of a file');
    expect($tool->inputSchema['type'])->toBe('object');
    expect($tool->toArray())->toHaveKeys(['name', 'description', 'inputSchema']);
});

// --- McpToolResult tests ---

test('McpToolResult creates from successful response', function () {
    $response = new McpResponse(
        id: 1,
        result: [
            'content' => [['type' => 'text', 'text' => 'Hello, world!']],
            'isError' => false,
        ],
        error: null,
    );

    $result = McpToolResult::fromResponse($response);

    expect($result->isError)->toBeFalse();
    expect($result->text())->toBe('Hello, world!');
});

test('McpToolResult creates from error response', function () {
    $response = new McpResponse(
        id: 1,
        result: null,
        error: new McpError(code: -1, message: 'Tool execution failed'),
    );

    $result = McpToolResult::fromResponse($response);

    expect($result->isError)->toBeTrue();
    expect($result->text())->toContain('Tool execution failed');
});

// --- McpClientService tests with mock transport ---

test('McpClientService connects and initializes via transport', function () {
    $transport = createMockTransport([
        // initialize response
        ['id' => 1, 'result' => ['protocolVersion' => '2024-11-05', 'capabilities' => new stdClass, 'serverInfo' => ['name' => 'test', 'version' => '1.0']]],
    ]);

    $client = new McpClientService;
    $client->connectWithTransport($transport);

    expect($client->isConnected())->toBeTrue();

    $client->disconnect();
    expect($client->isConnected())->toBeFalse();
});

test('McpClientService lists tools', function () {
    $transport = createMockTransport([
        // initialize
        ['id' => 1, 'result' => ['protocolVersion' => '2024-11-05', 'serverInfo' => ['name' => 'test', 'version' => '1.0']]],
        // tools/list
        ['id' => 2, 'result' => ['tools' => [
            ['name' => 'read_file', 'description' => 'Read a file', 'inputSchema' => ['type' => 'object']],
            ['name' => 'write_file', 'description' => 'Write a file', 'inputSchema' => ['type' => 'object']],
        ]]],
    ]);

    $client = new McpClientService;
    $client->connectWithTransport($transport);

    $tools = $client->listTools();

    expect($tools)->toHaveCount(2);
    expect($tools[0]->name)->toBe('read_file');
    expect($tools[1]->name)->toBe('write_file');

    // Second call should return cached tools
    $tools2 = $client->listTools();
    expect($tools2)->toHaveCount(2);

    $client->disconnect();
});

test('McpClientService calls a tool', function () {
    $transport = createMockTransport([
        // initialize
        ['id' => 1, 'result' => ['protocolVersion' => '2024-11-05', 'serverInfo' => ['name' => 'test', 'version' => '1.0']]],
        // tools/call
        ['id' => 2, 'result' => ['content' => [['type' => 'text', 'text' => 'file contents here']]]],
    ]);

    $client = new McpClientService;
    $client->connectWithTransport($transport);

    $result = $client->callTool('read_file', ['path' => '/tmp/test.txt']);

    expect($result->isError)->toBeFalse();
    expect($result->text())->toBe('file contents here');

    $client->disconnect();
});

test('McpClientService throws when not connected', function () {
    $client = new McpClientService;

    $client->listTools();
})->throws(McpConnectionException::class);

test('McpClientService handles initialization failure', function () {
    $transport = createMockTransport([
        ['id' => 1, 'error' => ['code' => -1, 'message' => 'Unsupported protocol version']],
    ]);

    $client = new McpClientService;
    $client->connectWithTransport($transport);
})->throws(McpConnectionException::class, 'MCP initialization failed');

// --- McpServerManager tests ---

test('McpServerManager tracks active connections', function () {
    $manager = new McpServerManager;

    expect($manager->activeCount())->toBe(0);

    $manager->disconnectAll();
});

test('McpServerManager prunes idle connections', function () {
    $manager = new McpServerManager;
    $manager->setIdleTimeout(0); // Immediate timeout

    $pruned = $manager->pruneIdle();
    expect($pruned)->toBe(0);
});

// --- MCP API endpoint tests ---

test('MCP tools endpoint returns 502 when server is unreachable', function () {
    $project = \App\Models\Project::create(['name' => 'MCP Test', 'path' => '/tmp/mcp-test']);
    $server = $project->mcpServers()->create([
        'name' => 'test-server',
        'transport' => 'stdio',
        'command' => '/nonexistent/command',
        'args' => [],
    ]);

    $response = $this->actingAs(\App\Models\User::factory()->create())
        ->getJson("/api/projects/{$project->id}/mcp-servers/{$server->id}/tools");

    $response->assertStatus(502);
    $response->assertJsonStructure(['tools', 'server', 'error']);
    expect($response->json('server.connected'))->toBeFalse();
});

test('MCP ping endpoint works for non-connected server', function () {
    $project = \App\Models\Project::create(['name' => 'MCP Test', 'path' => '/tmp/mcp-test']);
    $server = $project->mcpServers()->create([
        'name' => 'test-server',
        'transport' => 'stdio',
        'command' => '/nonexistent/command',
    ]);

    $response = $this->actingAs(\App\Models\User::factory()->create())
        ->postJson("/api/projects/{$project->id}/mcp-servers/{$server->id}/ping");

    $response->assertOk();
    expect($response->json('connected'))->toBeFalse();
});

// --- Helper: Mock Transport ---

function createMockTransport(array $responses): McpTransportInterface
{
    return new class($responses) implements McpTransportInterface
    {
        private bool $connected = false;

        private int $responseIndex = 0;

        public function __construct(private array $responses) {}

        public function connect(): void
        {
            $this->connected = true;
        }

        public function send(McpMessage $message): ?McpResponse
        {
            if ($message->isNotification()) {
                return null;
            }

            if ($this->responseIndex >= count($this->responses)) {
                throw new McpConnectionException('No more mock responses available');
            }

            $data = $this->responses[$this->responseIndex++];
            $data['jsonrpc'] = '2.0';

            return McpMessage::parseResponse($data);
        }

        public function disconnect(): void
        {
            $this->connected = false;
        }

        public function isConnected(): bool
        {
            return $this->connected;
        }
    };
}
