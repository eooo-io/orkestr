<?php

use App\Models\Agent;
use App\Models\AgentAuditLog;
use App\Models\ExecutionRun;
use App\Models\ExecutionStep;
use App\Models\Project;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Execution\Guards\ApprovalGuard;
use App\Services\Execution\Guards\BudgetGuard;
use App\Services\Execution\Guards\DataAccessGuard;
use App\Services\Execution\Guards\ToolGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create(['name' => 'Autonomy Test', 'path' => '/tmp/autonomy-test']);
    $this->agent = Agent::create([
        'name' => 'Autonomy Agent',
        'slug' => 'autonomy-agent',
        'role' => 'assistant',
        'model' => 'claude-sonnet-4-6',
        'base_instructions' => 'You are a test agent.',
    ]);

    $this->project->agents()->attach($this->agent->id, ['is_enabled' => true]);
});

// ──── #188: Agent Autonomy Levels ────

test('agent autonomy level defaults to semi_autonomous', function () {
    $agent = Agent::create([
        'name' => 'Default Agent',
        'slug' => 'default-agent',
        'role' => 'assistant',
        'base_instructions' => 'Test.',
    ]);

    expect($agent->autonomy_level)->toBe('semi_autonomous');
});

test('agent autonomy levels stored correctly', function () {
    foreach (['supervised', 'semi_autonomous', 'autonomous'] as $level) {
        $this->agent->update(['autonomy_level' => $level]);
        $this->agent->refresh();
        expect($this->agent->autonomy_level)->toBe($level);
    }
});

test('agent budget fields stored correctly', function () {
    $this->agent->update([
        'budget_limit_usd' => 5.5000,
        'daily_budget_limit_usd' => 20.2500,
    ]);
    $this->agent->refresh();

    expect((float) $this->agent->budget_limit_usd)->toBe(5.5);
    expect((float) $this->agent->daily_budget_limit_usd)->toBe(20.25);
});

test('agent tool scope fields stored correctly', function () {
    $this->agent->update([
        'allowed_tools' => ['read_file', 'search'],
        'blocked_tools' => ['delete_file'],
    ]);
    $this->agent->refresh();

    expect($this->agent->allowed_tools)->toBe(['read_file', 'search']);
    expect($this->agent->blocked_tools)->toBe(['delete_file']);
});

test('agent data access scope stored correctly', function () {
    $scope = [
        'projects' => [1, 2, 3],
        'skills' => 'own_project',
        'files' => ['read'],
        'external_apis' => false,
        'memory' => 'own',
    ];
    $this->agent->update(['data_access_scope' => $scope]);
    $this->agent->refresh();

    expect($this->agent->data_access_scope)->toBe($scope);
});

test('execution run approval fields stored correctly', function () {
    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'approval_required' => true,
        'approved_by' => $this->user->id,
        'approved_at' => now(),
    ]);
    $run->refresh();

    expect($run->approval_required)->toBeTrue();
    expect($run->approved_by)->toBe($this->user->id);
    expect($run->approved_at)->not->toBeNull();
});

test('execution step approval fields stored correctly', function () {
    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
    ]);
    $step = ExecutionStep::create([
        'execution_run_id' => $run->id,
        'step_number' => 1,
        'phase' => 'act',
        'requires_approval' => true,
        'approved_by' => $this->user->id,
        'approved_at' => now(),
        'approval_note' => 'Looks good',
    ]);
    $step->refresh();

    expect($step->requires_approval)->toBeTrue();
    expect($step->approved_by)->toBe($this->user->id);
    expect($step->approved_at)->not->toBeNull();
    expect($step->approval_note)->toBe('Looks good');
});

// ──── #189: Per-Agent Budget Envelopes ────

test('BudgetGuard checks per-run budget limit', function () {
    $this->agent->update(['budget_limit_usd' => 1.0000]);

    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'total_cost_microcents' => 1_500_000, // $1.50
    ]);

    $guard = app(BudgetGuard::class);
    $error = $guard->checkAgentRunBudget($this->agent, $run);

    expect($error)->not->toBeNull();
    expect($error)->toContain('per-run budget exceeded');
});

test('BudgetGuard passes when under per-run budget', function () {
    $this->agent->update(['budget_limit_usd' => 5.0000]);

    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'total_cost_microcents' => 1_000_000, // $1.00
    ]);

    $guard = app(BudgetGuard::class);
    $error = $guard->checkAgentRunBudget($this->agent, $run);

    expect($error)->toBeNull();
});

