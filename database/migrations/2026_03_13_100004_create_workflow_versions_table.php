<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workflow_id');
            $table->unsignedInteger('version_number');
            $table->json('snapshot');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['workflow_id', 'version_number']);

            if (config('database.default') !== 'sqlite') {
                $table->foreign('workflow_id')->references('id')->on('workflows')->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_versions');
    }
};
