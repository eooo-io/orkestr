<?php

use App\Jobs\ProjectScanJob;
use App\Models\Project;
use App\Models\Skill;
use App\Models\Tag;
use App\Services\SkillFileParser;
use Illuminate\Support\Facades\File;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/agentis-scan-' . uniqid();
    $skillsDir = $this->tempDir . '/.agentis/skills';
    mkdir($skillsDir, 0755, true);

    $parser = new SkillFileParser;

    file_put_contents($skillsDir . '/summarize.md', $parser->renderFile(
        ['id' => 'summarize', 'name' => 'Summarize', 'description' => 'Summarize docs', 'tags' => ['docs', 'ai']],
        'You summarize documents.',
    ));

    file_put_contents($skillsDir . '/review.md', $parser->renderFile(
        ['id' => 'review', 'name' => 'Code Review', 'model' => 'claude-sonnet-4-20250514', 'tags' => ['code']],
        'You review code.',
    ));

    file_put_contents($skillsDir . '/translate.md', $parser->renderFile(
        ['id' => 'translate', 'name' => 'Translate', 'max_tokens' => 2000],
        'You translate text.',
    ));
});

afterEach(function () {
    File::deleteDirectory($this->tempDir);
});

it('scans a project directory and creates skills in DB', function () {
    $project = Project::create([
        'name' => 'Scan Test',
        'path' => $this->tempDir,
    ]);

    (new ProjectScanJob($project))->handle(app(App\Services\AgentisManifestService::class));

    expect(Skill::where('project_id', $project->id)->count())->toBe(3);

    $summarize = Skill::where('slug', 'summarize')->first();
    expect($summarize->name)->toBe('Summarize');
    expect($summarize->description)->toBe('Summarize docs');
    expect($summarize->body)->toBe('You summarize documents.');

    $review = Skill::where('slug', 'review')->first();
    expect($review->model)->toBe('claude-sonnet-4-20250514');

    $translate = Skill::where('slug', 'translate')->first();
    expect($translate->max_tokens)->toBe(2000);
});

it('creates v1 snapshots for new skills', function () {
    $project = Project::create([
        'name' => 'Version Test',
        'path' => $this->tempDir,
    ]);

    (new ProjectScanJob($project))->handle(app(App\Services\AgentisManifestService::class));

    $skills = Skill::where('project_id', $project->id)->get();

    foreach ($skills as $skill) {
        expect($skill->versions()->count())->toBe(1);

        $version = $skill->versions()->first();
        expect($version->version_number)->toBe(1);
        expect($version->note)->toBe('Initial scan');
        expect($version->body)->toBe($skill->body);
    }
});

it('syncs tags from frontmatter', function () {
    $project = Project::create([
        'name' => 'Tag Test',
        'path' => $this->tempDir,
    ]);

    (new ProjectScanJob($project))->handle(app(App\Services\AgentisManifestService::class));

    $summarize = Skill::where('slug', 'summarize')->first();
    expect($summarize->tags->pluck('name')->sort()->values()->all())->toBe(['ai', 'docs']);

    // Tags should be created in the tags table
    expect(Tag::where('name', 'docs')->exists())->toBeTrue();
    expect(Tag::where('name', 'ai')->exists())->toBeTrue();
    expect(Tag::where('name', 'code')->exists())->toBeTrue();
});

it('updates synced_at on the project', function () {
    $project = Project::create([
        'name' => 'Sync Time Test',
        'path' => $this->tempDir,
    ]);

    expect($project->synced_at)->toBeNull();

    (new ProjectScanJob($project))->handle(app(App\Services\AgentisManifestService::class));

    $project->refresh();
    expect($project->synced_at)->not->toBeNull();
});

it('upserts skills on re-scan without duplicating versions', function () {
    $project = Project::create([
        'name' => 'Upsert Test',
        'path' => $this->tempDir,
    ]);

    $service = app(App\Services\AgentisManifestService::class);

    // First scan
    (new ProjectScanJob($project))->handle($service);
    expect(Skill::where('project_id', $project->id)->count())->toBe(3);

    // Second scan — should not duplicate
    (new ProjectScanJob($project))->handle($service);
    expect(Skill::where('project_id', $project->id)->count())->toBe(3);

    // Versions should still be 1 per skill (no new versions on re-scan)
    $skills = Skill::where('project_id', $project->id)->get();
    foreach ($skills as $skill) {
        expect($skill->versions()->count())->toBe(1);
    }
});
