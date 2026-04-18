<?php

use App\Jobs\RunEvalSuiteJob;
use App\Models\Project;
use App\Models\Skill;
use App\Models\SkillEvalPrompt;
use App\Models\SkillEvalRun;
use App\Models\SkillEvalSuite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::firstOrCreate(
        ['email' => 'eval@test.com'],
        ['name' => 'Eval Tester', 'password' => bcrypt('password')],
    );

    $this->project = Project::create([
        'name' => 'Eval Project',
        'path' => '/tmp/eval-project',
        'providers' => ['claude'],
        'owner_id' => $this->user->id,
    ]);

    $this->skill = Skill::create([
        'project_id' => $this->project->id,
        'name' => 'Eval Skill',
        'slug' => 'eval-skill',
        'body' => 'You are a helper.',
        'max_tokens' => 512,
    ]);

    $this->suite = SkillEvalSuite::create([
        'skill_id' => $this->skill->id,
        'name' => 'Suite A',
        'scorer' => 'keyword',
    ]);

    SkillEvalPrompt::create([
        'eval_suite_id' => $this->suite->id,
        'prompt' => 'Summarize the doc.',
        'expected_behavior' => 'Mention summary and bullets.',
        'sort_order' => 1,
    ]);
});

it('creates a pending run and dispatches RunEvalSuiteJob when triggered', function () {
    Queue::fake();

    $response = $this->actingAs($this->user)->postJson(
        "/api/eval-suites/{$this->suite->id}/run",
        ['model' => 'claude-sonnet-4-6', 'mode' => 'with_skill'],
    );

    $response->assertCreated();

    $run = SkillEvalRun::where('eval_suite_id', $this->suite->id)->first();
    expect($run)->not->toBeNull()
        ->and($run->status)->toBe('pending');

    Queue::assertPushed(RunEvalSuiteJob::class, fn ($job) => $job->runId === $run->id);
});

it('captures skill_version_id at dispatch time when a version exists', function () {
    Queue::fake();

    $version = $this->skill->versions()->create([
        'version_number' => 1,
        'frontmatter' => [],
        'body' => $this->skill->body,
        'saved_at' => now(),
    ]);

    $this->actingAs($this->user)->postJson(
        "/api/eval-suites/{$this->suite->id}/run",
        ['model' => 'claude-sonnet-4-6', 'mode' => 'with_skill'],
    )->assertCreated();

    $run = SkillEvalRun::where('eval_suite_id', $this->suite->id)->first();
    expect($run->skill_version_id)->toBe($version->id);
});

it('completes the run and captures per-prompt errors when the provider fails', function () {
    $run = SkillEvalRun::create([
        'eval_suite_id' => $this->suite->id,
        'model' => 'nonexistent-model-xxx',
        'mode' => 'with_skill',
        'status' => 'pending',
    ]);

    (new RunEvalSuiteJob($run->id))->handle(
        app(\App\Services\LLM\LLMProviderFactory::class),
        app(\App\Services\EvalScoring\ScorerFactory::class),
    );

    $fresh = $run->fresh();
    expect($fresh->status)->toBe('completed')
        ->and($fresh->results)->not->toBeNull()
        ->and($fresh->results[0])->toHaveKey('error');
});

it('short-circuits on an already-finished run', function () {
    $run = SkillEvalRun::create([
        'eval_suite_id' => $this->suite->id,
        'model' => 'claude-sonnet-4-6',
        'mode' => 'with_skill',
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    (new RunEvalSuiteJob($run->id))->handle(
        app(\App\Services\LLM\LLMProviderFactory::class),
        app(\App\Services\EvalScoring\ScorerFactory::class),
    );

    expect($run->fresh()->status)->toBe('completed');
});
