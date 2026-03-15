<?php

namespace App\Services\Execution\Guards;

use App\Models\ProjectA2aAgent;
use App\Models\ProjectMcpServer;

class EndpointGuard
{
    /**
     * Dangerous command patterns for stdio MCP servers.
     */
    private const DANGEROUS_COMMANDS = [
        'rm',
        'del',
        'format',
        'mkfs',
        'dd',
        'fdisk',
        'shutdown',
        'reboot',
        'halt',
        'init',
        'kill',
        'killall',
        'pkill',
    ];

    /**
     * Dangerous piped-command patterns (regex).
     */
    private const DANGEROUS_PIPE_PATTERNS = [
        '/\|\s*(bash|sh|zsh|cmd|powershell)/i',
        '/curl\s.*\|\s*(bash|sh)/i',
        '/wget\s.*\|\s*(bash|sh)/i',
    ];

    /**
     * Dangerous argument patterns (regex).
     */
    private const DANGEROUS_ARG_PATTERNS = [
        '/;\s*(rm|del|format|mkfs|dd)\s/i',
        '/`[^`]*`/',
        '/\$\([^)]+\)/',
        '/\beval\b/i',
        '/\bexec\b/i',
        '/>\s*\/dev\//i',
        '/--no-preserve-root/i',
    ];

    /**
     * Private/reserved IP ranges (CIDR patterns for regex).
     */
    private const PRIVATE_IP_PATTERNS = [
        '/^127\./',
        '/^10\./',
        '/^172\.(1[6-9]|2\d|3[01])\./',
        '/^192\.168\./',
        '/^0\.0\.0\.0$/',
        '/^::1$/',
        '/^fc00:/i',
        '/^fd[0-9a-f]{2}:/i',
        '/^fe80:/i',
    ];

    /**
     * Validate an MCP server endpoint.
     *
     * @return array<string> List of violation messages (empty = valid)
     */
    public function validateMcpServer(ProjectMcpServer $server, ?array $orgAllowlist = null): array
    {
        $violations = [];

        if ($server->transport === 'stdio') {
            $command = $server->command ?? '';
            $args = $server->args ?? [];
            $violations = array_merge($violations, $this->checkCommandSafety($command, $args));
        }

        if (in_array($server->transport, ['sse', 'streamable-http'], true) && $server->url) {
            $violations = array_merge($violations, $this->validateUrl($server->url));

            if ($orgAllowlist !== null && ! $this->isUrlAllowed($server->url, $orgAllowlist)) {
                $violations[] = "URL '{$server->url}' is not in the organization allowlist";
            }
        }

        return $violations;
    }

    /**
     * Validate an A2A agent endpoint.
     *
     * @return array<string> List of violation messages (empty = valid)
     */
    public function validateA2aAgent(ProjectA2aAgent $agent, ?array $orgAllowlist = null): array
    {
        $violations = [];

        if (! $agent->url) {
            $violations[] = 'A2A agent URL is required';

            return $violations;
        }

        $violations = array_merge($violations, $this->validateUrl($agent->url));

        if ($orgAllowlist !== null && ! $this->isUrlAllowed($agent->url, $orgAllowlist)) {
            $violations[] = "URL '{$agent->url}' is not in the organization allowlist";
        }

        return $violations;
    }

