<?php

namespace App\Services;

use App\Models\Skill;

class SkillStalenessService
{
    /**
     * Models that should always be flagged as stale. Updated as the catalog evolves.
     *
     * @var array<int, string>
     */
    public const DEPRECATED_MODELS = [
        'claude-3-opus-20240229',
        'claude-3-sonnet-20240229',
        'claude-3-haiku-20240307',
        'claude-3-5-sonnet-20240620',
        'claude-3-5-sonnet-20241022',
        'claude-3-5-haiku-20241022',
        'claude-sonnet-4-5',
        'claude-opus-4-5',
        'gpt-3.5-turbo',
        'gpt-4',
        'gpt-4-turbo',
        'gpt-4o',
        'gpt-4o-mini',
        'o1-preview',
        'o1-mini',
        'gemini-pro',
        'gemini-1.5-pro',
        'gemini-1.5-flash',
        'gemini-2.0-pro',
        'gemini-2.5-pro',
        'grok-2',
        'grok-beta',
    ];

    /**
     * Compute the staleness status for a skill.
     *
     * @return array{
     *     is_stale: bool,
     *     reason: string,
     *     tuned_for_model: string|null,
     *     last_validated_model: string|null,
     *     last_validated_at: string|null,
     *     suggested_action: string
     * }
     */
    public function statusFor(Skill $skill, ?string $currentModel = null): array
    {
        $tuned = $skill->tuned_for_model;
        $validatedModel = $skill->last_validated_model;
        $validatedAt = $skill->last_validated_at;

        $base = [
            'tuned_for_model' => $tuned,
            'last_validated_model' => $validatedModel,
            'last_validated_at' => $validatedAt?->toIso8601String(),
        ];

        if ($tuned !== null && in_array($tuned, self::DEPRECATED_MODELS, true)) {
            return $base + [
                'is_stale' => true,
                'reason' => 'model_deprecated',
                'suggested_action' => "Model {$tuned} is deprecated — retune for a current model.",
            ];
        }

        if ($tuned === null) {
            return $base + [
                'is_stale' => true,
                'reason' => 'needs_tuning',
                'suggested_action' => 'Set a "Tuned for model" so changes can be tracked against a baseline.',
            ];
        }

        if ($validatedAt === null || $validatedModel !== $tuned) {
            return $base + [
                'is_stale' => true,
                'reason' => 'needs_revalidation',
                'suggested_action' => "Run an eval suite against {$tuned} to confirm behavior.",
            ];
        }

        if ($currentModel !== null && $currentModel !== $tuned) {
            return $base + [
                'is_stale' => true,
                'reason' => 'needs_revalidation',
                'suggested_action' => "Last validated on {$tuned}; revalidate against {$currentModel}.",
            ];
        }

        return $base + [
            'is_stale' => false,
            'reason' => 'ok',
            'suggested_action' => 'Up to date.',
        ];
    }
}
