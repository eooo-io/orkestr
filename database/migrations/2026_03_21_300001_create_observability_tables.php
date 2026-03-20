<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_metrics', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->enum('query_type', [
                'count_runs',
                'sum_tokens',
                'avg_cost',
                'avg_duration',
                'error_rate',
                'custom',
            ]);
            $table->json('query_config')->nullable();
            $table->string('unit')->default('count');
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
        });

        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('metric_slug');
            $table->enum('condition', ['gt', 'lt', 'gte', 'lte', 'eq']);
            $table->decimal('threshold', 12, 4);
            $table->integer('window_minutes')->default(60);
            $table->integer('cooldown_minutes')->default(30);
            $table->foreignId('notification_channel_id')->nullable()->constrained('notification_channels')->nullOnDelete();
            $table->enum('severity', ['info', 'warning', 'critical'])->default('warning');
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'enabled']);
        });

        Schema::create('alert_incidents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('alert_rule_id')->constrained('alert_rules')->cascadeOnDelete();
            $table->decimal('metric_value', 12, 4);
            $table->decimal('threshold_value', 12, 4);
            $table->enum('status', ['firing', 'acknowledged', 'resolved'])->default('firing');
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['alert_rule_id', 'status']);
        });

        Schema::create('dashboard_layouts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->json('layout')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['organization_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_layouts');
        Schema::dropIfExists('alert_incidents');
        Schema::dropIfExists('alert_rules');
        Schema::dropIfExists('custom_metrics');
    }
};
