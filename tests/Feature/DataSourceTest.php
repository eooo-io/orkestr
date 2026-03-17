<?php

use App\Models\Agent;
use App\Models\DataSource;
use App\Models\Project;
use App\Models\User;
use App\Services\Mcp\DataSourceMcpTools;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create(['name' => 'DS Test', 'path' => '/tmp/ds-test']);
    $this->agent = Agent::create([
        'name' => 'DS Agent',
        'slug' => 'ds-agent',
        'role' => 'assistant',
        'base_instructions' => 'Test agent for data sources.',
    ]);
});

// ─── #422: CRUD via API ───────────────────────────────────────

test('can list data sources', function () {
    DataSource::create([
        'project_id' => $this->project->id,
        'name' => 'Test PG',
        'type' => 'postgres',
        'connection_config' => ['host' => 'localhost', 'database' => 'test'],
    ]);

    $response = $this->getJson("/api/projects/{$this->project->id}/data-sources");

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.name', 'Test PG');
    $response->assertJsonPath('data.0.type', 'postgres');
});

test('can create a data source', function () {
    $response = $this->postJson("/api/projects/{$this->project->id}/data-sources", [
        'name' => 'My Redis',
        'type' => 'redis',
        'connection_config' => ['host' => '127.0.0.1', 'port' => 6379],
        'access_mode' => 'read_write',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'My Redis');
    $response->assertJsonPath('data.type', 'redis');
    $response->assertJsonPath('data.access_mode', 'read_write');

    $this->assertDatabaseHas('data_sources', [
        'name' => 'My Redis',
        'type' => 'redis',
        'project_id' => $this->project->id,
    ]);
});

test('can update a data source', function () {
    $ds = DataSource::create([
        'project_id' => $this->project->id,
        'name' => 'Old Name',
        'type' => 'minio',
    ]);

    $response = $this->putJson("/api/data-sources/{$ds->id}", [
        'name' => 'New Name',
        'access_mode' => 'read_write',
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.name', 'New Name');
    $response->assertJsonPath('data.access_mode', 'read_write');
});

test('can delete a data source', function () {
    $ds = DataSource::create([
        'project_id' => $this->project->id,
        'name' => 'Delete Me',
        'type' => 'filesystem',
    ]);

    $response = $this->deleteJson("/api/data-sources/{$ds->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('data_sources', ['id' => $ds->id]);
});

test('validates type on create', function () {
    $response = $this->postJson("/api/projects/{$this->project->id}/data-sources", [
        'name' => 'Bad Type',
        'type' => 'mongodb', // not supported
    ]);

    $response->assertUnprocessable();
});

// ─── Connection test endpoint ─────────────────────────────────

test('connection test returns status for filesystem', function () {
    $ds = DataSource::create([
        'project_id' => $this->project->id,
        'name' => 'FS Test',
        'type' => 'filesystem',
        'connection_config' => ['path' => '/tmp'],
    ]);

    $response = $this->postJson("/api/data-sources/{$ds->id}/test");

    $response->assertOk();
    $response->assertJsonStructure(['data' => ['status', 'message']]);
    $response->assertJsonPath('data.status', 'healthy');
});

test('connection test handles missing filesystem path', function () {
    $ds = DataSource::create([
        'project_id' => $this->project->id,
        'name' => 'Bad FS',
        'type' => 'filesystem',
        'connection_config' => ['path' => '/nonexistent/path/here'],
    ]);

    $response = $this->postJson("/api/data-sources/{$ds->id}/test");

    $response->assertOk();
    $response->assertJsonPath('data.status', 'unhealthy');
});

// ─── #423: Agent binding ──────────────────────────────────────

test('can bind data sources to agent', function () {
    // Attach agent to project first
    $this->project->agents()->attach($this->agent->id, ['is_enabled' => true]);

    $ds1 = DataSource::create([
        'project_id' => $this->project->id,
        'name' => 'DS 1',
        'type' => 'postgres',
    ]);
    $ds2 = DataSource::create([
        'project_id' => $this->project->id,
        'name' => 'DS 2',
        'type' => 'redis',
    ]);

    $response = $this->putJson(
        "/api/projects/{$this->project->id}/agents/{$this->agent->id}/data-sources",
        ['data_source_ids' => [$ds1->id, $ds2->id]],
    );

    $response->assertOk();
    $response->assertJsonPath('data.data_source_ids', [$ds1->id, $ds2->id]);

    $this->assertDatabaseHas('agent_data_source', [
        'agent_id' => $this->agent->id,
        'data_source_id' => $ds1->id,
    ]);
    $this->assertDatabaseHas('agent_data_source', [
        'agent_id' => $this->agent->id,
        'data_source_id' => $ds2->id,
    ]);
});

// ─── Encrypted config storage ─────────────────────────────────

test('connection config is encrypted at rest', function () {
    $ds = DataSource::create([
        'project_id' => $this->project->id,
        'name' => 'Encrypted Test',
        'type' => 'postgres',
        'connection_config' => ['host' => 'db.example.com', 'password' => 'super_secret'],
    ]);

    // Re-fetch from database
    $fresh = DataSource::find($ds->id);

    // Decrypted config should match original
    expect($fresh->connection_config['host'])->toBe('db.example.com');
    expect($fresh->connection_config['password'])->toBe('super_secret');

    // Masked config should hide password
    $masked = $fresh->maskedConfig();
    expect($masked['host'])->toBe('db.example.com');
    expect($masked['password'])->not->toBe('super_secret');
});

// ─── #424: MCP tool generation ────────────────────────────────

test('generates query tool for postgres read_only', function () {
    $ds = DataSource::create([
        'project_id' => $this->project->id,
        'name' => 'PG RO',
        'type' => 'postgres',
        'access_mode' => 'read_only',
    ]);

    $tools = DataSourceMcpTools::forDataSource($ds);

    expect($tools)->toHaveCount(1);
    expect($tools[0]->name)->toBe("ds_{$ds->id}_query");
    expect($tools[0]->description)->toContain('Only SELECT');
});

test('generates query and execute tools for postgres read_write', function () {
    $ds = DataSource::create([
        'project_id' => $this->project->id,
        'name' => 'PG RW',
        'type' => 'postgres',
        'access_mode' => 'read_write',
    ]);

    $tools = DataSourceMcpTools::forDataSource($ds);

    expect($tools)->toHaveCount(2);
    $names = array_map(fn ($t) => $t->name, $tools);
    expect($names)->toContain("ds_{$ds->id}_query");
    expect($names)->toContain("ds_{$ds->id}_execute");
});

test('generates read/list tools for minio read_only', function () {
    $ds = DataSource::create([
        'project_id' => $this->project->id,
        'name' => 'MinIO RO',
        'type' => 'minio',
        'access_mode' => 'read_only',
    ]);

    $tools = DataSourceMcpTools::forDataSource($ds);

    expect($tools)->toHaveCount(2);
    $names = array_map(fn ($t) => $t->name, $tools);
    expect($names)->toContain("ds_{$ds->id}_read_document");
    expect($names)->toContain("ds_{$ds->id}_list_documents");
});

test('generates read/list/write tools for s3 read_write', function () {
    $ds = DataSource::create([
        'project_id' => $this->project->id,
        'name' => 'S3 RW',
        'type' => 's3',
        'access_mode' => 'read_write',
    ]);

    $tools = DataSourceMcpTools::forDataSource($ds);

    expect($tools)->toHaveCount(3);
    $names = array_map(fn ($t) => $t->name, $tools);
    expect($names)->toContain("ds_{$ds->id}_write_document");
});

test('generates read/list tools for filesystem read_only', function () {
    $ds = DataSource::create([
        'project_id' => $this->project->id,
        'name' => 'FS RO',
        'type' => 'filesystem',
        'access_mode' => 'read_only',
    ]);

    $tools = DataSourceMcpTools::forDataSource($ds);

    expect($tools)->toHaveCount(2);
    $names = array_map(fn ($t) => $t->name, $tools);
    expect($names)->toContain("ds_{$ds->id}_read_file");
    expect($names)->toContain("ds_{$ds->id}_list_files");
});

test('generates get/keys tools for redis read_only', function () {
    $ds = DataSource::create([
        'project_id' => $this->project->id,
        'name' => 'Redis RO',
        'type' => 'redis',
        'access_mode' => 'read_only',
    ]);

    $tools = DataSourceMcpTools::forDataSource($ds);

    expect($tools)->toHaveCount(2);
    $names = array_map(fn ($t) => $t->name, $tools);
    expect($names)->toContain("ds_{$ds->id}_get");
    expect($names)->toContain("ds_{$ds->id}_keys");
});

test('generates get/keys/set tools for redis read_write', function () {
    $ds = DataSource::create([
        'project_id' => $this->project->id,
        'name' => 'Redis RW',
        'type' => 'redis',
        'access_mode' => 'read_write',
    ]);

    $tools = DataSourceMcpTools::forDataSource($ds);

    expect($tools)->toHaveCount(3);
    $names = array_map(fn ($t) => $t->name, $tools);
    expect($names)->toContain("ds_{$ds->id}_set");
});

test('toArray serializes correctly', function () {
    $ds = DataSource::create([
        'project_id' => $this->project->id,
        'name' => 'Serialize Test',
        'type' => 'postgres',
        'access_mode' => 'read_only',
    ]);

    $arr = DataSourceMcpTools::toArray($ds);

    expect($arr)->toBeArray();
    expect($arr[0])->toHaveKeys(['name', 'description', 'inputSchema']);
});
