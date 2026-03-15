<?php

namespace Database\Seeders;

use App\Models\GuardrailProfile;
use Illuminate\Database\Seeder;

class GuardrailProfileSeeder extends Seeder
{
    public function run(): void
    {
        $profiles = [
            [
                'name' => 'Strict',
                'slug' => 'strict',
                'description' => 'Maximum safety — all tool calls require approval, tight budgets, PII redaction enabled, no external APIs.',
                'is_system' => true,
                'approval_level' => 'supervised',
                'budget_limits' => [
                    'max_cost_usd' => 1.00,
                    'daily_limit_usd' => 5.00,
                    'max_tokens' => 50000,
                    'max_iterations' => 10,
                ],
                'tool_restrictions' => [
                    'blocklist' => ['shell_execute', 'file_delete', 'http_request', 'deploy', 'database_execute'],
                ],
                'output_rules' => [
                    'redact_pii' => true,
                    'redact_secrets' => true,
                    'max_output_length' => 100000,
                ],
                'access_rules' => [
                    'external_apis' => false,
                    'files' => ['read'],
                ],
                'input_sanitization' => [
                    'block_on_injection' => true,
                    'strip_dangerous_patterns' => true,
                    'max_input_length' => 10000,
                ],
                'network_rules' => [
                    'air_gap_mode' => false,
                    'require_https' => true,
                ],
            ],
            [
                'name' => 'Moderate',
                'slug' => 'moderate',
                'description' => 'Balanced safety — sensitive operations need approval, reasonable budgets, PII warnings enabled.',
                'is_system' => true,
                'approval_level' => 'semi_autonomous',
                'budget_limits' => [
                    'max_cost_usd' => 10.00,
                    'daily_limit_usd' => 50.00,
                    'max_tokens' => 200000,
                    'max_iterations' => 25,
                ],
                'tool_restrictions' => [
                    'blocklist' => ['shell_execute', 'database_execute'],
                ],
                'output_rules' => [
                    'redact_pii' => false,
                    'redact_secrets' => true,
                    'max_output_length' => 500000,
                ],
                'access_rules' => [
                    'external_apis' => true,
                    'files' => ['read', 'write'],
                ],
                'input_sanitization' => [
                    'block_on_injection' => false,
                    'strip_dangerous_patterns' => true,
                    'max_input_length' => 50000,
                ],
                'network_rules' => [
                    'air_gap_mode' => false,
                    'require_https' => false,
                ],
            ],
            [
                'name' => 'Permissive',
                'slug' => 'permissive',
                'description' => 'Maximum flexibility — autonomous execution, high budgets, minimal restrictions. For trusted environments.',
                'is_system' => true,
                'approval_level' => 'autonomous',
                'budget_limits' => [
                    'max_cost_usd' => 100.00,
                    'daily_limit_usd' => 500.00,
                    'max_tokens' => 1000000,
                    'max_iterations' => 50,
                ],
                'tool_restrictions' => [
                    'blocklist' => [],
                ],
                'output_rules' => [
                    'redact_pii' => false,
                    'redact_secrets' => false,
                    'max_output_length' => 1000000,
                ],
                'access_rules' => [
                    'external_apis' => true,
                    'files' => ['read', 'write', 'execute'],
                ],
                'input_sanitization' => [
                    'block_on_injection' => false,
                    'strip_dangerous_patterns' => false,
                    'max_input_length' => 100000,
                ],
                'network_rules' => [
                    'air_gap_mode' => false,
                    'require_https' => false,
                ],
            ],
        ];

        foreach ($profiles as $data) {
            GuardrailProfile::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }
    }
}
