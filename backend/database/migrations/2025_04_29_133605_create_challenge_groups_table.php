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
        Schema::create('challenge_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('creator_id')->constrained('users')->onDelete('cascade');
            $table->boolean('premium_only')->default(true);
            $table->integer('max_members')->default(10); // Différent selon le statut premium
            $table->timestamps();
        });
        
        Schema::create('challenge_group_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_group_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('role')->default('member'); // creator, admin, member
            $table->timestamps();
        });
        
        Schema::create('challenge_group_challenge', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_group_id')->constrained()->onDelete('cascade');
            $table->foreignId('challenge_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('challenge_groups');
        Schema::dropIfExists('challenge_group_user');
        Schema::dropIfExists('challenge_group_challenge');
    }
};
