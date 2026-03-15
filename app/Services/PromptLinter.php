<?php

namespace App\Services;

class PromptLinter
{
    /**
     * Lint a skill body and return an array of issues.
     *
     * @return array<int, array{severity: string, rule: string, message: string, suggestion: string, line: int|null}>
     */
    public function lint(string $body): array
    {
        if (empty(trim($body))) {
            return [[
                'severity' => 'warning',
                'rule' => 'empty_body',
                'message' => 'Skill body is empty.',
                'suggestion' => 'Add instructions describing what the AI should do.',
                'line' => null,
            ]];
        }

        $issues = [];
        $lines = explode("\n", $body);

        $this->checkVagueInstructions($lines, $issues);
        $this->checkWeakConstraints($lines, $issues);
        $this->checkConflictingDirectives($body, $issues);
        $this->checkMissingOutputFormat($body, $issues);
        $this->checkExcessiveLength($body, $issues);
        $this->checkRoleConfusion($body, $issues);
        $this->checkMissingExamples($body, $issues);
        $this->checkRedundancy($lines, $issues);
        $this->checkSecrets($lines, $issues);

        return $issues;
    }

    protected function checkVagueInstructions(array $lines, array &$issues): void
    {
        $vaguePatterns = [
            '/\bdo your best\b/i' => 'Replace "do your best" with specific success criteria.',
            '/\btry to\b/i' => 'Replace "try to" with a direct instruction.',
            '/\bif possible\b/i' => 'Replace "if possible" with explicit conditions for when to act.',
            '/\bwhen appropriate\b/i' => 'Define what "appropriate" means with specific criteria.',
            '/\bas needed\b/i' => 'Specify the conditions that trigger this action.',
            '/\bfeel free to\b/i' => 'Replace "feel free to" with a clear directive.',
            '/\bmaybe\b/i' => 'Replace "maybe" with a definitive instruction.',
            '/\bsomehow\b/i' => 'Replace "somehow" with a specific approach.',
        ];

        foreach ($lines as $idx => $line) {
            foreach ($vaguePatterns as $pattern => $suggestion) {
                if (preg_match($pattern, $line)) {
                    $issues[] = [
                        'severity' => 'warning',
                        'rule' => 'vague_instruction',
                        'message' => 'Vague instruction detected.',
                        'suggestion' => $suggestion,
                        'line' => $idx + 1,
                    ];
                    break; // One issue per line for this rule
                }
            }
        }
    }

    protected function checkWeakConstraints(array $lines, array &$issues): void
    {
        foreach ($lines as $idx => $line) {
            // "should" used for critical requirements
            if (preg_match('/\byou should\b/i', $line) && ! preg_match('/\byou should not\b/i', $line)) {
                $issues[] = [
                    'severity' => 'suggestion',
                    'rule' => 'weak_constraint',
                    'message' => '"You should" is weaker than "You must".',
                    'suggestion' => 'Use "You must" or "Always" for critical requirements.',
                    'line' => $idx + 1,
                ];
            }
        }
    }

    protected function checkConflictingDirectives(string $body, array &$issues): void
    {
        $conflicts = [
            ['/\b(be concise|keep it brief|be short)\b/i', '/\b(explain in detail|be thorough|provide detailed)\b/i', 'Conflicting directives: asks to be both concise and detailed.'],
            ['/\b(never|do not|don\'t)\s+\w+\s+code\b/i', '/\b(always|must)\s+(include|provide|write)\s+code\b/i', 'Conflicting directives: contradictory code output rules.'],
            ['/\brespond only in (json|xml|yaml)\b/i', '/\buse (markdown|plain text|html)\b/i', 'Conflicting directives: contradictory output format requirements.'],
        ];

        foreach ($conflicts as [$pattern1, $pattern2, $message]) {
            if (preg_match($pattern1, $body) && preg_match($pattern2, $body)) {
                $issues[] = [
                    'severity' => 'warning',
                    'rule' => 'conflicting_directives',
                    'message' => $message,
                    'suggestion' => 'Resolve the contradiction by choosing one approach or adding conditions.',
                    'line' => null,
                ];
            }
        }
    }

    protected function checkMissingOutputFormat(string $body, array &$issues): void
    {
        $hasFormatSpec = preg_match('/\b(output format|respond with|respond in|return.*json|return.*yaml|format.*as|use.*markdown|structured as|example output|expected output)\b/i', $body);

        $isGenerationSkill = preg_match('/\b(generate|create|write|produce|build|compose|draft)\b/i', $body);

        if ($isGenerationSkill && ! $hasFormatSpec) {
            $issues[] = [
                'severity' => 'suggestion',
                'rule' => 'missing_output_format',
                'message' => 'No output format specified for a generation skill.',
                'suggestion' => 'Add an output format section (e.g., "Format your response as...")',
                'line' => null,
            ];
        }
    }

