<?php

use App\Models\Agent;
use App\Models\Project;
use App\Models\ProjectAgent;
use App\Models\Skill;
use App\Services\AgentComposeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->project = Project::create([
        'name' => 'Test Project',
        'path' => '/tmp/test-project',
        'providers' => ['claude'],
    ]);

    $this->agent = Agent::create([
        'name' => 'Test Agent',
        'role' => 'tester',
        'base_instructions' => 'You are a test agent.',
        'model' => 'claude-sonnet-4-6',
        'objective_template' => 'Complete the tests',
        'success_criteria' => ['all_pass'],
        'max_iterations' => 5,
        'planning_mode' => 'plan_then_act',
        'loop_condition' => 'goal_met',
    ]);

    ProjectAgent::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'is_enabled' => true,
    ]);

    $this->service = app(AgentComposeService::class);
});

it('composes markdown output for an agent', function () {
    $result = $this->service->compose($this->project, $this->agent);

    expect($result['content'])->toContain('# Test Agent');
    expect($result['content'])->toContain('You are a test agent.');
    expect($result['token_estimate'])->toBeGreaterThan(0);
    expect($result['agent']['name'])->toBe('Test Agent');
    expect($result['skill_count'])->toBe(0);
});

it('composes markdown with assigned skills', function () {
    $skill = Skill::create([
        'project_id' => $this->project->id,
        'name' => 'Test Skill',
        'slug' => 'test-skill',
        'body' => 'Skill body content.',
    ]);

    DB::table('agent_skill')->insert([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'skill_id' => $skill->id,
    ]);

    $result = $this->service->compose($this->project, $this->agent);

    expect($result['content'])->toContain('## Assigned Skills');
    expect($result['content'])->toContain('### Test Skill');
    expect($result['content'])->toContain('Skill body content.');
    expect($result['skill_count'])->toBe(1);
});

it('includes custom instructions in compose', function () {
    ProjectAgent::where('project_id', $this->project->id)
        ->where('agent_id', $this->agent->id)
        ->update(['custom_instructions' => 'Custom project-specific note.']);

    $result = $this->service->compose($this->project, $this->agent);

    expect($result['content'])->toContain('## Project-Specific Instructions');
    expect($result['content'])->toContain('Custom project-specific note.');
});

it('composes structured output', function () {
    $result = $this->service->composeStructured($this->project, $this->agent);

    expect($result['agent']['name'])->toBe('Test Agent');
    expect($result['model'])->toBe('claude-sonnet-4-6');
    expect($result['system_prompt'])->toContain('# Test Agent');
    expect($result['goal']['objective'])->toBe('Complete the tests');
    expect($result['goal']['success_criteria'])->toBe(['all_pass']);
    expect($result['goal']['max_iterations'])->toBe(5);
    expect($result['goal']['loop_condition'])->toBe('goal_met');
    expect($result['reasoning']['planning_mode'])->toBe('plan_then_act');
    expect($result['tools']['mcp_servers'])->toBeEmpty();
    expect($result['tools']['a2a_agents'])->toBeEmpty();
    expect($result['skills'])->toBeEmpty();
    expect($result['orchestration']['can_delegate'])->toBeFalsy();
});

it('applies project-level overrides in structured compose', function () {
    ProjectAgent::where('project_id', $this->project->id)
        ->where('agent_id', $this->agent->id)
        ->update([
            'model_override' => 'gpt-4',
            'max_iterations_override' => 20,
            'objective_override' => 'Overridden objective',
        ]);

    $result = $this->service->composeStructured($this->project, $this->agent);

    expect($result['model'])->toBe('gpt-4');
    expect($result['goal']['max_iterations'])->toBe(20);
    expect($result['goal']['objective'])->toBe('Overridden objective');
});

it('composes all enabled agents', function () {
    $agent2 = Agent::create([
        'name' => 'Disabled Agent',
        'role' => 'disabled',
        'base_instructions' => 'Disabled.',
    ]);

    ProjectAgent::create([
        'project_id' => $this->project->id,
        'agent_id' => $agent2->id,
        'is_enabled' => false,
    ]);

    $results = $this->service->composeAll($this->project);

    expect($results)->toHaveCount(1);
    expect($results[0]['agent']['name'])->toBe('Test Agent');
});

it('returns empty array when no agents enabled', function () {
    ProjectAgent::where('project_id', $this->project->id)->update(['is_enabled' => false]);

    $results = $this->service->composeAll($this->project);

    expect($results)->toBeEmpty();
});
