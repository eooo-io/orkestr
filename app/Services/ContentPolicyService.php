<?php

namespace App\Services;

use App\Models\ContentPolicy;
use App\Models\Skill;
use Illuminate\Support\Collection;

class ContentPolicyService
{
    /**
     * Built-in rule definitions.
     * Each rule type maps to a check method and description.
     */
    private const RULE_TYPES = [
        'block_secrets' => [
            'description' => 'Block skills containing hardcoded secrets or credentials',
        ],
        'block_dangerous_commands' => [
            'description' => 'Block skills containing dangerous shell commands or system operations',
        ],
        'block_data_exfiltration' => [
            'description' => 'Block skills that attempt to send data to external endpoints',
        ],
        'block_prompt_injection' => [
            'description' => 'Block skills containing common prompt injection patterns',
        ],
        'require_output_format' => [
            'description' => 'Require skills to specify an output format',
        ],
        'max_token_limit' => [
            'description' => 'Enforce a maximum token limit on skill prompts',
        ],
        'custom_pattern' => [
            'description' => 'Block or warn on a custom regex pattern',
        ],
    ];

    /**
     * Get all available rule types with descriptions.
     */
    public static function ruleTypes(): array
    {
        return self::RULE_TYPES;
    }

    /**
     * Check a skill against all active policies for its organization.
     *
     * @return array<int, array{policy: string, rule: string, action: string, message: string}>
     */
    public function checkSkillCompliance(Skill $skill, ?int $organizationId = null): array
    {
        $orgId = $organizationId ?? $skill->project?->organization_id;
        if (! $orgId) {
            return [];
        }

        $policies = ContentPolicy::forOrganization($orgId)->active()->get();
        $violations = [];

        foreach ($policies as $policy) {
            foreach ($policy->rules as $rule) {
                $ruleViolations = $this->evaluateRule($rule, $skill);
                foreach ($ruleViolations as $violation) {
                    $violations[] = [
                        'policy' => $policy->name,
                        'policy_id' => $policy->id,
                        'rule' => $rule['type'],
                        'action' => $rule['action'] ?? 'warn',
                        'message' => $violation,
                    ];
                }
            }
        }

        return $violations;
    }

    /**
     * Evaluate a single rule against a skill.
     *
     * @return array<string> List of violation messages
     */
    private function evaluateRule(array $rule, Skill $skill): array
    {
        $body = $skill->body ?? '';
        $type = $rule['type'] ?? '';

        return match ($type) {
            'block_secrets' => $this->checkSecrets($body),
            'block_dangerous_commands' => $this->checkDangerousCommands($body),
            'block_data_exfiltration' => $this->checkDataExfiltration($body),
            'block_prompt_injection' => $this->checkPromptInjection($body),
            'require_output_format' => $this->checkOutputFormat($body),
            'max_token_limit' => $this->checkTokenLimit($body, $rule['value'] ?? 5000),
            'custom_pattern' => $this->checkCustomPattern($body, $rule['pattern'] ?? '', $rule['message'] ?? 'Custom pattern violation'),
            default => [],
        };
    }

    private function checkSecrets(string $body): array
    {
        $patterns = [
            'API key' => '/\b(sk-ant-[a-zA-Z0-9]{20,}|sk-[a-zA-Z0-9]{20,}|AKIA[A-Z0-9]{16})\b/',
            'Private key' => '/-----BEGIN\s+(RSA\s+|EC\s+|DSA\s+|OPENSSH\s+)?PRIVATE KEY-----/',
            'Password' => '/password\s*[:=]\s*["\'][^"\']+["\']/i',
            'Connection string' => '/(?:mysql|postgres|mongodb|redis):\/\/[^\s]+:[^\s]+@/i',
            'GitHub token' => '/\b(ghp_[a-zA-Z0-9]{36}|ghs_[a-zA-Z0-9]{36}|github_pat_[a-zA-Z0-9_]{22,})\b/',
        ];

        $violations = [];
        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $body)) {
                $violations[] = "Potential {$type} detected in skill content";
            }
        }

        return $violations;
    }

    private function checkDangerousCommands(string $body): array
    {
        $patterns = [
            'Destructive command' => '/\b(rm\s+-rf|mkfs|dd\s+if=|format\s+[a-z]:)/i',
            'Privilege escalation' => '/\b(sudo\s+|chmod\s+777|chown\s+root)/i',
            'System manipulation' => '/\b(shutdown|reboot|init\s+[0-6]|systemctl\s+(stop|disable))\b/i',
            'Network exposure' => '/\b(nc\s+-l|ncat\s+-l|socat\s+.*LISTEN|reverse.shell)/i',
        ];

        $violations = [];
        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $body)) {
                $violations[] = "{$type} pattern detected in skill content";
            }
        }

        return $violations;
    }

    private function checkDataExfiltration(string $body): array
    {
        $patterns = [
            'Webhook exfiltration' => '/\b(curl|wget|fetch|axios\.post|requests\.post)\s+["\']?https?:\/\//i',
            'DNS exfiltration' => '/\bnslookup\s+.*\$|dig\s+.*\$/i',
            'Base64 encode + send' => '/base64.*curl|curl.*base64/i',
        ];

        $violations = [];
        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $body)) {
                $violations[] = "{$type} pattern detected in skill content";
            }
        }

        return $violations;
    }

    private function checkPromptInjection(string $body): array
    {
        $patterns = [
            'Role override' => '/ignore\s+(all\s+)?previous\s+instructions/i',
            'System prompt leak' => '/print\s+(your|the)\s+(system\s+)?prompt/i',
            'Jailbreak attempt' => '/\b(DAN|do anything now|ignore\s+safety|bypass\s+restrictions)\b/i',
        ];

        $violations = [];
        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $body)) {
                $violations[] = "{$type} detected in skill content";
            }
        }

        return $violations;
    }

    private function checkOutputFormat(string $body): array
    {
        $hasFormat = preg_match(
            '/\b(output format|respond with|respond in|return.*json|format.*as|structured as|example output)\b/i',
            $body
        );

        if (! $hasFormat) {
            return ['Skill does not specify an output format'];
        }

        return [];
    }

    private function checkTokenLimit(string $body, int $maxTokens): array
    {
        $tokens = (int) ceil(mb_strlen($body) / 4);

        if ($tokens > $maxTokens) {
            return ["Skill is ~{$tokens} tokens, exceeding the policy limit of {$maxTokens}"];
        }

        return [];
    }

    private function checkCustomPattern(string $body, string $pattern, string $message): array
    {
        if (empty($pattern)) {
            return [];
        }

        // Validate the regex
        if (@preg_match($pattern, '') === false) {
            return [];
        }

        if (preg_match($pattern, $body)) {
            return [$message];
        }

        return [];
    }
}
