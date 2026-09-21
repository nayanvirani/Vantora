<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->string('area'); // product_page|cart|trust|offers|mobile
            $table->unsignedTinyInteger('score');
            $table->timestamps();

            $table->index('audit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_scores');
    }
};
