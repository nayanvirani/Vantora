<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->string('code'); // e.g. F-01-sticky-cta
            $table->string('severity'); // high|medium|low
            $table->string('area');
            $table->string('title');
            $table->text('explanation')->nullable();
            $table->string('status')->default('open'); // open|fixed|dismissed|snoozed
            $table->string('fix_type')->nullable();
            $table->timestamps();

            $table->index('audit_id');
            $table->index(['audit_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_issues');
    }
};
