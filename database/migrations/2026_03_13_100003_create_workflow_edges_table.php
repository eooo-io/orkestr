<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_edges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workflow_id');
            $table->unsignedBigInteger('source_step_id');
            $table->unsignedBigInteger('target_step_id');
            $table->text('condition_expression')->nullable();
            $table->string('label')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->timestamps();

            $table->unique(['workflow_id', 'source_step_id', 'target_step_id'], 'workflow_edge_unique');

            if (config('database.default') !== 'sqlite') {
                $table->foreign('workflow_id')->references('id')->on('workflows')->cascadeOnDelete();
                $table->foreign('source_step_id')->references('id')->on('workflow_steps')->cascadeOnDelete();
                $table->foreign('target_step_id')->references('id')->on('workflow_steps')->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_edges');
    }
};
