<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->boolean('memory_enabled')->default(false)->after('is_template');
            $table->boolean('auto_remember')->default(false)->after('memory_enabled');
            $table->unsignedInteger('memory_recall_limit')->default(5)->after('auto_remember');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['memory_enabled', 'auto_remember', 'memory_recall_limit']);
        });
    }
};
