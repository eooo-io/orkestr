<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceSkill;
use App\Models\Organization;
use App\Services\Billing\MarketplacePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret = config('cashier.webhook.secret');

        if ($secret) {
            try {
                $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $secret);
            } catch (\Exception $e) {
                Log::warning('Stripe webhook signature verification failed', ['error' => $e->getMessage()]);

                return response()->json(['error' => 'Invalid signature'], 400);
            }
        } else {
            $event = json_decode($payload, false);
        }

        $method = 'handle' . str_replace('.', '_', ucfirst(str_replace('.', '_', $event->type ?? $event->type)));
        $method = lcfirst(str_replace('_', '', ucwords(str_replace('.', ' ', $event->type ?? ''))));
        $method = 'handle' . ucfirst($method);

        if (method_exists($this, $method)) {
            return $this->{$method}($event);
        }

        return response()->json(['status' => 'unhandled']);
    }

    protected function handleCustomerSubscriptionUpdated(object $event): JsonResponse
    {
        $subscription = $event->data->object;
        $stripeId = $subscription->customer;

        $org = Organization::where('stripe_id', $stripeId)->first();

        if (! $org) {
            return response()->json(['status' => 'org_not_found']);
        }

        if ($subscription->status === 'active') {
            $org->update([
                'subscription_ends_at' => isset($subscription->current_period_end)
                    ? \Carbon\Carbon::createFromTimestamp($subscription->current_period_end)
                    : null,
            ]);
        }

        return response()->json(['status' => 'ok']);
    }

    protected function handleCustomerSubscriptionDeleted(object $event): JsonResponse
    {
        $subscription = $event->data->object;
        $stripeId = $subscription->customer;

        $org = Organization::where('stripe_id', $stripeId)->first();

        if ($org) {
            $org->update([
                'plan' => 'free',
                'subscription_ends_at' => now(),
            ]);
        }

        return response()->json(['status' => 'ok']);
    }

    protected function handleInvoicePaymentSucceeded(object $event): JsonResponse
    {
        $invoice = $event->data->object;
        $stripeId = $invoice->customer;

        $org = Organization::where('stripe_id', $stripeId)->first();

        if ($org) {
            Log::info("Payment succeeded for org {$org->name}", [
                'amount' => $invoice->amount_paid,
                'invoice_id' => $invoice->id,
            ]);
        }

        return response()->json(['status' => 'ok']);
    }

    protected function handleInvoicePaymentFailed(object $event): JsonResponse
    {
        $invoice = $event->data->object;
        $stripeId = $invoice->customer;

        $org = Organization::where('stripe_id', $stripeId)->first();

        if ($org) {
            Log::warning("Payment failed for org {$org->name}", [
                'invoice_id' => $invoice->id,
            ]);
        }

        return response()->json(['status' => 'ok']);
    }

    protected function handlePaymentIntentSucceeded(object $event): JsonResponse
    {
        $paymentIntent = $event->data->object;
        $metadata = $paymentIntent->metadata;

        // Marketplace skill purchase
        if (isset($metadata->marketplace_skill_id)) {
            $skill = MarketplaceSkill::find($metadata->marketplace_skill_id);

            if ($skill) {
                $skill->increment('downloads');

                // Record payout to seller
                if (isset($metadata->seller_id) && $paymentIntent->transfer_data) {
                    app(MarketplacePaymentService::class)->recordPayout(
                        $paymentIntent->transfer_data->destination ?? 'direct',
                        (int) $metadata->marketplace_skill_id,
                        (int) $metadata->seller_id,
                        $paymentIntent->amount - ($paymentIntent->application_fee_amount ?? 0),
                    );
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }

    protected function handleAccountUpdated(object $event): JsonResponse
    {
        $account = $event->data->object;

        $user = \App\Models\User::where('stripe_connect_id', $account->id)->first();

        if ($user && $account->charges_enabled && $account->details_submitted) {
            $user->update(['stripe_connect_onboarded' => true]);
        }

        return response()->json(['status' => 'ok']);
    }
}
