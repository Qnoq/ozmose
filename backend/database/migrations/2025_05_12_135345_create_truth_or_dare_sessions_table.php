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
        Schema::create('truth_or_dare_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained('users')->onDelete('cascade');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->enum('intensity', ['soft', 'spicy', 'hot'])->default('soft');
            $table->boolean('is_public')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('join_code', 6)->unique()->nullable(); // Code pour rejoindre
            $table->integer('max_participants')->default(10);
            $table->boolean('premium_only')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('truth_or_dare_sessions');
    }
};
