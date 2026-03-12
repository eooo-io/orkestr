<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // free, pro, teams
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('price_monthly')->default(0); // cents
            $table->unsignedInteger('price_yearly')->default(0); // cents
            $table->unsignedInteger('max_projects')->default(3);
            $table->unsignedInteger('max_skills_per_project')->default(10);
            $table->unsignedInteger('max_providers')->default(2);
            $table->unsignedInteger('max_members')->default(1);
            $table->boolean('marketplace_publish')->default(false);
            $table->boolean('ai_generation')->default(false);
            $table->boolean('webhook_access')->default(false);
            $table->boolean('bundle_export')->default(false);
            $table->boolean('priority_support')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
