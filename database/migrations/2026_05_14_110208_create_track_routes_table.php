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
        Schema::create('track_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->date('activity_date');
            $table->string('activity_type', 32)->index();
            $table->longText('comment')->nullable();
            $table->json('points');
            $table->unsignedInteger('distance_m')->default(0);
            $table->unsignedInteger('elevation_gain_m')->default(0);
            $table->unsignedInteger('elevation_loss_m')->default(0);
            $table->boolean('is_shared')->default(false);
            $table->string('share_token', 64)->nullable()->unique();
            $table->timestamps();

            $table->index(['user_id', 'activity_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('track_routes');
    }
};