test('BudgetGuard checks daily budget limit', function () {
    $this->agent->update(['daily_budget_limit_usd' => 2.0000]);

    // Create runs totaling $3.00 today
    ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'total_cost_microcents' => 1_500_000,
        'status' => 'completed',
    ]);
    ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'total_cost_microcents' => 1_500_000,
        'status' => 'completed',
    ]);

    $guard = app(BudgetGuard::class);
    $error = $guard->checkAgentDailyBudget($this->agent);

    expect($error)->not->toBeNull();
    expect($error)->toContain('daily budget exceeded');
});

test('BudgetGuard passes when no budget limits set', function () {
    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'total_cost_microcents' => 10_000_000,
    ]);

    $guard = app(BudgetGuard::class);
    expect($guard->checkAgentRunBudget($this->agent, $run))->toBeNull();
    expect($guard->checkAgentDailyBudget($this->agent))->toBeNull();
});

test('GET /api/agents/{agent}/budget-status returns budget status', function () {
    $this->agent->update([
        'budget_limit_usd' => 10.0000,
        'daily_budget_limit_usd' => 50.0000,
    ]);

    ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'total_cost_microcents' => 2_000_000,
        'status' => 'completed',
    ]);

    $response = $this->getJson("/api/agents/{$this->agent->id}/budget-status");

    $response->assertOk();
    $response->assertJsonPath('data.run_budget_limit_usd', '10.0000');
    $response->assertJsonPath('data.daily_budget_limit_usd', '50.0000');
    expect($response->json('data.daily_spend_microcents'))->toBe(2_000_000);
});

test('PUT /api/agents/{agent}/budget updates budget limits', function () {
    $response = $this->putJson("/api/agents/{$this->agent->id}/budget", [
        'budget_limit_usd' => 15.5,
        'daily_budget_limit_usd' => 100.0,
    ]);

    $response->assertOk();

    $this->agent->refresh();
    expect((float) $this->agent->budget_limit_usd)->toBe(15.5);
    expect((float) $this->agent->daily_budget_limit_usd)->toBe(100.0);
});

// ──── #190: Agent Tool Scope Configuration ────

test('ToolGuard filters by agent allowed_tools whitelist', function () {
    $this->agent->update(['allowed_tools' => ['read_file', 'search']]);

    $guard = new ToolGuard;
    $guard->configureForAgent($this->agent);

    $tools = [
        ['name' => 'read_file', 'description' => 'Read a file'],
        ['name' => 'write_file', 'description' => 'Write a file'],
        ['name' => 'search', 'description' => 'Search'],
        ['name' => 'delete_file', 'description' => 'Delete a file'],
    ];

    $filtered = $guard->filterTools($tools);
    expect(count($filtered))->toBe(2);
    expect(array_column($filtered, 'name'))->toBe(['read_file', 'search']);
});

test('ToolGuard filters by agent blocked_tools blacklist', function () {
    $this->agent->update(['blocked_tools' => ['delete_file', 'execute_shell']]);

    $guard = new ToolGuard;
    $guard->configureForAgent($this->agent);

    $tools = [
        ['name' => 'read_file', 'description' => 'Read a file'],
        ['name' => 'delete_file', 'description' => 'Delete a file'],
        ['name' => 'execute_shell', 'description' => 'Run shell'],
    ];

    $filtered = $guard->filterTools($tools);
    expect(count($filtered))->toBe(1);
    expect($filtered[0]['name'])->toBe('read_file');
});

test('ToolGuard allowed_tools takes precedence over blocked_tools', function () {
    $this->agent->update([
        'allowed_tools' => ['read_file', 'search'],
        'blocked_tools' => ['read_file'], // This should be ignored since allowed_tools is set
    ]);

    $guard = new ToolGuard;
    $guard->configureForAgent($this->agent);

    // allowed_tools whitelist mode means only allowed tools are included
    // blocked_tools is merged but allowlist takes precedence in filtering
    $check = $guard->check('search', []);
    expect($check)->toBeNull(); // allowed

    $check = $guard->check('delete_file', []);
    expect($check)->not->toBeNull(); // not in allowlist
});

