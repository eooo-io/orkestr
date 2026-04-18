<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skill_eval_suites', function (Blueprint $table) {
            $table->string('scorer', 32)->default('keyword')->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('skill_eval_suites', function (Blueprint $table) {
            $table->dropColumn('scorer');
        });
    }
};
