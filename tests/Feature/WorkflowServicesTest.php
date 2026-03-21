<?php

use App\Models\Agent;
use App\Models\Project;
use App\Models\Workflow;
use App\Models\WorkflowEdge;
use App\Models\WorkflowStep;
use App\Services\DelegationChainResolver;
use App\Services\WorkflowConditionEvaluator;
use App\Services\WorkflowContextService;
use App\Services\WorkflowExportService;
use App\Services\WorkflowValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->project = Project::create([
        'name' => 'Service Test',
        'path' => '/tmp/svc-test',
    ]);
});

// --- WorkflowValidationService ---

it('validates a valid workflow', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Valid',
    ]);

    $s1 = WorkflowStep::create(['workflow_id' => $workflow->id, 'type' => 'start', 'name' => 'Start']);
    $s2 = WorkflowStep::create(['workflow_id' => $workflow->id, 'type' => 'end', 'name' => 'End']);
    WorkflowEdge::create(['workflow_id' => $workflow->id, 'source_step_id' => $s1->id, 'target_step_id' => $s2->id]);

    $validator = app(WorkflowValidationService::class);
    $result = $validator->validate($workflow);

    expect($result['valid'])->toBeTrue();
    expect($result['errors'])->toBeEmpty();
});

it('detects missing start node', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'No Start',
    ]);

    WorkflowStep::create(['workflow_id' => $workflow->id, 'type' => 'end', 'name' => 'End']);

    $result = app(WorkflowValidationService::class)->validate($workflow);

    expect($result['valid'])->toBeFalse();
    expect($result['errors'])->toContain('Workflow must have a start node.');
});

it('detects cycles', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Cycle',
    ]);

    $s1 = WorkflowStep::create(['workflow_id' => $workflow->id, 'type' => 'start', 'name' => 'A']);
    $s2 = WorkflowStep::create(['workflow_id' => $workflow->id, 'type' => 'agent', 'name' => 'B']);
    $s3 = WorkflowStep::create(['workflow_id' => $workflow->id, 'type' => 'end', 'name' => 'C']);

    WorkflowEdge::create(['workflow_id' => $workflow->id, 'source_step_id' => $s1->id, 'target_step_id' => $s2->id]);
    WorkflowEdge::create(['workflow_id' => $workflow->id, 'source_step_id' => $s2->id, 'target_step_id' => $s1->id]); // cycle

    $result = app(WorkflowValidationService::class)->validate($workflow);

    expect($result['valid'])->toBeFalse();
    expect(collect($result['errors'])->first(fn ($e) => str_contains($e, 'cycle')))->not->toBeNull();
});

it('warns about unassigned agent steps', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'No Agent',
    ]);

    $s1 = WorkflowStep::create(['workflow_id' => $workflow->id, 'type' => 'start', 'name' => 'Start']);
    $s2 = WorkflowStep::create(['workflow_id' => $workflow->id, 'type' => 'agent', 'name' => 'Worker']);
    $s3 = WorkflowStep::create(['workflow_id' => $workflow->id, 'type' => 'end', 'name' => 'End']);
    WorkflowEdge::create(['workflow_id' => $workflow->id, 'source_step_id' => $s1->id, 'target_step_id' => $s2->id]);
    WorkflowEdge::create(['workflow_id' => $workflow->id, 'source_step_id' => $s2->id, 'target_step_id' => $s3->id]);

    $result = app(WorkflowValidationService::class)->validate($workflow);

    expect($result['valid'])->toBeFalse();
    expect(collect($result['errors'])->first(fn ($e) => str_contains($e, 'agent assigned')))->not->toBeNull();
});

// --- WorkflowContextService ---

it('manages context bus state', function () {
    $ctx = new WorkflowContextService();
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Context Test',
    ]);

    $ctx->initialize($workflow, ['input' => 'hello']);

    expect($ctx->get('input'))->toBe('hello');
    expect($ctx->get('_workflow_name'))->toBe('Context Test');

    $ctx->set('result', 42);
    expect($ctx->get('result'))->toBe(42);

    $ctx->merge(['extra' => true]);
    expect($ctx->get('extra'))->toBeTrue();
});

