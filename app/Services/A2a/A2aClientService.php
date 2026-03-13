<?php

namespace App\Services\A2a;

use App\Models\ProjectA2aAgent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class A2aClientService
{
    private const TIMEOUT_SECONDS = 30;

    private const AGENT_CARD_PATH = '/.well-known/agent.json';

    /**
     * Discover an agent's capabilities by fetching its agent card.
     */
    public function discover(string $baseUrl): AgentCard
    {
        $cardUrl = rtrim($baseUrl, '/') . self::AGENT_CARD_PATH;

        $response = Http::timeout(self::TIMEOUT_SECONDS)
            ->acceptJson()
            ->get($cardUrl);

        if (! $response->successful()) {
            throw new \RuntimeException("Failed to fetch agent card from {$cardUrl}: HTTP {$response->status()}");
        }

        return AgentCard::fromArray($response->json(), $baseUrl);
    }

    /**
     * Discover from a ProjectA2aAgent model.
     */
    public function discoverFromModel(ProjectA2aAgent $agent): AgentCard
    {
        return $this->discover($agent->url);
    }

    /**
     * Send a task to an A2A agent and wait for the result.
     */
    public function sendTask(string $agentUrl, string $message, array $options = []): A2aTaskResult
    {
        $taskId = $options['task_id'] ?? (string) Str::uuid();

        $payload = [
            'jsonrpc' => '2.0',
            'id' => (string) Str::uuid(),
            'method' => 'tasks/send',
            'params' => [
                'id' => $taskId,
                'message' => [
                    'role' => 'user',
                    'parts' => [
                        ['type' => 'text', 'text' => $message],
                    ],
                ],
            ],
        ];

        // Add session ID if provided
        if (isset($options['session_id'])) {
            $payload['params']['sessionId'] = $options['session_id'];
        }

        $response = Http::timeout($options['timeout'] ?? self::TIMEOUT_SECONDS)
            ->acceptJson()
            ->post(rtrim($agentUrl, '/'), $payload);

        if (! $response->successful()) {
            return A2aTaskResult::error("HTTP {$response->status()}: {$response->body()}");
        }

        $data = $response->json();

        // Check for JSON-RPC error
        if (isset($data['error'])) {
            return A2aTaskResult::error($data['error']['message'] ?? 'Unknown A2A error');
        }

        return A2aTaskResult::fromResponse($data);
    }

    /**
     * Send a task using a ProjectA2aAgent model.
     */
    public function delegateTask(ProjectA2aAgent $agent, string $message, array $options = []): A2aTaskResult
    {
        try {
            return $this->sendTask($agent->url, $message, $options);
        } catch (\Throwable $e) {
            Log::error("A2A delegation failed for {$agent->name}: {$e->getMessage()}");

            return A2aTaskResult::error("A2A delegation failed: {$e->getMessage()}");
        }
    }

    /**
     * Get the status of an existing task.
     */
    public function getTaskStatus(string $agentUrl, string $taskId): A2aTaskResult
    {
        $payload = [
            'jsonrpc' => '2.0',
            'id' => (string) Str::uuid(),
            'method' => 'tasks/get',
            'params' => [
                'id' => $taskId,
            ],
        ];

        $response = Http::timeout(self::TIMEOUT_SECONDS)
            ->acceptJson()
            ->post(rtrim($agentUrl, '/'), $payload);

        if (! $response->successful()) {
            return A2aTaskResult::error("HTTP {$response->status()}");
        }

        $data = $response->json();

        if (isset($data['error'])) {
            return A2aTaskResult::error($data['error']['message'] ?? 'Unknown error');
        }

        return A2aTaskResult::fromResponse($data);
    }

    /**
     * Cancel an existing task.
     */
    public function cancelTask(string $agentUrl, string $taskId): A2aTaskResult
    {
        $payload = [
            'jsonrpc' => '2.0',
            'id' => (string) Str::uuid(),
            'method' => 'tasks/cancel',
            'params' => [
                'id' => $taskId,
            ],
        ];

        $response = Http::timeout(self::TIMEOUT_SECONDS)
            ->acceptJson()
            ->post(rtrim($agentUrl, '/'), $payload);

        if (! $response->successful()) {
            return A2aTaskResult::error("HTTP {$response->status()}");
        }

        return A2aTaskResult::fromResponse($response->json());
    }
}
