<?php

namespace App\Http\Middleware;

use App\Services\Billing\UsageTracker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUsageBudget
{
    public function __construct(
        protected UsageTracker $usageTracker,
    ) {}

    public function handle(Request $request, Closure $next, string $type = 'llm_tokens'): Response
    {
        $organization = app()->bound('current_organization')
            ? app('current_organization')
            : null;

        // No org context — allow (backward compat)
        if (! $organization) {
            return $next($request);
        }

        if ($type === 'llm_tokens' && $this->usageTracker->hasExceededTokenBudget($organization)) {
            return response()->json([
                'error' => 'Monthly token budget exceeded. Upgrade your plan for more tokens.',
                'usage' => [
                    'tokens_used' => $organization->currentMonthTokenUsage(),
                    'tokens_remaining' => 0,
                ],
                'upgrade_url' => '/settings/billing',
            ], Response::HTTP_PAYMENT_REQUIRED);
        }

        return $next($request);
    }
}
