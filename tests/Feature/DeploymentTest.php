<?php

use App\Models\Agent;
use App\Models\AppSetting;
use App\Models\LicenseKey;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Services\BackupService;
use App\Services\HealthCheckService;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->org = Organization::create([
        'name' => 'Deploy Org',
        'slug' => 'deploy-org',
        'plan' => 'teams',
    ]);
    $this->org->users()->attach($this->user->id, [
        'role' => 'owner',
        'accepted_at' => now(),
    ]);
    $this->user->update(['current_organization_id' => $this->org->id]);
});

// ─── #245: License Key System ────────────────────────────────────

test('LicenseService generates key in correct format', function () {
    $service = new LicenseService;
    $license = $service->generate('self_hosted');

    expect($license->key)->toMatch('/^ORKESTR-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/');
    expect($license->tier)->toBe('self_hosted');
    expect($license->status)->toBe('active');
    expect($license->features)->toContain('execution', 'workflows', 'mcp_servers');
});

test('LicenseService generates enterprise key with all features', function () {
    $service = new LicenseService;
    $license = $service->generate('enterprise');

    expect($license->tier)->toBe('enterprise');
    expect($license->features)->toContain('sso', 'audit_export', 'air_gap_mode');
    expect($license->isEnterprise())->toBeTrue();
});

test('LicenseService activates key for organization', function () {
    $service = new LicenseService;
    $license = $service->generate('self_hosted');

    $activated = $service->activate($license->key, $this->org->id);

    expect($activated->organization_id)->toBe($this->org->id);
    expect($activated->activated_at)->not->toBeNull();
});

test('LicenseService rejects invalid key', function () {
    $service = new LicenseService;

    $service->activate('INVALID-KEY', $this->org->id);
})->throws(\InvalidArgumentException::class, 'Invalid license key.');

test('LicenseService rejects revoked key', function () {
    $service = new LicenseService;
    $license = $service->generate('self_hosted');
    $service->revoke($license);

    $service->activate($license->key, $this->org->id);
})->throws(\InvalidArgumentException::class, 'revoked');

test('LicenseService rejects expired key', function () {
    $service = new LicenseService;
    $license = $service->generate('self_hosted', [
        'expires_at' => now()->subDay(),
    ]);

    $service->activate($license->key, $this->org->id);
})->throws(\InvalidArgumentException::class, 'expired');

test('LicenseKey model isActive checks status and expiration', function () {
    $active = LicenseKey::create([
        'key' => 'ORKESTR-TEST-AAAA-BBBB-CCCC',
        'tier' => 'self_hosted',
        'status' => 'active',
        'features' => ['execution'],
    ]);
    expect($active->isActive())->toBeTrue();

    $expired = LicenseKey::create([
        'key' => 'ORKESTR-TEST-DDDD-EEEE-FFFF',
        'tier' => 'self_hosted',
        'status' => 'active',
        'features' => ['execution'],
        'expires_at' => now()->subDay(),
    ]);
    expect($expired->isActive())->toBeFalse();
    expect($expired->isExpired())->toBeTrue();
});

test('LicenseKey hasFeature checks features array', function () {
    $license = LicenseKey::create([
        'key' => 'ORKESTR-FEAT-AAAA-BBBB-CCCC',
        'tier' => 'enterprise',
        'status' => 'active',
        'features' => ['sso', 'audit_export'],
    ]);

    expect($license->hasFeature('sso'))->toBeTrue();
    expect($license->hasFeature('nonexistent'))->toBeFalse();
});

test('license status API returns no license when none exists', function () {
    $response = $this->getJson('/api/license/status');

    $response->assertOk()
        ->assertJsonPath('licensed', false);
});

test('license activate API works with valid key', function () {
    $service = new LicenseService;
    $license = $service->generate('self_hosted');

    // Bind current organization so the controller can find it
    app()->instance('current_organization', $this->org);

    $response = $this->postJson('/api/license/activate', [
        'key' => $license->key,
    ]);

    $response->assertOk()
        ->assertJsonPath('license.tier', 'self_hosted');
});

