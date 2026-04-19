<?php

use App\Jobs\RunEvalSuiteJob;
use App\Models\Project;
use App\Models\Skill;
use App\Models\SkillEvalGate;
use App\Models\SkillEvalPrompt;
use App\Models\SkillEvalRun;
use App\Models\SkillEvalSuite;
use App\Models\SkillVersion;
use App\Services\SkillEvalGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(SkillEvalGateService::class);
    $this->project = Project::create([
        'name' => 'Gate Project',
        'path' => '/tmp/gate-project',
        'providers' => ['claude'],
    ]);
    $this->skill = Skill::create([
        'project_id' => $this->project->id,
        'name' => 'Gated Skill',
        'slug' => 'gated-skill',
        'body' => 'Skill body.',
        'model' => 'claude-sonnet-4-6',
        'tuned_for_model' => 'claude-sonnet-4-6',
    ]);
    $this->suite = SkillEvalSuite::create([
        'skill_id' => $this->skill->id,
        'name' => 'Main Suite',
    ]);
    SkillEvalPrompt::create([
        'eval_suite_id' => $this->suite->id,
        'prompt' => 'Summarize',
        'expected_behavior' => 'Must mention bullets',
        'sort_order' => 1,
    ]);
});

it('findBaselineFor returns the most recent completed run for (suite, model)', function () {
    $version1 = SkillVersion::create([
        'skill_id' => $this->skill->id,
        'version_number' => 1,
        'frontmatter' => [],
        'body' => 'v1',
        'saved_at' => now()->subDays(2),
    ]);
    $version2 = SkillVersion::create([
        'skill_id' => $this->skill->id,
        'version_number' => 2,
        'frontmatter' => [],
        'body' => 'v2',
        'saved_at' => now()->subDay(),
    ]);

    // Older run on v1
    SkillEvalRun::create([
        'eval_suite_id' => $this->suite->id,
        'skill_version_id' => $version1->id,
        'model' => 'claude-sonnet-4-6',
        'mode' => 'with_skill',
        'status' => 'completed',
        'overall_score' => 80,
        'completed_at' => now()->subDays(2),
    ]);

    // Newer run on v2 — should be baseline regardless of version
    $newer = SkillEvalRun::create([
        'eval_suite_id' => $this->suite->id,
        'skill_version_id' => $version2->id,
        'model' => 'claude-sonnet-4-6',
        'mode' => 'with_skill',
        'status' => 'completed',
        'overall_score' => 90,
        'completed_at' => now()->subDay(),
    ]);

    // Different model — should not be baseline
    SkillEvalRun::create([
        'eval_suite_id' => $this->suite->id,
        'skill_version_id' => $version2->id,
        'model' => 'claude-opus-4-6',
        'mode' => 'with_skill',
        'status' => 'completed',
        'overall_score' => 95,
        'completed_at' => now(),
    ]);

    $baseline = $this->service->findBaselineFor($this->skill, $this->suite, 'claude-sonnet-4-6');

    expect($baseline)->not->toBeNull()
        ->and($baseline->id)->toBe($newer->id);
});

it('computeDelta returns overall + per-prompt deltas', function () {
    $current = new SkillEvalRun([
        'overall_score' => 70,
        'results' => [
            ['prompt_id' => 1, 'score' => 60],
            ['prompt_id' => 2, 'score' => 80],
        ],
    ]);
    $baseline = new SkillEvalRun([
        'overall_score' => 85,
        'results' => [
            ['prompt_id' => 1, 'score' => 90],
            ['prompt_id' => 2, 'score' => 80],
        ],
    ]);

    $delta = $this->service->computeDelta($current, $baseline);

    expect($delta['overall_delta'])->toBe(-15.0)
        ->and($delta['per_prompt'][0]['delta'])->toBe(-30)
        ->and($delta['per_prompt'][1]['delta'])->toBe(0);
});

it('evaluateSkillSave enqueues runs for required suites when gate is on', function () {
    Queue::fake();

    SkillEvalGate::create([
        'skill_id' => $this->skill->id,
        'enabled' => true,
        'required_suite_ids' => [$this->suite->id],
        'fail_threshold_delta' => -5.00,
        'auto_run_on_save' => true,
        'block_sync' => false,
    ]);
    $this->skill->refresh();

    $version = SkillVersion::create([
        'skill_id' => $this->skill->id,
        'version_number' => 1,
        'frontmatter' => [],
        'body' => 'v1',
        'saved_at' => now(),
    ]);

    $decision = $this->service->evaluateSkillSave($this->skill, $version);

    expect($decision['reason'])->toBe('dispatched')
        ->and($decision['enqueued_run_ids'])->toHaveCount(1);

    Queue::assertPushed(RunEvalSuiteJob::class);
});

it('evaluateSkillSave skips when gate disabled', function () {
    Queue::fake();

    $version = SkillVersion::create([
        'skill_id' => $this->skill->id,
        'version_number' => 1,
        'frontmatter' => [],
        'body' => 'v1',
        'saved_at' => now(),
    ]);

    $decision = $this->service->evaluateSkillSave($this->skill, $version);

    expect($decision['reason'])->toBe('no_gate')
        ->and($decision['enqueued_run_ids'])->toBeEmpty();

    Queue::assertNothingPushed();
});

it('canSync returns true when gate not enabled', function () {
    expect($this->service->canSync($this->skill))->toBeTrue();
});

it('canSync returns false when latest run delta is below threshold', function () {
    SkillEvalGate::create([
        'skill_id' => $this->skill->id,
        'enabled' => true,
        'required_suite_ids' => [$this->suite->id],
        'fail_threshold_delta' => -5.00,
        'block_sync' => true,
    ]);
    $this->skill->refresh();

    SkillEvalRun::create([
        'eval_suite_id' => $this->suite->id,
        'model' => 'claude-sonnet-4-6',
        'mode' => 'with_skill',
        'status' => 'completed',
        'overall_score' => 70,
        'delta_score' => -10.00,
        'completed_at' => now(),
    ]);

    expect($this->service->canSync($this->skill))->toBeFalse();
});

it('canSync returns true when latest run delta is above threshold', function () {
    SkillEvalGate::create([
        'skill_id' => $this->skill->id,
        'enabled' => true,
        'required_suite_ids' => [$this->suite->id],
        'fail_threshold_delta' => -5.00,
        'block_sync' => true,
    ]);
    $this->skill->refresh();

    SkillEvalRun::create([
        'eval_suite_id' => $this->suite->id,
        'model' => 'claude-sonnet-4-6',
        'mode' => 'with_skill',
        'status' => 'completed',
        'overall_score' => 90,
        'delta_score' => -2.00,
        'completed_at' => now(),
    ]);

    expect($this->service->canSync($this->skill))->toBeTrue();
});
