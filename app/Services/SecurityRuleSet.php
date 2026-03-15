<?php

namespace App\Services;

class SecurityRuleSet
{
    /**
     * Scan content for security risks.
     * Returns structured warnings with risk levels.
     *
     * @return array<int, array{category: string, risk: string, pattern: string, message: string, line: int|null, passage: string|null}>
     */
    public function scan(string $content): array
    {
        $warnings = [];
        $lines = explode("\n", $content);

        $this->detectPromptInjection($lines, $warnings);
        $this->detectExfiltration($lines, $warnings);
        $this->detectCredentialHarvesting($lines, $warnings);
        $this->detectObfuscation($lines, $warnings);
        $this->detectUnsafeCodeInstructions($lines, $warnings);
        $this->detectSocialEngineering($lines, $warnings);

        return $warnings;
    }

    /**
     * Get the overall risk score (0-100) for a set of warnings.
     */
    public function riskScore(array $warnings): int
    {
        if (empty($warnings)) {
            return 0;
        }

        $riskWeights = ['low' => 10, 'medium' => 30, 'high' => 60, 'critical' => 90];
        $maxScore = 0;
        $totalScore = 0;

        foreach ($warnings as $w) {
            $weight = $riskWeights[$w['risk']] ?? 10;
            $maxScore = max($maxScore, $weight);
            $totalScore += $weight;
        }

        // Max of: highest single risk, or cumulative (capped at 100)
        return min(100, max($maxScore, (int) ($totalScore / 2)));
    }

    /**
     * Get the risk level string from a score.
     */
    public function riskLevel(int $score): string
    {
        return match (true) {
            $score <= 25 => 'low',
            $score <= 50 => 'medium',
            $score <= 75 => 'high',
            default => 'critical',
        };
    }

    private function detectPromptInjection(array $lines, array &$warnings): void
    {
        $patterns = [
            '/ignore\s+(all\s+)?previous\s+(instructions|rules|prompts)/i' => 'Prompt injection: attempts to override prior instructions.',
            '/disregard\s+(all\s+)?(previous|above|prior)/i' => 'Prompt injection: attempts to disregard prior context.',
            '/you\s+are\s+now\s+(a|an|the)\b/i' => 'Prompt injection: role-switching attack.',
            '/\bsystem\s*prompt\s*(override|injection|bypass)\b/i' => 'Prompt injection: explicit system prompt override attempt.',
            '/\bnew\s+instructions?\s*:/i' => 'Prompt injection: attempts to inject new instructions.',
            '/\b(jailbreak|DAN|do anything now)\b/i' => 'Prompt injection: known jailbreak pattern.',
            '/\bact\s+as\s+if\s+you\s+(have\s+no|don\'t\s+have)\s+restrictions\b/i' => 'Prompt injection: restriction bypass attempt.',
            '/\bpretend\s+(you\s+are|to\s+be)\s+(a|an)\s+unrestricted\b/i' => 'Prompt injection: restriction bypass via pretense.',
        ];

        foreach ($lines as $idx => $line) {
            foreach ($patterns as $pattern => $message) {
                if (preg_match($pattern, $line)) {
                    $warnings[] = [
                        'category' => 'prompt_injection',
                        'risk' => 'critical',
                        'pattern' => $pattern,
                        'message' => $message,
                        'line' => $idx + 1,
                        'passage' => trim($line),
                    ];
                    break;
                }
            }
        }
    }

    private function detectExfiltration(array $lines, array &$warnings): void
    {
        $patterns = [
            '/\b(curl|wget|fetch|http\.get|requests\.get|axios)\s*\(?["\']https?:\/\//i' => 'Data exfiltration: external HTTP request in prompt.',
            '/\bsend\s+(to|data\s+to|this\s+to)\s+https?:\/\//i' => 'Data exfiltration: instruction to send data externally.',
            '/\bpost\s+(to|data\s+to)\s+https?:\/\//i' => 'Data exfiltration: instruction to POST data externally.',
            '/\bupload\s+(to|data\s+to)\s+/i' => 'Data exfiltration: instruction to upload data.',
            '/\bforward\s+(all|the|this|any)\s+(data|output|response|content)\s+to\b/i' => 'Data exfiltration: instruction to forward data.',
            '/\binclude\s+(all\s+)?env(ironment)?\s+var(iable)?s?\s+in\b/i' => 'Data exfiltration: requests env vars in output.',
            '/\bread\s+.*\.(env|pem|key|crt|p12|pfx)\b/i' => 'Data exfiltration: attempts to read sensitive files.',
        ];

        foreach ($lines as $idx => $line) {
            foreach ($patterns as $pattern => $message) {
                if (preg_match($pattern, $line)) {
                    $warnings[] = [
                        'category' => 'exfiltration',
                        'risk' => 'high',
                        'pattern' => $pattern,
                        'message' => $message,
                        'line' => $idx + 1,
                        'passage' => trim($line),
                    ];
                    break;
                }
            }
        }
    }

