<?php

use App\Models\Project;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowEdge;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create([
        'name' => 'API Test Project',
        'path' => '/tmp/api-test',
    ]);
});

it('lists workflows for a project', function () {
    Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Workflow A',
    ]);

    Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Workflow B',
    ]);

    $response = $this->getJson("/api/projects/{$this->project->id}/workflows");

    $response->assertOk();
    $response->assertJsonCount(2, 'data');
});

it('creates a workflow', function () {
    $response = $this->postJson("/api/projects/{$this->project->id}/workflows", [
        'name' => 'New Workflow',
        'description' => 'Test description',
        'trigger_type' => 'manual',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'New Workflow');
    $response->assertJsonPath('data.status', 'draft');
});

it('shows a workflow with steps and edges', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Show Test',
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

    $response = $this->getJson("/api/projects/{$this->project->id}/workflows/{$workflow->id}");

    $response->assertOk();
    $response->assertJsonPath('data.name', 'Show Test');
    $response->assertJsonCount(2, 'data.steps');
    $response->assertJsonCount(1, 'data.edges');
});

it('updates a workflow', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Original',
    ]);

    $response = $this->putJson(
        "/api/projects/{$this->project->id}/workflows/{$workflow->id}",
        ['name' => 'Updated', 'status' => 'active'],
    );

    $response->assertOk();
    $response->assertJsonPath('data.name', 'Updated');
    $response->assertJsonPath('data.status', 'active');
});

it('deletes a workflow', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'To Delete',
    ]);

    $response = $this->deleteJson("/api/projects/{$this->project->id}/workflows/{$workflow->id}");

    $response->assertNoContent();
    expect(Workflow::find($workflow->id))->toBeNull();
});

it('duplicates a workflow with steps and edges', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Original',
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

    $response = $this->postJson("/api/projects/{$this->project->id}/workflows/{$workflow->id}/duplicate");

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'Original (copy)');
    $response->assertJsonCount(2, 'data.steps');
    $response->assertJsonCount(1, 'data.edges');
});

it('validates a workflow', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Valid Workflow',
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

    $response = $this->postJson("/api/projects/{$this->project->id}/workflows/{$workflow->id}/validate");

    $response->assertOk();
    $response->assertJsonPath('valid', true);
});

it('detects validation errors', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Invalid',
    ]);

    // No start, no end, no steps
    $response = $this->postJson("/api/projects/{$this->project->id}/workflows/{$workflow->id}/validate");

    $response->assertOk();
    $response->assertJsonPath('valid', false);
    expect($response->json('errors'))->not->toBeEmpty();
});

it('exports workflow as JSON', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Export Test',
    ]);

    WorkflowStep::create([
        'workflow_id' => $workflow->id,
        'type' => 'start',
        'name' => 'Start',
    ]);

    $response = $this->getJson("/api/projects/{$this->project->id}/workflows/{$workflow->id}/export");

    $response->assertOk();
    $response->assertJsonPath('format', 'agentis-workflow');
    $response->assertJsonPath('workflow.name', 'Export Test');
});

it('manages workflow versions', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Versioned',
    ]);

    WorkflowStep::create([
        'workflow_id' => $workflow->id,
        'type' => 'start',
        'name' => 'Start',
    ]);

    // Create version
    $response = $this->postJson(
        "/api/projects/{$this->project->id}/workflows/{$workflow->id}/versions",
        ['note' => 'v1'],
    );

    $response->assertCreated();
    $response->assertJsonPath('version_number', 1);

    // List versions
    $response = $this->getJson("/api/projects/{$this->project->id}/workflows/{$workflow->id}/versions");
    $response->assertOk();
    expect($response->json())->toHaveCount(1);
});
