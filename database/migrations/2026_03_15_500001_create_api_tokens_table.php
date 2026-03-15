<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->json('abilities')->nullable(); // ['*'] or ['skills:read', 'projects:write', ...]
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            if (config('database.default') !== 'sqlite') {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
