<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Skill reviews — approval workflow
        Schema::create('skill_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('skill_id');
            $table->unsignedBigInteger('skill_version_id')->nullable();
            $table->unsignedBigInteger('reviewer_id')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->text('comments')->nullable();
            $table->unsignedBigInteger('submitted_by');
            $table->timestamps();

            $table->index(['skill_id', 'status']);

            if (config('database.default') !== 'sqlite') {
                $table->foreign('skill_id')->references('id')->on('skills')->cascadeOnDelete();
                $table->foreign('skill_version_id')->references('id')->on('skill_versions')->nullOnDelete();
                $table->foreign('reviewer_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('submitted_by')->references('id')->on('users')->cascadeOnDelete();
            }
        });

        // Notifications
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('type');
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'read_at']);

            if (config('database.default') !== 'sqlite') {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
            }
        });

        // Skill analytics
        Schema::create('skill_analytics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('skill_id');
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->date('date');
            $table->integer('test_runs')->default(0);
            $table->integer('pass_count')->default(0);
            $table->integer('fail_count')->default(0);
            $table->float('avg_tokens')->nullable();
            $table->float('avg_cost_microcents')->nullable();
            $table->float('avg_latency_ms')->nullable();
            $table->timestamps();

            $table->unique(['skill_id', 'date']);
            $table->index(['organization_id', 'date']);

            if (config('database.default') !== 'sqlite') {
                $table->foreign('skill_id')->references('id')->on('skills')->cascadeOnDelete();
                $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
            }
        });

        // Skill test cases — regression testing
        Schema::create('skill_test_cases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('skill_id');
            $table->string('name');
            $table->text('input');
            $table->text('expected_output')->nullable();
            $table->string('assertion_type')->default('contains'); // contains, equals, regex, not_contains
            $table->float('pass_threshold')->default(0.8);
            $table->timestamps();

            $table->index('skill_id');

            if (config('database.default') !== 'sqlite') {
                $table->foreign('skill_id')->references('id')->on('skills')->cascadeOnDelete();
            }
        });

        // Add ownership and inheritance columns to skills
        Schema::table('skills', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_id')->nullable()->after('project_id');
            $table->json('codeowners')->nullable()->after('owner_id');
            $table->unsignedBigInteger('extends_skill_id')->nullable()->after('template_variables');
            $table->json('override_sections')->nullable()->after('extends_skill_id');

            if (config('database.default') !== 'sqlite') {
                $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('extends_skill_id')->references('id')->on('skills')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            if (config('database.default') !== 'sqlite') {
                $table->dropForeign(['owner_id']);
                $table->dropForeign(['extends_skill_id']);
            }
            $table->dropColumn(['owner_id', 'codeowners', 'extends_skill_id', 'override_sections']);
        });

        Schema::dropIfExists('skill_test_cases');
        Schema::dropIfExists('skill_analytics');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('skill_reviews');
    }
};
