<?php

namespace App\Services\Execution\Guards;

class ToolGuard
{
    private array $allowlist = [];

    private array $blocklist = [];

    /**
     * Configure from agent/config settings.
     */
    public function configure(array $config = []): self
    {
        $this->allowlist = $config['tool_allowlist'] ?? [];
        $this->blocklist = $config['tool_blocklist'] ?? [];

        return $this;
    }

    /**
     * Check if a tool call is allowed.
     * Returns null if allowed, or a string error message if blocked.
     */
    public function check(string $toolName, array $input = []): ?string
    {
        // Blocklist takes priority
        if (! empty($this->blocklist) && in_array($toolName, $this->blocklist, true)) {
            return "Tool '{$toolName}' is blocked by policy";
        }

        // If allowlist is set, only those tools are permitted
        if (! empty($this->allowlist) && ! in_array($toolName, $this->allowlist, true)) {
            return "Tool '{$toolName}' is not in the allowlist";
        }

        // Check for dangerous patterns in input
        $dangerousPatterns = $this->detectDangerousInput($toolName, $input);
        if ($dangerousPatterns) {
            return $dangerousPatterns;
        }

        return null;
    }

    /**
     * Filter a list of tool definitions to only include allowed tools.
     */
    public function filterTools(array $toolDefinitions): array
    {
        if (empty($this->allowlist) && empty($this->blocklist)) {
            return $toolDefinitions;
        }

        return array_values(array_filter($toolDefinitions, function ($tool) {
            $name = $tool['name'] ?? '';
            if (! empty($this->blocklist) && in_array($name, $this->blocklist, true)) {
                return false;
            }
            if (! empty($this->allowlist) && ! in_array($name, $this->allowlist, true)) {
                return false;
            }

            return true;
        }));
    }

    /**
     * Detect potentially dangerous patterns in tool input.
     */
    private function detectDangerousInput(string $toolName, array $input): ?string
    {
        $inputStr = json_encode($input);

        // Block shell injection patterns in common tool inputs
        $shellPatterns = [
            '/;\s*(rm|del|format|mkfs|dd)\s/i',
            '/\|\s*(bash|sh|cmd|powershell)/i',
            '/`[^`]*`/',  // backtick execution
            '/\$\([^)]+\)/', // command substitution
        ];

        foreach ($shellPatterns as $pattern) {
            if (preg_match($pattern, $inputStr)) {
                return "Potentially dangerous input pattern detected for tool '{$toolName}'";
            }
        }

        return null;
    }
}
