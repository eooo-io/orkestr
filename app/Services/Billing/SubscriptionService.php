<?php

namespace App\Services\Billing;

use App\Models\Organization;
use App\Models\Plan;
use Laravel\Cashier\Exceptions\IncompletePayment;

class SubscriptionService
{
    /**
     * Subscribe an organization to a plan.
     */
    public function subscribe(Organization $org, Plan $plan, string $interval = 'monthly', ?string $paymentMethod = null): array
    {
        $priceId = $interval === 'yearly'
            ? $plan->stripe_yearly_price_id
            : $plan->stripe_monthly_price_id;

        if (! $priceId) {
            throw new \RuntimeException("Plan {$plan->slug} has no Stripe {$interval} price configured.");
        }

        try {
            $builder = $org->newSubscription('default', $priceId);

            if ($plan->slug !== 'free') {
                $builder->trialDays(14);
            }

            if ($paymentMethod) {
                $subscription = $builder->create($paymentMethod);
            } else {
                $subscription = $builder->create();
            }

            $org->update([
                'plan' => $plan->slug,
                'subscription_ends_at' => $subscription->ends_at,
                'trial_ends_at' => $subscription->trial_ends_at,
            ]);

            return [
                'subscription_id' => $subscription->stripe_id,
                'status' => $subscription->stripe_status,
                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
                'current_period_end' => $subscription->ends_at?->toIso8601String(),
            ];
        } catch (IncompletePayment $e) {
            return [
                'requires_action' => true,
                'payment_intent' => $e->payment->asStripePaymentIntent()->client_secret,
            ];
        }
    }

    /**
     * Switch plan (upgrade/downgrade).
     */
    public function changePlan(Organization $org, Plan $newPlan, string $interval = 'monthly'): array
    {
        $priceId = $interval === 'yearly'
            ? $newPlan->stripe_yearly_price_id
            : $newPlan->stripe_monthly_price_id;

        if (! $priceId) {
            throw new \RuntimeException("Plan {$newPlan->slug} has no Stripe {$interval} price configured.");
        }

        $subscription = $org->subscription('default');

        if (! $subscription) {
            return $this->subscribe($org, $newPlan, $interval);
        }

        // Upgrade = immediate proration, downgrade = at period end
        $currentPlan = Plan::findBySlug($org->plan);
        $isUpgrade = $newPlan->price_monthly > ($currentPlan->price_monthly ?? 0);

        if ($isUpgrade) {
            $subscription->swap($priceId);
        } else {
            $subscription->noProrate()->swap($priceId);
        }

        $org->update(['plan' => $newPlan->slug]);

        return [
            'plan' => $newPlan->slug,
            'status' => $subscription->fresh()->stripe_status,
            'proration' => $isUpgrade ? 'immediate' : 'none',
        ];
    }

    /**
     * Cancel subscription at period end.
     */
    public function cancel(Organization $org): array
    {
        $subscription = $org->subscription('default');

        if (! $subscription || $subscription->cancelled()) {
            return ['status' => 'already_cancelled'];
        }

        $subscription->cancel();

        return [
            'status' => 'cancelling',
            'ends_at' => $subscription->ends_at->toIso8601String(),
        ];
    }

    /**
     * Resume a cancelled subscription.
     */
    public function resume(Organization $org): array
    {
        $subscription = $org->subscription('default');

        if (! $subscription || ! $subscription->onGracePeriod()) {
            return ['status' => 'cannot_resume'];
        }

        $subscription->resume();

        return [
            'status' => 'active',
        ];
    }

    /**
     * Get billing status for an organization.
     */
    public function status(Organization $org): array
    {
        $plan = Plan::findBySlug($org->plan);
        $subscription = $org->subscription('default');

        return [
            'plan' => [
                'slug' => $plan?->slug ?? 'free',
                'name' => $plan?->name ?? 'Free',
                'price_monthly' => $plan?->formattedMonthlyPrice() ?? '$0.00',
                'price_yearly' => $plan?->formattedYearlyPrice() ?? '$0.00',
            ],
            'subscription' => $subscription ? [
                'status' => $subscription->stripe_status,
                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
                'ends_at' => $subscription->ends_at?->toIso8601String(),
                'on_grace_period' => $subscription->onGracePeriod(),
                'cancelled' => $subscription->cancelled(),
            ] : null,
            'usage' => [
                'tokens_used' => $org->currentMonthTokenUsage(),
                'tokens_included' => $plan?->included_tokens_monthly ?? 0,
                'tokens_remaining' => $org->tokenBudgetRemaining(),
                'overage_rate' => $plan?->overage_price_per_1k_tokens ?? 0,
            ],
            'payment_method' => $org->pm_type ? [
                'type' => $org->pm_type,
                'last_four' => $org->pm_last_four,
            ] : null,
            'has_stripe_id' => ! empty($org->stripe_id),
        ];
    }
}
