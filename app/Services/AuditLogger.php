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
        ?int $skillId = null,
        string $severity = 'info',
        ?string $requestId = null,
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
            'skill_id' => $skillId,
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'event' => $event,
            'severity' => $severity,
            'description' => $description,
            'metadata' => $metadata ?: null,
            'ip_address' => $request?->ip(),
            'request_id' => $requestId,
        ]);
    }
}
