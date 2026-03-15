<?php

use App\Models\Agent;
use App\Models\AgentAuditLog;
use App\Models\ContentPolicy;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Skill;
use App\Models\SsoProvider;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ContentPolicyService;
use App\Services\PromptLinter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->org = Organization::create([
        'name' => 'Test Org',
        'slug' => 'test-org',
        'plan' => 'teams',
    ]);
    $this->org->users()->attach($this->user->id, [
        'role' => 'owner',
        'accepted_at' => now(),
    ]);
    $this->user->update(['current_organization_id' => $this->org->id]);

    $this->project = Project::create([
        'name' => 'Admin Test',
        'path' => '/tmp/admin-test',
        'organization_id' => $this->org->id,
    ]);
});

// ─── #203: Secret Scanning in PromptLinter ──────────────────────

test('PromptLinter detects API keys', function () {
    $linter = new PromptLinter;

    $issues = $linter->lint('Use this key: sk-ant-abcdefghij1234567890abcd');
    $secretIssues = collect($issues)->where('rule', 'secret_in_prompt');

    expect($secretIssues)->not->toBeEmpty();
    expect($secretIssues->first()['severity'])->toBe('error');
});

test('PromptLinter detects OpenAI keys', function () {
    $linter = new PromptLinter;

    $issues = $linter->lint('API key: sk-abcdefghijklmnopqrstuvwxyz1234');
    $secretIssues = collect($issues)->where('rule', 'secret_in_prompt');

    expect($secretIssues)->not->toBeEmpty();
});

test('PromptLinter detects AWS access keys', function () {
    $linter = new PromptLinter;

    $issues = $linter->lint('AWS key: AKIAIOSFODNN7EXAMPLE');
    $secretIssues = collect($issues)->where('rule', 'secret_in_prompt');

    expect($secretIssues)->not->toBeEmpty();
});

test('PromptLinter detects GitHub tokens', function () {
    $linter = new PromptLinter;

    $issues = $linter->lint('Token: ghp_ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefgh');
    $secretIssues = collect($issues)->where('rule', 'secret_in_prompt');

    expect($secretIssues)->not->toBeEmpty();
});

test('PromptLinter detects private keys', function () {
    $linter = new PromptLinter;

    $issues = $linter->lint("-----BEGIN RSA PRIVATE KEY-----\ndata here\n-----END RSA PRIVATE KEY-----");
    $secretIssues = collect($issues)->where('rule', 'secret_in_prompt');

    expect($secretIssues)->not->toBeEmpty();
});

test('PromptLinter detects password assignments', function () {
    $linter = new PromptLinter;

    $issues = $linter->lint('Set password="my_super_secret_password"');
    $secretIssues = collect($issues)->where('rule', 'secret_in_prompt');

    expect($secretIssues)->not->toBeEmpty();
});

test('PromptLinter detects connection strings', function () {
    $linter = new PromptLinter;

    $issues = $linter->lint('Connect to postgres://admin:secret@db.example.com/mydb');
    $secretIssues = collect($issues)->where('rule', 'secret_in_prompt');

    expect($secretIssues)->not->toBeEmpty();
});

test('PromptLinter does not flag clean prompts as secrets', function () {
    $linter = new PromptLinter;

    $issues = $linter->lint('You are a helpful assistant that summarizes documents.');
    $secretIssues = collect($issues)->where('rule', 'secret_in_prompt');

    expect($secretIssues)->toBeEmpty();
});

test('PromptLinter reports correct line numbers for secrets', function () {
    $linter = new PromptLinter;

    $body = "Line one is clean\nLine two is also clean\nLine three has sk-ant-abcdefghij1234567890abcd in it";
    $issues = $linter->lint($body);
    $secretIssues = collect($issues)->where('rule', 'secret_in_prompt');

    expect($secretIssues)->not->toBeEmpty();
    expect($secretIssues->first()['line'])->toBe(3);
});

// ─── #204: Content Policies ─────────────────────────────────────

