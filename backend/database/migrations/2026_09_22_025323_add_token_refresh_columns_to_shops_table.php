<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * New public apps must use expiring offline access tokens for Admin API
 * GraphQL requests (Shopify requirement, verified against
 * shopify.dev/docs/apps/build/authentication-authorization/access-tokens/authorization-code-grant
 * as of 2026-09-22) -- a 1-hour access token plus a 90-day refresh token,
 * requested with `expiring=1` on the code exchange. shops.access_token was
 * originally written assuming a permanent (non-expiring) token, which is
 * no longer valid for a new public app like this one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->text('refresh_token')->nullable()->after('access_token');
            $table->timestamp('access_token_expires_at')->nullable()->after('refresh_token');
            $table->timestamp('refresh_token_expires_at')->nullable()->after('access_token_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['refresh_token', 'access_token_expires_at', 'refresh_token_expires_at']);
        });
    }
};
