<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_skills', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->json('tags');
            $table->json('frontmatter');
            $table->longText('body');
            $table->string('author')->nullable();
            $table->string('source')->nullable();
            $table->unsignedInteger('downloads')->default(0);
            $table->unsignedInteger('upvotes')->default(0);
            $table->unsignedInteger('downvotes')->default(0);
            $table->string('version')->default('1.0.0');
            $table->timestamps();

            if (config('database.default') !== 'sqlite') {
                $table->fullText(['name', 'description']);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_skills');
    }
};
