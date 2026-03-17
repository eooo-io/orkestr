<?php

use App\Models\Agent;
use App\Models\AgentMemory;
use App\Models\Project;
use App\Models\User;
use App\Services\Memory\DocumentIndexer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');

    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create(['name' => 'Doc Test', 'path' => '/tmp/doc-test']);
    $this->agent = Agent::create([
        'name' => 'Doc Agent',
        'slug' => 'doc-agent',
        'role' => 'assistant',
        'base_instructions' => 'Test agent.',
        'document_access' => true,
    ]);
});

// --- Document API tests ---

test('upload document via JSON body', function () {
    $response = $this->postJson("/api/projects/{$this->project->id}/documents", [
        'path' => 'notes/readme.txt',
        'content' => 'Hello, World!',
    ]);

    $response->assertStatus(201);
    expect($response->json('data.path'))->toBe('notes/readme.txt');

    Storage::disk('local')->assertExists("documents/projects/{$this->project->id}/notes/readme.txt");
});

test('upload document via multipart file', function () {
    $file = \Illuminate\Http\UploadedFile::fake()->create('test.txt', 100);

    $response = $this->post("/api/projects/{$this->project->id}/documents", [
        'file' => $file,
    ], ['Content-Type' => 'multipart/form-data']);

    $response->assertStatus(201);
    expect($response->json('data.path'))->toBe('test.txt');
});

test('list documents', function () {
    Storage::disk('local')->put("documents/projects/{$this->project->id}/file1.txt", 'content1');
    Storage::disk('local')->put("documents/projects/{$this->project->id}/file2.txt", 'content2');
    Storage::disk('local')->put("documents/projects/{$this->project->id}/sub/file3.txt", 'content3');

    $response = $this->getJson("/api/projects/{$this->project->id}/documents");

    $response->assertOk();
    // Only direct files, not files in subdirectories
    expect($response->json('data'))->toHaveCount(2);
});

