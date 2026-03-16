<?php

namespace App\Jobs;

use App\Models\Agent;
use App\Models\ExecutionRun;
use App\Models\ExecutionStep;
use App\Models\Project;
use App\Models\ProjectA2aAgent;
use App\Models\ProjectMcpServer;
use App\Services\AgentComposeService;
use App\Services\AuditLogger;
use App\Services\Execution\BudgetEnforcer;
use App\Services\Execution\CostCalculator;
use App\Services\Execution\Guards\ApprovalGuard;
use App\Services\Execution\Guards\DataAccessGuard;
use App\Services\Execution\Guards\OutputGuard;
use App\Services\Execution\Guards\ToolGuard;
use App\Services\Execution\ToolCallResult;
use App\Services\Execution\ToolDispatcher;
use App\Services\LLM\CostOptimizedRouter;
use App\Services\LLM\LLMProviderFactory;
use App\Services\LLM\ModelRouter;
use App\Services\Mcp\McpConnectionPool;
use App\Services\Mcp\McpServerManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RunAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 3600;

    public array $backoff = [10, 30, 60];

    public function __construct(
        public int $projectId,
        public int $agentId,
        public array $input,
        public string $triggerType,  // manual, schedule, webhook, a2a
        public string $executionId,
        public ?int $createdBy = null,
        public array $config = [],
    ) {}

    public function handle(
        AgentComposeService $composeService,
        ModelRouter $modelRouter,
        CostOptimizedRouter $costRouter,
        McpServerManager $serverManager,
        BudgetEnforcer $budgetEnforcer,
        CostCalculator $costCalculator,
    ): void {
        $project = Project::find($this->projectId);
        $agent = Agent::find($this->agentId);

        if (! $project || ! $agent) {
            Log::error("RunAgentJob: project or agent not found", [
                'project_id' => $this->projectId,
                'agent_id' => $this->agentId,
            ]);

            return;
        }

        // Find or create execution run
        $run = ExecutionRun::where('uuid', $this->executionId)->first();
        if (! $run) {
            $run = ExecutionRun::create([
                'uuid' => $this->executionId,
                'project_id' => $project->id,
                'agent_id' => $agent->id,
                'input' => $this->input,
                'config' => array_merge($this->config, ['trigger_type' => $this->triggerType]),
                'created_by' => $this->createdBy,
            ]);
        }

        $this->publishEvent($run, 'status', ['status' => 'running']);
        $run->markRunning();

        AuditLogger::log('agent.executed', "Agent '{$agent->name}' async execution started", [
            'run_id' => $run->id,
            'agent_slug' => $agent->slug,
            'trigger_type' => $this->triggerType,
            'input' => $this->input,
        ], $agent->id, $project->id);

        try {
            $this->runLoop(
                $run, $project, $agent,
                $composeService, $modelRouter, $costRouter,
                $serverManager, $budgetEnforcer, $costCalculator,
            );
        } catch (\Throwable $e) {
            $run->markFailed($e->getMessage());
            $this->publishEvent($run, 'status', ['status' => 'failed', 'error' => $e->getMessage()]);
            Log::error("RunAgentJob failed: {$e->getMessage()}", [
                'run_id' => $run->id,
                'agent' => $agent->slug,
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            // Release all MCP connections for this execution
            McpConnectionPool::release($this->executionId);
        }
    }

    private function runLoop(
        ExecutionRun $run,
        Project $project,
        Agent $agent,
        AgentComposeService $composeService,
        ModelRouter $modelRouter,
        CostOptimizedRouter $costRouter,
        McpServerManager $serverManager,
        BudgetEnforcer $budgetEnforcer,
        CostCalculator $costCalculator,
    ): void {
        $projectAgent = $project->projectAgents()->where('agent_id', $agent->id)->first();

        // Resolve config with project overrides
        $model = $projectAgent?->model_override ?? $agent->model ?? 'claude-sonnet-4-6';
        $maxIterations = $projectAgent?->max_iterations_override ?? $agent->max_iterations ?? 10;
        $maxTokens = $this->config['max_tokens'] ?? $agent->max_tokens ?? 4096;
        $timeout = $agent->timeout_seconds ?? 300;

        // Setup tool dispatcher
        $dispatcher = $this->setupToolDispatcher($project, $agent, $serverManager);

        // Setup tool guard and filter allowed tools
        $toolGuard = new ToolGuard;
        $toolGuard->configure($this->config);
        $toolGuard->configureForAgent($agent);
        $toolDefinitions = $toolGuard->filterTools($dispatcher->getToolDefinitions());

        // Build system prompt
        $composed = $composeService->composeStructured($project, $agent);
        $systemPrompt = $composed['system_prompt'] ?? '';

        // Build initial messages
        $messages = [];
        $userMessage = $this->input['message'] ?? $this->input['goal'] ?? json_encode($this->input);
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        // Resolve fallback chain
        $fallbackChain = $this->config['fallback_models'] ?? $agent->fallback_models ?? [];
        $routingStrategy = $agent->routing_strategy ?? 'default';
        $reorderedModels = $costRouter->selectModels($model, $fallbackChain, $routingStrategy);
        $model = $reorderedModels[0];
        $fallbackChain = array_slice($reorderedModels, 1);

        $stepNumber = 0;
        $deadline = microtime(true) + $timeout;
        $approvalGuard = new ApprovalGuard;
        $dataAccessGuard = new DataAccessGuard;

        for ($iteration = 0; $iteration < $maxIterations; $iteration++) {
            if (microtime(true) > $deadline) {
                $run->markFailed("Execution timeout after {$timeout} seconds");
                $this->publishEvent($run, 'status', ['status' => 'failed', 'error' => 'timeout']);

                return;
            }

            // Check cancellation flag
            $freshRun = $run->fresh();
            if ($freshRun->isCancelled()) {
                $this->publishEvent($run, 'status', ['status' => 'cancelled']);

                return;
            }

            // Budget enforcement
            $costUsd = $freshRun->total_cost_microcents / 1_000_000;
            $budgetResult = $budgetEnforcer->check($agent, $freshRun->total_tokens, $costUsd);
            if (! $budgetResult['allowed']) {
                $reason = $budgetResult['reason'];
                AuditLogger::log('agent.budget_exceeded', "Budget exceeded for agent '{$agent->name}': {$reason}", [
                    'run_id' => $run->id,
                ], $agent->id, $project->id);
                $run->markFailed("Budget guardrail: {$reason}");
                $this->publishEvent($run, 'status', ['status' => 'failed', 'error' => "budget: {$reason}"]);

                return;
            }

            // --- PERCEIVE ---
            $stepNumber++;
            $perceiveStep = $this->recordStep($run, $stepNumber, 'perceive', [
                'messages_count' => count($messages),
                'tools_count' => count($toolDefinitions),
                'iteration' => $iteration,
            ]);
            $perceiveStep->markCompleted(['context_assembled' => true]);

            // --- REASON ---
            $stepNumber++;
            $reasonStep = $this->recordStep($run, $stepNumber, 'reason', [
                'model' => $model,
                'max_tokens' => $maxTokens,
            ]);
            $reasonStep->markRunning();
            $this->publishEvent($run, 'step', [
                'step_number' => $stepNumber,
                'phase' => 'reason',
                'status' => 'running',
                'model' => $model,
            ]);

            $reasonStart = microtime(true);
            try {
                $llmResponse = $modelRouter->chatWithFallback(
                    $systemPrompt,
                    $messages,
                    $model,
                    $maxTokens,
                    $toolDefinitions,
                    $fallbackChain,
                );
            } catch (\Throwable $e) {
                $reasonStep->markFailed($e->getMessage());
                $run->markFailed("LLM call failed: {$e->getMessage()}");
                $this->publishEvent($run, 'status', ['status' => 'failed', 'error' => $e->getMessage()]);

                return;
            }

            $actualModelUsed = $llmResponse['model_used'] ?? $model;
            $reasonDuration = (int) ((microtime(true) - $reasonStart) * 1000);
            $reasonStep->markCompleted([
                'content' => $llmResponse['content'],
                'stop_reason' => $llmResponse['stop_reason'],
                'usage' => $llmResponse['usage'],
            ], $reasonDuration);

            $reasonStep->update([
                'token_usage' => $llmResponse['usage'],
                'model_used' => $actualModelUsed,
                'model_requested' => $model,
            ]);
            $run->addTokenUsage($llmResponse['usage']);

            // Calculate and record cost
            $stepCost = $costCalculator->calculate($actualModelUsed, $llmResponse['usage']);
            $run->increment('total_cost_microcents', $stepCost);
            $budgetEnforcer->recordCost($agent->id, $stepCost / 1_000_000);

            if (! $run->model_used) {
                $run->update(['model_used' => $actualModelUsed]);
            }

            // Publish usage update
            $this->publishEvent($run, 'usage', [
                'total_tokens' => $run->fresh()->total_tokens,
                'total_cost_microcents' => $run->fresh()->total_cost_microcents,
                'step_tokens' => ($llmResponse['usage']['input_tokens'] ?? 0) + ($llmResponse['usage']['output_tokens'] ?? 0),
            ]);

            $this->publishEvent($run, 'step', [
                'step_number' => $stepNumber,
                'phase' => 'reason',
                'status' => 'completed',
                'duration_ms' => $reasonDuration,
            ]);

            // Check if LLM wants to use tools
            $toolUseCalls = collect($llmResponse['content'])->where('type', 'tool_use')->values()->all();

            if (empty($toolUseCalls)) {
                // Final output
                $stepNumber++;
                $textContent = collect($llmResponse['content'])->where('type', 'text')->pluck('text')->implode('');

                $outputGuard = new OutputGuard;
                $warnings = $outputGuard->check($textContent);

                $observeStep = $this->recordStep($run, $stepNumber, 'observe', [
                    'final_output' => $textContent,
                    'safety_warnings' => $warnings,
                ]);
                $observeStep->markCompleted(['loop_complete' => true, 'reason' => 'no_tool_calls']);

                $run->markCompleted([
                    'response' => $textContent,
                    'content' => $llmResponse['content'],
                    'safety_warnings' => $warnings,
                ]);

                $this->publishEvent($run, 'step', [
                    'step_number' => $stepNumber,
                    'phase' => 'observe',
                    'status' => 'completed',
                    'output' => mb_substr($textContent, 0, 500),
                ]);

                $this->publishEvent($run, 'status', ['status' => 'completed']);

                return;
            }

            // --- ACT ---
            $stepNumber++;
            $actStep = $this->recordStep($run, $stepNumber, 'act', [
                'tool_calls' => array_map(fn ($tc) => ['name' => $tc['name'], 'input' => $tc['input']], $toolUseCalls),
            ]);
            $actStep->markRunning();

            $messages[] = ['role' => 'assistant', 'content' => $llmResponse['content']];

            // Check approval requirements
            $needsApproval = false;
            foreach ($toolUseCalls as $toolCall) {
                if ($approvalGuard->requiresApproval($agent, $toolCall['name'])) {
                    $needsApproval = true;
                    break;
                }
            }

            if ($needsApproval) {
                $actStep->update([
                    'requires_approval' => true,
                    'tool_calls' => array_map(fn ($tc) => ['name' => $tc['name'], 'input' => $tc['input'] ?? []], $toolUseCalls),
                ]);
                $actStep->markPendingApproval();

                $run->update([
                    'config' => array_merge($this->config, [
                        '_resume_state' => [
                            'messages' => $messages,
                            'step_number' => $stepNumber,
                            'iteration' => $iteration,
                            'model' => $model,
                            'max_tokens' => $maxTokens,
                            'fallback_chain' => $fallbackChain,
                        ],
                    ]),
                ]);
                $run->markAwaitingApproval();

                $this->publishEvent($run, 'status', ['status' => 'awaiting_approval']);
                $this->publishEvent($run, 'step', [
                    'step_number' => $stepNumber,
                    'phase' => 'act',
                    'status' => 'pending_approval',
                    'tools' => array_map(fn ($tc) => $tc['name'], $toolUseCalls),
                ]);

                return;
            }

            // Execute tool calls
            $this->publishEvent($run, 'step', [
                'step_number' => $stepNumber,
                'phase' => 'act',
                'status' => 'running',
                'tools' => array_map(fn ($tc) => $tc['name'], $toolUseCalls),
            ]);

            $toolResults = [];
            $allToolCallData = [];

            foreach ($toolUseCalls as $toolCall) {
                $dataAccessError = $dataAccessGuard->check($agent, $toolCall['name'], $toolCall['input'] ?? [], $project->id);
                if ($dataAccessError) {
                    $result = new ToolCallResult(
                        toolName: $toolCall['name'],
                        content: [['type' => 'text', 'text' => "Blocked: {$dataAccessError}"]],
                        isError: true,
                        durationMs: 0,
                    );
                } elseif ($toolError = $toolGuard->check($toolCall['name'], $toolCall['input'] ?? [])) {
                    $result = new ToolCallResult(
                        toolName: $toolCall['name'],
                        content: [['type' => 'text', 'text' => "Blocked: {$toolError}"]],
                        isError: true,
                        durationMs: 0,
                    );
                } else {
                    $result = $dispatcher->dispatch($toolCall['name'], $toolCall['input'] ?? []);
                }

                $toolResults[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $toolCall['id'],
                    'content' => $result->text(),
                    'is_error' => $result->isError,
                ];
                $allToolCallData[] = $result->toArray();
            }

            $actStep->update(['tool_calls' => $allToolCallData]);
            $actStep->markCompleted([
                'tools_executed' => count($toolResults),
                'any_errors' => collect($toolResults)->contains('is_error', true),
            ], 0);

            $this->publishEvent($run, 'step', [
                'step_number' => $stepNumber,
                'phase' => 'act',
                'status' => 'completed',
                'tools_executed' => count($toolResults),
            ]);

            $messages[] = ['role' => 'user', 'content' => $toolResults];

            // --- OBSERVE ---
            $stepNumber++;
            $observeStep = $this->recordStep($run, $stepNumber, 'observe', [
                'iteration' => $iteration,
                'tool_results_count' => count($toolResults),
            ]);
            $observeStep->markCompleted(['continue_loop' => true]);
        }

        // Max iterations reached
        $run->markCompleted(['reason' => 'max_iterations']);
        $this->publishEvent($run, 'status', ['status' => 'completed', 'reason' => 'max_iterations']);
    }

    private function setupToolDispatcher(Project $project, Agent $agent, McpServerManager $serverManager): ToolDispatcher
    {
        $dispatcher = new ToolDispatcher($serverManager);

        $serverIds = DB::table('agent_mcp_server')
            ->where('project_id', $project->id)
            ->where('agent_id', $agent->id)
            ->pluck('project_mcp_server_id');

        if ($serverIds->isNotEmpty()) {
            $servers = ProjectMcpServer::whereIn('id', $serverIds)->where('enabled', true)->get()->all();
            $dispatcher->registerMcpServers($servers);
        }

        $a2aIds = DB::table('agent_a2a_agent')
            ->where('project_id', $project->id)
            ->where('agent_id', $agent->id)
            ->pluck('project_a2a_agent_id');

        if ($a2aIds->isNotEmpty()) {
            $a2aAgents = ProjectA2aAgent::whereIn('id', $a2aIds)->where('enabled', true)->get()->all();
            $dispatcher->registerA2aAgents($a2aAgents);
        }

        return $dispatcher;
    }

    private function recordStep(ExecutionRun $run, int $stepNumber, string $phase, array $input = []): ExecutionStep
    {
        return ExecutionStep::create([
            'execution_run_id' => $run->id,
            'step_number' => $stepNumber,
            'phase' => $phase,
            'input' => $input,
        ]);
    }

    /**
     * Publish an event to the Redis list for SSE streaming.
     *
     * Uses a Redis list (rpush) so the SSE controller can consume events
     * via lpop without blocking pub/sub. The list auto-expires after 10 minutes.
     */
    private function publishEvent(ExecutionRun $run, string $type, array $data): void
    {
        try {
            $listKey = "execution_events:{$run->uuid}";
            $payload = json_encode([
                'type' => $type,
                'timestamp' => now()->toIso8601String(),
                'data' => $data,
            ]);

            Redis::rpush($listKey, $payload);
            Redis::expire($listKey, 600); // 10 minutes TTL
        } catch (\Throwable $e) {
            // Redis not available — log but don't fail the execution
            Log::debug("Failed to publish execution event: {$e->getMessage()}");
        }
    }

    public function failed(?\Throwable $e): void
    {
        $run = ExecutionRun::where('uuid', $this->executionId)->first();
        if ($run && ! $run->isFinished()) {
            $run->markFailed($e?->getMessage() ?? 'Job failed after max retries');
        }

        McpConnectionPool::release($this->executionId);

        Log::error("RunAgentJob permanently failed", [
            'execution_id' => $this->executionId,
            'error' => $e?->getMessage(),
        ]);
    }
}
