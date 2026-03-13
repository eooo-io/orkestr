<?php

use App\Models\Agent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates an agent with loop fields', function () {
    $agent = Agent::create([
        'name' => 'Test Agent',
        'role' => 'tester',
        'base_instructions' => 'Test instructions',
        'objective_template' => 'Run all tests',
        'success_criteria' => ['tests_pass', 'no_regressions'],
        'max_iterations' => 10,
        'timeout_seconds' => 300,
        'context_strategy' => 'full',
        'planning_mode' => 'plan_then_act',
        'loop_condition' => 'goal_met',
        'can_delegate' => true,
        'is_template' => true,
        'delegation_rules' => ['max_depth' => 2],
        'custom_tools' => [['name' => 'lint', 'description' => 'Run linter']],
    ]);

    expect($agent->id)->toBeInt();
    expect($agent->uuid)->toBeString();
    expect($agent->slug)->toBe('test-agent');
    expect($agent->success_criteria)->toBe(['tests_pass', 'no_regressions']);
    expect($agent->max_iterations)->toBe(10);
    expect($agent->can_delegate)->toBeTrue();
    expect($agent->is_template)->toBeTrue();
    expect($agent->delegation_rules)->toBe(['max_depth' => 2]);
    expect($agent->custom_tools)->toBe([['name' => 'lint', 'description' => 'Run linter']]);
});

it('auto-generates uuid and slug', function () {
    $agent = Agent::create([
        'name' => 'Auto Generated',
        'role' => 'auto',
        'base_instructions' => 'Test',
    ]);

    expect($agent->uuid)->toMatch('/^[0-9a-f]{8}-/');
    expect($agent->slug)->toBe('auto-generated');
});

it('has parent/child agent relationships', function () {
    $parent = Agent::create([
        'name' => 'Parent Agent',
        'role' => 'orchestrator',
        'base_instructions' => 'Parent',
        'can_delegate' => true,
    ]);

    $child = Agent::create([
        'name' => 'Child Agent',
        'role' => 'worker',
        'base_instructions' => 'Child',
        'parent_agent_id' => $parent->id,
    ]);

    expect($child->parentAgent->id)->toBe($parent->id);
    expect($parent->childAgents->count())->toBe(1);
    expect($parent->childAgents->first()->id)->toBe($child->id);
});

it('returns effective system prompt', function () {
    $agent = Agent::create([
        'name' => 'Prompt Agent',
        'role' => 'test',
        'base_instructions' => 'Base instructions here',
        'persona_prompt' => 'Persona prompt here',
    ]);

    // system_prompt is null, so falls back to persona_prompt
    expect($agent->getEffectiveSystemPrompt())->toBe('Persona prompt here');

    $agent->update(['system_prompt' => 'Explicit system prompt']);
    expect($agent->getEffectiveSystemPrompt())->toBe('Explicit system prompt');
});

it('detects loop config correctly', function () {
    $noLoop = Agent::create([
        'name' => 'No Loop',
        'role' => 'basic',
        'base_instructions' => 'Basic',
    ]);

    expect($noLoop->hasLoopConfig())->toBeFalse();

    $withLoop = Agent::create([
        'name' => 'With Loop',
        'role' => 'looper',
        'base_instructions' => 'Loop',
        'objective_template' => 'Do the thing',
        'max_iterations' => 5,
    ]);

    expect($withLoop->hasLoopConfig())->toBeTrue();
});

it('scopes templates and delegators', function () {
    Agent::query()->delete();

    Agent::create(['name' => 'Tmpl', 'role' => 'a', 'base_instructions' => 'x', 'is_template' => true]);
    Agent::create(['name' => 'Deleg', 'role' => 'b', 'base_instructions' => 'x', 'can_delegate' => true]);
    Agent::create(['name' => 'Normal', 'role' => 'c', 'base_instructions' => 'x']);

    expect(Agent::templates()->count())->toBe(1);
    expect(Agent::delegators()->count())->toBe(1);
});

it('casts JSON columns correctly', function () {
    $agent = Agent::create([
        'name' => 'JSON Test',
        'role' => 'test',
        'base_instructions' => 'Test',
        'input_schema' => ['type' => 'object', 'properties' => ['q' => ['type' => 'string']]],
        'output_schema' => ['type' => 'string'],
        'eval_criteria' => ['accurate', 'complete'],
        'memory_sources' => ['conversation', 'files'],
    ]);

    $fresh = Agent::find($agent->id);
    expect($fresh->input_schema)->toBeArray();
    expect($fresh->input_schema['type'])->toBe('object');
    expect($fresh->output_schema)->toBeArray();
    expect($fresh->eval_criteria)->toBe(['accurate', 'complete']);
    expect($fresh->memory_sources)->toBe(['conversation', 'files']);
});
