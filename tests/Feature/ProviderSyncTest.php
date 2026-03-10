<?php

use App\Models\Project;
use App\Models\Skill;
use App\Models\Tag;
use App\Services\Providers\ClineDriver;
use App\Services\Providers\ClaudeDriver;
use App\Services\Providers\CopilotDriver;
use App\Services\Providers\CursorDriver;
use App\Services\Providers\OpenAIDriver;
use App\Services\Providers\WindsurfDriver;
use App\Services\ProviderSyncService;
use Illuminate\Support\Facades\File;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/agentis-sync-' . uniqid();
    mkdir($this->tempDir, 0755, true);

    $this->project = Project::create([
        'name' => 'Sync Test Project',
        'path' => $this->tempDir,
    ]);

    $tag = Tag::create(['name' => 'testing']);

    $skill1 = $this->project->skills()->create([
        'name' => 'Summarize',
        'slug' => 'summarize',
        'description' => 'Summarizes documents',
        'body' => 'You are a document summarizer.',
    ]);
    $skill1->tags()->attach($tag);

    $skill2 = $this->project->skills()->create([
        'name' => 'Code Review',
        'slug' => 'code-review',
        'description' => 'Reviews code quality',
        'body' => 'You are a code reviewer.',
    ]);

    $this->skills = $this->project->skills()->with('tags')->get();
});

afterEach(function () {
    File::deleteDirectory($this->tempDir);
});

it('ClaudeDriver writes a single CLAUDE.md with H2 headings', function () {
    $driver = new ClaudeDriver;
    $driver->sync($this->project, $this->skills);

    $path = $this->tempDir . '/.claude/CLAUDE.md';
    expect(File::exists($path))->toBeTrue();

    $content = File::get($path);
    expect($content)->toContain('# CLAUDE.md');
    expect($content)->toContain('## Summarize');
    expect($content)->toContain('You are a document summarizer.');
    expect($content)->toContain('## Code Review');
    expect($content)->toContain('You are a code reviewer.');
});

it('CursorDriver writes one .mdc file per skill', function () {
    $driver = new CursorDriver;
    $driver->sync($this->project, $this->skills);

    $dir = $this->tempDir . '/.cursor/rules';
    expect(File::exists($dir . '/summarize.mdc'))->toBeTrue();
    expect(File::exists($dir . '/code-review.mdc'))->toBeTrue();

    $summarize = File::get($dir . '/summarize.mdc');
    expect($summarize)->toContain('Summarizes documents');
    expect($summarize)->toContain('alwaysApply: false');
    expect($summarize)->toContain('- testing');
    expect($summarize)->toContain('You are a document summarizer.');

    $review = File::get($dir . '/code-review.mdc');
    expect($review)->toContain('Reviews code quality');
    expect($review)->not->toContain('tags:'); // No tags on this skill
});

it('CopilotDriver writes copilot-instructions.md', function () {
    $driver = new CopilotDriver;
    $driver->sync($this->project, $this->skills);

    $path = $this->tempDir . '/.github/copilot-instructions.md';
    expect(File::exists($path))->toBeTrue();

    $content = File::get($path);
    expect($content)->toContain('# GitHub Copilot Instructions');
    expect($content)->toContain('## Summarize');
    expect($content)->toContain('## Code Review');
});

it('WindsurfDriver writes one .md file per skill', function () {
    $driver = new WindsurfDriver;
    $driver->sync($this->project, $this->skills);

    $dir = $this->tempDir . '/.windsurf/rules';
    expect(File::exists($dir . '/summarize.md'))->toBeTrue();
    expect(File::exists($dir . '/code-review.md'))->toBeTrue();

    $content = File::get($dir . '/summarize.md');
    expect($content)->toContain('# Summarize');
    expect($content)->toContain('You are a document summarizer.');
});

it('ClineDriver writes a single .clinerules file', function () {
    $driver = new ClineDriver;
    $driver->sync($this->project, $this->skills);

    $path = $this->tempDir . '/.clinerules';
    expect(File::exists($path))->toBeTrue();

    $content = File::get($path);
    expect($content)->toContain('# Summarize');
    expect($content)->toContain('# Code Review');
});

it('OpenAIDriver writes instructions.md', function () {
    $driver = new OpenAIDriver;
    $driver->sync($this->project, $this->skills);

    $path = $this->tempDir . '/.openai/instructions.md';
    expect(File::exists($path))->toBeTrue();

    $content = File::get($path);
    expect($content)->toContain('# Instructions');
    expect($content)->toContain('## Summarize');
    expect($content)->toContain('## Code Review');
});

it('drivers can clean their output files', function () {
    $drivers = [
        new ClaudeDriver,
        new CopilotDriver,
        new ClineDriver,
        new OpenAIDriver,
    ];

    foreach ($drivers as $driver) {
        $driver->sync($this->project, $this->skills);
    }

    // Verify files exist
    expect(File::exists($this->tempDir . '/.claude/CLAUDE.md'))->toBeTrue();
    expect(File::exists($this->tempDir . '/.clinerules'))->toBeTrue();

    // Clean
    foreach ($drivers as $driver) {
        $driver->clean($this->project);
    }

    expect(File::exists($this->tempDir . '/.claude/CLAUDE.md'))->toBeFalse();
    expect(File::exists($this->tempDir . '/.clinerules'))->toBeFalse();
});

it('ProviderSyncService syncs all active providers', function () {
    $this->project->providers()->create(['provider_slug' => 'claude']);
    $this->project->providers()->create(['provider_slug' => 'cursor']);
    $this->project->providers()->create(['provider_slug' => 'cline']);

    $service = app(ProviderSyncService::class);
    $service->syncProject($this->project);

    expect(File::exists($this->tempDir . '/.claude/CLAUDE.md'))->toBeTrue();
    expect(File::exists($this->tempDir . '/.cursor/rules/summarize.mdc'))->toBeTrue();
    expect(File::exists($this->tempDir . '/.clinerules'))->toBeTrue();

    // Not synced — not in active providers
    expect(File::exists($this->tempDir . '/.github/copilot-instructions.md'))->toBeFalse();
    expect(File::exists($this->tempDir . '/.openai/instructions.md'))->toBeFalse();

    $this->project->refresh();
    expect($this->project->synced_at)->not->toBeNull();
});

it('ProviderSyncService throws for unknown provider', function () {
    $service = app(ProviderSyncService::class);
    $service->getDriver('unknown');
})->throws(InvalidArgumentException::class, 'Unknown provider: unknown');
