<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->foreignId('owner_user_id')
                ->nullable()
                ->after('created_by')
                ->constrained('users')
                ->nullOnDelete();

            $table->decimal('reputation_score', 5, 2)->nullable()->after('owner_user_id');
            $table->timestamp('reputation_last_computed_at')->nullable()->after('reputation_score');
        });

        // Backfill: creator becomes initial owner
        DB::table('agents')
            ->whereNull('owner_user_id')
            ->whereNotNull('created_by')
            ->update(['owner_user_id' => DB::raw('created_by')]);
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_user_id');
            $table->dropColumn(['reputation_score', 'reputation_last_computed_at']);
        });
    }
};
