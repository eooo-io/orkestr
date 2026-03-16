<?php

use App\Models\Agent;
use App\Models\AgentTask;
use App\Models\Project;
use App\Models\User;
use App\Services\OrchestratorRoutingService;
use App\Services\TaskPickupService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::firstOrCreate(
        ['email' => 'test@test.com'],
        ['name' => 'Test User', 'password' => bcrypt('password')],
    );

    $this->project = Project::create(['name' => 'Task Test', 'path' => '/tmp/task-test']);

    $this->agent = Agent::create([
        'name' => 'Test Agent',
        'slug' => 'test-agent',
        'role' => 'generalist',
        'base_instructions' => 'You are a test agent.',
    ]);

    // Attach agent to project
    $this->project->agents()->attach($this->agent->id, ['is_enabled' => true]);
});

// ─── Task CRUD via API ──────────────────────────────────────────

it('lists tasks for a project', function () {
    AgentTask::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'title' => 'Task One',
        'priority' => 'high',
        'status' => 'pending',
    ]);

    AgentTask::create([
        'project_id' => $this->project->id,
        'title' => 'Task Two',
        'priority' => 'low',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/projects/{$this->project->id}/tasks");

    $response->assertOk();
    $response->assertJsonCount(2, 'data');
});

it('filters tasks by status', function () {
    AgentTask::create([
        'project_id' => $this->project->id,
        'title' => 'Pending Task',
        'status' => 'pending',
    ]);

    AgentTask::create([
        'project_id' => $this->project->id,
        'title' => 'Completed Task',
        'status' => 'completed',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/projects/{$this->project->id}/tasks?status=pending");

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.title', 'Pending Task');
});

it('creates a task', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/tasks", [
            'title' => 'Fix the bug',
            'description' => 'There is a bug in the login page.',
            'priority' => 'high',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.title', 'Fix the bug');
    $response->assertJsonPath('data.priority', 'high');
    $response->assertJsonPath('data.status', 'pending');

    $this->assertDatabaseHas('agent_tasks', [
        'title' => 'Fix the bug',
        'project_id' => $this->project->id,
    ]);
});

it('creates a task assigned to an agent', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/tasks", [
            'title' => 'Write tests',
            'agent_id' => $this->agent->id,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.agent_id', $this->agent->id);
    $response->assertJsonPath('data.status', 'assigned');
});

