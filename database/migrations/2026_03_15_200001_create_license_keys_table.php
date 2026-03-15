<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_keys', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('key')->unique(); // ORKESTR-XXXX-XXXX-XXXX-XXXX
            $table->string('tier'); // self_hosted, enterprise
            $table->string('status')->default('active'); // active, revoked, expired
            $table->integer('max_users')->default(0); // 0 = unlimited
            $table->integer('max_agents')->default(0); // 0 = unlimited
            $table->json('features')->nullable(); // feature flags
            $table->string('licensee_name')->nullable();
            $table->string('licensee_email')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('key');
            $table->index('status');

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
        Schema::dropIfExists('license_keys');
    }
};