test('list documents with prefix filter', function () {
    Storage::disk('local')->put("documents/projects/{$this->project->id}/notes/a.txt", 'a');
    Storage::disk('local')->put("documents/projects/{$this->project->id}/notes/b.txt", 'b');
    Storage::disk('local')->put("documents/projects/{$this->project->id}/other/c.txt", 'c');

    $response = $this->getJson("/api/projects/{$this->project->id}/documents?prefix=notes");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

test('download document', function () {
    Storage::disk('local')->put(
        "documents/projects/{$this->project->id}/download-me.txt",
        'File content here'
    );

    $response = $this->get("/api/projects/{$this->project->id}/documents/download?path=download-me.txt");

    $response->assertOk();
    expect($response->getContent())->toBe('File content here');
});

test('download returns 404 for missing document', function () {
    $response = $this->getJson("/api/projects/{$this->project->id}/documents/download?path=missing.txt");

    $response->assertStatus(404);
});

test('delete document', function () {
    Storage::disk('local')->put(
        "documents/projects/{$this->project->id}/delete-me.txt",
        'To be deleted'
    );

    $response = $this->deleteJson("/api/projects/{$this->project->id}/documents?path=delete-me.txt");

    $response->assertStatus(204);
    Storage::disk('local')->assertMissing("documents/projects/{$this->project->id}/delete-me.txt");
});

test('delete returns 404 for missing document', function () {
    $response = $this->deleteJson("/api/projects/{$this->project->id}/documents?path=nonexistent.txt");

    $response->assertStatus(404);
});

test('path scoping prevents directory traversal', function () {
    // Upload with traversal attempt
    $response = $this->postJson("/api/projects/{$this->project->id}/documents", [
        'path' => '../../etc/passwd',
        'content' => 'hacked',
    ]);

    $response->assertStatus(201);
    // The ".." should be stripped out, so the path is sanitized
    $path = $response->json('data.path');
    expect($path)->not->toContain('..');
});

test('documents from one project are not visible to another', function () {
    $otherProject = Project::create(['name' => 'Other', 'path' => '/tmp/other']);

    Storage::disk('local')->put(
        "documents/projects/{$this->project->id}/secret.txt",
        'secret data'
    );

    $response = $this->getJson("/api/projects/{$otherProject->id}/documents");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(0);
});

// --- DocumentIndexer tests ---

test('document indexer creates chunks', function () {
    $indexer = new DocumentIndexer;

    $content = str_repeat('A', 1200); // Will need multiple chunks at 512 chars
    $count = $indexer->index($this->agent->id, $this->project->id, 'test.txt', $content);

    expect($count)->toBeGreaterThan(1);

    $memories = AgentMemory::where('agent_id', $this->agent->id)
        ->where('project_id', $this->project->id)
        ->where('key', 'like', 'doc:test.txt:chunk:%')
        ->get();

    expect($memories)->toHaveCount($count);
    expect($memories->first()->metadata['type'])->toBe('document_chunk');
    expect($memories->first()->metadata['path'])->toBe('test.txt');
});

test('document indexer chunk overlap works correctly', function () {
    $indexer = new DocumentIndexer;

    // 100 chars, chunk size 60, overlap 20 => overlapping segments
    $content = str_repeat('X', 100);
    $chunks = $indexer->chunk($content, 60, 20);

    expect(count($chunks))->toBeGreaterThanOrEqual(2);

    // First chunk should be 60 chars
    expect(strlen($chunks[0]))->toBe(60);

    // The second chunk should start 40 chars in (60 - 20 overlap)
    // and overlap with the first chunk's last 20 chars
    expect(strlen($chunks[1]))->toBe(60);
});

test('document indexer handles empty content', function () {
    $indexer = new DocumentIndexer;

    $chunks = $indexer->chunk('');
    expect($chunks)->toHaveCount(0);

    $count = $indexer->index($this->agent->id, $this->project->id, 'empty.txt', '');
    expect($count)->toBe(0);
});

test('document indexer handles small content (no chunking needed)', function () {
    $indexer = new DocumentIndexer;

    $chunks = $indexer->chunk('Small text', 512, 50);
    expect($chunks)->toHaveCount(1);
    expect($chunks[0])->toBe('Small text');
});

test('document indexer remove index cleans up', function () {
    $indexer = new DocumentIndexer;

    $indexer->index($this->agent->id, $this->project->id, 'remove-me.txt', str_repeat('A', 1200));

    $before = AgentMemory::where('key', 'like', 'doc:remove-me.txt:chunk:%')->count();
    expect($before)->toBeGreaterThan(0);

    $removed = $indexer->removeIndex($this->agent->id, $this->project->id, 'remove-me.txt');
    expect($removed)->toBe($before);

    $after = AgentMemory::where('key', 'like', 'doc:remove-me.txt:chunk:%')->count();
    expect($after)->toBe(0);
});

test('document indexer re-indexes on update', function () {
    $indexer = new DocumentIndexer;

    // Index initial version
    $indexer->index($this->agent->id, $this->project->id, 'reindex.txt', str_repeat('A', 600));
    $firstCount = AgentMemory::where('key', 'like', 'doc:reindex.txt:chunk:%')->count();

    // Re-index with different content
    $indexer->index($this->agent->id, $this->project->id, 'reindex.txt', str_repeat('B', 1500));
    $secondCount = AgentMemory::where('key', 'like', 'doc:reindex.txt:chunk:%')->count();

    // Should have different chunk counts (1500 chars > 600 chars)
    expect($secondCount)->toBeGreaterThan($firstCount);

    // All chunks should be from the new version (containing 'B')
    $chunks = AgentMemory::where('key', 'like', 'doc:reindex.txt:chunk:%')->get();
    foreach ($chunks as $chunk) {
        expect($chunk->content['text'])->toContain('B');
    }
});
