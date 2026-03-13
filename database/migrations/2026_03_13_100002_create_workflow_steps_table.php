<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workflow_id');
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->string('type'); // agent, checkpoint, condition, parallel_split, parallel_join, start, end
            $table->string('name');
            $table->float('position_x')->default(0);
            $table->float('position_y')->default(0);
            $table->json('config')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            if (config('database.default') !== 'sqlite') {
                $table->foreign('workflow_id')->references('id')->on('workflows')->cascadeOnDelete();
                $table->foreign('agent_id')->references('id')->on('agents')->nullOnDelete();
            }
        });

        // Add entry_step_id FK now that workflow_steps exists
        if (config('database.default') !== 'sqlite') {
            Schema::table('workflows', function (Blueprint $table) {
                $table->foreign('entry_step_id')->references('id')->on('workflow_steps')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (config('database.default') !== 'sqlite') {
            Schema::table('workflows', function (Blueprint $table) {
                $table->dropForeign(['entry_step_id']);
            });
        }

        Schema::dropIfExists('workflow_steps');
    }
};