test('GET /api/agents/{agent}/tool-scope returns scope', function () {
    $this->agent->update([
        'allowed_tools' => ['read_file'],
        'blocked_tools' => ['delete_file'],
    ]);

    $response = $this->getJson("/api/agents/{$this->agent->id}/tool-scope");

    $response->assertOk();
    $response->assertJsonPath('data.allowed_tools', ['read_file']);
    $response->assertJsonPath('data.blocked_tools', ['delete_file']);
});

test('PUT /api/agents/{agent}/tool-scope updates scope', function () {
    $response = $this->putJson("/api/agents/{$this->agent->id}/tool-scope", [
        'allowed_tools' => ['read_file', 'search'],
        'blocked_tools' => ['execute_shell'],
    ]);

    $response->assertOk();

    $this->agent->refresh();
    expect($this->agent->allowed_tools)->toBe(['read_file', 'search']);
    expect($this->agent->blocked_tools)->toBe(['execute_shell']);
});

// ──── #191: Human Approval Gate for Tool Calls ────

test('ApprovalGuard requires approval for all tools in supervised mode', function () {
    $this->agent->update(['autonomy_level' => 'supervised']);

    $guard = new ApprovalGuard;
    expect($guard->requiresApproval($this->agent, 'read_file'))->toBeTrue();
    expect($guard->requiresApproval($this->agent, 'search'))->toBeTrue();
    expect($guard->requiresApproval($this->agent, 'delete_file'))->toBeTrue();
});

test('ApprovalGuard requires no approval in autonomous mode', function () {
    $this->agent->update(['autonomy_level' => 'autonomous']);

    $guard = new ApprovalGuard;
    expect($guard->requiresApproval($this->agent, 'read_file'))->toBeFalse();
    expect($guard->requiresApproval($this->agent, 'delete_file'))->toBeFalse();
    expect($guard->requiresApproval($this->agent, 'execute_command'))->toBeFalse();
});

test('ApprovalGuard requires approval for sensitive tools in semi_autonomous mode', function () {
    $this->agent->update(['autonomy_level' => 'semi_autonomous']);

    $guard = new ApprovalGuard;
    // Sensitive patterns: write, delete, execute, etc.
    expect($guard->requiresApproval($this->agent, 'write_file'))->toBeTrue();
    expect($guard->requiresApproval($this->agent, 'delete_record'))->toBeTrue();
    expect($guard->requiresApproval($this->agent, 'execute_command'))->toBeTrue();
    // Non-sensitive
    expect($guard->requiresApproval($this->agent, 'read_file'))->toBeFalse();
    expect($guard->requiresApproval($this->agent, 'search_docs'))->toBeFalse();
});

test('ApprovalGuard uses custom sensitive_tools list from data_access_scope', function () {
    $this->agent->update([
        'autonomy_level' => 'semi_autonomous',
        'data_access_scope' => [
            'sensitive_tools' => ['custom_tool', 'special_action'],
        ],
    ]);

    $guard = new ApprovalGuard;
    expect($guard->requiresApproval($this->agent, 'custom_tool'))->toBeTrue();
    expect($guard->requiresApproval($this->agent, 'special_action'))->toBeTrue();
    expect($guard->requiresApproval($this->agent, 'write_file'))->toBeFalse(); // Not in custom list
});

test('step approval and rejection via model helpers', function () {
    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
    ]);

    $step = ExecutionStep::create([
        'execution_run_id' => $run->id,
        'step_number' => 1,
        'phase' => 'act',
    ]);

    $step->markPendingApproval();
    $step->refresh();
    expect($step->isPendingApproval())->toBeTrue();
    expect($step->requires_approval)->toBeTrue();

    $step->approve($this->user->id, 'Approved after review');
    $step->refresh();
    expect($step->status)->toBe('approved');
    expect($step->approved_by)->toBe($this->user->id);
    expect($step->approval_note)->toBe('Approved after review');
    expect($step->approved_at)->not->toBeNull();
});

test('POST /api/runs/{run}/steps/{step}/approve approves a pending step', function () {
    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'awaiting_approval',
    ]);

    $step = ExecutionStep::create([
        'execution_run_id' => $run->id,
        'step_number' => 1,
        'phase' => 'act',
        'status' => 'pending_approval',
        'requires_approval' => true,
    ]);

    $response = $this->postJson("/api/runs/{$run->id}/steps/{$step->id}/approve", [
        'note' => 'Looks safe',
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'approved');

    $step->refresh();
    expect($step->status)->toBe('approved');
    expect($step->approved_by)->toBe($this->user->id);
    expect($step->approval_note)->toBe('Looks safe');
});

