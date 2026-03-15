<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_endpoints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('base_url');
            $table->text('api_key')->nullable();
            $table->json('models')->nullable();
            $table->boolean('enabled')->default(true);
            $table->string('health_status')->default('unknown'); // healthy, unhealthy, unknown
            $table->timestamp('last_health_check')->nullable();
            $table->float('avg_latency_ms')->nullable();
            $table->timestamps();

            if (config('database.default') !== 'sqlite') {
                $table->foreign('organization_id')
                    ->references('id')
                    ->on('organizations')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_endpoints');
    }
};
