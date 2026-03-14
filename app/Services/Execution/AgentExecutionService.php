<?php

namespace App\Services\Execution;

use App\Models\Agent;
use App\Models\ExecutionRun;
use App\Models\ExecutionStep;
use App\Models\Project;
use App\Models\ProjectA2aAgent;
use App\Models\ProjectMcpServer;
use App\Services\AgentComposeService;
use App\Services\Execution\Guards\BudgetGuard;
use App\Services\Execution\Guards\OutputGuard;
use App\Services\Execution\Guards\ToolGuard;
use App\Services\LLM\CostOptimizedRouter;
use App\Services\LLM\LLMProviderFactory;
use App\Services\LLM\ModelRouter;
use App\Services\Mcp\McpServerManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AgentExecutionService
{
    public function __construct(
        private AgentComposeService $composeService,
        private LLMProviderFactory $providerFactory,
        private ModelRouter $modelRouter,
        private CostOptimizedRouter $costRouter,
        private McpServerManager $serverManager,
        private BudgetGuard $budgetGuard,
    ) {}

    /**
     * Execute an agent loop: Goal → Perceive → Reason → Act → Observe → Repeat.
     */
    public function execute(Project $project, Agent $agent, array $input = [], array $config = [], ?int $createdBy = null): ExecutionRun
    {
        $run = ExecutionRun::create([
            'project_id' => $project->id,
            'agent_id' => $agent->id,
            'input' => $input,
            'config' => $config,
            'created_by' => $createdBy,
        ]);

        $run->markRunning();

        try {
            $this->runLoop($run, $project, $agent, $input, $config);
        } catch (\Throwable $e) {
            $run->markFailed($e->getMessage());
            Log::error("Agent execution failed: {$e->getMessage()}", [
                'run_id' => $run->id,
                'agent' => $agent->slug,
            ]);
        }

        return $run->fresh(['steps']);
    }

    private function runLoop(ExecutionRun $run, Project $project, Agent $agent, array $input, array $config): void
    {
        $projectAgent = $project->projectAgents()->where('agent_id', $agent->id)->first();

        // Resolve config with project overrides
        $model = $projectAgent?->model_override ?? $agent->model ?? 'claude-sonnet-4-6';
        $maxIterations = $projectAgent?->max_iterations_override ?? $agent->max_iterations ?? 10;
        $maxTokens = $config['max_tokens'] ?? $agent->max_tokens ?? 4096;
        $timeout = $agent->timeout ?? 300; // seconds

        // Setup tool dispatcher
        $dispatcher = $this->setupToolDispatcher($project, $agent);

        // Setup tool guard and filter allowed tools
        $toolGuard = new ToolGuard;
        $toolGuard->configure($config);
        $toolDefinitions = $toolGuard->filterTools($dispatcher->getToolDefinitions());

        // Build system prompt from agent compose
        $composed = $this->composeService->composeStructured($project, $agent);
        $systemPrompt = $composed['system_prompt'] ?? '';

        // Build initial messages from input
        $messages = [];
        $userMessage = $input['message'] ?? $input['goal'] ?? json_encode($input);
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        // Resolve fallback chain from config or agent
        $fallbackChain = $config['fallback_models'] ?? $agent->fallback_models ?? [];

        // Apply cost-optimized routing strategy to reorder model list
        $routingStrategy = $agent->routing_strategy ?? 'default';
        $reorderedModels = $this->costRouter->selectModels($model, $fallbackChain, $routingStrategy);
        $model = $reorderedModels[0];
        $fallbackChain = array_slice($reorderedModels, 1);

        $stepNumber = 0;
        $deadline = microtime(true) + $timeout;

        for ($iteration = 0; $iteration < $maxIterations; $iteration++) {
            if (microtime(true) > $deadline) {
                $run->markFailed("Execution timeout after {$timeout} seconds");
                return;
            }

            if ($run->fresh()->isCancelled()) {
                return;
            }

            // Budget check before each iteration
            $budgetError = $this->budgetGuard->check($run->fresh(), $config);
            if ($budgetError) {
                $run->markFailed("Budget guardrail: {$budgetError}");
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

            $reasonStart = microtime(true);
            try {
                $llmResponse = $this->modelRouter->chatWithFallback(
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
                return;
            }

            $actualModelUsed = $llmResponse['model_used'] ?? $model;
            $reasonDuration = (int) ((microtime(true) - $reasonStart) * 1000);
            $reasonStep->markCompleted([
                'content' => $llmResponse['content'],
                'stop_reason' => $llmResponse['stop_reason'],
                'usage' => $llmResponse['usage'],
            ], $reasonDuration);

            // Track token usage and model attribution
            $reasonStep->update([
                'token_usage' => $llmResponse['usage'],
                'model_used' => $actualModelUsed,
                'model_requested' => $model,
            ]);
            $run->addTokenUsage($llmResponse['usage']);

            // Record model_used on the run (first successful LLM call sets it)
            if (! $run->model_used) {
                $run->update(['model_used' => $actualModelUsed]);
            }

            // Check if LLM wants to use tools
            $toolUseCalls = collect($llmResponse['content'])->where('type', 'tool_use')->values()->all();

            if (empty($toolUseCalls)) {
                // --- OBSERVE (final) ---
                $stepNumber++;
                $textContent = collect($llmResponse['content'])->where('type', 'text')->pluck('text')->implode('');

                // Output safety check
                $outputGuard = new OutputGuard;
                $warnings = $outputGuard->check($textContent);

                $observeStep = $this->recordStep($run, $stepNumber, 'observe', [
                    'final_output' => $textContent,
                    'safety_warnings' => $warnings,
                ]);
                $observeStep->markCompleted(['loop_complete' => true, 'reason' => 'no_tool_calls', 'warnings' => $warnings]);

                $run->markCompleted([
                    'response' => $textContent,
                    'content' => $llmResponse['content'],
                    'safety_warnings' => $warnings,
                ]);
                return;
            }

            // --- ACT ---
            $stepNumber++;
            $actStep = $this->recordStep($run, $stepNumber, 'act', [
                'tool_calls' => array_map(fn ($tc) => ['name' => $tc['name'], 'input' => $tc['input']], $toolUseCalls),
            ]);
            $actStep->markRunning();

            // Add assistant message with tool_use to conversation
            $messages[] = ['role' => 'assistant', 'content' => $llmResponse['content']];

            // Execute tool calls
            $toolResults = [];
            $allToolCallData = [];

            foreach ($toolUseCalls as $toolCall) {
                // Tool guard check
                $toolError = $toolGuard->check($toolCall['name'], $toolCall['input'] ?? []);
                if ($toolError) {
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

            // Add tool results to messages
            $messages[] = ['role' => 'user', 'content' => $toolResults];

            // --- OBSERVE ---
            $stepNumber++;
            $observeStep = $this->recordStep($run, $stepNumber, 'observe', [
                'iteration' => $iteration,
                'tool_results_count' => count($toolResults),
            ]);
            $observeStep->markCompleted(['continue_loop' => true]);

            // Loop continues to next iteration...
        }

        // Max iterations reached
        $lastText = $this->extractLastText($messages);
        $run->markCompleted(['response' => $lastText, 'reason' => 'max_iterations']);
    }

    private function setupToolDispatcher(Project $project, Agent $agent): ToolDispatcher
    {
        $dispatcher = new ToolDispatcher($this->serverManager);

        // Get MCP servers bound to this agent
        $serverIds = DB::table('agent_mcp_server')
            ->where('project_id', $project->id)
            ->where('agent_id', $agent->id)
            ->pluck('project_mcp_server_id');

        if ($serverIds->isNotEmpty()) {
            $servers = ProjectMcpServer::whereIn('id', $serverIds)->where('enabled', true)->get()->all();
            $dispatcher->registerMcpServers($servers);
        }

        // Get A2A agents bound to this agent
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

    private function extractLastText(array $messages): string
    {
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            $msg = $messages[$i];
            if (($msg['role'] ?? '') === 'assistant') {
                $content = $msg['content'] ?? '';
                if (is_array($content)) {
                    return collect($content)->where('type', 'text')->pluck('text')->implode('');
                }

                return (string) $content;
            }
        }

        return '';
    }
}
