<?php

use App\Models\Agent;
use App\Models\Project;
use App\Models\ProjectA2aAgent;
use App\Models\User;
use App\Services\A2a\A2aClientService;
use App\Services\A2a\A2aTaskResult;
use App\Services\A2a\AgentCard;
use App\Services\Execution\ToolDispatcher;
use App\Services\Mcp\McpServerManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create(['name' => 'A2A Test', 'path' => '/tmp/a2a-test']);
    $this->agent = Agent::create([
        'name' => 'A2A Agent',
        'slug' => 'a2a-agent',
        'role' => 'assistant',
        'model' => 'claude-sonnet-4-6',
        'base_instructions' => 'You are a test agent.',
    ]);
});

// --- AgentCard tests ---

test('AgentCard parses from array', function () {
    $data = [
        'name' => 'Weather Agent',
        'description' => 'Provides weather information',
        'version' => '1.0.0',
        'provider' => ['organization' => 'Acme Corp'],
        'skills' => [
            ['id' => 'weather', 'name' => 'Get Weather', 'description' => 'Fetches current weather'],
            ['id' => 'forecast', 'name' => 'Get Forecast', 'description' => 'Fetches 5-day forecast'],
        ],
        'capabilities' => ['streaming', 'push-notifications'],
    ];

    $card = AgentCard::fromArray($data, 'https://weather.example.com');

    expect($card->name)->toBe('Weather Agent');
    expect($card->url)->toBe('https://weather.example.com');
    expect($card->description)->toBe('Provides weather information');
    expect($card->version)->toBe('1.0.0');
    expect($card->provider)->toBe('Acme Corp');
    expect($card->skills)->toHaveCount(2);
    expect($card->skillNames())->toBe(['Get Weather', 'Get Forecast']);
    expect($card->hasCapability('streaming'))->toBeTrue();
    expect($card->hasCapability('batch'))->toBeFalse();
});

test('AgentCard handles minimal data', function () {
    $card = AgentCard::fromArray([], 'https://agent.example.com');

    expect($card->name)->toBe('Unknown Agent');
    expect($card->url)->toBe('https://agent.example.com');
    expect($card->description)->toBeNull();
    expect($card->skills)->toBeEmpty();
});

// --- A2aTaskResult tests ---

test('A2aTaskResult parses success response', function () {
    $data = [
        'result' => [
            'id' => 'task-123',
            'status' => [
                'state' => 'completed',
                'message' => [
                    'role' => 'agent',
                    'parts' => [
                        ['type' => 'text', 'text' => 'The weather is sunny'],
                    ],
                ],
            ],
        ],
    ];

    $result = A2aTaskResult::fromResponse($data);

    expect($result->taskId)->toBe('task-123');
    expect($result->status)->toBe('completed');
    expect($result->isCompleted())->toBeTrue();
    expect($result->isFailed())->toBeFalse();
    expect($result->text())->toBe('The weather is sunny');
});

test('A2aTaskResult parses error response', function () {
    $result = A2aTaskResult::error('Connection refused');

    expect($result->isFailed())->toBeTrue();
    expect($result->text())->toContain('Connection refused');
});

test('A2aTaskResult handles working state', function () {
    $data = [
        'result' => [
            'id' => 'task-456',
            'status' => ['state' => 'working'],
        ],
    ];

    $result = A2aTaskResult::fromResponse($data);

    expect($result->isWorking())->toBeTrue();
    expect($result->isCompleted())->toBeFalse();
});

test('A2aTaskResult extracts text from artifacts', function () {
    $data = [
        'result' => [
            'id' => 'task-789',
            'status' => ['state' => 'completed'],
            'artifacts' => [
                [
                    'parts' => [
                        ['type' => 'text', 'text' => 'Artifact content here'],
                    ],
                ],
            ],
        ],
    ];

    $result = A2aTaskResult::fromResponse($data);
    expect($result->text())->toBe('Artifact content here');
});

// --- A2aClientService tests (with HTTP fakes) ---

test('A2aClientService discovers agent card', function () {
    Http::fake([
        'https://agent.example.com/.well-known/agent.json' => Http::response([
            'name' => 'Test Agent',
            'description' => 'A test agent',
            'skills' => [
                ['id' => 'greet', 'name' => 'Greet', 'description' => 'Says hello'],
            ],
        ]),
    ]);

    $client = new A2aClientService;
    $card = $client->discover('https://agent.example.com');

    expect($card->name)->toBe('Test Agent');
    expect($card->skillNames())->toBe(['Greet']);
});

