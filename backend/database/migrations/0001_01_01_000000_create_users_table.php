<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // No generic "users" table -- this app has two distinct identities
        // only: AdminUser (super admin, see create_admin_users_table) and
        // Shop (a merchant, authenticated via Shopify OAuth, see
        // create_shops_table). password_reset_tokens is unused for the
        // same reason (Shopify auth doesn't need it; the super admin
        // account is single-operator, reset via a fresh artisan seed if
        // ever needed rather than a self-service email flow).
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->unsignedBigInteger('admin_user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