    protected function checkExcessiveLength(string $body, array &$issues): void
    {
        $tokens = (int) ceil(mb_strlen($body) / 4);

        if ($tokens > 5000) {
            $issues[] = [
                'severity' => 'warning',
                'rule' => 'excessive_length',
                'message' => "Skill is ~{$tokens} tokens. Very long prompts can dilute focus.",
                'suggestion' => 'Consider splitting into smaller skills and using includes.',
                'line' => null,
            ];
        } elseif ($tokens > 2000) {
            $issues[] = [
                'severity' => 'suggestion',
                'rule' => 'long_skill',
                'message' => "Skill is ~{$tokens} tokens. Consider if all instructions are necessary.",
                'suggestion' => 'Review for redundant or low-value instructions.',
                'line' => null,
            ];
        }
    }

    protected function checkRoleConfusion(string $body, array &$issues): void
    {
        $roleCount = preg_match_all('/\byou are (a|an|the)\b/i', $body);

        if ($roleCount > 2) {
            $issues[] = [
                'severity' => 'warning',
                'rule' => 'role_confusion',
                'message' => "Skill defines {$roleCount} different roles. This can confuse the model.",
                'suggestion' => 'Focus on a single role per skill, or use structured sections.',
                'line' => null,
            ];
        }
    }

    protected function checkMissingExamples(string $body, array &$issues): void
    {
        $isComplex = preg_match('/\b(complex|nuanced|edge case|ambiguous|multi-step)\b/i', $body);
        $hasExamples = preg_match('/\b(example|for instance|e\.g\.|such as|like this|sample)\b/i', $body);

        if ($isComplex && ! $hasExamples) {
            $issues[] = [
                'severity' => 'suggestion',
                'rule' => 'missing_examples',
                'message' => 'Complex skill without examples.',
                'suggestion' => 'Add few-shot examples to clarify expected behavior for edge cases.',
                'line' => null,
            ];
        }
    }

    protected function checkRedundancy(array $lines, array &$issues): void
    {
        $normalized = [];
        foreach ($lines as $idx => $line) {
            $trimmed = strtolower(trim($line));
            if (strlen($trimmed) < 20 || str_starts_with($trimmed, '#')) {
                continue;
            }

            foreach ($normalized as [$prevIdx, $prevLine]) {
                similar_text($trimmed, $prevLine, $percent);
                if ($percent > 85) {
                    $issues[] = [
                        'severity' => 'suggestion',
                        'rule' => 'redundancy',
                        'message' => 'This line is very similar to line ' . ($prevIdx + 1) . '.',
                        'suggestion' => 'Remove the duplicate instruction.',
                        'line' => $idx + 1,
                    ];
                    break;
                }
            }

            $normalized[] = [$idx, $trimmed];
        }
    }

    protected function checkSecrets(array $lines, array &$issues): void
    {
        $suggestion = 'Remove the hardcoded secret. Use template variables (e.g., {{api_key}}) or environment references instead.';

        $secretPatterns = [
            '/sk-ant-/i' => 'Potential Anthropic API key detected.',
            '/sk-[a-zA-Z0-9]{20,}/' => 'Potential OpenAI API key detected.',
            '/AKIA[A-Z0-9]{16}/' => 'Potential AWS Access Key detected.',
            '/aws_secret_access_key\s*[:=]/i' => 'Potential AWS Secret Key assignment detected.',
            '/(ghp_|ghs_|ghu_|gho_|github_pat_)[a-zA-Z0-9_]+/' => 'Potential GitHub token detected.',
            '/-----BEGIN (RSA |EC |DSA |OPENSSH )?PRIVATE KEY-----/' => 'Potential private key detected.',
            '/password\s*[:=]\s*["\'][^"\']+["\']/i' => 'Potential password assignment detected.',
            '/(mysql|postgres|mongodb|redis|amqp):\/\/[^\s]+:[^\s]+@/' => 'Potential connection string with credentials detected.',
            '/(api[_\-]?key|api[_\-]?secret|access[_\-]?token|auth[_\-]?token)\s*[:=]\s*["\'][^"\']{8,}["\']/i' => 'Potential API key or token assignment detected.',
            '/Bearer\s+[a-zA-Z0-9._\-]{20,}/' => 'Potential Bearer token detected.',
        ];

        foreach ($lines as $idx => $line) {
            foreach ($secretPatterns as $pattern => $message) {
                if (preg_match($pattern, $line)) {
                    $issues[] = [
                        'severity' => 'error',
                        'rule' => 'secret_in_prompt',
                        'message' => $message,
                        'suggestion' => $suggestion,
                        'line' => $idx + 1,
                    ];
                    break; // One issue per line for this rule
                }
            }
        }
    }
}
