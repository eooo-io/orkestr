<?php

use App\Jobs\RunEvalSuiteJob;
use App\Models\Project;
use App\Models\Skill;
use App\Models\SkillEvalGate;
use App\Models\SkillEvalPrompt;
use App\Models\SkillEvalRun;
use App\Models\SkillEvalSuite;
use App\Models\User;
use App\Services\EvalGateBlockedException;
use App\Services\ProviderSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::firstOrCreate(
        ['email' => 'gateint@test.com'],
        ['name' => 'Gate Int', 'password' => bcrypt('password')],
    );

    $this->project = Project::create([
        'name' => 'Gate Int Project',
        'path' => '/tmp/gate-int',
        'providers' => ['claude'],
        'owner_id' => $this->user->id,
    ]);

    $this->skill = Skill::create([
        'project_id' => $this->project->id,
        'name' => 'Critical Skill',
        'slug' => 'critical-skill',
        'body' => 'Be careful.',
        'model' => 'claude-sonnet-4-6',
        'tuned_for_model' => 'claude-sonnet-4-6',
    ]);

    $this->suite = SkillEvalSuite::create([
        'skill_id' => $this->skill->id,
        'name' => 'Main',
    ]);

    SkillEvalPrompt::create([
        'eval_suite_id' => $this->suite->id,
        'prompt' => 'Say hi.',
        'expected_behavior' => 'Must greet.',
        'sort_order' => 1,
    ]);
});

it('save triggers gate decision when auto_run_on_save is enabled', function () {
    Queue::fake();

    SkillEvalGate::create([
        'skill_id' => $this->skill->id,
        'enabled' => true,
        'required_suite_ids' => [$this->suite->id],
        'auto_run_on_save' => true,
        'block_sync' => false,
    ]);

    $response = $this->actingAs($this->user)
        ->putJson("/api/skills/{$this->skill->id}", ['body' => 'Updated body.']);

    $response->assertOk()
        ->assertJsonPath('gate_decision.reason', 'dispatched');

    Queue::assertPushed(RunEvalSuiteJob::class);
});

it('save returns no_gate when no gate configured', function () {
    $response = $this->actingAs($this->user)
        ->putJson("/api/skills/{$this->skill->id}", ['body' => 'Updated body.']);

    $response->assertOk()
        ->assertJsonPath('gate_decision.reason', 'no_gate');
});

it('canSync blocks project sync when a skill has a failing delta', function () {
    SkillEvalGate::create([
        'skill_id' => $this->skill->id,
        'enabled' => true,
        'required_suite_ids' => [$this->suite->id],
        'fail_threshold_delta' => -5.00,
        'block_sync' => true,
    ]);

    SkillEvalRun::create([
        'eval_suite_id' => $this->suite->id,
        'model' => 'claude-sonnet-4-6',
        'mode' => 'with_skill',
        'status' => 'completed',
        'overall_score' => 60,
        'delta_score' => -10.00,
        'completed_at' => now(),
    ]);

    $service = app(ProviderSyncService::class);

    expect(fn () => $service->assertCanSync($this->project->fresh()))
        ->toThrow(EvalGateBlockedException::class);
});

it('EvalGateBlockedException renders a 409 with blocked skill details', function () {
    $exception = new EvalGateBlockedException([
        [
            'skill_id' => 7,
            'skill_slug' => 'foo',
            'skill_name' => 'Foo',
            'last_delta' => -8.50,
            'last_run_id' => 42,
        ],
    ]);

    $response = $exception->render();

    expect($response->getStatusCode())->toBe(409);
    expect(json_decode($response->getContent(), true))->toMatchArray([
        'error' => 'Sync blocked by one or more skill eval gates.',
        'blocked_skills' => [[
            'skill_id' => 7,
            'skill_slug' => 'foo',
            'skill_name' => 'Foo',
            'last_delta' => -8.50,
            'last_run_id' => 42,
        ]],
    ]);
});
