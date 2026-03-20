<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->json('schema')->nullable();
            $table->unsignedInteger('retention_hours')->default(72);
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
        });

        Schema::create('event_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained('event_topics')->cascadeOnDelete();
            $table->string('subscriber_type', 30); // agent, webhook, channel
            $table->unsignedBigInteger('subscriber_id');
            $table->json('filter_expression')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['topic_id', 'subscriber_type']);
        });

        Schema::create('event_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained('event_topics')->cascadeOnDelete();
            $table->string('publisher_type', 30)->nullable();
            $table->unsignedBigInteger('publisher_id')->nullable();
            $table->string('event_type', 100);
            $table->json('payload')->nullable();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->index(['topic_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_log');
        Schema::dropIfExists('event_subscriptions');
        Schema::dropIfExists('event_topics');
    }
};
