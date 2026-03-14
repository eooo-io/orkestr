<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('autonomy_level')->default('semi_autonomous')->after('custom_tools');
            $table->decimal('budget_limit_usd', 10, 4)->nullable()->after('autonomy_level');
            $table->decimal('daily_budget_limit_usd', 10, 4)->nullable()->after('budget_limit_usd');
            $table->json('allowed_tools')->nullable()->after('daily_budget_limit_usd');
            $table->json('blocked_tools')->nullable()->after('allowed_tools');
            $table->json('data_access_scope')->nullable()->after('blocked_tools');
        });

        Schema::table('execution_runs', function (Blueprint $table) {
            $table->boolean('approval_required')->default(false)->after('model_used');
            $table->unsignedBigInteger('approved_by')->nullable()->after('approval_required');
            $table->timestamp('approved_at')->nullable()->after('approved_by');

            if (config('database.default') !== 'sqlite') {
                $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            }
        });

        Schema::table('execution_steps', function (Blueprint $table) {
            $table->boolean('requires_approval')->default(false)->after('model_requested');
            $table->unsignedBigInteger('approved_by')->nullable()->after('requires_approval');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('approval_note')->nullable()->after('approved_at');

            if (config('database.default') !== 'sqlite') {
                $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            }
        });

        Schema::create('agent_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('event');
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
            $table->index(['agent_id', 'created_at']);
            $table->index('event');

            if (config('database.default') !== 'sqlite') {
                $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
                $table->foreign('agent_id')->references('id')->on('agents')->nullOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_audit_logs');

        Schema::table('execution_steps', function (Blueprint $table) {
            if (config('database.default') !== 'sqlite') {
                $table->dropForeign(['approved_by']);
            }
            $table->dropColumn(['requires_approval', 'approved_by', 'approved_at', 'approval_note']);
        });

        Schema::table('execution_runs', function (Blueprint $table) {
            if (config('database.default') !== 'sqlite') {
                $table->dropForeign(['approved_by']);
            }
            $table->dropColumn(['approval_required', 'approved_by', 'approved_at']);
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn([
                'autonomy_level',
                'budget_limit_usd',
                'daily_budget_limit_usd',
                'allowed_tools',
                'blocked_tools',
                'data_access_scope',
            ]);
        });
    }
};
