<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('surface'); // cart|product|checkout|post_purchase|thank_you
            $table->json('trigger')->nullable();
            $table->string('product_id')->nullable();
            $table->json('discount')->nullable();
            $table->unsignedInteger('timer_seconds')->nullable();
            $table->unsignedTinyInteger('chain_order')->nullable();
            $table->string('status')->default('draft'); // draft|active|paused
            $table->timestamps();

            $table->index('shop_id');
            $table->index(['shop_id', 'surface', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
