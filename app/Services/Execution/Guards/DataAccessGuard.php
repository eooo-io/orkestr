<?php

namespace App\Services\Execution\Guards;

use App\Models\Agent;

class DataAccessGuard
{
    /**
     * Validate that a tool call is within the agent's data access scope.
     *
     * Returns null if access is allowed, or a string violation description if not.
     */
    public function check(Agent $agent, string $toolName, array $input = [], ?int $projectId = null): ?string
    {
        $scope = $agent->data_access_scope;

        // No scope defined means unrestricted access
        if (empty($scope)) {
            return null;
        }

        // Check project access
        $projectViolation = $this->checkProjectAccess($scope, $input, $projectId);
        if ($projectViolation) {
            return $projectViolation;
        }

        // Check file access permissions
        $fileViolation = $this->checkFileAccess($scope, $toolName, $input);
        if ($fileViolation) {
            return $fileViolation;
        }

        // Check external API access
        $apiViolation = $this->checkExternalApiAccess($scope, $toolName);
        if ($apiViolation) {
            return $apiViolation;
        }

        return null;
    }

    /**
     * Check if the tool is accessing an allowed project.
     */
    private function checkProjectAccess(array $scope, array $input, ?int $projectId): ?string
    {
        $allowedProjects = $scope['projects'] ?? null;

        // No restriction or wildcard
        if ($allowedProjects === null || $allowedProjects === '*') {
            return null;
        }

        if (! is_array($allowedProjects)) {
            return null;
        }

        // Check input for project_id references
        $targetProject = $input['project_id'] ?? $projectId;
        if ($targetProject && ! in_array((int) $targetProject, $allowedProjects, true)) {
            return "Agent is not allowed to access project {$targetProject}. Allowed projects: " . implode(', ', $allowedProjects);
        }

        return null;
    }

    /**
     * Check file access permissions.
     */
    private function checkFileAccess(array $scope, string $toolName, array $input): ?string
    {
        $filePerms = $scope['files'] ?? null;

        if ($filePerms === null) {
            return null;
        }

        if (! is_array($filePerms)) {
            return null;
        }

        $lowerTool = strtolower($toolName);

        // Determine what file operation the tool is performing
        if ($this->isWriteOperation($lowerTool) && ! in_array('write', $filePerms, true)) {
            return "Agent is not allowed to write files. Allowed file operations: " . implode(', ', $filePerms);
        }

        if ($this->isExecuteOperation($lowerTool) && ! in_array('execute', $filePerms, true)) {
            return "Agent is not allowed to execute files. Allowed file operations: " . implode(', ', $filePerms);
        }

        return null;
    }

    /**
     * Check if external API calls are allowed.
     */
    private function checkExternalApiAccess(array $scope, string $toolName): ?string
    {
        $allowExternal = $scope['external_apis'] ?? true;

        if ($allowExternal) {
            return null;
        }

        $lowerTool = strtolower($toolName);
        $externalPatterns = ['http', 'fetch', 'request', 'api', 'webhook', 'curl'];

        foreach ($externalPatterns as $pattern) {
            if (str_contains($lowerTool, $pattern)) {
                return "Agent is not allowed to make external API calls. Tool '{$toolName}' appears to be an external API tool.";
            }
        }

        return null;
    }

    private function isWriteOperation(string $toolName): bool
    {
        $writePatterns = ['write', 'create', 'update', 'modify', 'delete', 'remove', 'save', 'put', 'patch'];

        foreach ($writePatterns as $pattern) {
            if (str_contains($toolName, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function isExecuteOperation(string $toolName): bool
    {
        $execPatterns = ['execute', 'run', 'exec', 'shell', 'command', 'bash'];

        foreach ($execPatterns as $pattern) {
            if (str_contains($toolName, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
