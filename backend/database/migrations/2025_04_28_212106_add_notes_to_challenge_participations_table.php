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
            $table->text('notes')->nullable()->after('proof_media_id');
            // Ajoutons également les autres champs que vous utilisez mais qui ne sont pas dans le schéma
            $table->text('feedback')->nullable()->after('notes');
            $table->integer('rating')->nullable()->after('feedback');
            $table->unsignedBigInteger('invited_by')->nullable()->after('rating');
            $table->text('invitation_message')->nullable()->after('invited_by');
            $table->dateTime('started_at')->nullable()->after('invitation_message');
            
            // Ajout de clé étrangère si nécessaire
            $table->foreign('invited_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('challenge_participations', function (Blueprint $table) {
            $table->dropForeign(['invited_by']);
            $table->dropColumn(['notes', 'feedback', 'rating', 'invited_by', 'invitation_message', 'started_at']);
        });
    }
};
