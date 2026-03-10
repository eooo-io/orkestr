<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('provider_slug'); // claude|cursor|copilot|windsurf|cline|openai
            $table->timestamps();

            $table->unique(['project_id', 'provider_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_providers');
    }
};