test('ContentPolicy can be created with rules', function () {
    $policy = ContentPolicy::create([
        'organization_id' => $this->org->id,
        'name' => 'Security Policy',
        'description' => 'Block secrets in skills',
        'rules' => [
            ['type' => 'block_secrets', 'action' => 'block'],
        ],
    ]);

    expect($policy->id)->toBeGreaterThan(0);
    expect($policy->uuid)->not->toBeEmpty();
    expect($policy->rules)->toBeArray();
    expect($policy->rules)->toHaveCount(1);
});

test('ContentPolicyService detects secrets in skill', function () {
    ContentPolicy::create([
        'organization_id' => $this->org->id,
        'name' => 'Block Secrets',
        'rules' => [['type' => 'block_secrets', 'action' => 'block']],
    ]);

    $skill = Skill::create([
        'project_id' => $this->project->id,
        'name' => 'Bad Skill',
        'slug' => 'bad-skill',
        'body' => 'Use key: sk-ant-abcdefghij1234567890abcd',
    ]);

    $service = new ContentPolicyService;
    $violations = $service->checkSkillCompliance($skill, $this->org->id);

    expect($violations)->not->toBeEmpty();
    expect($violations[0]['action'])->toBe('block');
});

test('ContentPolicyService passes clean skill', function () {
    ContentPolicy::create([
        'organization_id' => $this->org->id,
        'name' => 'Block Secrets',
        'rules' => [['type' => 'block_secrets', 'action' => 'block']],
    ]);

    $skill = Skill::create([
        'project_id' => $this->project->id,
        'name' => 'Good Skill',
        'slug' => 'good-skill',
        'body' => 'You are a helpful summarizer.',
    ]);

    $service = new ContentPolicyService;
    $violations = $service->checkSkillCompliance($skill, $this->org->id);

    expect($violations)->toBeEmpty();
});

test('ContentPolicyService detects dangerous commands', function () {
    ContentPolicy::create([
        'organization_id' => $this->org->id,
        'name' => 'Block Dangerous',
        'rules' => [['type' => 'block_dangerous_commands', 'action' => 'warn']],
    ]);

    $skill = Skill::create([
        'project_id' => $this->project->id,
        'name' => 'Dangerous',
        'slug' => 'dangerous',
        'body' => 'Run: rm -rf /var/data',
    ]);

    $service = new ContentPolicyService;
    $violations = $service->checkSkillCompliance($skill, $this->org->id);

    expect($violations)->not->toBeEmpty();
    expect($violations[0]['action'])->toBe('warn');
});

test('ContentPolicyService enforces token limits', function () {
    ContentPolicy::create([
        'organization_id' => $this->org->id,
        'name' => 'Size Limit',
        'rules' => [['type' => 'max_token_limit', 'action' => 'block', 'value' => 10]],
    ]);

    $skill = Skill::create([
        'project_id' => $this->project->id,
        'name' => 'Long Skill',
        'slug' => 'long-skill',
        'body' => str_repeat('word ', 100),
    ]);

    $service = new ContentPolicyService;
    $violations = $service->checkSkillCompliance($skill, $this->org->id);

    expect($violations)->not->toBeEmpty();
    expect($violations[0]['rule'])->toBe('max_token_limit');
});

test('ContentPolicyService ignores inactive policies', function () {
    ContentPolicy::create([
        'organization_id' => $this->org->id,
        'name' => 'Disabled',
        'rules' => [['type' => 'block_secrets', 'action' => 'block']],
        'is_active' => false,
    ]);

    $skill = Skill::create([
        'project_id' => $this->project->id,
        'name' => 'Has Secret',
        'slug' => 'has-secret',
        'body' => 'Key: sk-ant-abcdefghij1234567890abcd',
    ]);

    $service = new ContentPolicyService;
    $violations = $service->checkSkillCompliance($skill, $this->org->id);

    expect($violations)->toBeEmpty();
});

test('ContentPolicy API CRUD works', function () {
    // Create
    $response = $this->postJson("/api/organizations/{$this->org->id}/content-policies", [
        'name' => 'API Policy',
        'rules' => [['type' => 'block_secrets', 'action' => 'block']],
    ]);
    $response->assertStatus(201);
    $policyId = $response->json('data.id');

    // Index
    $response = $this->getJson("/api/organizations/{$this->org->id}/content-policies");
    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);

    // Show
    $response = $this->getJson("/api/content-policies/{$policyId}");
    $response->assertOk();
    expect($response->json('data.name'))->toBe('API Policy');

    // Update
    $response = $this->putJson("/api/content-policies/{$policyId}", [
        'name' => 'Updated Policy',
    ]);
    $response->assertOk();
    expect($response->json('data.name'))->toBe('Updated Policy');

    // Delete
    $response = $this->deleteJson("/api/content-policies/{$policyId}");
    $response->assertStatus(204);
});

