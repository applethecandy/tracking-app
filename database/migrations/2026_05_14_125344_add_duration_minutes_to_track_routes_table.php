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
        Schema::table('track_routes', function (Blueprint $table) {
            $table->unsignedInteger('duration_minutes')->nullable()->after('activity_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('track_routes', function (Blueprint $table) {
            $table->dropColumn('duration_minutes');
        });
    }
};
