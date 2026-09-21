<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('config_id')->nullable()->constrained('feature_configs')->nullOnDelete();
            $table->string('type'); // impression|click|add|accept
            $table->string('session_ref')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index('shop_id');
            $table->index(['shop_id', 'config_id', 'type']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
