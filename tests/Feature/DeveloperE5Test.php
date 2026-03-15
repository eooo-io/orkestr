<?php

use App\Models\ApiToken;
use App\Models\Organization;
use App\Models\User;
use App\Services\OpenApiSpecService;
use App\Services\SdkGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->org = Organization::create([
        'name' => 'Dev Org',
        'slug' => 'dev-org-e5',
        'plan' => 'teams',
    ]);
    $this->org->users()->attach($this->user->id, ['role' => 'owner']);
    $this->user->update(['current_organization_id' => $this->org->id]);
    app()->instance('current_organization', $this->org);
});

// --- #212 OpenAPI specification ---

test('OpenAPI spec is generated as valid JSON', function () {
    $service = new OpenApiSpecService();
    $spec = $service->generate();

    expect($spec['openapi'])->toBe('3.1.0');
    expect($spec['info']['title'])->toContain('API');
    expect($spec['paths'])->toBeArray();
    expect($spec['components'])->toHaveKey('securitySchemes');
});

test('OpenAPI spec includes API paths', function () {
    $service = new OpenApiSpecService();
    $spec = $service->generate();

    // Should have many paths
    expect(count($spec['paths']))->toBeGreaterThan(10);
});

test('OpenAPI spec endpoint returns JSON', function () {
    $response = $this->getJson('/api/openapi.json');
    $response->assertOk();
    $response->assertJsonStructure(['openapi', 'info', 'paths']);
});

test('API docs page returns HTML', function () {
    $response = $this->get('/api/docs');
    $response->assertOk();
    expect($response->getContent())->toContain('swagger-ui');
});

// --- #213 & #214 SDK generation ---

test('TypeScript SDK is generated', function () {
    $specService = new OpenApiSpecService();
    $generator = new SdkGeneratorService($specService);

    $ts = $generator->generateTypeScript();

    expect($ts)->toContain('OrkestrClient');
    expect($ts)->toContain('export class');
    expect($ts)->toContain('async');
    expect($ts)->toContain('baseUrl');
});

test('PHP SDK is generated', function () {
    $specService = new OpenApiSpecService();
    $generator = new SdkGeneratorService($specService);

    $php = $generator->generatePhp();

    expect($php)->toContain('OrkestrClient');
    expect($php)->toContain('namespace Eooo');
    expect($php)->toContain('function request');
});

test('TypeScript SDK download endpoint works', function () {
    $response = $this->get('/api/sdk/typescript');
    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('orkestr-client.ts');
});

test('PHP SDK download endpoint works', function () {
    $response = $this->get('/api/sdk/php');
    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('OrkestrClient.php');
});

test('Python SDK is generated', function () {
    $specService = new OpenApiSpecService();
    $generator = new SdkGeneratorService($specService);

    $py = $generator->generatePython();

    expect($py)->toContain('OrkestrClient');
    expect($py)->toContain('class OrkestrClient');
    expect($py)->toContain('def _request');
    expect($py)->toContain('urllib');
});

test('Python SDK download endpoint works', function () {
    $response = $this->get('/api/sdk/python');
    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('orkestr_client.py');
});

// --- #215 API token authentication ---

test('ApiToken model creates with hashed token', function () {
    $result = ApiToken::createToken($this->user, 'Test Token', ['*'], $this->org);

    expect($result['plain_token'])->toBeString();
    expect(strlen($result['plain_token']))->toBe(48);
    expect($result['token']->name)->toBe('Test Token');
    expect($result['token']->token)->not->toBe($result['plain_token']);
});

test('ApiToken can be found by plain token', function () {
    $result = ApiToken::createToken($this->user, 'Findable Token');

    $found = ApiToken::findByPlainToken($result['plain_token']);
    expect($found)->not->toBeNull();
    expect($found->id)->toBe($result['token']->id);
});

test('ApiToken returns null for invalid plain token', function () {
    $found = ApiToken::findByPlainToken('nonexistent-token-value');
    expect($found)->toBeNull();
});

test('ApiToken ability check works', function () {
    $result = ApiToken::createToken($this->user, 'Wildcard', ['*']);
    expect($result['token']->hasAbility('anything'))->toBeTrue();

    $result2 = ApiToken::createToken($this->user, 'Scoped', ['skills:read', 'projects:*']);
    expect($result2['token']->hasAbility('skills:read'))->toBeTrue();
    expect($result2['token']->hasAbility('skills:write'))->toBeFalse();
    expect($result2['token']->hasAbility('projects:read'))->toBeTrue();
    expect($result2['token']->hasAbility('projects:write'))->toBeTrue();
});

