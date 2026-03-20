<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->string('p256dh_key');
            $table->string('auth_key');
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        // Add unique index on endpoint — use a hash for TEXT columns
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('push_subscriptions', function (Blueprint $table) {
                $table->unique('endpoint');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