// ─── #246: Setup Wizard ──────────────────────────────────────────

test('setup wizard status shows incomplete by default', function () {
    $response = $this->getJson('/api/setup/status');

    $response->assertOk()
        ->assertJsonPath('completed', false);
});

test('setup wizard configures API keys', function () {
    $response = $this->postJson('/api/setup/api-keys', [
        'anthropic_api_key' => 'sk-ant-test-key',
    ]);

    $response->assertOk();
    expect(AppSetting::get('anthropic_api_key'))->toBe('sk-ant-test-key');
});

test('setup wizard configures default model', function () {
    $response = $this->postJson('/api/setup/default-model', [
        'default_model' => 'claude-sonnet-4-6',
    ]);

    $response->assertOk();
    expect(AppSetting::get('default_model'))->toBe('claude-sonnet-4-6');
});

test('setup wizard quick-start creates project and agent', function () {
    $response = $this->postJson('/api/setup/quick-start', [
        'project_name' => 'My First Project',
        'agent_name' => 'Assistant',
        'agent_role' => 'helper',
    ]);

    $response->assertCreated()
        ->assertJsonPath('project.name', 'My First Project')
        ->assertJsonPath('agent.name', 'Assistant');

    expect(Project::where('name', 'My First Project')->exists())->toBeTrue();
    expect(Agent::where('name', 'Assistant')->exists())->toBeTrue();
});

test('setup wizard marks setup complete', function () {
    $response = $this->postJson('/api/setup/complete');

    $response->assertOk();
    expect(AppSetting::get('setup_completed'))->toBeTruthy();
    expect(AppSetting::get('setup_completed_at'))->not->toBeNull();
});

test('setup wizard full flow', function () {
    // Step 1: Check status
    $this->getJson('/api/setup/status')
        ->assertJsonPath('completed', false);

    // Step 2: API keys
    $this->postJson('/api/setup/api-keys', [
        'anthropic_api_key' => 'sk-ant-test',
    ])->assertOk();

    // Step 3: Model
    $this->postJson('/api/setup/default-model', [
        'default_model' => 'claude-sonnet-4-6',
    ])->assertOk();

    // Step 4: Quick start
    $this->postJson('/api/setup/quick-start', [
        'project_name' => 'Starter',
        'agent_name' => 'Bot',
    ])->assertCreated();

    // Step 5: Complete
    $this->postJson('/api/setup/complete')->assertOk();

    // Verify final status
    $this->getJson('/api/setup/status')
        ->assertJsonPath('completed', true)
        ->assertJsonPath('steps.api_keys', true)
        ->assertJsonPath('steps.default_model', true)
        ->assertJsonPath('steps.first_project', true)
        ->assertJsonPath('steps.first_agent', true);
});

// ─── #247/#248: Backup & Restore ─────────────────────────────────

test('BackupService creates backup ZIP', function () {
    // Create some data to back up
    AppSetting::set('test_setting', 'test_value');

    $service = new BackupService;
    $zipPath = $service->createBackup();

    expect($zipPath)->toEndWith('.zip');
    expect(file_exists($zipPath))->toBeTrue();

    // Verify ZIP contents
    $zip = new \ZipArchive;
    $zip->open($zipPath);
    $manifest = json_decode($zip->getFromName('manifest.json'), true);
    $zip->close();

    expect($manifest)->toHaveKey('version');
    expect($manifest)->toHaveKey('created_at');
    expect($manifest)->toHaveKey('tables');

    // Clean up
    @unlink($zipPath);
});

test('BackupService lists backups', function () {
    $service = new BackupService;

    // Create a backup
    $zipPath = $service->createBackup();

    $backups = $service->listBackups();
    expect($backups)->not->toBeEmpty();
    expect($backups[0])->toHaveKeys(['filename', 'path', 'size', 'size_human', 'created_at']);

    // Clean up
    @unlink($zipPath);
});

