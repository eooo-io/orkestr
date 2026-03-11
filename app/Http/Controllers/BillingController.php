<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Plan;
use App\Services\Billing\MarketplacePaymentService;
use App\Services\Billing\SubscriptionService;
use App\Services\Billing\UsageTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
        protected UsageTracker $usageTracker,
        protected MarketplacePaymentService $paymentService,
    ) {}

    /**
     * GET /api/billing/plans — List available plans.
     */
    public function plans(): JsonResponse
    {
        $plans = Plan::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Plan $plan) => [
                'slug' => $plan->slug,
                'name' => $plan->name,
                'description' => $plan->description,
                'price_monthly' => $plan->formattedMonthlyPrice(),
                'price_yearly' => $plan->formattedYearlyPrice(),
                'price_monthly_cents' => $plan->price_monthly,
                'price_yearly_cents' => $plan->price_yearly,
                'limits' => [
                    'max_projects' => $plan->max_projects,
                    'max_skills_per_project' => $plan->max_skills_per_project,
                    'max_providers' => $plan->max_providers,
                    'max_members' => $plan->max_members,
                    'included_tokens_monthly' => $plan->included_tokens_monthly,
                ],
                'features' => [
                    'marketplace_publish' => $plan->marketplace_publish,
                    'ai_generation' => $plan->ai_generation,
                    'webhook_access' => $plan->webhook_access,
                    'bundle_export' => $plan->bundle_export,
                    'repository_access' => $plan->repository_access,
                    'priority_support' => $plan->priority_support,
                ],
            ]);

        return response()->json(['data' => $plans]);
    }

    /**
     * GET /api/billing/status — Current billing status for the organization.
     */
    public function status(Request $request): JsonResponse
    {
        $org = $this->resolveOrganization($request);

        if (! $org) {
            return response()->json(['error' => 'No organization context'], 400);
        }

        return response()->json([
            'data' => $this->subscriptionService->status($org),
        ]);
    }

    /**
     * POST /api/billing/subscribe — Subscribe to a plan.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan' => 'required|string|exists:plans,slug',
            'interval' => 'required|in:monthly,yearly',
            'payment_method' => 'nullable|string',
        ]);

        $org = $this->resolveOrganization($request);

        if (! $org) {
            return response()->json(['error' => 'No organization context'], 400);
        }

        $plan = Plan::findBySlug($validated['plan']);

        $result = $this->subscriptionService->subscribe(
            $org,
            $plan,
            $validated['interval'],
            $validated['payment_method'] ?? null,
        );

        return response()->json(['data' => $result]);
    }

    /**
     * POST /api/billing/change-plan — Switch plan.
     */
    public function changePlan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan' => 'required|string|exists:plans,slug',
            'interval' => 'required|in:monthly,yearly',
        ]);

        $org = $this->resolveOrganization($request);

        if (! $org) {
            return response()->json(['error' => 'No organization context'], 400);
        }

        $plan = Plan::findBySlug($validated['plan']);

        $result = $this->subscriptionService->changePlan($org, $plan, $validated['interval']);

        return response()->json(['data' => $result]);
    }

    /**
     * POST /api/billing/cancel — Cancel subscription.
     */
    public function cancel(Request $request): JsonResponse
    {
        $org = $this->resolveOrganization($request);

        if (! $org) {
            return response()->json(['error' => 'No organization context'], 400);
        }

        $result = $this->subscriptionService->cancel($org);

        return response()->json(['data' => $result]);
    }

    /**
     * POST /api/billing/resume — Resume cancelled subscription.
     */
    public function resume(Request $request): JsonResponse
    {
        $org = $this->resolveOrganization($request);

        if (! $org) {
            return response()->json(['error' => 'No organization context'], 400);
        }

        $result = $this->subscriptionService->resume($org);

        return response()->json(['data' => $result]);
    }

    /**
     * POST /api/billing/setup-intent — Create Stripe SetupIntent for collecting payment method.
     */
    public function setupIntent(Request $request): JsonResponse
    {
        $org = $this->resolveOrganization($request);

        if (! $org) {
            return response()->json(['error' => 'No organization context'], 400);
        }

        $org->createOrGetStripeCustomer();

        return response()->json([
            'data' => [
                'client_secret' => $org->createSetupIntent()->client_secret,
            ],
        ]);
    }

    /**
     * PUT /api/billing/payment-method — Update default payment method.
     */
    public function updatePaymentMethod(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_method' => 'required|string',
        ]);

        $org = $this->resolveOrganization($request);

        if (! $org) {
            return response()->json(['error' => 'No organization context'], 400);
        }

        $org->updateDefaultPaymentMethod($validated['payment_method']);

        return response()->json(['data' => ['message' => 'Payment method updated']]);
    }

    /**
     * GET /api/billing/invoices — List invoices.
     */
    public function invoices(Request $request): JsonResponse
    {
        $org = $this->resolveOrganization($request);

        if (! $org || ! $org->stripe_id) {
            return response()->json(['data' => []]);
        }

        $invoices = $org->invoices()->map(fn ($invoice) => [
            'id' => $invoice->id,
            'date' => $invoice->date()->toIso8601String(),
            'total' => $invoice->total(),
            'status' => $invoice->status,
            'pdf_url' => $invoice->invoicePdfUrl(),
        ]);

        return response()->json(['data' => $invoices]);
    }

    /**
     * GET /api/billing/usage — Current usage summary.
     */
    public function usage(Request $request): JsonResponse
    {
        $org = $this->resolveOrganization($request);

        if (! $org) {
            return response()->json(['error' => 'No organization context'], 400);
        }

        $summary = $this->usageTracker->summary($org);
        $daily = $this->usageTracker->dailyTokenUsage($org);

        return response()->json([
            'data' => [
                'summary' => $summary,
                'daily_tokens' => $daily,
            ],
        ]);
    }

    /**
     * POST /api/billing/connect — Start Stripe Connect onboarding for marketplace sellers.
     */
    public function connectSetup(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        if ($user->stripe_connect_id) {
            $status = $this->paymentService->checkConnectStatus($user);

            if ($status['onboarded']) {
                $dashboardUrl = $this->paymentService->sellerDashboardLink($user);

                return response()->json([
                    'data' => [
                        'status' => 'active',
                        'dashboard_url' => $dashboardUrl,
                    ],
                ]);
            }
        }

        $result = $this->paymentService->createConnectAccount($user);

        return response()->json(['data' => $result]);
    }

    /**
     * GET /api/billing/connect/status — Check Connect account status.
     */
    public function connectStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        return response()->json([
            'data' => $this->paymentService->checkConnectStatus($user),
        ]);
    }

    /**
     * GET /api/billing/earnings — Seller earnings summary.
     */
    public function earnings(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        return response()->json([
            'data' => $this->paymentService->sellerEarnings($user),
        ]);
    }

    private function resolveOrganization(Request $request): ?Organization
    {
        if (app()->bound('current_organization')) {
            return app('current_organization');
        }

        $user = $request->user();

        return $user?->currentOrganization;
    }
}
