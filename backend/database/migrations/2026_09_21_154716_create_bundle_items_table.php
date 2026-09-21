<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_id')->constrained()->cascadeOnDelete();
            $table->string('product_id'); // Shopify product GID
            $table->string('variant_id')->nullable();
            $table->unsignedInteger('qty')->default(1);
            $table->timestamps();

            $table->index('bundle_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_items');
    }
};
