<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_gotchas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('severity', 20)->default('warning'); // critical, warning, info
            $table->string('source', 20)->default('manual'); // manual, test_failure, execution, review
            $table->unsignedBigInteger('source_reference_id')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['skill_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_gotchas');
    }
};
