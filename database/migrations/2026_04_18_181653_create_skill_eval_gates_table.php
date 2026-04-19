<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_eval_gates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->json('required_suite_ids')->nullable();
            $table->decimal('fail_threshold_delta', 5, 2)->default(-5.00);
            $table->boolean('auto_run_on_save')->default(false);
            $table->boolean('block_sync')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_eval_gates');
    }
};