    private function detectCredentialHarvesting(array $lines, array &$warnings): void
    {
        $patterns = [
            '/\b(output|print|return|include|show)\s+(the\s+)?(api[_\s-]?key|password|secret|token|credential)/i' => 'Credential harvesting: requests credentials in output.',
            '/\b(list|display|reveal)\s+(all\s+)?(env|environment|config)\s+(var|variable|setting)/i' => 'Credential harvesting: requests environment variables.',
            '/\bextract\s+.*\b(key|secret|token|password|credential)\b/i' => 'Credential harvesting: attempts to extract credentials.',
            '/\bwhat\s+(is|are)\s+(the\s+)?(api|secret|access)\s+(key|token)/i' => 'Credential harvesting: queries for credential values.',
        ];

        foreach ($lines as $idx => $line) {
            foreach ($patterns as $pattern => $message) {
                if (preg_match($pattern, $line)) {
                    $warnings[] = [
                        'category' => 'credential_harvesting',
                        'risk' => 'high',
                        'pattern' => $pattern,
                        'message' => $message,
                        'line' => $idx + 1,
                        'passage' => trim($line),
                    ];
                    break;
                }
            }
        }
    }

    private function detectObfuscation(array $lines, array &$warnings): void
    {
        foreach ($lines as $idx => $line) {
            // Base64-encoded content (long base64 strings are suspicious in prompts)
            if (preg_match('/[A-Za-z0-9+\/]{40,}={0,2}/', $line) && ! preg_match('/https?:\/\//', $line)) {
                // Verify it's actually base64
                $matches = [];
                if (preg_match('/([A-Za-z0-9+\/]{40,}={0,2})/', $line, $matches)) {
                    $decoded = base64_decode($matches[1], true);
                    if ($decoded !== false && mb_detect_encoding($decoded, 'UTF-8', true)) {
                        $warnings[] = [
                            'category' => 'obfuscation',
                            'risk' => 'medium',
                            'pattern' => 'base64_content',
                            'message' => 'Obfuscation: detected base64-encoded content in prompt.',
                            'line' => $idx + 1,
                            'passage' => substr(trim($line), 0, 100),
                        ];
                    }
                }
            }

            // Hex-encoded content
            if (preg_match('/\\\\x[0-9a-fA-F]{2}(\\\\x[0-9a-fA-F]{2}){4,}/', $line)) {
                $warnings[] = [
                    'category' => 'obfuscation',
                    'risk' => 'medium',
                    'pattern' => 'hex_encoded',
                    'message' => 'Obfuscation: detected hex-encoded content in prompt.',
                    'line' => $idx + 1,
                    'passage' => substr(trim($line), 0, 100),
                ];
            }

            // Unicode homoglyphs (Cyrillic characters mixed with Latin)
            if (preg_match('/[\x{0400}-\x{04FF}]/u', $line) && preg_match('/[a-zA-Z]/', $line)) {
                $warnings[] = [
                    'category' => 'obfuscation',
                    'risk' => 'high',
                    'pattern' => 'unicode_homoglyph',
                    'message' => 'Obfuscation: mixed Cyrillic and Latin characters detected (potential homoglyph attack).',
                    'line' => $idx + 1,
                    'passage' => substr(trim($line), 0, 100),
                ];
            }
        }
    }

    private function detectUnsafeCodeInstructions(array $lines, array &$warnings): void
    {
        $patterns = [
            '/\b(disable|skip|bypass|remove)\s+(csrf|xss|cors|authentication|authorization|validation)/i' => 'Unsafe code: instruction to disable security features.',
            '/\beval\s*\(/i' => 'Unsafe code: eval() usage (code injection risk).',
            '/\b--no-verify\b/' => 'Unsafe code: --no-verify flag bypasses safety checks.',
            '/\b(innerHTML|document\.write)\b/' => 'Unsafe code: DOM manipulation methods (XSS risk).',
            '/\bexec\s*\(\s*\$/' => 'Unsafe code: shell exec with variable input (injection risk).',
            '/\bsystem\s*\(\s*\$/' => 'Unsafe code: system() with variable input.',
            '/\b(md5|sha1)\s*\(.*password/i' => 'Unsafe code: weak hashing for passwords.',
            '/\bALLOW\s+ALL\b/i' => 'Unsafe code: overly permissive access rule.',
        ];

        foreach ($lines as $idx => $line) {
            foreach ($patterns as $pattern => $message) {
                if (preg_match($pattern, $line)) {
                    $warnings[] = [
                        'category' => 'unsafe_code',
                        'risk' => 'medium',
                        'pattern' => $pattern,
                        'message' => $message,
                        'line' => $idx + 1,
                        'passage' => trim($line),
                    ];
                    break;
                }
            }
        }
    }

    private function detectSocialEngineering(array $lines, array &$warnings): void
    {
        $patterns = [
            '/\b(don\'t|do\s+not)\s+tell\s+(the\s+)?(user|human|operator)\b/i' => 'Social engineering: instruction to hide information from user.',
            '/\bkeep\s+(this|it)\s+(secret|hidden|private)\s+from\s+(the\s+)?(user|human)\b/i' => 'Social engineering: attempts to hide behavior from user.',
            '/\bnever\s+(mention|reveal|disclose)\s+(that|this|your)\b/i' => 'Social engineering: instruction to conceal actions.',
            '/\b(pretend|act\s+as\s+if)\s+.*\bnot\b.*\b(do|did|doing)\b/i' => 'Social engineering: instruction to deceive about actions taken.',
        ];

        foreach ($lines as $idx => $line) {
            foreach ($patterns as $pattern => $message) {
                if (preg_match($pattern, $line)) {
                    $warnings[] = [
                        'category' => 'social_engineering',
                        'risk' => 'high',
                        'pattern' => $pattern,
                        'message' => $message,
                        'line' => $idx + 1,
                        'passage' => trim($line),
                    ];
                    break;
                }
            }
        }
    }
}
