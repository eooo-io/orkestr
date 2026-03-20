<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon', 50)->nullable();
            $table->string('color', 7)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('skills', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('template_variables')
                ->constrained('skill_categories')->nullOnDelete();
            $table->string('skill_type', 30)->nullable()->after('category_id'); // capability_uplift, encoded_preference, hybrid
        });

        Schema::table('library_skills', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('tags')
                ->constrained('skill_categories')->nullOnDelete();
            $table->string('skill_type', 30)->nullable()->after('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn('skill_type');
        });

        Schema::table('library_skills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn('skill_type');
        });

        Schema::dropIfExists('skill_categories');
    }
};
