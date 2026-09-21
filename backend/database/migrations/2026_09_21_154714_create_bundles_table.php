<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bundles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('config_id')->constrained('feature_configs')->cascadeOnDelete();
            $table->string('mode'); // fixed|mix_match|fbt
            $table->string('discount_type'); // percentage|fixed_amount
            $table->decimal('discount_value', 10, 2)->default(0);
            $table->timestamps();

            $table->index('config_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundles');
    }
};
