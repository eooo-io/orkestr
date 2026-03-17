<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->boolean('document_access')->default(false)->after('data_access_scope');
            $table->boolean('knowledge_access')->default(false)->after('document_access');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['document_access', 'knowledge_access']);
        });
    }
};
