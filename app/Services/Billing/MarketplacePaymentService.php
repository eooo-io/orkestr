<?php

namespace App\Services\Billing;

use App\Models\MarketplacePayout;
use App\Models\MarketplaceSkill;
use App\Models\User;
use Stripe\StripeClient;

class MarketplacePaymentService
{
    private const PLATFORM_FEE_PERCENT = 20; // 20% platform cut

    protected ?StripeClient $stripe = null;

    private function stripe(): StripeClient
    {
        if (! $this->stripe) {
            $this->stripe = new StripeClient(config('cashier.secret'));
        }

        return $this->stripe;
    }

    /**
     * Create a Stripe Connect account for a seller.
     */
    public function createConnectAccount(User $user): array
    {
        $account = $this->stripe()->accounts->create([
            'type' => 'express',
            'email' => $user->email,
            'metadata' => [
                'user_id' => $user->id,
                'platform' => 'agentis_studio',
            ],
        ]);

        $user->update(['stripe_connect_id' => $account->id]);

        // Generate onboarding link
        $link = $this->stripe()->accountLinks->create([
            'account' => $account->id,
            'refresh_url' => config('app.spa_url') . '/settings/billing?connect=refresh',
            'return_url' => config('app.spa_url') . '/settings/billing?connect=complete',
            'type' => 'account_onboarding',
        ]);

        return [
            'account_id' => $account->id,
            'onboarding_url' => $link->url,
        ];
    }

    /**
     * Check if a seller's Connect account is fully onboarded.
     */
    public function checkConnectStatus(User $user): array
    {
        if (! $user->stripe_connect_id) {
            return ['status' => 'not_connected', 'onboarded' => false];
        }

        $account = $this->stripe()->accounts->retrieve($user->stripe_connect_id);

        $onboarded = $account->charges_enabled && $account->details_submitted;

        if ($onboarded && ! $user->stripe_connect_onboarded) {
            $user->update(['stripe_connect_onboarded' => true]);
        }

        return [
            'status' => $onboarded ? 'active' : 'pending',
            'onboarded' => $onboarded,
            'charges_enabled' => $account->charges_enabled,
            'payouts_enabled' => $account->payouts_enabled,
        ];
    }

    /**
     * Process a marketplace skill purchase.
     * Returns a Stripe PaymentIntent client_secret for the buyer.
     */
    public function createPurchase(MarketplaceSkill $skill, User $buyer): array
    {
        if ($skill->price === 0) {
            return ['status' => 'free', 'requires_payment' => false];
        }

        if (! $skill->seller_id || ! $skill->seller?->stripe_connect_id) {
            throw new \RuntimeException('Seller has not set up payment account.');
        }

        $platformFee = (int) ceil($skill->price * self::PLATFORM_FEE_PERCENT / 100);

        $paymentIntent = $this->stripe()->paymentIntents->create([
            'amount' => $skill->price,
            'currency' => $skill->currency,
            'application_fee_amount' => $platformFee,
            'transfer_data' => [
                'destination' => $skill->seller->stripe_connect_id,
            ],
            'metadata' => [
                'marketplace_skill_id' => $skill->id,
                'buyer_id' => $buyer->id,
                'seller_id' => $skill->seller_id,
            ],
        ]);

        return [
            'status' => 'requires_payment',
            'requires_payment' => true,
            'client_secret' => $paymentIntent->client_secret,
            'amount' => $skill->price,
            'currency' => $skill->currency,
            'platform_fee' => $platformFee,
            'seller_receives' => $skill->price - $platformFee,
        ];
    }

    /**
     * Record a completed payout (called from Stripe webhook).
     */
    public function recordPayout(string $transferId, int $skillId, int $sellerId, int $amount): MarketplacePayout
    {
        return MarketplacePayout::create([
            'user_id' => $sellerId,
            'marketplace_skill_id' => $skillId,
            'stripe_transfer_id' => $transferId,
            'amount' => $amount,
            'status' => 'completed',
            'paid_at' => now(),
        ]);
    }

    /**
     * Get a seller's earnings summary.
     */
    public function sellerEarnings(User $user): array
    {
        $payouts = MarketplacePayout::where('user_id', $user->id);

        return [
            'total_earned' => $payouts->clone()->where('status', 'completed')->sum('amount'),
            'pending' => $payouts->clone()->where('status', 'pending')->sum('amount'),
            'payout_count' => $payouts->clone()->where('status', 'completed')->count(),
            'connect_status' => $this->checkConnectStatus($user),
        ];
    }

    /**
     * Create a Stripe login link for the seller dashboard.
     */
    public function sellerDashboardLink(User $user): ?string
    {
        if (! $user->stripe_connect_id || ! $user->stripe_connect_onboarded) {
            return null;
        }

        $link = $this->stripe()->accounts->createLoginLink($user->stripe_connect_id);

        return $link->url;
    }
}
