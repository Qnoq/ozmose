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
        Schema::table('challenge_participations', function (Blueprint $table) {
            $table->dateTime('abandoned_at')->nullable();
            $table->dateTime('feedback_at')->nullable();
            $table->unsignedBigInteger('proof_media_id')->nullable();
            
            // Clé étrangère vers challenge_media si nécessaire
            $table->foreign('proof_media_id')
                  ->references('id')
                  ->on('challenge_media')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('challenge_participations', function (Blueprint $table) {
            $table->dropForeign(['proof_media_id']);
            $table->dropColumn('abandoned_at');
            $table->dropColumn('feedback_at');
            $table->dropColumn('proof_media_id');
        });
    }
};
