<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skill_versions', function (Blueprint $table) {
            $table->string('tuned_for_model', 100)->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('skill_versions', function (Blueprint $table) {
            $table->dropColumn('tuned_for_model');
        });
    }
};
