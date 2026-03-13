<?php

use App\Models\Agent;
use App\Models\Project;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowEdge;
use App\Models\WorkflowRun;
use App\Models\WorkflowRunStep;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create(['name' => 'WF Exec Test', 'path' => '/tmp/wf-exec']);
});

// --- WorkflowRun model tests ---

test('WorkflowRun creates with auto UUID and defaults', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Test WF',
    ]);

    $run = WorkflowRun::create([
        'workflow_id' => $workflow->id,
        'project_id' => $this->project->id,
        'input' => ['key' => 'value'],
    ]);

    expect($run->uuid)->not->toBeNull();
    expect($run->status)->toBe('pending');
    expect($run->isPending())->toBeTrue();
});

test('WorkflowRun status transitions work', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Test WF',
    ]);

    $run = WorkflowRun::create([
        'workflow_id' => $workflow->id,
        'project_id' => $this->project->id,
    ]);

    $run->markRunning();
    expect($run->isRunning())->toBeTrue();
    expect($run->started_at)->not->toBeNull();

    $run->markCompleted(['result' => 'done']);
    expect($run->isCompleted())->toBeTrue();
    expect($run->isFinished())->toBeTrue();
});

test('WorkflowRun checkpoint pause works', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Test WF',
    ]);

    $step = WorkflowStep::create([
        'workflow_id' => $workflow->id,
        'type' => 'checkpoint',
        'name' => 'Approval Gate',
        'position_x' => 0,
        'position_y' => 0,
    ]);

    $run = WorkflowRun::create([
        'workflow_id' => $workflow->id,
        'project_id' => $this->project->id,
    ]);

    $run->markRunning();
    $run->markWaitingCheckpoint($step->id);

    expect($run->isWaitingCheckpoint())->toBeTrue();
    expect($run->current_step_id)->toBe($step->id);
});

test('WorkflowRun cascades deletion to run steps', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Test WF',
    ]);

    $step = WorkflowStep::create([
        'workflow_id' => $workflow->id,
        'type' => 'start',
        'name' => 'Start',
        'position_x' => 0,
        'position_y' => 0,
    ]);

    $run = WorkflowRun::create([
        'workflow_id' => $workflow->id,
        'project_id' => $this->project->id,
    ]);

    WorkflowRunStep::create([
        'workflow_run_id' => $run->id,
        'workflow_step_id' => $step->id,
    ]);

    expect(WorkflowRunStep::where('workflow_run_id', $run->id)->count())->toBe(1);

    $run->delete();

    expect(WorkflowRunStep::where('workflow_run_id', $run->id)->count())->toBe(0);
});

// --- WorkflowRunStep model tests ---

test('WorkflowRunStep creates with auto UUID', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Test WF',
    ]);

    $step = WorkflowStep::create([
        'workflow_id' => $workflow->id,
        'type' => 'agent',
        'name' => 'Agent Step',
        'position_x' => 0,
        'position_y' => 0,
    ]);

    $run = WorkflowRun::create([
        'workflow_id' => $workflow->id,
        'project_id' => $this->project->id,
    ]);

    $runStep = WorkflowRunStep::create([
        'workflow_run_id' => $run->id,
        'workflow_step_id' => $step->id,
    ]);

    expect($runStep->uuid)->not->toBeNull();
    expect($runStep->status)->toBe('pending');
});

test('WorkflowRunStep status transitions', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Test WF',
    ]);

    $step = WorkflowStep::create([
        'workflow_id' => $workflow->id,
        'type' => 'checkpoint',
        'name' => 'Gate',
        'position_x' => 0,
        'position_y' => 0,
    ]);

    $run = WorkflowRun::create([
        'workflow_id' => $workflow->id,
        'project_id' => $this->project->id,
    ]);

    $runStep = WorkflowRunStep::create([
        'workflow_run_id' => $run->id,
        'workflow_step_id' => $step->id,
    ]);

    $runStep->markRunning();
    expect($runStep->fresh()->status)->toBe('running');

    $runStep->markWaitingApproval();
    expect($runStep->fresh()->status)->toBe('waiting_approval');

    $runStep->markCompleted(['approved' => true]);
    expect($runStep->fresh()->status)->toBe('completed');
});

// --- API tests ---

test('workflow runs list endpoint works', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Test WF',
    ]);

    WorkflowRun::create([
        'workflow_id' => $workflow->id,
        'project_id' => $this->project->id,
        'status' => 'completed',
    ]);

    $response = $this->getJson("/api/projects/{$this->project->id}/workflow-runs");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

test('workflow run show includes run steps', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Test WF',
    ]);

    $step = WorkflowStep::create([
        'workflow_id' => $workflow->id,
        'type' => 'start',
        'name' => 'Start',
        'position_x' => 0,
        'position_y' => 0,
    ]);

    $run = WorkflowRun::create([
        'workflow_id' => $workflow->id,
        'project_id' => $this->project->id,
    ]);

    WorkflowRunStep::create([
        'workflow_run_id' => $run->id,
        'workflow_step_id' => $step->id,
        'status' => 'completed',
    ]);

    $response = $this->getJson("/api/workflow-runs/{$run->id}");

    $response->assertOk();
    expect($response->json('data.run_steps'))->toHaveCount(1);
});

test('workflow run cancel works', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Test WF',
    ]);

    $run = WorkflowRun::create([
        'workflow_id' => $workflow->id,
        'project_id' => $this->project->id,
        'status' => 'running',
        'started_at' => now(),
    ]);

    $response = $this->postJson("/api/workflow-runs/{$run->id}/cancel");

    $response->assertOk();
    expect($run->fresh()->status)->toBe('cancelled');
});

test('workflow run cancel rejects finished runs', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Test WF',
    ]);

    $run = WorkflowRun::create([
        'workflow_id' => $workflow->id,
        'project_id' => $this->project->id,
        'status' => 'completed',
    ]);

    $response = $this->postJson("/api/workflow-runs/{$run->id}/cancel");

    $response->assertStatus(422);
});

// --- Execution with start → end workflow ---

test('simple start-to-end workflow executes', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Simple WF',
    ]);

    $start = WorkflowStep::create([
        'workflow_id' => $workflow->id,
        'type' => 'start',
        'name' => 'Start',
        'position_x' => 0,
        'position_y' => 0,
    ]);

    $end = WorkflowStep::create([
        'workflow_id' => $workflow->id,
        'type' => 'end',
        'name' => 'End',
        'position_x' => 200,
        'position_y' => 0,
    ]);

    $workflow->update(['entry_step_id' => $start->id]);

    WorkflowEdge::create([
        'workflow_id' => $workflow->id,
        'source_step_id' => $start->id,
        'target_step_id' => $end->id,
    ]);

    $response = $this->postJson("/api/projects/{$this->project->id}/workflows/{$workflow->id}/execute", [
        'input' => ['test' => true],
    ]);

    $response->assertStatus(201);
    expect($response->json('data.status'))->toBe('completed');
    expect($response->json('data.run_steps'))->toHaveCount(2); // start + end
});
