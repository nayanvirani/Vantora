<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('period'); // YYYY-MM
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['shop_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage');
    }
};