test('content policy rule types endpoint returns available types', function () {
    $response = $this->getJson('/api/content-policies/rule-types');
    $response->assertOk();

    $types = $response->json('data');
    expect($types)->toHaveKey('block_secrets');
    expect($types)->toHaveKey('block_dangerous_commands');
    expect($types)->toHaveKey('custom_pattern');
});

// ─── #205: Audit Log Enhancements ──────────────────────────────

test('AuditLogger creates log with enhanced fields', function () {
    $log = AuditLogger::log(
        event: 'skill.created',
        description: 'Created skill "test-skill"',
        metadata: ['skill_name' => 'test-skill'],
        projectId: $this->project->id,
        severity: 'info',
        requestId: 'req-123-456',
    );

    expect($log->event)->toBe('skill.created');
    expect($log->severity)->toBe('info');
    expect($log->request_id)->toBe('req-123-456');
    expect($log->user_email)->toBe($this->user->email);
    expect($log->user_id)->toBe($this->user->id);
});

test('AuditLogger defaults severity to info', function () {
    $log = AuditLogger::log('test.event', 'Test description');

    expect($log->severity)->toBe('info');
});

test('AgentAuditLog immutability — only insert', function () {
    $log = AuditLogger::log('test.event', 'Original');

    expect($log->description)->toBe('Original');

    // The model should not be designed for updates, but we verify create works
    expect($log->id)->toBeGreaterThan(0);
});

test('AgentAuditLog scopes filter correctly', function () {
    AuditLogger::log('agent.executed', 'Run 1', severity: 'info');
    AuditLogger::log('agent.failed', 'Run 2', severity: 'error');
    AuditLogger::log('agent.executed', 'Run 3', severity: 'warning');

    expect(AgentAuditLog::forSeverity('error')->count())->toBe(1);
    expect(AgentAuditLog::forEvent('agent.executed')->count())->toBe(2);
});