test('A2aClientService handles discovery failure', function () {
    Http::fake([
        'https://bad.example.com/.well-known/agent.json' => Http::response('Not Found', 404),
    ]);

    $client = new A2aClientService;

    expect(fn () => $client->discover('https://bad.example.com'))
        ->toThrow(\RuntimeException::class, 'Failed to fetch agent card');
});

test('A2aClientService sends task successfully', function () {
    Http::fake([
        'https://agent.example.com' => Http::response([
            'jsonrpc' => '2.0',
            'id' => 'req-1',
            'result' => [
                'id' => 'task-100',
                'status' => [
                    'state' => 'completed',
                    'message' => [
                        'role' => 'agent',
                        'parts' => [['type' => 'text', 'text' => 'Task done!']],
                    ],
                ],
            ],
        ]),
    ]);

    $client = new A2aClientService;
    $result = $client->sendTask('https://agent.example.com', 'Do something');

    expect($result->isCompleted())->toBeTrue();
    expect($result->text())->toBe('Task done!');
});

test('A2aClientService handles JSON-RPC error', function () {
    Http::fake([
        'https://agent.example.com' => Http::response([
            'jsonrpc' => '2.0',
            'id' => 'req-1',
            'error' => [
                'code' => -32600,
                'message' => 'Invalid request',
            ],
        ]),
    ]);

    $client = new A2aClientService;
    $result = $client->sendTask('https://agent.example.com', 'Bad request');

    expect($result->isFailed())->toBeTrue();
    expect($result->text())->toContain('Invalid request');
});

test('A2aClientService delegates task from model', function () {
    Http::fake([
        'https://helper.example.com' => Http::response([
            'jsonrpc' => '2.0',
            'result' => [
                'id' => 'task-200',
                'status' => [
                    'state' => 'completed',
                    'message' => [
                        'role' => 'agent',
                        'parts' => [['type' => 'text', 'text' => 'Delegated result']],
                    ],
                ],
            ],
        ]),
    ]);

    $a2aAgent = ProjectA2aAgent::create([
        'project_id' => $this->project->id,
        'name' => 'Helper Agent',
        'url' => 'https://helper.example.com',
        'description' => 'Helps with tasks',
    ]);

    $client = new A2aClientService;
    $result = $client->delegateTask($a2aAgent, 'Help me');

    expect($result->isCompleted())->toBeTrue();
    expect($result->text())->toBe('Delegated result');
});

// --- ToolDispatcher A2A integration tests ---

test('ToolDispatcher registers A2A agents as tools', function () {
    $a2aAgent = ProjectA2aAgent::create([
        'project_id' => $this->project->id,
        'name' => 'search-agent',
        'url' => 'https://search.example.com',
        'description' => 'Searches the web',
        'skills' => ['web-search', 'image-search'],
    ]);

    $dispatcher = new ToolDispatcher(new McpServerManager);
    $dispatcher->registerA2aAgents([$a2aAgent]);

    expect($dispatcher->registeredTools())->toContain('a2a_search_agent');

    $definitions = $dispatcher->getToolDefinitions();
    expect($definitions)->toHaveCount(1);
    expect($definitions[0]['name'])->toBe('a2a_search_agent');
    expect($definitions[0]['description'])->toContain('Searches the web');
    expect($definitions[0]['description'])->toContain('web-search');
    expect($definitions[0]['input_schema']['properties'])->toHaveKey('message');
});

test('ToolDispatcher dispatches A2A tool call', function () {
    Http::fake([
        'https://code.example.com' => Http::response([
            'jsonrpc' => '2.0',
            'result' => [
                'id' => 'task-300',
                'status' => [
                    'state' => 'completed',
                    'message' => [
                        'role' => 'agent',
                        'parts' => [['type' => 'text', 'text' => 'Code review complete']],
                    ],
                ],
            ],
        ]),
    ]);

    $a2aAgent = ProjectA2aAgent::create([
        'project_id' => $this->project->id,
        'name' => 'code-reviewer',
        'url' => 'https://code.example.com',
    ]);

    $dispatcher = new ToolDispatcher(new McpServerManager);
    $dispatcher->registerA2aAgents([$a2aAgent]);

    $result = $dispatcher->dispatch('a2a_code_reviewer', ['message' => 'Review this code']);

    expect($result->isError)->toBeFalse();
    expect($result->text())->toBe('Code review complete');
});

test('ToolDispatcher handles A2A agent not found', function () {
    $dispatcher = new ToolDispatcher(new McpServerManager);
    // Manually register with a non-existent agent ID
    $dispatcher->registerA2aAgents([]);

    // Force a direct dispatch to non-existent tool
    $result = $dispatcher->dispatch('a2a_nonexistent', ['message' => 'test']);
    expect($result->isError)->toBeTrue();
    expect($result->text())->toContain('Unknown tool');
});
