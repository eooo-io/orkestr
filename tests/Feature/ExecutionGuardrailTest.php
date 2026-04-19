<?php

use App\Models\Agent;
use App\Models\ExecutionRun;
use App\Models\ExecutionStep;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Services\Execution\ExecutionGuardrailService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(ExecutionGuardrailService::class);

    $this->user = User::firstOrCreate(
        ['email' => 'guard@test.com'],
        ['name' => 'Guardrail', 'password' => bcrypt('password')],
    );

    $this->org = Organization::create([
        'name' => 'Guardrail Org',
        'max_agent_turns_per_run' => 5,
        'default_run_token_budget' => 10000,
        'default_run_cost_budget_usd' => 1.00,
    ]);

    $this->project = Project::create([
        'name' => 'Guardrail Project',
        'path' => '/tmp/guardrail',
        'providers' => ['claude'],
        'owner_id' => $this->user->id,
        'organization_id' => $this->org->id,
    ]);

    $this->agent = Agent::create([
        'name' => 'Loopy',
        'role' => 'worker',
        'base_instructions' => 'Do a thing.',
        'model' => 'claude-sonnet-4-6',
        'objective_template' => 'Task',
        'success_criteria' => ['done'],
        'max_iterations' => 20,
        'planning_mode' => 'plan_then_act',
        'loop_condition' => 'goal_met',
    ]);

    $this->run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'input' => ['message' => 'hi'],
        'created_by' => $this->user->id,
    ]);
});

function seedActStep(ExecutionRun $run, int $step, string $tool, array $input): ExecutionStep
{
    return ExecutionStep::create([
        'execution_run_id' => $run->id,
        'step_number' => $step,
        'phase' => 'act',
        'input' => ['tool_calls' => [['name' => $tool, 'input' => $input]]],
        'status' => 'completed',
    ]);
}

it('detectLoop fires when same tool+input repeats past threshold', function () {
    seedActStep($this->run, 1, 'search', ['q' => 'widgets']);
    seedActStep($this->run, 2, 'search', ['q' => 'widgets']);
    seedActStep($this->run, 3, 'search', ['q' => 'widgets']);

    $reason = $this->service->detectLoop(
        $this->run->fresh(),
        $this->agent->id,
        'search',
        ['q' => 'widgets'],
    );

    expect($reason)->toBe(ExecutionGuardrailService::REASON_LOOP_DETECTED);
});

it('detectLoop ignores repeats with different inputs', function () {
    seedActStep($this->run, 1, 'search', ['q' => 'widgets']);
    seedActStep($this->run, 2, 'search', ['q' => 'gears']);
    seedActStep($this->run, 3, 'search', ['q' => 'sprockets']);

    $reason = $this->service->detectLoop(
        $this->run->fresh(),
        $this->agent->id,
        'search',
        ['q' => 'cogs'],
    );

    expect($reason)->toBeNull();
});

it('checkTurnCap fires at org-configured cap', function () {
    expect($this->service->checkTurnCap($this->run, 5))->toBeNull();
    expect($this->service->checkTurnCap($this->run, 6))->toBe(ExecutionGuardrailService::REASON_TURN_CAP_EXCEEDED);
});

it('checkBudget fires when total_tokens exceeds org default', function () {
    $this->run->update(['total_tokens' => 10000]);

    $reason = $this->service->checkBudget($this->run->fresh());

    expect($reason)->toBe(ExecutionGuardrailService::REASON_BUDGET_TOKEN_EXCEEDED);
});

it('checkBudget fires when total_cost_microcents exceeds org default', function () {
    // $1.00 = 1,000,000 microcents
    $this->run->update(['total_cost_microcents' => 1_000_000, 'total_tokens' => 0]);

    $reason = $this->service->checkBudget($this->run->fresh());

    expect($reason)->toBe(ExecutionGuardrailService::REASON_BUDGET_COST_EXCEEDED);
});

it('per-run override takes precedence over agent + org defaults', function () {
    $this->run->update([
        'token_budget' => 500,
        'total_tokens' => 600,
    ]);

    $reason = $this->service->checkBudget($this->run->fresh());

    expect($reason)->toBe(ExecutionGuardrailService::REASON_BUDGET_TOKEN_EXCEEDED);
});

it('agent override takes precedence over org default', function () {
    $this->agent->update(['run_token_budget' => 200]);
    $this->run->update(['total_tokens' => 300]);

    $reason = $this->service->checkBudget($this->run->fresh());

    expect($reason)->toBe(ExecutionGuardrailService::REASON_BUDGET_TOKEN_EXCEEDED);
});

it('checkBudget passes cleanly when under all budgets', function () {
    $this->run->update(['total_tokens' => 100, 'total_cost_microcents' => 100]);

    expect($this->service->checkBudget($this->run->fresh()))->toBeNull();
});

it('halt sets halted_guardrail status and notifies owner', function () {
    $this->service->halt($this->run, ExecutionGuardrailService::REASON_LOOP_DETECTED);

    $fresh = $this->run->fresh();
    expect($fresh->status)->toBe('halted_guardrail')
        ->and($fresh->halt_reason)->toBe('loop_detected')
        ->and($fresh->completed_at)->not->toBeNull();

    $notification = Notification::where('user_id', $this->user->id)->first();
    expect($notification)->not->toBeNull()
        ->and($notification->type)->toBe('agent.halt')
        ->and($notification->data)->toMatchArray([
            'run_id' => $this->run->id,
            'halt_reason' => 'loop_detected',
        ]);
});

it('halt records halt_step_id when a step is provided', function () {
    $step = seedActStep($this->run, 1, 'search', ['q' => 'x']);

    $this->service->halt(
        $this->run,
        ExecutionGuardrailService::REASON_LOOP_DETECTED,
        $step,
    );

    expect($this->run->fresh()->halt_step_id)->toBe($step->id);
});

it('resolveTurnCap falls back to 40 when no org linked', function () {
    $solo = Project::create([
        'name' => 'Solo',
        'path' => '/tmp/solo',
        'providers' => ['claude'],
        'owner_id' => $this->user->id,
    ]);
    $run = ExecutionRun::create([
        'project_id' => $solo->id,
        'agent_id' => $this->agent->id,
        'input' => [],
    ]);

    expect($this->service->resolveTurnCap($run))->toBe(40);
});
