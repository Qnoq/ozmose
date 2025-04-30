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
        Schema::table('challenges', function (Blueprint $table) {
            $table->boolean('premium_only')->default(false);
            $table->boolean('multi_stage')->default(false);  // Défis à plusieurs étapes
            $table->boolean('scheduled')->default(false);    // Défis programmés
            $table->timestamp('scheduled_for')->nullable();  // Date de programmation
            $table->smallInteger('media_limit')->default(1); // Limite de médias par preuve
            $table->smallInteger('video_duration_limit')->default(60); // En secondes
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->dropColumn('premium_only');
            $table->dropColumn('multi_stage');
            $table->dropColumn('scheduled');
            $table->dropColumn('scheduled_for');
            $table->dropColumn('media_limit');
            $table->dropColumn('video_duration_limit');
        });
    }
};
