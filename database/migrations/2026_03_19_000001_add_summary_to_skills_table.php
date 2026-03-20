<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->string('summary', 500)->nullable()->after('description');
        });

        Schema::table('library_skills', function (Blueprint $table) {
            $table->string('summary', 500)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->dropColumn('summary');
        });

        Schema::table('library_skills', function (Blueprint $table) {
            $table->dropColumn('summary');
        });
    }
};
