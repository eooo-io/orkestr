<?php

use App\Models\Agent;
use App\Models\ExecutionRun;
use App\Models\Project;
use App\Models\User;
use App\Services\Execution\CostCalculator;
use App\Services\Execution\Guards\BudgetGuard;
use App\Services\Execution\Guards\OutputGuard;
use App\Services\Execution\Guards\ToolGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create(['name' => 'Guard Test', 'path' => '/tmp/guard-test']);
    $this->agent = Agent::create([
        'name' => 'Guard Agent',
        'slug' => 'guard-agent',
        'role' => 'assistant',
        'model' => 'claude-sonnet-4-6',
        'base_instructions' => 'You are a test agent.',
    ]);
});

// --- BudgetGuard tests ---

test('BudgetGuard passes when under limits', function () {
    $guard = new BudgetGuard(new CostCalculator);

    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'total_tokens' => 1000,
        'total_cost_microcents' => 100,
    ]);

    expect($guard->check($run))->toBeNull();
});

test('BudgetGuard fails when tokens exceeded', function () {
    $guard = new BudgetGuard(new CostCalculator);

    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'total_tokens' => 150000,
        'total_cost_microcents' => 100,
    ]);

    $result = $guard->check($run);
    expect($result)->toContain('Token budget exceeded');
});

test('BudgetGuard fails when cost exceeded', function () {
    $guard = new BudgetGuard(new CostCalculator);

    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'total_tokens' => 1000,
        'total_cost_microcents' => 6000000, // $6
    ]);

    $result = $guard->check($run);
    expect($result)->toContain('Cost budget exceeded');
});

test('BudgetGuard respects custom limits', function () {
    $guard = new BudgetGuard(new CostCalculator);

    $run = ExecutionRun::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'total_tokens' => 500,
        'total_cost_microcents' => 100,
    ]);

    // Under default but over custom limit
    $result = $guard->check($run, ['max_total_tokens' => 200]);
    expect($result)->toContain('Token budget exceeded');
});

test('BudgetGuard returns limits for display', function () {
    $limits = BudgetGuard::getLimits();

    expect($limits['max_total_tokens'])->toBe(100000);
    expect($limits['max_cost_microcents'])->toBe(5000000);
    expect($limits['max_iterations'])->toBe(25);
    expect($limits['max_cost_formatted'])->toBe('$5.0000');
});

// --- ToolGuard tests ---

test('ToolGuard allows all tools with no config', function () {
    $guard = new ToolGuard;
    $guard->configure([]);

    expect($guard->check('any_tool', []))->toBeNull();
});

test('ToolGuard blocks tools on blocklist', function () {
    $guard = new ToolGuard;
    $guard->configure(['tool_blocklist' => ['dangerous_tool']]);

    expect($guard->check('safe_tool', []))->toBeNull();
    expect($guard->check('dangerous_tool', []))->toContain('blocked by policy');
});

test('ToolGuard allows only allowlisted tools', function () {
    $guard = new ToolGuard;
    $guard->configure(['tool_allowlist' => ['read_file', 'list_dir']]);

    expect($guard->check('read_file', []))->toBeNull();
    expect($guard->check('list_dir', []))->toBeNull();
    expect($guard->check('delete_file', []))->toContain('not in the allowlist');
});

test('ToolGuard blocklist takes priority over allowlist', function () {
    $guard = new ToolGuard;
    $guard->configure([
        'tool_allowlist' => ['read_file', 'write_file'],
        'tool_blocklist' => ['write_file'],
    ]);

    expect($guard->check('read_file', []))->toBeNull();
    expect($guard->check('write_file', []))->toContain('blocked by policy');
});

test('ToolGuard detects dangerous input patterns', function () {
    $guard = new ToolGuard;
    $guard->configure([]);

    expect($guard->check('run_command', ['cmd' => 'ls']))->toBeNull();
    expect($guard->check('run_command', ['cmd' => '; rm -rf /']))->toContain('dangerous input pattern');
    expect($guard->check('run_command', ['cmd' => '| bash']))->toContain('dangerous input pattern');
});

test('ToolGuard filters tool definitions', function () {
    $guard = new ToolGuard;
    $guard->configure(['tool_blocklist' => ['dangerous']]);

    $tools = [
        ['name' => 'safe_tool', 'description' => 'Safe'],
        ['name' => 'dangerous', 'description' => 'Dangerous'],
        ['name' => 'another_safe', 'description' => 'Also safe'],
    ];

    $filtered = $guard->filterTools($tools);
    expect($filtered)->toHaveCount(2);
    expect(array_column($filtered, 'name'))->toBe(['safe_tool', 'another_safe']);
});

// --- OutputGuard tests ---

test('OutputGuard passes clean output', function () {
    $guard = new OutputGuard;

    $warnings = $guard->check('This is a clean response with no sensitive data.');
    expect($warnings)->toBeEmpty();
});

test('OutputGuard detects SSN patterns', function () {
    $guard = new OutputGuard;

    $warnings = $guard->check('The SSN is 123-45-6789.');
    expect($warnings)->not->toBeEmpty();
    expect($warnings[0])->toContain('PII');
});

test('OutputGuard detects credit card patterns', function () {
    $guard = new OutputGuard;

    $warnings = $guard->check('Card: 4111-1111-1111-1111');
    expect($warnings)->not->toBeEmpty();
    expect($warnings[0])->toContain('PII');
});

test('OutputGuard detects API key patterns', function () {
    $guard = new OutputGuard;

    $warnings = $guard->check('Key: sk-abcdefghijklmnopqrstuvwxyz1234');
    expect($warnings)->not->toBeEmpty();
    expect($warnings[0])->toContain('secrets');
});

test('OutputGuard detects private key patterns', function () {
    $guard = new OutputGuard;

    $warnings = $guard->check("-----BEGIN RSA PRIVATE KEY-----\ndata\n-----END RSA PRIVATE KEY-----");
    expect($warnings)->not->toBeEmpty();
    expect($warnings[0])->toContain('secrets');
});

test('OutputGuard redacts PII', function () {
    $guard = new OutputGuard;

    $input = 'Contact john@example.com or call 555-123-4567. SSN: 123-45-6789';
    $redacted = $guard->redact($input);

    expect($redacted)->toContain('[EMAIL_REDACTED]');
    expect($redacted)->toContain('[PHONE_REDACTED]');
    expect($redacted)->toContain('[SSN_REDACTED]');
    expect($redacted)->not->toContain('john@example.com');
    expect($redacted)->not->toContain('555-123-4567');
});

test('OutputGuard warns on excessive output length', function () {
    $guard = new OutputGuard;

    $longOutput = str_repeat('x', 600000);
    $warnings = $guard->check($longOutput);
    expect($warnings)->not->toBeEmpty();
    expect($warnings[0])->toContain('maximum length');
});
