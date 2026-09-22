<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // The pixel's full event payload (page URL/title for
            // page_viewed, search query, collection/product ids, etc.) --
            // previously received by PixelEventController but discarded
            // except for checkout_completed. Needed to build anything
            // beyond raw counts (campaign targeting, time-on-page, funnel
            // paths) since that all depends on what each event was about,
            // not just that it happened.
            $table->json('data')->nullable()->after('session_ref');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('data');
        });
    }
};
