<?php

namespace App\Services\Execution\Guards;

use App\Models\Agent;

class ApprovalGuard
{
    /**
     * Tools considered "sensitive" by default — they modify, delete, or make external calls.
     */
    private const DEFAULT_SENSITIVE_PATTERNS = [
        'write', 'delete', 'remove', 'update', 'create', 'modify', 'execute',
        'deploy', 'publish', 'send', 'post', 'push', 'install', 'uninstall',
    ];

    /**
     * Check if a tool call requires human approval based on the agent's autonomy level.
     *
     * Returns true if approval is required, false if the tool can proceed.
     */
    public function requiresApproval(Agent $agent, string $toolName): bool
    {
        $autonomyLevel = $agent->autonomy_level ?? 'semi_autonomous';

        return match ($autonomyLevel) {
            'supervised' => true,
            'autonomous' => false,
            'semi_autonomous' => $this->isSensitiveTool($agent, $toolName),
            default => true, // fail-safe: require approval for unknown levels
        };
    }

    /**
     * Determine if a tool is considered "sensitive" for semi-autonomous mode.
     */
    private function isSensitiveTool(Agent $agent, string $toolName): bool
    {
        // Check if the agent defines its own sensitive_tools list in data_access_scope
        $scope = $agent->data_access_scope ?? [];
        $sensitiveTools = $scope['sensitive_tools'] ?? null;

        if (is_array($sensitiveTools)) {
            return in_array($toolName, $sensitiveTools, true);
        }

        // Fall back to pattern matching on tool name
        $lowerName = strtolower($toolName);
        foreach (self::DEFAULT_SENSITIVE_PATTERNS as $pattern) {
            if (str_contains($lowerName, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the autonomy level description for API responses.
     */
    public static function describeLevel(string $level): string
    {
        return match ($level) {
            'supervised' => 'All tool calls require human approval before execution',
            'semi_autonomous' => 'Only sensitive tool calls require approval',
            'autonomous' => 'All tool calls execute automatically',
            default => 'Unknown autonomy level',
        };
    }
}
