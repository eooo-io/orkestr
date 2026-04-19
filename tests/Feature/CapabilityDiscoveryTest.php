<?php

use App\Models\Agent;
use App\Models\CapabilitySuggestionDismissal;
use App\Models\Project;
use App\Models\ProjectAgent;
use App\Models\ProjectMcpServer;
use App\Models\Skill;
use App\Models\User;
use App\Services\CapabilityDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(CapabilityDiscoveryService::class);

    $this->user = User::firstOrCreate(
        ['email' => 'cap@test.com'],
        ['name' => 'Cap', 'password' => bcrypt('password')],
    );

    $this->project = Project::create([
        'name' => 'Cap Project',
        'path' => '/tmp/cap',
        'providers' => ['claude'],
        'owner_id' => $this->user->id,
    ]);

    $this->agent = Agent::create([
        'name' => 'Capper',
        'role' => 'worker',
        'base_instructions' => 'hi',
        'model' => 'claude-sonnet-4-6',
        'objective_template' => 'x',
        'success_criteria' => [],
        'max_iterations' => 5,
        'planning_mode' => 'plan_then_act',
        'loop_condition' => 'goal_met',
        'owner_user_id' => $this->user->id,
    ]);

    ProjectAgent::create([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'is_enabled' => true,
    ]);
});

it('suggests unused MCP servers configured in the project', function () {
    $server = ProjectMcpServer::create([
        'project_id' => $this->project->id,
        'name' => 'GitHub',
        'transport' => 'stdio',
        'command' => 'github-mcp',
    ]);

    $suggestions = $this->service->suggestFor($this->agent);

    $keys = array_column($suggestions, 'key');
    expect($keys)->toContain("unused_mcp:{$server->id}");

    $matching = collect($suggestions)->firstWhere('key', "unused_mcp:{$server->id}");
    expect($matching['type'])->toBe('unused_tool')
        ->and($matching['title'])->toContain('GitHub');
});

it('does not suggest an MCP server that is already attached to the agent', function () {
    $server = ProjectMcpServer::create([
        'project_id' => $this->project->id,
        'name' => 'GitHub',
        'transport' => 'stdio',
        'command' => 'github-mcp',
    ]);

    DB::table('agent_mcp_server')->insert([
        'project_id' => $this->project->id,
        'agent_id' => $this->agent->id,
        'project_mcp_server_id' => $server->id,
    ]);

    $suggestions = $this->service->suggestFor($this->agent);

    expect(array_column($suggestions, 'key'))->not->toContain("unused_mcp:{$server->id}");
});

it('suggests popular peer skills with the right rationale', function () {
    $peer = Agent::create([
        'name' => 'Peer',
        'role' => 'worker',
        'base_instructions' => 'hi',
        'model' => 'claude-sonnet-4-6',
        'objective_template' => 'x',
        'success_criteria' => [],
        'max_iterations' => 5,
        'planning_mode' => 'plan_then_act',
        'loop_condition' => 'goal_met',
    ]);
    ProjectAgent::create([
        'project_id' => $this->project->id,
        'agent_id' => $peer->id,
        'is_enabled' => true,
    ]);

    $skill = Skill::create([
        'project_id' => $this->project->id,
        'name' => 'Shared Helper',
        'slug' => 'shared-helper',
        'summary' => 'Helpful things',
        'body' => '...',
    ]);

    DB::table('agent_skill')->insert([
        'project_id' => $this->project->id,
        'agent_id' => $peer->id,
        'skill_id' => $skill->id,
    ]);

    $suggestions = $this->service->suggestFor($this->agent);
    $match = collect($suggestions)->firstWhere('key', "peer_skill:{$skill->id}");

    expect($match)->not->toBeNull()
        ->and($match['type'])->toBe('popular_skill')
        ->and($match['rationale'])->toContain('1 other agent');
});

it('respects dismissals scoped to user + agent + suggestion_key', function () {
    $server = ProjectMcpServer::create([
        'project_id' => $this->project->id,
        'name' => 'GitHub',
        'transport' => 'stdio',
        'command' => 'github-mcp',
    ]);

    CapabilitySuggestionDismissal::create([
        'user_id' => $this->user->id,
        'agent_id' => $this->agent->id,
        'suggestion_key' => "unused_mcp:{$server->id}",
        'expires_at' => now()->addDays(30),
    ]);

    $suggestions = $this->service->suggestFor($this->agent, $this->user->id);

    expect(array_column($suggestions, 'key'))->not->toContain("unused_mcp:{$server->id}");
});

it('does not apply dismissals when userId is null (no auth context)', function () {
    $server = ProjectMcpServer::create([
        'project_id' => $this->project->id,
        'name' => 'GitHub',
        'transport' => 'stdio',
        'command' => 'github-mcp',
    ]);

    CapabilitySuggestionDismissal::create([
        'user_id' => $this->user->id,
        'agent_id' => $this->agent->id,
        'suggestion_key' => "unused_mcp:{$server->id}",
        'expires_at' => now()->addDays(30),
    ]);

    $suggestions = $this->service->suggestFor($this->agent, null);

    expect(array_column($suggestions, 'key'))->toContain("unused_mcp:{$server->id}");
});

it('dismiss endpoint persists dismissal with 30-day default expiry', function () {
    $response = $this->actingAs($this->user)->postJson(
        "/api/agents/{$this->agent->id}/capability-suggestions/dismiss",
        ['suggestion_key' => 'peer_skill:42'],
    );

    $response->assertOk();

    $row = CapabilitySuggestionDismissal::where('user_id', $this->user->id)->first();
    expect($row)->not->toBeNull()
        ->and($row->suggestion_key)->toBe('peer_skill:42')
        ->and($row->expires_at)->not->toBeNull();
});