it('updates a task', function () {
    $task = AgentTask::create([
        'project_id' => $this->project->id,
        'title' => 'Old Title',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->user)
        ->putJson("/api/tasks/{$task->id}", [
            'title' => 'New Title',
            'priority' => 'critical',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.title', 'New Title');
    $response->assertJsonPath('data.priority', 'critical');
});

it('deletes a pending task', function () {
    $task = AgentTask::create([
        'project_id' => $this->project->id,
        'title' => 'Delete Me',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/tasks/{$task->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('agent_tasks', ['id' => $task->id]);
});

it('cancels a running task instead of deleting', function () {
    $task = AgentTask::create([
        'project_id' => $this->project->id,
        'title' => 'Running Task',
        'status' => 'running',
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/tasks/{$task->id}");

    $response->assertOk();
    $this->assertDatabaseHas('agent_tasks', [
        'id' => $task->id,
        'status' => 'cancelled',
    ]);
});

// ─── Task Assignment ────────────────────────────────────────────

it('assigns a task to an agent', function () {
    $task = AgentTask::create([
        'project_id' => $this->project->id,
        'title' => 'Unassigned Task',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/tasks/{$task->id}/assign", [
            'agent_id' => $this->agent->id,
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.agent_id', $this->agent->id);
    $response->assertJsonPath('data.status', 'assigned');
});

// ─── Orchestrator Routing ───────────────────────────────────────

it('decomposes a task into subtasks using orchestrator routing', function () {
    // Create a security-focused agent
    $secAgent = Agent::create([
        'name' => 'Security Engineer',
        'slug' => 'security-engineer',
        'role' => 'security',
        'base_instructions' => 'You handle security.',
    ]);

    $task = AgentTask::create([
        'project_id' => $this->project->id,
        'title' => 'Audit the security of the authentication system',
        'description' => 'Check for vulnerabilities and authorization issues.',
        'status' => 'pending',
    ]);

    $router = app(OrchestratorRoutingService::class);
    $router->decompose($task);

    $task->refresh();
    expect($task->status)->toBe('assigned');

    $childTasks = AgentTask::where('parent_task_id', $task->id)->get();
    expect($childTasks)->toHaveCount(1);
    expect($childTasks->first()->agent_id)->toBe($secAgent->id);
});

it('falls back to first enabled project agent when no keyword match', function () {
    $task = AgentTask::create([
        'project_id' => $this->project->id,
        'title' => 'Do something generic',
        'status' => 'pending',
    ]);

    $router = app(OrchestratorRoutingService::class);
    $router->decompose($task);

    $task->refresh();
    expect($task->status)->toBe('assigned');

    $childTasks = AgentTask::where('parent_task_id', $task->id)->get();
    expect($childTasks)->toHaveCount(1);
    expect($childTasks->first()->agent_id)->toBe($this->agent->id);
});

// ─── Task Pickup Service ────────────────────────────────────────

it('picks up the highest priority pending task', function () {
    AgentTask::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'title' => 'Low Priority',
        'priority' => 'low',
        'status' => 'pending',
    ]);

    $highTask = AgentTask::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'title' => 'High Priority',
        'priority' => 'high',
        'status' => 'pending',
    ]);

    AgentTask::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'title' => 'Medium Priority',
        'priority' => 'medium',
        'status' => 'pending',
    ]);

    $service = app(TaskPickupService::class);
    $picked = $service->pickupNext($this->agent, $this->project);

    expect($picked)->not->toBeNull();
    expect($picked->id)->toBe($highTask->id);
});

it('marks a task as running with execution id', function () {
    $task = AgentTask::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'title' => 'Run Me',
        'status' => 'pending',
    ]);

    $service = app(TaskPickupService::class);
    $service->markRunning($task, 42);

    $task->refresh();
    expect($task->status)->toBe('running');
    expect($task->execution_id)->toBe(42);
    expect($task->started_at)->not->toBeNull();
});

it('marks a task as completed with output', function () {
    $task = AgentTask::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'title' => 'Complete Me',
        'status' => 'running',
        'assigned_by_user_id' => $this->user->id,
        'started_at' => now()->subMinutes(5),
    ]);

    $service = app(TaskPickupService::class);
    $service->markCompleted($task, ['result' => 'success']);

    $task->refresh();
    expect($task->status)->toBe('completed');
    expect($task->output_data)->toBe(['result' => 'success']);
    expect($task->completed_at)->not->toBeNull();
});

it('marks a task as failed with error', function () {
    $task = AgentTask::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'title' => 'Fail Me',
        'status' => 'running',
        'assigned_by_user_id' => $this->user->id,
        'started_at' => now()->subMinutes(2),
    ]);

    $service = app(TaskPickupService::class);
    $service->markFailed($task, 'Something went wrong');

    $task->refresh();
    expect($task->status)->toBe('failed');
    expect($task->output_data)->toBe(['error' => 'Something went wrong']);
    expect($task->completed_at)->not->toBeNull();
});

// ─── Task Status Transitions ────────────────────────────────────

it('transitions task status correctly via run endpoint', function () {
    $task = AgentTask::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'title' => 'Execute Me',
        'status' => 'assigned',
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/tasks/{$task->id}/run");

    $response->assertOk();
    $task->refresh();
    expect($task->status)->toBe('running');
    expect($task->started_at)->not->toBeNull();
});

it('validates required title on create', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/tasks", [
            'description' => 'No title provided',
        ]);

    $response->assertUnprocessable();
});