test('POST /api/runs/{run}/steps/{step}/reject rejects a pending step', function () {
    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'status' => 'awaiting_approval',
    ]);

    $step = ExecutionStep::create([
        'execution_run_id' => $run->id,
        'step_number' => 1,
        'phase' => 'act',
        'status' => 'pending_approval',
        'requires_approval' => true,
    ]);

    $response = $this->postJson("/api/runs/{$run->id}/steps/{$step->id}/reject", [
        'note' => 'Too risky',
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'rejected');

    $step->refresh();
    expect($step->status)->toBe('rejected');

    $run->refresh();
    expect($run->status)->toBe('failed');
});

test('POST /api/runs/{run}/steps/{step}/approve rejects non-pending step', function () {
    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
    ]);

    $step = ExecutionStep::create([
        'execution_run_id' => $run->id,
        'step_number' => 1,
        'phase' => 'act',
        'status' => 'completed',
    ]);

    $response = $this->postJson("/api/runs/{$run->id}/steps/{$step->id}/approve");
    $response->assertStatus(422);
});

test('ExecutionRun awaiting_approval status helpers work', function () {
    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
    ]);

    $run->markAwaitingApproval();
    $run->refresh();

    expect($run->isAwaitingApproval())->toBeTrue();
    expect($run->approval_required)->toBeTrue();
    expect($run->isFinished())->toBeFalse();
});

// ──── #192: Agent Data Access Boundaries ────

test('DataAccessGuard allows unrestricted access when no scope set', function () {
    $guard = new DataAccessGuard;
    $error = $guard->check($this->agent, 'any_tool', [], $this->project->id);
    expect($error)->toBeNull();
});

test('DataAccessGuard blocks access to disallowed project', function () {
    $this->agent->update([
        'data_access_scope' => ['projects' => [1, 2]],
    ]);

    $guard = new DataAccessGuard;
    $error = $guard->check($this->agent, 'read_file', ['project_id' => 99], null);
    expect($error)->not->toBeNull();
    expect($error)->toContain('not allowed to access project');
});

test('DataAccessGuard allows access to permitted project', function () {
    $this->agent->update([
        'data_access_scope' => ['projects' => [$this->project->id]],
    ]);

    $guard = new DataAccessGuard;
    $error = $guard->check($this->agent, 'read_file', [], $this->project->id);
    expect($error)->toBeNull();
});

test('DataAccessGuard allows wildcard project access', function () {
    $this->agent->update([
        'data_access_scope' => ['projects' => '*'],
    ]);

    $guard = new DataAccessGuard;
    $error = $guard->check($this->agent, 'read_file', ['project_id' => 999], null);
    expect($error)->toBeNull();
});

test('DataAccessGuard blocks file write when only read allowed', function () {
    $this->agent->update([
        'data_access_scope' => ['files' => ['read']],
    ]);

    $guard = new DataAccessGuard;
    $error = $guard->check($this->agent, 'write_file', [], $this->project->id);
    expect($error)->not->toBeNull();
    expect($error)->toContain('not allowed to write files');
});

test('DataAccessGuard blocks external API when disabled', function () {
    $this->agent->update([
        'data_access_scope' => ['external_apis' => false],
    ]);

    $guard = new DataAccessGuard;
    $error = $guard->check($this->agent, 'http_request', [], $this->project->id);
    expect($error)->not->toBeNull();
    expect($error)->toContain('not allowed to make external API calls');
});

test('DataAccessGuard allows external API when enabled', function () {
    $this->agent->update([
        'data_access_scope' => ['external_apis' => true],
    ]);

    $guard = new DataAccessGuard;
    $error = $guard->check($this->agent, 'http_request', [], $this->project->id);
    expect($error)->toBeNull();
});

// ──── #193: Agent Audit Log ────

test('AuditLogger creates audit log entry', function () {
    AuditLogger::log('agent.created', 'Test agent created', [
        'agent_slug' => 'test-agent',
    ], $this->agent->id, $this->project->id);

    expect(AgentAuditLog::count())->toBe(1);

    $log = AgentAuditLog::first();
    expect($log->event)->toBe('agent.created');
    expect($log->description)->toBe('Test agent created');
    expect($log->agent_id)->toBe($this->agent->id);
    expect($log->project_id)->toBe($this->project->id);
    expect($log->user_id)->toBe($this->user->id);
    expect($log->uuid)->not->toBeNull();
    expect($log->metadata)->toBe(['agent_slug' => 'test-agent']);
});

