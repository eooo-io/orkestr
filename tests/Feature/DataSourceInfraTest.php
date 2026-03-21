<?php

use App\Services\HealthCheckService;
use App\Services\Storage\MinioService;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Data Source Infrastructure Tests (Phase N.1 — #397–#401)
|--------------------------------------------------------------------------
*/

// #397 — Knowledge DB config
it('has knowledge database connection configured', function () {
    $config = config('database.connections.knowledge');

    expect($config)->not->toBeNull()
        ->and($config['driver'])->toBe('pgsql')
        ->and($config['database'])->toBe('orkestr_knowledge')
        ->and($config['schema'])->toBe('public');
});

// #398 — MinIO filesystem disk config
it('has minio filesystem disk configured', function () {
    $config = config('filesystems.disks.minio');

    expect($config)->not->toBeNull()
        ->and($config['driver'])->toBe('s3')
        ->and($config['use_path_style_endpoint'])->toBeTrue()
        ->and($config['bucket'])->toBe('agent-artifacts');
});

// #400 — MinioService upload/download/list/delete with fake filesystem
it('can upload and download via MinioService', function () {
    Storage::fake('minio');

    $service = new MinioService;

    $result = $service->upload('test/file.txt', 'hello world');
    expect($result)->toBeTrue();

    $content = $service->download('test/file.txt');
    expect($content)->toBe('hello world');
});

it('can list files via MinioService', function () {
    Storage::fake('minio');

    $service = new MinioService;
    $service->upload('docs/a.txt', 'aaa');
    $service->upload('docs/b.txt', 'bbb');

    $files = $service->list('docs');
    expect($files)->toContain('docs/a.txt')
        ->and($files)->toContain('docs/b.txt');
});

it('can delete files via MinioService', function () {
    Storage::fake('minio');

    $service = new MinioService;
    $service->upload('temp/remove-me.txt', 'data');

    expect($service->exists('temp/remove-me.txt'))->toBeTrue();

    $service->delete('temp/remove-me.txt');

    expect($service->exists('temp/remove-me.txt'))->toBeFalse();
});

it('builds scoped paths correctly', function () {
    $service = new MinioService;

    $path = $service->scopedPath('my-agent', 42, 'artifacts/output.json');
    expect($path)->toBe('projects/42/agents/my-agent/artifacts/output.json');
});

it('can upload and download with scoping', function () {
    Storage::fake('minio');

    $service = new MinioService;
    $service->scopedUpload('agent-a', 1, 'result.json', '{"ok":true}');

    $content = $service->scopedDownload('agent-a', 1, 'result.json');
    expect($content)->toBe('{"ok":true}');

    expect($service->exists('projects/1/agents/agent-a/result.json'))->toBeTrue();
});

// #401 — Health checks return appropriate status
it('health check knowledge_db returns valid structure', function () {
    $service = new HealthCheckService;
    $result = $service->checkKnowledgeDb();

    expect($result)->toHaveKey('status')
        ->and($result)->toHaveKey('message')
        ->and($result)->toHaveKey('latency_ms');
});

it('health check pgvector returns valid structure', function () {
    $service = new HealthCheckService;
    $result = $service->checkPgvector();

    expect($result)->toHaveKey('status')
        ->and($result)->toHaveKey('message');
});

it('health check minio returns valid structure', function () {
    $service = new HealthCheckService;
    $result = $service->checkMinio();

    expect($result)->toHaveKey('status')
        ->and($result)->toHaveKey('message');
});

it('knowledge db check handles missing connection gracefully', function () {
    // In test env (SQLite), knowledge DB won't be reachable
    $service = new HealthCheckService;
    $result = $service->checkKnowledgeDb();

    // Should return a valid status, not throw
    expect($result['status'])->toBeIn(['healthy', 'unhealthy', 'not_configured']);
});

it('minio check handles unreachable endpoint gracefully', function () {
    $service = new HealthCheckService;
    $result = $service->checkMinio();

    // Should return a valid status, not throw
    expect($result['status'])->toBeIn(['healthy', 'degraded', 'unreachable', 'not_configured']);
});
