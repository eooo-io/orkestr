<?php

use App\Models\Agent;
use App\Models\Project;
use App\Models\Workflow;
use App\Models\WorkflowEdge;
use App\Models\WorkflowStep;
use App\Models\WorkflowVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->project = Project::create([
        'name' => 'Test Project',
        'path' => '/tmp/test-project',
    ]);
});

it('creates a workflow with auto-generated uuid and slug', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'My Test Workflow',
    ]);

    expect($workflow->uuid)->not->toBeEmpty();
    expect($workflow->slug)->toBe('my-test-workflow');
    expect($workflow->status)->toBe('draft');
    expect($workflow->trigger_type)->toBe('manual');
});

it('creates workflow steps with types', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Step Test',
    ]);

    $start = WorkflowStep::create([
        'workflow_id' => $workflow->id,
        'type' => 'start',
        'name' => 'Begin',
        'position_x' => 0,
        'position_y' => 0,
    ]);

    $agent = Agent::create([
        'name' => 'Worker',
        'role' => 'worker',
        'base_instructions' => 'Work hard.',
    ]);

    $agentStep = WorkflowStep::create([
        'workflow_id' => $workflow->id,
        'agent_id' => $agent->id,
        'type' => 'agent',
        'name' => 'Do Work',
        'position_x' => 100,
        'position_y' => 100,
    ]);

    expect($start->uuid)->not->toBeEmpty();
    expect($start->isTerminal())->toBeFalse();
    expect($agentStep->isAgent())->toBeTrue();
    expect($agentStep->requiresAgent())->toBeTrue();
    expect($agentStep->agent->name)->toBe('Worker');
});

it('creates edges between steps', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Edge Test',
    ]);

    $s1 = WorkflowStep::create([
        'workflow_id' => $workflow->id,
        'type' => 'start',
        'name' => 'Start',
    ]);

    $s2 = WorkflowStep::create([
        'workflow_id' => $workflow->id,
        'type' => 'end',
        'name' => 'End',
    ]);

    $edge = WorkflowEdge::create([
        'workflow_id' => $workflow->id,
        'source_step_id' => $s1->id,
        'target_step_id' => $s2->id,
        'condition_expression' => 'status == "done"',
        'label' => 'Complete',
        'priority' => 1,
    ]);

    expect($edge->hasCondition())->toBeTrue();
    expect($edge->sourceStep->name)->toBe('Start');
    expect($edge->targetStep->name)->toBe('End');
});

it('has correct relationships', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Relationship Test',
    ]);

    WorkflowStep::create([
        'workflow_id' => $workflow->id,
        'type' => 'start',
        'name' => 'Start',
    ]);

    WorkflowStep::create([
        'workflow_id' => $workflow->id,
        'type' => 'end',
        'name' => 'End',
    ]);

    expect($workflow->project->name)->toBe('Test Project');
    expect($workflow->steps)->toHaveCount(2);
    expect($this->project->workflows)->toHaveCount(1);
});

it('creates and retrieves versions', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Version Test',
    ]);

    WorkflowStep::create([
        'workflow_id' => $workflow->id,
        'type' => 'start',
        'name' => 'Start',
    ]);

    $workflow->load(['steps', 'edges']);

    $version = WorkflowVersion::create([
        'workflow_id' => $workflow->id,
        'version_number' => $workflow->nextVersionNumber(),
        'snapshot' => $workflow->snapshot(),
        'note' => 'Initial version',
    ]);

    expect($version->version_number)->toBe(1);
    expect($version->snapshot)->toBeArray();
    expect($version->snapshot['steps'])->toHaveCount(1);
    expect($workflow->nextVersionNumber())->toBe(2);
});

it('returns correct status helpers', function () {
    $draft = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Draft',
        'status' => 'draft',
    ]);

    $active = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Active',
        'status' => 'active',
    ]);

    expect($draft->isDraft())->toBeTrue();
    expect($draft->isActive())->toBeFalse();
    expect($active->isActive())->toBeTrue();
    expect($active->isDraft())->toBeFalse();
});

it('cascades deletion of steps and edges', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Cascade Test',
    ]);

    $s1 = WorkflowStep::create([
        'workflow_id' => $workflow->id,
        'type' => 'start',
        'name' => 'Start',
    ]);

    $s2 = WorkflowStep::create([
        'workflow_id' => $workflow->id,
        'type' => 'end',
        'name' => 'End',
    ]);

    WorkflowEdge::create([
        'workflow_id' => $workflow->id,
        'source_step_id' => $s1->id,
        'target_step_id' => $s2->id,
    ]);

    $workflow->delete();

    expect(WorkflowStep::where('workflow_id', $workflow->id)->count())->toBe(0);
    expect(WorkflowEdge::where('workflow_id', $workflow->id)->count())->toBe(0);
});
