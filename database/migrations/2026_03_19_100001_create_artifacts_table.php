<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artifacts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('execution_run_id')->nullable();
            $table->string('type', 30); // report, code, dataset, decision, document, image, other
            $table->string('title');
            $table->text('description')->nullable();
            $table->longText('content')->nullable();
            $table->json('metadata')->nullable();
            $table->string('format', 20)->default('markdown'); // markdown, json, csv, html, pdf, plain, binary
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->string('status', 20)->default('draft'); // draft, pending_review, approved, rejected, published
            $table->unsignedInteger('version_number')->default(1);
            $table->unsignedBigInteger('parent_artifact_id')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'type']);
            $table->index(['project_id', 'status']);
            $table->index(['agent_id']);
            $table->index(['parent_artifact_id']);

            $table->foreign('execution_run_id')->references('id')->on('execution_runs')->nullOnDelete();
            $table->foreign('parent_artifact_id')->references('id')->on('artifacts')->nullOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artifacts');
    }
};
