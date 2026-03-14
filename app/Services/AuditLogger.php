<?php

namespace App\Services;

use App\Models\AgentAuditLog;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * Log an audit event.
     */
    public static function log(
        string $event,
        string $description,
        array $metadata = [],
        ?int $agentId = null,
        ?int $projectId = null,
    ): AgentAuditLog {
        $user = Auth::user();
        $request = request();

        $organizationId = null;
        if ($user && method_exists($user, 'currentOrganization')) {
            $organizationId = $user->current_organization_id;
        }

        return AgentAuditLog::create([
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'agent_id' => $agentId,
            'user_id' => $user?->id,
            'event' => $event,
            'description' => $description,
            'metadata' => $metadata ?: null,
            'ip_address' => $request?->ip(),
        ]);
    }
}
