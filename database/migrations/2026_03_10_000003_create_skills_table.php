<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('max_tokens')->nullable();
            $table->json('tools')->nullable();
            $table->longText('body')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'slug']);
        });

        // FULLTEXT index for MariaDB/MySQL only (not supported by SQLite)
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement('ALTER TABLE skills ADD FULLTEXT fulltext_search (name, description, body)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('skills');
    }
};