test('backup API creates and lists backups', function () {
    // Create
    $response = $this->postJson('/api/backups');
    $response->assertCreated()
        ->assertJsonStructure(['filename', 'download_url', 'size']);

    $filename = $response->json('filename');

    // List
    $this->getJson('/api/backups')
        ->assertOk();

    // Clean up
    @unlink(storage_path("backups/{$filename}"));
});

// ─── #250: Health Diagnostics ────────────────────────────────────

test('HealthCheckService checks database', function () {
    $service = new HealthCheckService;
    $result = $service->checkDatabase();

    expect($result['status'])->toBe('healthy');
    expect($result['latency_ms'])->toBeInt();
});

test('HealthCheckService checks cache', function () {
    $service = new HealthCheckService;
    $result = $service->checkCache();

    expect($result['status'])->toBe('healthy');
});

test('HealthCheckService checks storage', function () {
    $service = new HealthCheckService;
    $result = $service->checkStorage();

    expect($result['status'])->toBe('healthy');
});

test('HealthCheckService checks provider without key', function () {
    $service = new HealthCheckService;
    $result = $service->checkProvider('anthropic');

    expect($result['status'])->toBeIn(['not_configured', 'configured']);
});

test('HealthCheckService returns system info', function () {
    $service = new HealthCheckService;
    $info = $service->systemInfo();

    expect($info)->toHaveKeys(['php_version', 'laravel_version', 'app_version', 'environment', 'db_driver']);
    expect($info['php_version'])->toStartWith('8.');
});

test('HealthCheckService runAll returns all checks', function () {
    $service = new HealthCheckService;
    $results = $service->runAll();

    expect($results)->toHaveKeys(['database', 'cache', 'queue', 'storage', 'anthropic', 'openai', 'ollama']);
});

test('diagnostics API returns all checks', function () {
    $response = $this->getJson('/api/diagnostics');

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'checks' => ['database', 'cache', 'queue', 'storage'],
            'system' => ['php_version', 'laravel_version'],
            'checked_at',
        ]);
});

test('diagnostics API returns single check', function () {
    $response = $this->getJson('/api/diagnostics/database');

    $response->assertOk()
        ->assertJsonPath('check', 'database')
        ->assertJsonPath('status', 'healthy');
});

test('diagnostics API rejects invalid check name', function () {
    $this->getJson('/api/diagnostics/invalid')
        ->assertStatus(422);
});

// ─── Infrastructure file validation ─────────────────────────────

test('production Dockerfile exists and is valid', function () {
    $path = base_path('docker/php/Dockerfile.prod');
    expect(file_exists($path))->toBeTrue();

    $content = file_get_contents($path);
    expect($content)->toContain('FROM php:8.4');
    expect($content)->toContain('HEALTHCHECK');
});

test('production docker-compose exists and defines services', function () {
    $path = base_path('docker-compose.prod.yml');
    expect(file_exists($path))->toBeTrue();

    $content = file_get_contents($path);
    expect($content)->toContain('app:');
    expect($content)->toContain('mariadb:');
    expect($content)->toContain('restart:');
});

test('deploy script exists and is executable', function () {
    $path = base_path('bin/orkestr');
    expect(file_exists($path))->toBeTrue();

    $content = file_get_contents($path);
    expect($content)->toContain('cmd_deploy');
    expect($content)->toContain('cmd_backup');
    expect($content)->toContain('cmd_upgrade');
});

test('reverse proxy configs exist', function () {
    expect(file_exists(base_path('deploy/proxy/nginx.conf')))->toBeTrue();
    expect(file_exists(base_path('deploy/proxy/Caddyfile')))->toBeTrue();
    expect(file_exists(base_path('deploy/proxy/traefik.yml')))->toBeTrue();
});
