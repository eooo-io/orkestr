<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\CapabilitySuggestionDismissal;
use App\Models\LibrarySkill;
use App\Models\ProjectMcpServer;
use App\Models\Skill;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bridges the "imagination gap" — users often don't realize what their agents
 * can do with the tools already available. This service scans for unused
 * capabilities and suggests concrete next actions.
 *
 * Three signal sources, ranked high→low:
 *   1. Unused MCP tools — agent is in a project that has an MCP server, but
 *      the agent isn't wired to use it. Highest-value because capability is
 *      already configured.
 *   2. Popular skills in peer agents — other agents in the same project use
 *      skills this agent doesn't have. Social proof signal.
 *   3. Library skills tagged for the agent's role — starter suggestions even
 *      before peer data accumulates.
 */
class CapabilityDiscoveryService
{
    public const MAX_SUGGESTIONS = 6;

    /**
     * @return array<int, array{key: string, type: string, title: string, rationale: string, example_prompt: string, action_url: string}>
     */
    public function suggestFor(Agent $agent, ?int $userId = null): array
    {
        $suggestions = [];

        $suggestions = array_merge($suggestions, $this->unusedMcpServers($agent));
        $suggestions = array_merge($suggestions, $this->popularPeerSkills($agent));
        $suggestions = array_merge($suggestions, $this->libraryStarterSkills($agent));

        if ($userId !== null) {
            $dismissed = CapabilitySuggestionDismissal::query()
                ->where('user_id', $userId)
                ->where('agent_id', $agent->id)
                ->active()
                ->pluck('suggestion_key')
                ->all();

            if (! empty($dismissed)) {
                $suggestions = array_values(array_filter(
                    $suggestions,
                    fn ($s) => ! in_array($s['key'], $dismissed, true),
                ));
            }
        }

        return array_slice($suggestions, 0, self::MAX_SUGGESTIONS);
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function unusedMcpServers(Agent $agent): array
    {
        $projectIds = DB::table('project_agent')
            ->where('agent_id', $agent->id)
            ->where('is_enabled', true)
            ->pluck('project_id');

        if ($projectIds->isEmpty()) return [];

        $projectServers = ProjectMcpServer::whereIn('project_id', $projectIds)->get();
        $attachedServerIds = DB::table('agent_mcp_server')
            ->where('agent_id', $agent->id)
            ->pluck('project_mcp_server_id');

        $suggestions = [];
        foreach ($projectServers as $server) {
            if ($attachedServerIds->contains($server->id)) continue;

            $suggestions[] = [
                'key' => "unused_mcp:{$server->id}",
                'type' => 'unused_tool',
                'title' => "Try the {$server->name} MCP server",
                'rationale' => "This server is configured in your project but this agent isn't using it yet.",
                'example_prompt' => "Using the {$server->name} tool, ",
                'action_url' => "/agents/{$agent->id}#mcp-servers",
            ];
        }

        return $suggestions;
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function popularPeerSkills(Agent $agent): array
    {
        $projectIds = DB::table('project_agent')
            ->where('agent_id', $agent->id)
            ->where('is_enabled', true)
            ->pluck('project_id');

        if ($projectIds->isEmpty()) return [];

        $ownedSkillIds = DB::table('agent_skill')
            ->where('agent_id', $agent->id)
            ->pluck('skill_id');

        $peerSkillUsage = DB::table('agent_skill')
            ->whereIn('project_id', $projectIds)
            ->where('agent_id', '!=', $agent->id)
            ->select('skill_id', DB::raw('COUNT(*) as peers'))
            ->groupBy('skill_id')
            ->orderByDesc('peers')
            ->limit(10)
            ->get();

        $suggestions = [];
        foreach ($peerSkillUsage as $row) {
            if ($ownedSkillIds->contains($row->skill_id)) continue;
            $skill = Skill::find($row->skill_id);
            if (! $skill) continue;

            $suggestions[] = [
                'key' => "peer_skill:{$skill->id}",
                'type' => 'popular_skill',
                'title' => "Add {$skill->name}",
                'rationale' => "{$row->peers} other agent" . ($row->peers > 1 ? 's' : '') . " in this project use this skill.",
                'example_prompt' => ($skill->summary ?? $skill->description ?? 'Try this skill on a real task.'),
                'action_url' => "/skills/{$skill->id}",
            ];
        }

        return $suggestions;
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function libraryStarterSkills(Agent $agent): array
    {
        if (! Schema::hasTable('library_skills')) return [];

        $role = $agent->role;
        if (! $role) return [];

        $candidates = LibrarySkill::query()
            ->whereJsonContains('tags', $role)
            ->orderBy('name')
            ->limit(5)
            ->get();

        $suggestions = [];
        foreach ($candidates as $lib) {
            $suggestions[] = [
                'key' => "library_skill:{$lib->id}",
                'type' => 'new_combo',
                'title' => "Library skill: {$lib->name}",
                'rationale' => "Other {$role} agents commonly start here.",
                'example_prompt' => $lib->description ?? '',
                'action_url' => "/library?skill={$lib->id}",
            ];
        }

        return $suggestions;
    }
}
