<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('sso_issuer', 2048)->nullable()->after('id');
            $table->uuid('sso_subject')->nullable()->after('sso_issuer');
            $table->string('avatar_url', 2048)->nullable()->after('email_verified_at');
            $table->boolean('is_active')->default(true)->after('avatar_url');
            $table->timestampTz('last_login_at')->nullable()->after('is_active');
            $table->string('password')->nullable()->change();

            $table->unique(['sso_issuer', 'sso_subject'], 'users_sso_identity_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_sso_identity_unique');
            $table->dropColumn(['sso_issuer', 'sso_subject', 'avatar_url', 'is_active', 'last_login_at']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
