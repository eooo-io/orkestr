<?php

namespace App\Services\Execution;

use App\Models\Agent;
use App\Models\AgentConversation;
use App\Models\AgentMemory;
use App\Models\Project;

class AgentMemoryService
{
    /**
     * Store a memory for an agent.
     */
    public function store(Agent $agent, Project $project, string $type, mixed $content, string $key = null, array $metadata = [], ?\DateTimeInterface $expiresAt = null): AgentMemory
    {
        return AgentMemory::create([
            'agent_id' => $agent->id,
            'project_id' => $project->id,
            'type' => $type,
            'key' => $key,
            'content' => is_array($content) ? $content : ['value' => $content],
            'metadata' => $metadata ?: null,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Retrieve memories for an agent, filtered by type and optionally key.
     *
     * @return \Illuminate\Support\Collection<AgentMemory>
     */
    public function retrieve(Agent $agent, Project $project, string $type = null, string $key = null, int $limit = 50): \Illuminate\Support\Collection
    {
        $query = AgentMemory::where('agent_id', $agent->id)
            ->where('project_id', $project->id)
            ->active();

        if ($type) {
            $query->ofType($type);
        }

        if ($key) {
            $query->where('key', $key);
        }

        return $query->orderByDesc('created_at')->limit($limit)->get();
    }

    /**
     * Search memories by content (basic JSON search).
     */
    public function search(Agent $agent, Project $project, string $query, int $limit = 20): \Illuminate\Support\Collection
    {
        return AgentMemory::where('agent_id', $agent->id)
            ->where('project_id', $project->id)
            ->active()
            ->where('content', 'like', "%{$query}%")
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Delete a specific memory.
     */
    public function forget(int $memoryId): bool
    {
        return AgentMemory::where('id', $memoryId)->delete() > 0;
    }

    /**
     * Clear all memories of a specific type for an agent.
     */
    public function clearType(Agent $agent, Project $project, string $type): int
    {
        return AgentMemory::where('agent_id', $agent->id)
            ->where('project_id', $project->id)
            ->where('type', $type)
            ->delete();
    }

    /**
     * Save a conversation history from an execution run.
     */
    public function saveConversation(Agent $agent, Project $project, array $messages, int $executionRunId = null, string $summary = null): AgentConversation
    {
        $tokenCount = (int) ceil(strlen(json_encode($messages)) / 4);

        return AgentConversation::create([
            'agent_id' => $agent->id,
            'project_id' => $project->id,
            'execution_run_id' => $executionRunId,
            'messages' => $messages,
            'summary' => $summary,
            'token_count' => $tokenCount,
        ]);
    }

    /**
     * Get recent conversations for an agent.
     */
    public function getConversations(Agent $agent, Project $project, int $limit = 10): \Illuminate\Support\Collection
    {
        return AgentConversation::where('agent_id', $agent->id)
            ->where('project_id', $project->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Assemble relevant memories into a context block for the agent's perceive phase.
     *
     * @return array{memories: array, token_estimate: int}
     */
    public function assembleContext(Agent $agent, Project $project, string $contextStrategy = 'recent', int $tokenBudget = 4000): array
    {
        $memories = [];
        $tokenEstimate = 0;

        match ($contextStrategy) {
            'recent' => $this->assembleRecent($agent, $project, $memories, $tokenEstimate, $tokenBudget),
            'all' => $this->assembleAll($agent, $project, $memories, $tokenEstimate, $tokenBudget),
            'long_term_only' => $this->assembleLongTerm($agent, $project, $memories, $tokenEstimate, $tokenBudget),
            default => $this->assembleRecent($agent, $project, $memories, $tokenEstimate, $tokenBudget),
        };

        return [
            'memories' => $memories,
            'token_estimate' => $tokenEstimate,
        ];
    }

    private function assembleRecent(Agent $agent, Project $project, array &$memories, int &$tokenEstimate, int $tokenBudget): void
    {
        // Recent long-term memories
        $longTerm = $this->retrieve($agent, $project, 'long_term', limit: 20);
        foreach ($longTerm as $memory) {
            $text = json_encode($memory->content);
            $tokens = (int) ceil(strlen($text) / 4);

            if ($tokenEstimate + $tokens > $tokenBudget) {
                break;
            }

            $memories[] = ['type' => 'long_term', 'key' => $memory->key, 'content' => $memory->content];
            $tokenEstimate += $tokens;
        }

        // Last conversation summary
        $lastConv = $this->getConversations($agent, $project, 1)->first();
        if ($lastConv && $lastConv->summary) {
            $tokens = (int) ceil(strlen($lastConv->summary) / 4);
            if ($tokenEstimate + $tokens <= $tokenBudget) {
                $memories[] = ['type' => 'conversation_summary', 'content' => $lastConv->summary];
                $tokenEstimate += $tokens;
            }
        }
    }

    private function assembleAll(Agent $agent, Project $project, array &$memories, int &$tokenEstimate, int $tokenBudget): void
    {
        $allMemories = $this->retrieve($agent, $project, limit: 100);
        foreach ($allMemories as $memory) {
            $text = json_encode($memory->content);
            $tokens = (int) ceil(strlen($text) / 4);

            if ($tokenEstimate + $tokens > $tokenBudget) {
                break;
            }

            $memories[] = ['type' => $memory->type, 'key' => $memory->key, 'content' => $memory->content];
            $tokenEstimate += $tokens;
        }
    }

    private function assembleLongTerm(Agent $agent, Project $project, array &$memories, int &$tokenEstimate, int $tokenBudget): void
    {
        $longTerm = $this->retrieve($agent, $project, 'long_term', limit: 50);
        foreach ($longTerm as $memory) {
            $text = json_encode($memory->content);
            $tokens = (int) ceil(strlen($text) / 4);

            if ($tokenEstimate + $tokens > $tokenBudget) {
                break;
            }

            $memories[] = ['type' => 'long_term', 'key' => $memory->key, 'content' => $memory->content];
            $tokenEstimate += $tokens;
        }
    }

    /**
     * Clean up expired memories.
     */
    public function pruneExpired(): int
    {
        return AgentMemory::where('expires_at', '<', now())->delete();
    }
}
