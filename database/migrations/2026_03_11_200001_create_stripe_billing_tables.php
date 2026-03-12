<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cashier: subscriptions table (for Organization billing)
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('stripe_id')->unique();
            $table->string('stripe_status');
            $table->string('stripe_price')->nullable();
            $table->integer('quantity')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'stripe_status']);
        });

        // Cashier: subscription_items table
        Schema::create('subscription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_id')->unique();
            $table->string('stripe_product');
            $table->string('stripe_price');
            $table->integer('quantity')->nullable();
            $table->timestamps();
            $table->index(['subscription_id', 'stripe_price']);
        });

        // Add Stripe columns to organizations table
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('stripe_id')->nullable()->unique()->after('plan_limits');
            $table->string('pm_type')->nullable()->after('stripe_id');
            $table->string('pm_last_four', 4)->nullable()->after('pm_type');
        });

        // Usage metering: track LLM token consumption
        Schema::create('usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // 'llm_tokens', 'api_calls', 'sync_operations'
            $table->unsignedBigInteger('quantity');
            $table->string('model')->nullable(); // e.g. 'claude-sonnet-4-6'
            $table->json('metadata')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->index(['organization_id', 'type', 'recorded_at']);
        });

        // Marketplace: seller payouts via Stripe Connect
        Schema::create('marketplace_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_skill_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_transfer_id')->nullable();
            $table->unsignedInteger('amount'); // cents
            $table->string('currency', 3)->default('usd');
            $table->string('status')->default('pending'); // pending, completed, failed
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        // Add Stripe Connect fields to users for marketplace sellers
        Schema::table('users', function (Blueprint $table) {
            $table->string('stripe_connect_id')->nullable()->after('current_organization_id');
            $table->boolean('stripe_connect_onboarded')->default(false)->after('stripe_connect_id');
        });

        // Add pricing to marketplace_skills for paid skills
        Schema::table('marketplace_skills', function (Blueprint $table) {
            $table->unsignedInteger('price')->default(0)->after('version'); // cents, 0 = free
            $table->string('currency', 3)->default('usd')->after('price');
            $table->foreignId('seller_id')->nullable()->after('currency')
                ->constrained('users')->nullOnDelete();
            $table->string('stripe_product_id')->nullable()->after('seller_id');
            $table->string('stripe_price_id')->nullable()->after('stripe_product_id');
        });

        // Add Stripe price IDs to plans
        Schema::table('plans', function (Blueprint $table) {
            $table->string('stripe_monthly_price_id')->nullable()->after('price_yearly');
            $table->string('stripe_yearly_price_id')->nullable()->after('stripe_monthly_price_id');
            $table->unsignedBigInteger('included_tokens_monthly')->default(0)->after('stripe_yearly_price_id');
            $table->unsignedInteger('overage_price_per_1k_tokens')->default(0)->after('included_tokens_monthly'); // cents
        });

        // Personal access tokens for API auth (Sanctum)
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_monthly_price_id',
                'stripe_yearly_price_id',
                'included_tokens_monthly',
                'overage_price_per_1k_tokens',
            ]);
        });

        Schema::table('marketplace_skills', function (Blueprint $table) {
            $table->dropForeign(['seller_id']);
            $table->dropColumn([
                'price', 'currency', 'seller_id',
                'stripe_product_id', 'stripe_price_id',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['stripe_connect_id', 'stripe_connect_onboarded']);
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['stripe_id', 'pm_type', 'pm_last_four']);
        });

        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('marketplace_payouts');
        Schema::dropIfExists('usage_records');
        Schema::dropIfExists('subscription_items');
        Schema::dropIfExists('subscriptions');
    }
};
