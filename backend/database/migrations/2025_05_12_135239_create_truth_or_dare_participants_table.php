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
        Schema::create('truth_or_dare_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('truth_or_dare_sessions')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade'); // Nullable pour invités
            $table->string('guest_name')->nullable(); // Nom pour les invités
            $table->string('guest_avatar')->nullable(); // Avatar/emoji pour les invités
            $table->enum('status', ['active', 'left', 'kicked'])->default('active');
            $table->integer('truths_answered')->default(0);
            $table->integer('dares_completed')->default(0);
            $table->integer('skips_used')->default(0);
            $table->integer('turn_order')->default(0); // Ordre dans le tour
            $table->timestamps();
            
            // Index unique pour éviter les doublons
            $table->unique(['session_id', 'user_id']);
            $table->index(['session_id', 'guest_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('truth_or_dare_participants');
    }
};
