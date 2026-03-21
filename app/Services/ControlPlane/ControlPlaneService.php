<?php

namespace App\Services\ControlPlane;

use App\Models\AppSetting;
use App\Models\ControlPlaneMessage;
use App\Models\ControlPlaneSession;
use App\Models\User;
use App\Services\LLM\LLMProviderFactory;

class ControlPlaneService
{
    protected const MAX_TOOL_ROUNDS = 5;

    public function __construct(
        protected LLMProviderFactory $providerFactory,
        protected ControlPlaneToolRegistry $toolRegistry,
        protected ControlPlaneActionExecutor $actionExecutor,
    ) {}

    /**
     * Process a chat message in a session, yielding SSE event arrays.
     *
     * @return \Generator<int, array>
     */
    public function chat(ControlPlaneSession $session, string $userMessage, User $user): \Generator
    {
        $startTime = microtime(true);

        // Store user message
        ControlPlaneMessage::create([
            'session_id' => $session->id,
            'role' => 'user',
            'content' => $userMessage,
        ]);

        // Auto-title on first message
        if ($session->messages()->count() <= 1 && empty($session->title)) {
            $title = mb_strlen($userMessage) > 60
                ? mb_substr($userMessage, 0, 57) . '...'
                : $userMessage;
            $session->update(['title' => $title]);
        }

        // Build conversation history for the LLM
        $messages = $this->buildMessages($session);
        $systemPrompt = $this->buildSystemPrompt($session);
        $tools = $this->toolRegistry->tools();
        $model = $this->resolveModel($session);
        $maxTokens = 4096;

        $totalInputTokens = 0;
        $totalOutputTokens = 0;
        $actionsExecuted = [];

        // Tool-use loop: send to LLM, execute tools, feed results back
        for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
            $provider = $this->providerFactory->make($model);
            $response = $provider->chat($systemPrompt, $messages, $model, $maxTokens, $tools);

            $totalInputTokens += $response['usage']['input_tokens'] ?? 0;
            $totalOutputTokens += $response['usage']['output_tokens'] ?? 0;

            $textParts = [];
            $toolCalls = [];

            foreach ($response['content'] as $block) {
                if ($block['type'] === 'text' && ! empty($block['text'])) {
                    $textParts[] = $block['text'];
                } elseif ($block['type'] === 'tool_use') {
                    $toolCalls[] = $block;
                }
            }

            // Emit any text content
            if (! empty($textParts)) {
                $text = implode('', $textParts);
                yield ['type' => 'delta', 'text' => $text];
            }

            // If no tool calls, we're done
            if (empty($toolCalls) || $response['stop_reason'] !== 'tool_use') {
                $fullText = implode('', $textParts);

                // Store assistant message
                ControlPlaneMessage::create([
                    'session_id' => $session->id,
                    'role' => 'assistant',
                    'content' => $fullText,
                    'tool_calls' => ! empty($toolCalls) ? $toolCalls : null,
                    'metadata' => [
                        'input_tokens' => $totalInputTokens,
                        'output_tokens' => $totalOutputTokens,
                        'latency_ms' => (int) ((microtime(true) - $startTime) * 1000),
                        'model' => $model,
                        'tool_rounds' => $round,
                        'actions' => $actionsExecuted,
                    ],
                ]);

                yield ['type' => 'done', 'input_tokens' => $totalInputTokens, 'output_tokens' => $totalOutputTokens];

                return;
            }

            // Add assistant message with tool calls to conversation
            $messages[] = [
                'role' => 'assistant',
                'content' => $response['content'],
            ];

            // Execute each tool call and add results
            $toolResults = [];
            foreach ($toolCalls as $toolCall) {
                $toolName = $toolCall['name'];
                $toolInput = $toolCall['input'] ?? [];
                $toolId = $toolCall['id'];

                yield [
                    'type' => 'tool_call',
                    'tool_name' => $toolName,
                    'tool_id' => $toolId,
                    'input' => $toolInput,
                ];

                $result = $this->actionExecutor->execute($toolName, $toolInput, $user, $session);
                $actionsExecuted[] = ['tool' => $toolName, 'input' => $toolInput, 'success' => ! isset($result['error'])];

                yield [
                    'type' => 'tool_result',
                    'tool_name' => $toolName,
                    'tool_id' => $toolId,
                    'result' => $result,
                ];

                $toolResults[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $toolId,
                    'content' => json_encode($result),
                ];

                // Store tool result message
                ControlPlaneMessage::create([
                    'session_id' => $session->id,
                    'role' => 'tool_result',
                    'content' => json_encode($result),
                    'metadata' => [
                        'tool_name' => $toolName,
                        'tool_id' => $toolId,
                        'input' => $toolInput,
                    ],
                ]);
            }

            // Store assistant's tool-calling message
            ControlPlaneMessage::create([
                'session_id' => $session->id,
                'role' => 'assistant',
                'content' => implode('', $textParts),
                'tool_calls' => $toolCalls,
            ]);

            // Add tool results for the next round
            $messages[] = [
                'role' => 'user',
                'content' => $toolResults,
            ];
        }

