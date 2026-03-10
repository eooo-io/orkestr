<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->json('frontmatter')->nullable();
            $table->longText('body')->nullable();
            $table->string('note')->nullable();
            $table->timestamp('saved_at');

            $table->unique(['skill_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_versions');
    }
};
