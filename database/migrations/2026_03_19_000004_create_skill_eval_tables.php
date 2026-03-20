<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_eval_suites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('skill_eval_prompts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eval_suite_id')->constrained('skill_eval_suites')->cascadeOnDelete();
            $table->text('prompt');
            $table->text('expected_behavior')->nullable();
            $table->json('grading_criteria')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('skill_eval_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eval_suite_id')->constrained('skill_eval_suites')->cascadeOnDelete();
            $table->string('model', 100);
            $table->string('mode', 20); // with_skill, without_skill, ab_test
            $table->string('status', 20)->default('pending'); // pending, running, completed, failed
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->json('results')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['eval_suite_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_eval_runs');
        Schema::dropIfExists('skill_eval_prompts');
        Schema::dropIfExists('skill_eval_suites');
    }
};
