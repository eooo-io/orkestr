<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;

class EvalGateBlockedException extends \RuntimeException
{
    /**
     * @param  array<int, array<string, mixed>>  $blockedSkills
     */
    public function __construct(public readonly array $blockedSkills)
    {
        parent::__construct('Sync blocked by one or more skill eval gates.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => $this->getMessage(),
            'blocked_skills' => $this->blockedSkills,
        ], 409);
    }
}
