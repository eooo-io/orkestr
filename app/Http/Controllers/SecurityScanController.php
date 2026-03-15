<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use App\Services\SecurityRuleSet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecurityScanController extends Controller
{
    public function __construct(
        private SecurityRuleSet $ruleSet,
    ) {}

    /**
     * POST /api/skills/{skill}/security-scan
     * Run a static security scan on a skill.
     */
    public function scanSkill(Skill $skill): JsonResponse
    {
        $content = $skill->body ?? '';
        if ($skill->description) {
            $content = $skill->description . "\n" . $content;
        }

        $warnings = $this->ruleSet->scan($content);
        $score = $this->ruleSet->riskScore($warnings);

        return response()->json([
            'skill_id' => $skill->id,
            'risk_score' => $score,
            'risk_level' => $this->ruleSet->riskLevel($score),
            'warnings' => $warnings,
            'scanned_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * POST /api/security-scan
     * Scan arbitrary content for security risks.
     */
    public function scanContent(Request $request): JsonResponse
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        $warnings = $this->ruleSet->scan($request->input('content'));
        $score = $this->ruleSet->riskScore($warnings);

        return response()->json([
            'risk_score' => $score,
            'risk_level' => $this->ruleSet->riskLevel($score),
            'warnings' => $warnings,
            'scanned_at' => now()->toIso8601String(),
        ]);
    }
}
