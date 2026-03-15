<?php

namespace App\Services;

use App\Models\Skill;
use App\Models\SkillTestCase;

class SkillRegressionService
{
    /**
     * Run a single test case against its expected output.
     *
     * Since actual LLM execution is external, this simulates the assertion step
     * by comparing a provided actual_output against the test case expectations.
     */
    public function runTestCase(SkillTestCase $testCase, string $actualOutput): array
    {
        $passed = $this->assertOutput($testCase, $actualOutput);

        return [
            'test_case_id' => $testCase->id,
            'name' => $testCase->name,
            'assertion_type' => $testCase->assertion_type,
            'passed' => $passed,
            'actual_output' => $actualOutput,
            'expected_output' => $testCase->expected_output,
        ];
    }

    /**
     * Run all test cases for a skill. Returns results array.
     * Accepts a map of test_case_id => actual_output for assertions.
     */
    public function runAllForSkill(Skill $skill, array $outputs = []): array
    {
        $testCases = $skill->testCases()->get();
        $results = [];
        $passCount = 0;

        foreach ($testCases as $testCase) {
            $actualOutput = $outputs[$testCase->id] ?? '';
            $result = $this->runTestCase($testCase, $actualOutput);
            $results[] = $result;

            if ($result['passed']) {
                $passCount++;
            }
        }

        $total = count($results);

        return [
            'skill_id' => $skill->id,
            'total' => $total,
            'passed' => $passCount,
            'failed' => $total - $passCount,
            'pass_rate' => $total > 0 ? round($passCount / $total, 2) : 0,
            'results' => $results,
        ];
    }

    /**
     * Get all test case results (without running — just list them).
     */
    public function getResults(Skill $skill): array
    {
        return $skill->testCases()->get()->map(fn ($tc) => [
            'id' => $tc->id,
            'name' => $tc->name,
            'input' => $tc->input,
            'expected_output' => $tc->expected_output,
            'assertion_type' => $tc->assertion_type,
            'pass_threshold' => $tc->pass_threshold,
        ])->toArray();
    }

    /**
     * Assert actual output against a test case's expected output.
     */
    private function assertOutput(SkillTestCase $testCase, string $actualOutput): bool
    {
        if ($testCase->expected_output === null) {
            // No expected output — pass if output is non-empty
            return strlen(trim($actualOutput)) > 0;
        }

        return match ($testCase->assertion_type) {
            'contains' => str_contains(strtolower($actualOutput), strtolower($testCase->expected_output)),
            'equals' => trim($actualOutput) === trim($testCase->expected_output),
            'regex' => (bool) preg_match($testCase->expected_output, $actualOutput),
            'not_contains' => ! str_contains(strtolower($actualOutput), strtolower($testCase->expected_output)),
            default => false,
        };
    }
}