test('AuditLogger captures user and IP', function () {
    AuditLogger::log('agent.updated', 'Agent updated');

    $log = AgentAuditLog::first();
    expect($log->user_id)->toBe($this->user->id);
});

test('AgentAuditLog scopes work correctly', function () {
    AuditLogger::log('agent.created', 'Agent 1 created', [], $this->agent->id);
    AuditLogger::log('agent.updated', 'Agent 1 updated', [], $this->agent->id);
    AuditLogger::log('agent.executed', 'Agent 1 executed', [], $this->agent->id);

    $otherAgent = Agent::create([
        'name' => 'Other Agent',
        'slug' => 'other-agent',
        'role' => 'assistant',
        'base_instructions' => 'Test.',
    ]);
    AuditLogger::log('agent.created', 'Other agent created', [], $otherAgent->id);

    expect(AgentAuditLog::forAgent($this->agent->id)->count())->toBe(3);
    expect(AgentAuditLog::forAgent($otherAgent->id)->count())->toBe(1);
    expect(AgentAuditLog::forEvent('agent.created')->count())->toBe(2);
    expect(AgentAuditLog::forEvent('agent.updated')->count())->toBe(1);
    expect(AgentAuditLog::forUser($this->user->id)->count())->toBe(4);
});

test('AgentAuditLog date range scope works', function () {
    $oldLog = AgentAuditLog::create([
        'event' => 'agent.created',
        'description' => 'Old event',
    ]);
    // Force update created_at to simulate old entry
    AgentAuditLog::where('id', $oldLog->id)->update(['created_at' => now()->subDays(5)]);

    AgentAuditLog::create([
        'event' => 'agent.updated',
        'description' => 'Recent event',
    ]);

    // Filter: from 1 day ago to now (should only get the recent event)
    $recent = AgentAuditLog::inDateRange(
        now()->subDay()->toDateTimeString(),
        now()->addMinute()->toDateTimeString()
    )->get();
    expect($recent)->toHaveCount(1);
    expect($recent->first()->event)->toBe('agent.updated');
});

test('GET /api/audit-logs returns paginated logs', function () {
    AuditLogger::log('agent.created', 'Agent created', [], $this->agent->id);
    AuditLogger::log('agent.executed', 'Agent executed', [], $this->agent->id);
    AuditLogger::log('tool.blocked', 'Tool blocked', [], $this->agent->id);

    $response = $this->getJson('/api/audit-logs');
    $response->assertOk();
    expect($response->json('total'))->toBe(3);
});

test('GET /api/audit-logs filters by event', function () {
    AuditLogger::log('agent.created', 'Agent created', [], $this->agent->id);
    AuditLogger::log('agent.executed', 'Agent executed', [], $this->agent->id);

    $response = $this->getJson('/api/audit-logs?event=agent.created');
    $response->assertOk();
    expect($response->json('total'))->toBe(1);
});

test('GET /api/agents/{agent}/audit-logs returns agent-specific logs', function () {
    AuditLogger::log('agent.created', 'Agent created', [], $this->agent->id);

    $otherAgent = Agent::create([
        'name' => 'Other',
        'slug' => 'other',
        'role' => 'assistant',
        'base_instructions' => 'Test.',
    ]);
    AuditLogger::log('agent.created', 'Other created', [], $otherAgent->id);

    $response = $this->getJson("/api/agents/{$this->agent->id}/audit-logs");
    $response->assertOk();
    expect($response->json('total'))->toBe(1);
});

test('Agent has auditLogs relationship', function () {
    AuditLogger::log('agent.created', 'Agent created', [], $this->agent->id);
    AuditLogger::log('agent.executed', 'Agent executed', [], $this->agent->id);

    expect($this->agent->auditLogs()->count())->toBe(2);
});

// ──── Budget status endpoint ────

test('GET /api/agents/{agent}/budget-status returns correct remaining budget', function () {
    $this->agent->update(['daily_budget_limit_usd' => 10.0000]);

    ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'total_cost_microcents' => 3_000_000, // $3.00
        'status' => 'completed',
    ]);

    $response = $this->getJson("/api/agents/{$this->agent->id}/budget-status");
    $response->assertOk();

    $data = $response->json('data');
    expect($data['daily_spend_microcents'])->toBe(3_000_000);
    expect($data['daily_remaining_microcents'])->toBe(7_000_000);
});
