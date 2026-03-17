<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_sources', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('name');
            $table->string('type'); // postgres, mysql, minio, s3, filesystem, redis
            $table->text('connection_config')->nullable(); // encrypted JSON
            $table->string('access_mode')->default('read_only'); // read_only, read_write
            $table->boolean('enabled')->default(true);
            $table->string('health_status')->nullable(); // healthy, unhealthy, unknown
            $table->timestamp('last_health_check')->nullable();
            $table->timestamps();

            $table->index('project_id');

            if (config('database.default') !== 'sqlite') {
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            }
        });

        Schema::create('agent_data_source', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id');
            $table->unsignedBigInteger('data_source_id');
            $table->unsignedBigInteger('project_id');
            $table->string('access_mode')->default('read_only');
            $table->timestamps();

            $table->unique(['agent_id', 'data_source_id']);
            $table->index('project_id');

            if (config('database.default') !== 'sqlite') {
                $table->foreign('agent_id')->references('id')->on('agents')->cascadeOnDelete();
                $table->foreign('data_source_id')->references('id')->on('data_sources')->cascadeOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_data_source');
        Schema::dropIfExists('data_sources');
    }
};