    /**
     * Check if a URL is allowed against a domain/pattern allowlist.
     *
     * Allowlist entries can be:
     * - Exact domains: "example.com"
     * - Wildcard subdomains: "*.example.com"
     * - Full URLs: "https://api.example.com/v1"
     */
    public function isUrlAllowed(string $url, array $allowlist): bool
    {
        if (empty($allowlist)) {
            return true;
        }

        $parsed = parse_url($url);
        if (! $parsed || ! isset($parsed['host'])) {
            return false;
        }

        $host = strtolower($parsed['host']);

        foreach ($allowlist as $entry) {
            $entry = strtolower(trim($entry));

            // Full URL prefix match
            if (str_starts_with($entry, 'http://') || str_starts_with($entry, 'https://')) {
                if (str_starts_with(strtolower($url), $entry)) {
                    return true;
                }

                continue;
            }

            // Wildcard subdomain match: *.example.com
            if (str_starts_with($entry, '*.')) {
                $baseDomain = substr($entry, 2);
                if ($host === $baseDomain || str_ends_with($host, '.'.$baseDomain)) {
                    return true;
                }

                continue;
            }

            // Exact domain match
            if ($host === $entry) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a URL points to localhost or private IP ranges.
     */
    public function isLocalUrl(string $url): bool
    {
        $parsed = parse_url($url);
        if (! $parsed || ! isset($parsed['host'])) {
            return false;
        }

        $host = strtolower($parsed['host']);

        // Localhost variants
        if (in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0', '[::1]'], true)) {
            return true;
        }

        // .local domains
        if (str_ends_with($host, '.local') || str_ends_with($host, '.localhost')) {
            return true;
        }

        // Private IP ranges
        foreach (self::PRIVATE_IP_PATTERNS as $pattern) {
            if (preg_match($pattern, $host)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Structural URL validation.
     *
     * @return array<string> List of violation messages
     */
    public function validateUrl(string $url): array
    {
        $violations = [];

        // Basic URL structure
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            $violations[] = "Invalid URL format: '{$url}'";

            return $violations;
        }

        $parsed = parse_url($url);
        if (! $parsed || ! isset($parsed['scheme'], $parsed['host'])) {
            $violations[] = "URL must have a valid scheme and host: '{$url}'";

            return $violations;
        }

        // HTTPS enforcement in production
        $requireHttps = config('app.env') === 'production' && config('agentis.endpoints.require_https', true);
        if ($requireHttps && $parsed['scheme'] !== 'https') {
            if (! $this->isLocalUrl($url)) {
                $violations[] = "HTTPS is required in production for non-local URLs: '{$url}'";
            }
        }

        // Reject raw IP addresses unless explicitly allowed
        $allowRawIps = config('agentis.endpoints.allow_raw_ips', false);
        if (! $allowRawIps && $this->isRawIp($parsed['host'])) {
            if (! $this->isLocalUrl($url)) {
                $violations[] = "Raw IP addresses are not allowed — use a domain name: '{$url}'";
            }
        }

        // Reject dangerous schemes
        $allowedSchemes = ['http', 'https'];
        if (! in_array($parsed['scheme'], $allowedSchemes, true)) {
            $violations[] = "URL scheme '{$parsed['scheme']}' is not allowed — use HTTP or HTTPS";
        }

        return $violations;
    }

    /**
     * Validate command safety for stdio MCP servers.
     *
     * @return array<string> List of violation messages
     */
    public function checkCommandSafety(string $command, array $args = []): array
    {
        $violations = [];

        if (empty($command)) {
            $violations[] = 'MCP server command cannot be empty';

            return $violations;
        }

        // Extract the base command (ignore path)
        $baseCommand = strtolower(basename($command));

        // Check against dangerous commands
        foreach (self::DANGEROUS_COMMANDS as $dangerous) {
            if ($baseCommand === $dangerous || $baseCommand === $dangerous.'.exe') {
                $violations[] = "Dangerous command '{$command}' is not allowed for MCP servers";

                break;
            }
        }

        // Check for dangerous patterns in the full command + args string
        $fullCommand = $command.' '.implode(' ', $args);

        foreach (self::DANGEROUS_PIPE_PATTERNS as $pattern) {
            if (preg_match($pattern, $fullCommand)) {
                $violations[] = "Dangerous pipe pattern detected in command: '{$fullCommand}'";

                break;
            }
        }

        foreach (self::DANGEROUS_ARG_PATTERNS as $pattern) {
            foreach ($args as $arg) {
                if (preg_match($pattern, $arg)) {
                    $violations[] = "Dangerous argument pattern detected: '{$arg}'";

                    break 2;
                }
            }
        }

        return $violations;
    }

    /**
     * Check if a host string is a raw IP address.
     */
    private function isRawIp(string $host): bool
    {
        // Remove brackets for IPv6
        $host = trim($host, '[]');

        return filter_var($host, FILTER_VALIDATE_IP) !== false;
    }
}