test('ApiToken expiration check works', function () {
    $result = ApiToken::createToken($this->user, 'Expired', ['*'], null, now()->subDay());
    expect($result['token']->isExpired())->toBeTrue();

    $result2 = ApiToken::createToken($this->user, 'Valid', ['*'], null, now()->addDay());
    expect($result2['token']->isExpired())->toBeFalse();
});

test('ApiToken markUsed updates last_used_at', function () {
    $result = ApiToken::createToken($this->user, 'Used Token');
    expect($result['token']->last_used_at)->toBeNull();

    $result['token']->markUsed();
    $result['token']->refresh();
    expect($result['token']->last_used_at)->not->toBeNull();
});

test('API token list endpoint works', function () {
    ApiToken::createToken($this->user, 'Token One');
    ApiToken::createToken($this->user, 'Token Two');

    $response = $this->getJson('/api/api-tokens');
    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

test('API token create endpoint returns plain token', function () {
    $response = $this->postJson('/api/api-tokens', [
        'name' => 'My CI Token',
        'abilities' => ['skills:read', 'projects:read'],
        'expires_in_days' => 30,
    ]);

    $response->assertStatus(201);
    expect($response->json('data.plain_token'))->toBeString();
    expect($response->json('data.name'))->toBe('My CI Token');
    expect($response->json('message'))->toContain('not be shown again');
});

test('API token create validates name', function () {
    $response = $this->postJson('/api/api-tokens', []);
    $response->assertStatus(422);
});

test('API token delete endpoint works', function () {
    $result = ApiToken::createToken($this->user, 'Delete Me');

    $response = $this->deleteJson("/api/api-tokens/{$result['token']->id}");
    $response->assertStatus(204);

    expect(ApiToken::find($result['token']->id))->toBeNull();
});

test('Cannot delete another users token', function () {
    $other = User::factory()->create();
    $result = ApiToken::createToken($other, 'Other Token');

    $response = $this->deleteJson("/api/api-tokens/{$result['token']->id}");
    $response->assertStatus(403);
});

// --- #216 CLI Commands ---

test('orkestr:deploy command runs checks', function () {
    $this->artisan('orkestr:deploy', ['--check' => true])
        ->assertExitCode(0);
});

test('orkestr:manage status command shows counts', function () {
    $this->artisan('orkestr:manage', ['action' => 'status'])
        ->assertExitCode(0);
});

test('orkestr:manage projects command works', function () {
    $this->artisan('orkestr:manage', ['action' => 'projects'])
        ->assertExitCode(0);
});

test('orkestr:manage agents command works', function () {
    $this->artisan('orkestr:manage', ['action' => 'agents'])
        ->assertExitCode(0);
});

test('orkestr:manage skills command works', function () {
    $this->artisan('orkestr:manage', ['action' => 'skills'])
        ->assertExitCode(0);
});

test('orkestr:manage unknown action fails', function () {
    $this->artisan('orkestr:manage', ['action' => 'invalid'])
        ->assertFailed();
});

// --- Middleware integration ---

test('AuthenticateApiToken middleware validates token correctly', function () {
    $result = ApiToken::createToken($this->user, 'Middleware Test', ['*'], $this->org);
    $middleware = new \App\Http\Middleware\AuthenticateApiToken();

    // Test with valid token
    $request = \Illuminate\Http\Request::create('/api/test', 'GET');
    $request->headers->set('Authorization', 'Bearer ' . $result['plain_token']);

    $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));
    expect($response->getStatusCode())->toBe(200);

    // Test with invalid token
    $request2 = \Illuminate\Http\Request::create('/api/test', 'GET');
    $request2->headers->set('Authorization', 'Bearer invalid-token');

    $response2 = $middleware->handle($request2, fn () => response()->json(['ok' => true]));
    expect($response2->getStatusCode())->toBe(401);
});

test('AuthenticateApiToken rejects expired tokens', function () {
    $result = ApiToken::createToken($this->user, 'Expired', ['*'], null, now()->subDay());
    $middleware = new \App\Http\Middleware\AuthenticateApiToken();

    $request = \Illuminate\Http\Request::create('/api/test', 'GET');
    $request->headers->set('Authorization', 'Bearer ' . $result['plain_token']);

    $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));
    expect($response->getStatusCode())->toBe(401);
});

test('OpenAPI spec is publicly accessible', function () {
    auth()->forgetGuards();

    // Ensure we're NOT logged in for this test
    $this->app['auth']->forgetGuards();

    $response = $this->getJson('/api/openapi.json');
    $response->assertOk();
});
