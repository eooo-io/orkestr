<?php

namespace App\Services\Execution;

use App\Models\Agent;
use App\Models\ExecutionRun;
use App\Models\ExecutionStep;
use App\Models\Project;
use App\Models\ProjectA2aAgent;
use App\Models\ProjectMcpServer;
use App\Services\AgentComposeService;
use App\Services\AuditLogger;
use App\Services\Execution\Guards\ApprovalGuard;
use App\Services\Execution\Guards\BudgetGuard;
use App\Services\Execution\Guards\DataAccessGuard;
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
        private ExecutionGuardrailService $guardrails,
        private ApprovalGuard $approvalGuard = new ApprovalGuard,
        private DataAccessGuard $dataAccessGuard = new DataAccessGuard,
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

        AuditLogger::log('agent.executed', "Agent '{$agent->name}' execution started", [
            'run_id' => $run->id,
            'agent_slug' => $agent->slug,
            'input' => $input,
        ], $agent->id, $project->id);

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

        // Setup tool guard and filter allowed tools (with per-agent scoping)
        $toolGuard = new ToolGuard;
        $toolGuard->configure($config);
        $toolGuard->configureForAgent($agent);
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

            $freshRun = $run->fresh();
            if ($freshRun->isCancelled()) {
                return;
            }

            // Org-level turn cap
            if ($turnCapReason = $this->guardrails->checkTurnCap($freshRun, $iteration + 1)) {
                $this->guardrails->halt($freshRun, $turnCapReason);
                return;
            }

            // Per-run token/cost budget
            if ($budgetReason = $this->guardrails->checkBudget($freshRun)) {
                $this->guardrails->halt($freshRun, $budgetReason);
                return;
            }

            // Budget check before each iteration (per-agent cumulative)
            $budgetError = $this->budgetGuard->check($run->fresh(), $config);
            if ($budgetError) {
                AuditLogger::log('agent.budget_exceeded', "Budget exceeded for agent '{$agent->name}': {$budgetError}", [
                    'run_id' => $run->id,
                    'agent_slug' => $agent->slug,
                ], $agent->id, $project->id);
                $run->markFailed("Budget guardrail: {$budgetError}");
                return;
            }

            // Per-agent run budget check
            $agentRunBudgetError = $this->budgetGuard->checkAgentRunBudget($agent, $run->fresh());
            if ($agentRunBudgetError) {
                AuditLogger::log('agent.budget_exceeded', $agentRunBudgetError, [
                    'run_id' => $run->id,
                    'agent_slug' => $agent->slug,
                ], $agent->id, $project->id);
                $run->markFailed("Budget guardrail: {$agentRunBudgetError}");
                return;
            }

            // Per-agent daily budget check
            $agentDailyBudgetError = $this->budgetGuard->checkAgentDailyBudget($agent);
            if ($agentDailyBudgetError) {
                AuditLogger::log('agent.budget_exceeded', $agentDailyBudgetError, [
                    'run_id' => $run->id,
                    'agent_slug' => $agent->slug,
                ], $agent->id, $project->id);
                $run->markFailed("Budget guardrail: {$agentDailyBudgetError}");
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

            // Loop detection: any repeated tool call past threshold halts.
            foreach ($toolUseCalls as $toolCall) {
                $loopReason = $this->guardrails->detectLoop(
                    $run->fresh(),
                    $agent->id,
                    $toolCall['name'],
                    $toolCall['input'] ?? [],
                );
                if ($loopReason) {
                    $actStep->markFailed('Halted: loop detected');
                    $this->guardrails->halt($run->fresh(), $loopReason, $actStep);
                    return;
                }
            }

            // Add assistant message with tool_use to conversation
            $messages[] = ['role' => 'assistant', 'content' => $llmResponse['content']];

            // Check ApprovalGuard before executing tools
            $needsApproval = false;
            foreach ($toolUseCalls as $toolCall) {
                if ($this->approvalGuard->requiresApproval($agent, $toolCall['name'])) {
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

                // Store conversation state so execution can resume later
                $run->update([
                    'config' => array_merge($config, [
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

                AuditLogger::log('tool.approval_required', "Tool calls require approval for agent '{$agent->name}'", [
                    'run_id' => $run->id,
                    'step_id' => $actStep->id,
                    'tools' => array_map(fn ($tc) => $tc['name'], $toolUseCalls),
                ], $agent->id, $project->id);

                return;
            }

            // Execute tool calls
            $toolResults = [];
            $allToolCallData = [];

            foreach ($toolUseCalls as $toolCall) {
                // Data access guard check
                $dataAccessError = $this->dataAccessGuard->check($agent, $toolCall['name'], $toolCall['input'] ?? [], $project->id);
                if ($dataAccessError) {
                    AuditLogger::log('tool.blocked', "Tool '{$toolCall['name']}' blocked by data access guard: {$dataAccessError}", [
                        'run_id' => $run->id,
                        'tool' => $toolCall['name'],
                    ], $agent->id, $project->id);
                    $result = new ToolCallResult(
                        toolName: $toolCall['name'],
                        content: [['type' => 'text', 'text' => "Blocked: {$dataAccessError}"]],
                        isError: true,
                        durationMs: 0,
                    );
                }
                // Tool guard check
                elseif ($toolError = $toolGuard->check($toolCall['name'], $toolCall['input'] ?? [])) {
                    AuditLogger::log('tool.blocked', "Tool '{$toolCall['name']}' blocked: {$toolError}", [
                        'run_id' => $run->id,
                        'tool' => $toolCall['name'],
                    ], $agent->id, $project->id);
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

    /**
     * Resume execution after a step has been approved.
     */
    public function resumeExecution(ExecutionRun $run): ExecutionRun
    {
        if (! $run->isAwaitingApproval()) {
            throw new \RuntimeException('Run is not awaiting approval.');
        }

        $config = $run->config ?? [];
        $resumeState = $config['_resume_state'] ?? null;

        if (! $resumeState) {
            throw new \RuntimeException('No resume state found. Cannot resume execution.');
        }

        $agent = $run->agent;
        $project = $run->project;

        // Remove resume state from config to avoid re-use
        unset($config['_resume_state']);
        $run->update(['config' => $config]);

        $run->markRunning();

        try {
            // Get the approved step and execute the pending tool calls
            $pendingStep = $run->steps()
                ->where('status', 'approved')
                ->where('phase', 'act')
                ->orderByDesc('step_number')
                ->first();

            if ($pendingStep && ! empty($pendingStep->tool_calls)) {
                $dispatcher = $this->setupToolDispatcher($project, $agent);
                $toolGuard = new ToolGuard;
                $toolGuard->configure($config);
                $toolGuard->configureForAgent($agent);

                $messages = $resumeState['messages'];

                // Execute the tool calls that were pending
                $toolResults = [];
                $allToolCallData = [];

                foreach ($pendingStep->tool_calls as $toolCall) {
                    $toolName = $toolCall['name'] ?? '';
                    $toolInput = $toolCall['input'] ?? [];

                    $result = $dispatcher->dispatch($toolName, $toolInput);

                    $toolResults[] = [
                        'type' => 'tool_result',
                        'tool_use_id' => $toolCall['id'] ?? uniqid(),
                        'content' => $result->text(),
                        'is_error' => $result->isError,
                    ];
                    $allToolCallData[] = $result->toArray();
                }

                $pendingStep->update(['tool_calls' => $allToolCallData]);
                $pendingStep->markCompleted([
                    'tools_executed' => count($toolResults),
                    'any_errors' => collect($toolResults)->contains('is_error', true),
                ], 0);

                // Add tool results to messages and continue the loop
                $messages[] = ['role' => 'user', 'content' => $toolResults];

                // Continue execution from where we left off
                $this->runLoop(
                    $run,
                    $project,
                    $agent,
                    $run->input ?? [],
                    $config,
                );
            } else {
                $run->markFailed('No approved step with tool calls found for resume.');
            }
        } catch (\Throwable $e) {
            $run->markFailed($e->getMessage());
            Log::error("Agent resume failed: {$e->getMessage()}", [
                'run_id' => $run->id,
                'agent' => $agent->slug,
            ]);
        }

        return $run->fresh(['steps']);
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
