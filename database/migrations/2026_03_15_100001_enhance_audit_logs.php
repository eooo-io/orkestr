<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_audit_logs', function (Blueprint $table) {
            $table->uuid('request_id')->nullable()->after('uuid');
            $table->unsignedBigInteger('skill_id')->nullable()->after('agent_id');
            $table->string('severity')->default('info')->after('event');
            $table->string('user_email')->nullable()->after('user_id');

            $table->index('request_id');

            if (config('database.default') !== 'sqlite') {
                $table->foreign('skill_id')->references('id')->on('skills')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('agent_audit_logs', function (Blueprint $table) {
            if (config('database.default') !== 'sqlite') {
                $table->dropForeign(['skill_id']);
            }

            $table->dropIndex(['request_id']);
            $table->dropColumn(['request_id', 'skill_id', 'severity', 'user_email']);
        });
    }
};