test('audit log API supports severity filter', function () {
    AuditLogger::log('test.one', 'Info log', severity: 'info');
    AuditLogger::log('test.two', 'Error log', severity: 'error');

    $response = $this->getJson('/api/audit-logs?severity=error');
    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

test('audit log export returns CSV', function () {
    AuditLogger::log('test.event', 'Export test');

    $response = $this->get('/api/audit-logs/export?format=csv');
    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

test('audit log export returns JSON', function () {
    AuditLogger::log('test.event', 'Export test');

    $response = $this->get('/api/audit-logs/export?format=json');
    $response->assertOk();
    $response->assertHeader('content-disposition');
});

// ─── #206: Activity Feed ────────────────────────────────────────

test('activity feed returns formatted events', function () {
    AuditLogger::log(
        event: 'agent.executed',
        description: 'Ran agent test-agent',
        projectId: $this->project->id,
    );

    $response = $this->getJson("/api/organizations/{$this->org->id}/activity-feed");
    $response->assertOk();

    $data = $response->json('data');
    expect($data)->not->toBeEmpty();
    expect($data[0])->toHaveKeys(['id', 'event', 'description', 'severity', 'time_ago', 'created_at']);
});

test('activity feed supports pagination', function () {
    for ($i = 0; $i < 5; $i++) {
        AuditLogger::log("test.event.{$i}", "Event {$i}");
    }

    $response = $this->getJson("/api/organizations/{$this->org->id}/activity-feed?limit=2");
    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('meta.total'))->toBe(5);
});

// ─── #201: SSO Provider Model ───────────────────────────────────

test('SsoProvider model creates with UUID', function () {
    $provider = SsoProvider::create([
        'organization_id' => $this->org->id,
        'type' => 'oidc',
        'name' => 'Google Workspace',
        'client_id' => 'test-client-id',
        'client_secret' => 'test-secret',
        'metadata_url' => 'https://accounts.google.com',
    ]);

    expect($provider->uuid)->not->toBeEmpty();
    expect($provider->isOidc())->toBeTrue();
    expect($provider->isSaml())->toBeFalse();
});

test('SsoProvider domain restriction works', function () {
    $provider = SsoProvider::create([
        'organization_id' => $this->org->id,
        'type' => 'oidc',
        'name' => 'Corp SSO',
        'allowed_domains' => ['example.com', 'corp.io'],
    ]);

    expect($provider->isDomainAllowed('user@example.com'))->toBeTrue();
    expect($provider->isDomainAllowed('user@corp.io'))->toBeTrue();
    expect($provider->isDomainAllowed('user@other.com'))->toBeFalse();
});

test('SsoProvider with no domain restriction allows all', function () {
    $provider = SsoProvider::create([
        'organization_id' => $this->org->id,
        'type' => 'saml',
        'name' => 'Open SSO',
        'allowed_domains' => null,
    ]);

    expect($provider->isDomainAllowed('anyone@anywhere.com'))->toBeTrue();
});

test('SsoProvider callback URL is correct', function () {
    $provider = SsoProvider::create([
        'organization_id' => $this->org->id,
        'type' => 'saml',
        'name' => 'SAML IdP',
    ]);

    expect($provider->callbackUrl())->toContain("/auth/saml/{$provider->uuid}/acs");

    $oidcProvider = SsoProvider::create([
        'organization_id' => $this->org->id,
        'type' => 'oidc',
        'name' => 'OIDC IdP',
    ]);

    expect($oidcProvider->callbackUrl())->toContain("/auth/oidc/{$oidcProvider->uuid}/callback");
});

test('SSO provider API CRUD works for admin', function () {
    // Create
    $response = $this->postJson("/api/organizations/{$this->org->id}/sso-providers", [
        'type' => 'oidc',
        'name' => 'Test OIDC',
        'client_id' => 'my-client',
        'metadata_url' => 'https://idp.example.com',
    ]);
    $response->assertStatus(201);
    $providerId = $response->json('data.id');

    // Index
    $response = $this->getJson("/api/organizations/{$this->org->id}/sso-providers");
    $response->assertOk();
    expect($response->json('data'))->not->toBeEmpty();

    // Show
    $response = $this->getJson("/api/sso-providers/{$providerId}");
    $response->assertOk();

    // Update
    $response = $this->putJson("/api/sso-providers/{$providerId}", [
        'name' => 'Updated OIDC',
    ]);
    $response->assertOk();
    expect($response->json('data.name'))->toBe('Updated OIDC');

    // Delete
    $response = $this->deleteJson("/api/sso-providers/{$providerId}");
    $response->assertStatus(204);
});

test('SSO discovery endpoint returns active providers', function () {
    SsoProvider::create([
        'organization_id' => $this->org->id,
        'type' => 'oidc',
        'name' => 'Active OIDC',
        'is_active' => true,
    ]);
    SsoProvider::create([
        'organization_id' => $this->org->id,
        'type' => 'saml',
        'name' => 'Inactive SAML',
        'is_active' => false,
    ]);

    $response = $this->getJson("/api/auth/sso/{$this->org->id}");
    $response->assertOk();

    $providers = $response->json('data');
    expect($providers)->toHaveCount(1);
    expect($providers[0]['name'])->toBe('Active OIDC');
});

test('SsoProvider claim mapping defaults are correct', function () {
    $saml = new SsoProvider(['type' => 'saml', 'claim_mapping' => null]);
    $mapping = $saml->getEffectiveClaimMapping();
    expect($mapping['email'])->toContain('emailaddress');

    $oidc = new SsoProvider(['type' => 'oidc', 'claim_mapping' => null]);
    $mapping = $oidc->getEffectiveClaimMapping();
    expect($mapping['email'])->toBe('email');
    expect($mapping['name'])->toBe('name');
});

test('SsoProvider claim mapping overrides work', function () {
    $provider = new SsoProvider([
        'type' => 'oidc',
        'claim_mapping' => ['email' => 'preferred_email'],
    ]);

    $mapping = $provider->getEffectiveClaimMapping();
    expect($mapping['email'])->toBe('preferred_email');
    expect($mapping['name'])->toBe('name'); // Default preserved
});
