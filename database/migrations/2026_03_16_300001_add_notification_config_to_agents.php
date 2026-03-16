<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->boolean('notify_on_success')->default(false)->after('is_template');
            $table->boolean('notify_on_failure')->default(true)->after('notify_on_success');
        });

        // Add trigger_type to execution_runs for tracking how the run was triggered
        Schema::table('execution_runs', function (Blueprint $table) {
            $table->string('trigger_type')->nullable()->after('schedule_id'); // manual, cron, webhook, a2a, event
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['notify_on_success', 'notify_on_failure']);
        });

        Schema::table('execution_runs', function (Blueprint $table) {
            $table->dropColumn('trigger_type');
        });
    }
};
