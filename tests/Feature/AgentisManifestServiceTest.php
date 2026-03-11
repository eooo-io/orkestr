<?php

use App\Models\Project;
use App\Services\AgentisManifestService;
use App\Services\SkillFileParser;
use Illuminate\Support\Facades\File;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/agentis-test-' . uniqid();
    mkdir($this->tempDir, 0755, true);
    $this->service = new AgentisManifestService(new SkillFileParser);
});

afterEach(function () {
    File::deleteDirectory($this->tempDir);
});

it('scaffolds a new .agentis directory', function () {
    $this->service->scaffoldProject($this->tempDir, 'Test Project');

    expect(is_dir($this->tempDir . '/.agentis'))->toBeTrue();
    expect(is_dir($this->tempDir . '/.agentis/skills'))->toBeTrue();
    expect(file_exists($this->tempDir . '/.agentis/manifest.json'))->toBeTrue();

    $manifest = json_decode(file_get_contents($this->tempDir . '/.agentis/manifest.json'), true);
    expect($manifest['name'])->toBe('Test Project');
    expect($manifest['providers'])->toBe([]);
    expect($manifest['skills'])->toBe([]);
});

it('writes and reads skill files', function () {
    $this->service->scaffoldProject($this->tempDir, 'Test Project');

    $frontmatter = ['id' => 'test-skill', 'name' => 'Test Skill', 'tags' => ['test']];
    $body = 'You are a test skill.';

    $this->service->writeSkillFile($this->tempDir, $frontmatter, $body);

    expect($this->service->skillExists($this->tempDir, 'test-skill'))->toBeTrue();
    expect($this->service->skillExists($this->tempDir, 'nonexistent'))->toBeFalse();
});

it('deletes a skill file', function () {
    $this->service->scaffoldProject($this->tempDir, 'Test Project');
    $this->service->writeSkillFile($this->tempDir, ['id' => 'to-delete', 'name' => 'Delete Me'], 'Body.');

    expect($this->service->skillExists($this->tempDir, 'to-delete'))->toBeTrue();

    $this->service->deleteSkillFile($this->tempDir, 'to-delete');

    expect($this->service->skillExists($this->tempDir, 'to-delete'))->toBeFalse();
});

it('scans a project directory with multiple skills', function () {
    // Create .agentis directory with 3 skill files
    $skillsDir = $this->tempDir . '/.agentis/skills';
    mkdir($skillsDir, 0755, true);

    $parser = new SkillFileParser;

    file_put_contents($skillsDir . '/summarize.md', $parser->renderFile(
        ['id' => 'summarize', 'name' => 'Summarize', 'tags' => ['docs']],
        'You summarize documents.',
    ));

    file_put_contents($skillsDir . '/review.md', $parser->renderFile(
        ['id' => 'review', 'name' => 'Code Review', 'model' => 'claude-sonnet-4-6'],
        'You review code carefully.',
    ));

    file_put_contents($skillsDir . '/translate.md', $parser->renderFile(
        ['id' => 'translate', 'name' => 'Translate', 'max_tokens' => 2000],
        'You translate text.',
    ));

    // Write a manifest too
    file_put_contents($this->tempDir . '/.agentis/manifest.json', json_encode([
        'id' => 'test-uuid',
        'name' => 'Test Project',
    ]));

    $result = $this->service->scanProject($this->tempDir);

    expect($result['manifest'])->not->toBeNull();
    expect($result['manifest']['name'])->toBe('Test Project');
    expect($result['skills'])->toHaveCount(3);

    $skillNames = collect($result['skills'])->pluck('frontmatter.name')->sort()->values()->all();
    expect($skillNames)->toBe(['Code Review', 'Summarize', 'Translate']);
});

it('writes a manifest from project DB state', function () {
    $project = Project::create([
        'name' => 'Manifest Test',
        'path' => $this->tempDir,
        'description' => 'Testing manifest write',
    ]);

    $project->providers()->create(['provider_slug' => 'claude']);
    $project->providers()->create(['provider_slug' => 'cursor']);

    $project->skills()->create([
        'name' => 'Skill One',
        'slug' => 'skill-one',
        'body' => 'Body one.',
    ]);

    mkdir($this->tempDir . '/.agentis', 0755, true);
    $this->service->writeManifest($project);

    $manifest = json_decode(file_get_contents($this->tempDir . '/.agentis/manifest.json'), true);

    expect($manifest['name'])->toBe('Manifest Test');
    expect($manifest['providers'])->toContain('claude', 'cursor');
    expect($manifest['skills'])->toContain('skill-one');
});