it('resolves dot-notation expressions', function () {
    $ctx = new WorkflowContextService();
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Dot Test',
    ]);
    $ctx->initialize($workflow);

    $ctx->set('user', ['name' => 'Alice', 'role' => 'admin']);

    expect($ctx->resolveExpression('user.name'))->toBe('Alice');
    expect($ctx->resolveExpression('user.role'))->toBe('admin');
    expect($ctx->resolveExpression('missing'))->toBeNull();
});

it('validates context against schema', function () {
    $ctx = new WorkflowContextService();
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Schema Test',
    ]);
    $ctx->initialize($workflow);
    $ctx->set('name', 'Alice');

    $errors = $ctx->validateAgainstSchema([
        'name' => ['required' => true, 'type' => 'string'],
        'age' => ['required' => true, 'type' => 'integer'],
    ]);

    expect($errors)->toHaveCount(1);
    expect($errors[0])->toContain('age');
});

// --- WorkflowConditionEvaluator ---

it('evaluates comparison expressions', function () {
    $ctx = new WorkflowContextService();
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Cond Test',
    ]);
    $ctx->initialize($workflow);
    $ctx->set('score', 85);
    $ctx->set('status', 'approved');

    $evaluator = new WorkflowConditionEvaluator($ctx);

    expect($evaluator->evaluateExpression('score > 70'))->toBeTrue();
    expect($evaluator->evaluateExpression('score < 50'))->toBeFalse();
    expect($evaluator->evaluateExpression('status == "approved"'))->toBeTrue();
    expect($evaluator->evaluateExpression('status != "rejected"'))->toBeTrue();
    expect($evaluator->evaluateExpression('true'))->toBeTrue();
    expect($evaluator->evaluateExpression('false'))->toBeFalse();
    expect($evaluator->evaluateExpression('score exists'))->toBeTrue();
    expect($evaluator->evaluateExpression('missing exists'))->toBeFalse();
});

// --- WorkflowExportService ---

it('exports workflow as generic JSON', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'Export Test',
    ]);

    $s1 = WorkflowStep::create(['workflow_id' => $workflow->id, 'type' => 'start', 'name' => 'Start']);
    $s2 = WorkflowStep::create(['workflow_id' => $workflow->id, 'type' => 'end', 'name' => 'End']);
    WorkflowEdge::create(['workflow_id' => $workflow->id, 'source_step_id' => $s1->id, 'target_step_id' => $s2->id]);

    $exporter = app(WorkflowExportService::class);
    $json = $exporter->exportJson($workflow);

    expect($json['format'])->toBe('orkestr-workflow');
    expect($json['workflow']['name'])->toBe('Export Test');
    expect($json['steps'])->toHaveCount(2);
    expect($json['edges'])->toHaveCount(1);
});

it('exports workflow as CrewAI config', function () {
    $workflow = Workflow::create([
        'project_id' => $this->project->id,
        'name' => 'CrewAI Test',
    ]);

    $agent = Agent::create([
        'name' => 'Researcher',
        'role' => 'researcher',
        'base_instructions' => 'Research topics.',
    ]);

    WorkflowStep::create(['workflow_id' => $workflow->id, 'type' => 'start', 'name' => 'Start']);
    WorkflowStep::create([
        'workflow_id' => $workflow->id,
        'type' => 'agent',
        'name' => 'Research',
        'agent_id' => $agent->id,
    ]);
    WorkflowStep::create(['workflow_id' => $workflow->id, 'type' => 'end', 'name' => 'End']);

    $exporter = app(WorkflowExportService::class);
    $config = $exporter->exportCrewAI($workflow);

    expect($config['crew']['name'])->toBe('CrewAI Test');
    expect($config['agents'])->toHaveCount(1);
    expect($config['agents'][0]['role'])->toBe('researcher');
    expect($config['tasks'])->toHaveCount(1);
});
