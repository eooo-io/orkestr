<?php

use App\Models\Project;
use App\Models\Skill;
use App\Services\SkillStalenessService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(SkillStalenessService::class);
    $this->project = Project::create([
        'name' => 'Test Project',
        'path' => '/tmp/test-project',
        'providers' => ['claude'],
    ]);
});

function makeSkill(array $attrs = [], ?int $projectId = null): Skill
{
    return Skill::create(array_merge([
        'project_id' => $projectId ?? test()->project->id,
        'name' => 'Test Skill',
        'slug' => 'test-skill-' . uniqid(),
        'body' => 'Test body.',
    ], $attrs));
}

it('returns needs_tuning when tuned_for_model is null', function () {
    $skill = makeSkill(['model' => 'claude-sonnet-4-6']);

    $status = $this->service->statusFor($skill);

    expect($status['reason'])->toBe('needs_tuning')
        ->and($status['is_stale'])->toBeTrue()
        ->and($status['tuned_for_model'])->toBeNull();
});

it('returns model_deprecated when tuned_for_model is a retired model', function () {
    $skill = makeSkill([
        'model' => 'gpt-4',
        'tuned_for_model' => 'gpt-4',
    ]);

    $status = $this->service->statusFor($skill);

    expect($status['reason'])->toBe('model_deprecated')
        ->and($status['is_stale'])->toBeTrue()
        ->and($status['suggested_action'])->toContain('gpt-4');
});

it('returns needs_revalidation when never validated', function () {
    $skill = makeSkill([
        'model' => 'claude-sonnet-4-6',
        'tuned_for_model' => 'claude-sonnet-4-6',
    ]);

    $status = $this->service->statusFor($skill);

    expect($status['reason'])->toBe('needs_revalidation')
        ->and($status['is_stale'])->toBeTrue();
});

it('returns needs_revalidation when last_validated_model differs from tuned_for_model', function () {
    $skill = makeSkill([
        'model' => 'claude-sonnet-4-6',
        'tuned_for_model' => 'claude-sonnet-4-6',
        'last_validated_model' => 'claude-opus-4-6',
        'last_validated_at' => now(),
    ]);

    $status = $this->service->statusFor($skill);

    expect($status['reason'])->toBe('needs_revalidation')
        ->and($status['is_stale'])->toBeTrue();
});

it('returns needs_revalidation when currentModel differs from tuned_for_model', function () {
    $skill = makeSkill([
        'model' => 'claude-sonnet-4-6',
        'tuned_for_model' => 'claude-sonnet-4-6',
        'last_validated_model' => 'claude-sonnet-4-6',
        'last_validated_at' => now(),
    ]);

    $status = $this->service->statusFor($skill, currentModel: 'claude-opus-4-6');

    expect($status['reason'])->toBe('needs_revalidation')
        ->and($status['suggested_action'])->toContain('claude-opus-4-6');
});

it('returns ok when tuned, validated on same model, and current matches', function () {
    $skill = makeSkill([
        'model' => 'claude-sonnet-4-6',
        'tuned_for_model' => 'claude-sonnet-4-6',
        'last_validated_model' => 'claude-sonnet-4-6',
        'last_validated_at' => now(),
    ]);

    $status = $this->service->statusFor($skill, currentModel: 'claude-sonnet-4-6');

    expect($status['reason'])->toBe('ok')
        ->and($status['is_stale'])->toBeFalse();
});

it('returns ok when tuned and validated with no currentModel supplied', function () {
    $skill = makeSkill([
        'model' => 'claude-sonnet-4-6',
        'tuned_for_model' => 'claude-sonnet-4-6',
        'last_validated_model' => 'claude-sonnet-4-6',
        'last_validated_at' => now(),
    ]);

    $status = $this->service->statusFor($skill);

    expect($status['reason'])->toBe('ok')
        ->and($status['is_stale'])->toBeFalse();
});

it('prioritizes model_deprecated over needs_revalidation', function () {
    $skill = makeSkill([
        'model' => 'gpt-4',
        'tuned_for_model' => 'gpt-4',
        'last_validated_model' => 'gpt-4',
        'last_validated_at' => now(),
    ]);

    $status = $this->service->statusFor($skill);

    expect($status['reason'])->toBe('model_deprecated');
});
