<?php

namespace App\Services\Execution\Guards;

use App\Models\AppSetting;

class NetworkGuard
{
    private bool $airGapEnabled = false;
    private array $allowedHosts = [];
    private array $allowedCidrs = [];

    /**
     * Configure the guard from app settings.
     */
    public function configure(?array $config = null): self
    {
        if ($config === null) {
            $this->airGapEnabled = (bool) AppSetting::get('air_gap_mode', false);
            $allowedHostsRaw = AppSetting::get('air_gap_allowed_hosts', '');
            $this->allowedHosts = array_filter(array_map('trim', explode(',', $allowedHostsRaw)));
        } else {
            $this->airGapEnabled = $config['air_gap_mode'] ?? false;
            $this->allowedHosts = $config['allowed_hosts'] ?? [];
        }

        // Always allow localhost variants
        $this->allowedCidrs = [
            '127.0.0.0/8',
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
        ];

        return $this;
    }

    /**
     * Check if air-gap mode is currently enabled.
     */
    public function isAirGapEnabled(): bool
    {
        return $this->airGapEnabled;
    }

    /**
     * Validate a URL against air-gap restrictions.
     * Returns null if allowed, or a string violation message.
     */
    public function check(string $url): ?string
    {
        if (!$this->airGapEnabled) {
            return null;
        }

        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['host'])) {
            return "Invalid URL: cannot parse host from '{$url}'";
        }

        $host = $parsed['host'];

        // Allow localhost
        if ($this->isLocalHost($host)) {
            return null;
        }

        // Allow explicitly configured hosts
        if ($this->isAllowedHost($host)) {
            return null;
        }

        // Check if host resolves to a private IP
        if ($this->isPrivateIp($host)) {
            return null;
        }

        return "Air-gap mode: outbound connection to '{$host}' is blocked. Only local and explicitly allowed hosts are permitted.";
    }

    /**
     * Validate a list of URLs and return all violations.
     */
    public function checkMultiple(array $urls): array
    {
        $violations = [];
        foreach ($urls as $url) {
            $violation = $this->check($url);
            if ($violation) {
                $violations[] = ['url' => $url, 'violation' => $violation];
            }
        }
        return $violations;
    }

    /**
     * Validate all endpoints in a project for air-gap compliance.
     */
    public function validateProjectEndpoints(int $projectId): array
    {
        $violations = [];

        // Check MCP servers
        $mcpServers = \App\Models\ProjectMcpServer::where('project_id', $projectId)
            ->where('enabled', true)
            ->get();

        foreach ($mcpServers as $server) {
            if ($server->url) {
                $violation = $this->check($server->url);
                if ($violation) {
                    $violations[] = [
                        'type' => 'mcp_server',
                        'name' => $server->name,
                        'url' => $server->url,
                        'violation' => $violation,
                    ];
                }
            }
        }

        // Check A2A agents
        $a2aAgents = \App\Models\ProjectA2aAgent::where('project_id', $projectId)
            ->where('enabled', true)
            ->get();

        foreach ($a2aAgents as $agent) {
            if ($agent->url) {
                $violation = $this->check($agent->url);
                if ($violation) {
                    $violations[] = [
                        'type' => 'a2a_agent',
                        'name' => $agent->name,
                        'url' => $agent->url,
                        'violation' => $violation,
                    ];
                }
            }
        }

        return $violations;
    }

    /**
     * Check if a host is localhost.
     */
    private function isLocalHost(string $host): bool
    {
        $localHosts = ['localhost', '127.0.0.1', '::1', '0.0.0.0', 'host.docker.internal'];
        return in_array(strtolower($host), $localHosts, true);
    }

    /**
     * Check if a host is in the allowed hosts list.
     */
    private function isAllowedHost(string $host): bool
    {
        $host = strtolower($host);

        foreach ($this->allowedHosts as $allowed) {
            $allowed = strtolower(trim($allowed));

            // Exact match
            if ($host === $allowed) {
                return true;
            }

            // Wildcard subdomain match (*.example.com)
            if (str_starts_with($allowed, '*.')) {
                $domain = substr($allowed, 2);
                if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if a host resolves to a private IP address.
     */
    private function isPrivateIp(string $host): bool
    {
        // If it's already an IP, check directly
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isPrivateIpAddress($host);
        }

        // Resolve hostname to IP (with timeout protection)
        $ip = @gethostbyname($host);
        if ($ip === $host) {
            // Resolution failed — treat as non-local for safety
            return false;
        }

        return $this->isPrivateIpAddress($ip);
    }

    /**
     * Check if an IP address is in a private range.
     */
    private function isPrivateIpAddress(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