        // Max rounds exceeded — emit what we have
        yield ['type' => 'delta', 'text' => "\n\n[Reached maximum tool execution rounds]"];
        yield ['type' => 'done', 'input_tokens' => $totalInputTokens, 'output_tokens' => $totalOutputTokens];
    }

    /**
     * One-shot command: execute without session persistence.
     *
     * @return \Generator<int, array>
     */
    public function quick(string $message, User $user): \Generator
    {
        // Create a temporary session
        $session = ControlPlaneSession::create([
            'user_id' => $user->id,
            'organization_id' => $user->current_organization_id,
            'title' => 'Quick command',
        ]);

        try {
            yield from $this->chat($session, $message, $user);
        } finally {
            // Clean up temporary session and messages
            $session->messages()->delete();
            $session->delete();
        }
    }

    protected function buildSystemPrompt(ControlPlaneSession $session): string
    {
        $context = $session->context ?? [];
        $projectInfo = '';
        if (! empty($context['project_id'])) {
            $projectInfo = "\n\nCurrent active project: {$context['project_name']} (ID: {$context['project_id']})";
        }

        return <<<PROMPT
You are the Control Plane assistant for Orkestr, an AI agent orchestration platform. You help system administrators and developers manage their agents, skills, projects, and executions through natural language commands.

Your capabilities include:
- Listing, creating, starting, stopping, and toggling agents
- Listing, searching, creating, and testing skills
- Starting executions, viewing recent runs, and investigating failures
- Checking system diagnostics, provider health, and fleet status
- Listing and switching between projects, viewing dependency graphs

Guidelines:
- Be concise and actionable in your responses
- When listing items, format them clearly with relevant details
- If a user's intent is ambiguous, ask for clarification before executing
- After executing actions, confirm what was done
- When errors occur, explain what went wrong and suggest fixes
- If asked to do something dangerous (deleting all agents, etc.), confirm first
- Use the available tools to fulfill requests — do not fabricate data
- When referencing IDs, always include the human-readable name too{$projectInfo}
PROMPT;
    }

    protected function buildMessages(ControlPlaneSession $session): array
    {
        $messages = [];

        $dbMessages = $session->messages()
            ->orderBy('created_at')
            ->get();

        foreach ($dbMessages as $msg) {
            if ($msg->role === 'user') {
                $messages[] = ['role' => 'user', 'content' => $msg->content];
            } elseif ($msg->role === 'assistant') {
                if (! empty($msg->tool_calls)) {
                    // Reconstruct content blocks
                    $content = [];
                    if (! empty($msg->content)) {
                        $content[] = ['type' => 'text', 'text' => $msg->content];
                    }
                    foreach ($msg->tool_calls as $tc) {
                        $content[] = $tc;
                    }
                    $messages[] = ['role' => 'assistant', 'content' => $content];
                } else {
                    $messages[] = ['role' => 'assistant', 'content' => $msg->content];
                }
            } elseif ($msg->role === 'tool_result') {
                $messages[] = [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'tool_result',
                            'tool_use_id' => $msg->metadata['tool_id'] ?? '',
                            'content' => $msg->content,
                        ],
                    ],
                ];
            }
        }

        return $messages;
    }

    protected function resolveModel(ControlPlaneSession $session): string
    {
        // Check organization default, then system default
        if ($session->organization) {
            $orgModel = $session->organization->plan_limits['default_model'] ?? null;
            if ($orgModel) {
                return $orgModel;
            }
        }

        return AppSetting::get('default_model', 'claude-sonnet-4-6');
    }
}
