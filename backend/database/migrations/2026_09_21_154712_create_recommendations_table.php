<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('issue_code');
            $table->unsignedInteger('priority')->default(0);
            $table->string('impact')->nullable(); // high|medium|low
            $table->string('effort')->nullable(); // high|medium|low
            $table->json('preset')->nullable();
            $table->string('status')->default('open'); // open|dismissed|snoozed|applied
            $table->timestamps();

            $table->index('shop_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendations');
    }
};
