<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->string('avatar')->nullable()->after('email');
            $table->string('github_id')->nullable()->unique()->after('avatar');
            $table->string('apple_id')->nullable()->unique()->after('github_id');
            $table->string('auth_provider')->nullable()->after('apple_id'); // email, github, apple
            $table->json('social_metadata')->nullable()->after('auth_provider');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
            $table->dropColumn(['avatar', 'github_id', 'apple_id', 'auth_provider', 'social_metadata']);
        });
    }
};
