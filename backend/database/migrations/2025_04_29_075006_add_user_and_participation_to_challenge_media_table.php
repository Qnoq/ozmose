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
        Schema::table('challenge_media', function (Blueprint $table) {
            // Ajouter user_id et participation_id
            $table->unsignedBigInteger('user_id')->nullable()->after('challenge_id');
            $table->unsignedBigInteger('participation_id')->nullable()->after('user_id');
            
            // Ajouter les nouveaux champs pour la gestion des preuves
            $table->string('type')->nullable()->after('participation_id');
            $table->string('path')->nullable()->after('type');
            $table->string('caption')->nullable()->after('path');
            
            // Ajouter les clés étrangères
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
                  
            $table->foreign('participation_id')
                  ->references('id')
                  ->on('challenge_participations')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('challenge_media', function (Blueprint $table) {
            // Supprimer les clés étrangères d'abord
            $table->dropForeign(['user_id']);
            $table->dropForeign(['participation_id']);
            
            // Puis supprimer les colonnes
            $table->dropColumn(['user_id', 'participation_id', 'type', 'path', 'caption']);
        });
    }
};
