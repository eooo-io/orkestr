<?php

namespace App\Services\Execution\Guards;

class InputSanitizationGuard
{
    /**
     * Validate and sanitize user input before it enters the agent execution loop.
     * Returns an array with 'sanitized' input and any 'warnings'.
     */
    public function process(string $input, array $config = []): array
    {
        $warnings = [];
        $sanitized = $input;

        // 1. Check for prompt injection attempts
        $injectionWarnings = $this->detectInjectionAttempts($input);
        if (! empty($injectionWarnings)) {
            $warnings = array_merge($warnings, $injectionWarnings);

            $blockOnInjection = $config['block_on_injection'] ?? false;
            if ($blockOnInjection) {
                return [
                    'sanitized' => null,
                    'blocked' => true,
                    'warnings' => $warnings,
                ];
            }
        }

        // 2. Strip dangerous patterns if configured
        if ($config['strip_dangerous_patterns'] ?? false) {
            $sanitized = $this->stripDangerousPatterns($sanitized);
        }

        // 3. Enforce max length
        $maxLength = $config['max_input_length'] ?? 50000;
        if (strlen($sanitized) > $maxLength) {
            $sanitized = substr($sanitized, 0, $maxLength);
            $warnings[] = [
                'type' => 'truncated',
                'message' => "Input truncated to {$maxLength} characters.",
            ];
        }

        // 4. Check for embedded instructions
        $embeddedWarnings = $this->detectEmbeddedInstructions($input);
        $warnings = array_merge($warnings, $embeddedWarnings);

        // 5. Detect potential data in input (URLs, code blocks)
        $dataWarnings = $this->detectSuspiciousData($input);
        $warnings = array_merge($warnings, $dataWarnings);

        return [
            'sanitized' => $sanitized,
            'blocked' => false,
            'warnings' => $warnings,
        ];
    }

    /**
     * Quick check: does this input look safe?
     */
    public function isSafe(string $input): bool
    {
        return empty($this->detectInjectionAttempts($input));
    }

    /**
     * Detect prompt injection attempts in user input.
     */
    private function detectInjectionAttempts(string $input): array
    {
        $warnings = [];

        $patterns = [
            '/ignore\s+(all\s+)?previous\s+(instructions|rules|prompts|context)/i' => 'Prompt injection: override previous instructions.',
            '/\bnew\s+system\s+prompt\s*:/i' => 'Prompt injection: new system prompt injection.',
            '/\[SYSTEM\]|\[INST\]|\<\|system\|\>/i' => 'Prompt injection: raw model control tokens.',
            '/\bDELIMITER\b.*\bEND\s+DELIMITER\b/is' => 'Prompt injection: delimiter-based escape.',
            '/```\s*system\s*\n/i' => 'Prompt injection: code-fenced system prompt.',
            '/\bhuman:\s*\n.*\bassistant:\s*\n/is' => 'Prompt injection: conversation role injection.',
            '/\b(you\s+are\s+now|from\s+now\s+on\s+you\s+are|your\s+new\s+role\s+is)\b/i' => 'Prompt injection: role reassignment.',
        ];

        foreach ($patterns as $pattern => $message) {
            if (preg_match($pattern, $input)) {
                $warnings[] = [
                    'type' => 'injection',
                    'message' => $message,
                    'severity' => 'high',
                ];
            }
        }

        return $warnings;
    }

    /**
     * Strip known dangerous patterns from input.
     */
    private function stripDangerousPatterns(string $input): string
    {
        // Remove control characters (except newline, tab)
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);

        // Remove zero-width characters that could hide instructions
        $input = preg_replace('/[\x{200B}-\x{200F}\x{2028}-\x{202F}\x{FEFF}]/u', '', $input);

        return $input;
    }

    /**
     * Detect embedded instruction patterns.
     */
    private function detectEmbeddedInstructions(string $input): array
    {
        $warnings = [];

        // Multiple "instruction-like" sentences
        $instructionPatterns = [
            '/\b(always|never|must|do not|you should)\b/i',
        ];

        $instructionCount = 0;
        foreach ($instructionPatterns as $pattern) {
            $instructionCount += preg_match_all($pattern, $input);
        }

        if ($instructionCount > 5) {
            $warnings[] = [
                'type' => 'embedded_instructions',
                'message' => "Input contains {$instructionCount} instruction-like directives — may be attempting to override agent behavior.",
                'severity' => 'medium',
            ];
        }

        return $warnings;
    }

    /**
     * Detect suspicious data patterns in input.
     */
    private function detectSuspiciousData(string $input): array
    {
        $warnings = [];

        // External URLs in user input
        $urlCount = preg_match_all('/https?:\/\/[^\s]+/i', $input);
        if ($urlCount > 3) {
            $warnings[] = [
                'type' => 'suspicious_urls',
                'message' => "Input contains {$urlCount} URLs — review for potential exfiltration targets.",
                'severity' => 'low',
            ];
        }

        // Large base64 blobs
        if (preg_match('/[A-Za-z0-9+\/]{100,}={0,2}/', $input)) {
            $warnings[] = [
                'type' => 'encoded_content',
                'message' => 'Input contains large base64-encoded content.',
                'severity' => 'medium',
            ];
        }

        return $warnings;
    }
}
