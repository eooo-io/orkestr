<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('project_id');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('trigger_type')->default('manual'); // manual, webhook, schedule, event
            $table->json('trigger_config')->nullable();
            $table->unsignedBigInteger('entry_step_id')->nullable();
            $table->string('status')->default('draft'); // draft, active, archived
            $table->json('context_schema')->nullable();
            $table->json('termination_policy')->nullable();
            $table->json('config')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'slug']);

            if (config('database.default') !== 'sqlite') {
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflows');
    }
};
