<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extension_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('extension_key'); // checkout_trust_badges|checkout_goal_progress|...
            $table->boolean('eligible')->default(false);
            $table->string('reason')->nullable(); // e.g. "Requires Shopify Plus"
            $table->boolean('enabled')->default(false);
            $table->timestamps();

            $table->unique(['shop_id', 'extension_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extension_status');
    }
};
