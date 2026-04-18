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

it('returns target_model and model_context_window from agent model', function () {
    $result = $this->service->compose($this->project, $this->agent);

    expect($result['target_model'])->toBe('claude-sonnet-4-6')
        ->and($result['model_context_window'])->toBe(200000);
});

it('applies modelOverride to target_model and context window', function () {
    $result = $this->service->compose($this->project, $this->agent, 'full', 'gpt-5.4');

    expect($result['target_model'])->toBe('gpt-5.4')
        ->and($result['model_context_window'])->toBe(1048576);
});

it('returns 0 context_window for an unknown model', function () {
    $result = $this->service->compose($this->project, $this->agent, 'full', 'mystery-model');

    expect($result['target_model'])->toBe('mystery-model')
        ->and($result['model_context_window'])->toBe(0);
});

it('returns skill_breakdown with offsets aligned to content', function () {
    $skillA = Skill::create([
        'project_id' => $this->project->id,
        'name' => 'Alpha Skill',
        'slug' => 'alpha-skill',
        'body' => 'Alpha body content.',
        'tuned_for_model' => 'claude-sonnet-4-6',
    ]);
    $skillB = Skill::create([
        'project_id' => $this->project->id,
        'name' => 'Beta Skill',
        'slug' => 'beta-skill',
        'body' => 'Beta body content.',
        'last_validated_model' => 'claude-opus-4-6',
    ]);

    DB::table('agent_skill')->insert([
        ['project_id' => $this->project->id, 'agent_id' => $this->agent->id, 'skill_id' => $skillA->id],
        ['project_id' => $this->project->id, 'agent_id' => $this->agent->id, 'skill_id' => $skillB->id],
    ]);

    $result = $this->service->compose($this->project, $this->agent);
    $breakdown = $result['skill_breakdown'];

    expect($breakdown)->toHaveCount(2);

    foreach ($breakdown as $entry) {
        $slice = substr($result['content'], $entry['starts_at_char'], $entry['ends_at_char'] - $entry['starts_at_char']);
        expect($slice)->toStartWith("### {$entry['name']}");
    }

    $alphaEntry = collect($breakdown)->firstWhere('slug', 'alpha-skill');
    $betaEntry = collect($breakdown)->firstWhere('slug', 'beta-skill');

    expect($alphaEntry['tuned_for_model'])->toBe('claude-sonnet-4-6')
        ->and($betaEntry['last_validated_model'])->toBe('claude-opus-4-6')
        ->and($alphaEntry['ends_at_char'])->toBeLessThanOrEqual($betaEntry['starts_at_char']);
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
