<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // sticky_atc|shipping_bar|trust_badges|faq|free_gift|bogo|quantity_discount|bundle|cart_upsell|fbt|goal_tracker|...
            $table->string('name')->nullable();
            $table->string('status')->default('draft'); // draft|active|paused
            $table->json('settings')->nullable();
            $table->json('targeting')->nullable();
            $table->json('shopify_ref_ids')->nullable();
            $table->timestamps();

            $table->index('shop_id');
            $table->index(['shop_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_configs');
    }
};
