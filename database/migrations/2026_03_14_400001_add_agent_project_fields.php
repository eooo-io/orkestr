<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('default_model')->nullable()->after('path');
            $table->decimal('monthly_budget_usd', 10, 2)->nullable()->after('default_model');
            $table->string('environment')->default('development')->after('monthly_budget_usd');
            $table->string('icon')->nullable()->after('environment');
            $table->string('color')->nullable()->after('icon');
            $table->string('path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['default_model', 'monthly_budget_usd', 'environment', 'icon', 'color']);
            $table->string('path')->nullable(false)->change();
        });
    }
};
