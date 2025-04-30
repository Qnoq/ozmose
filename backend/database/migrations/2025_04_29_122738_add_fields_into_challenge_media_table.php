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
            // Ajout des nouveaux champs
            $table->string('original_name')->nullable()->after('file_path');
            $table->unsignedBigInteger('size')->nullable()->after('original_name');
            $table->string('mime_type')->nullable()->after('size');
            $table->unsignedInteger('width')->nullable()->after('mime_type');
            $table->unsignedInteger('height')->nullable()->after('width');
            $table->float('duration')->nullable()->after('height');
            $table->boolean('is_public')->default(true)->after('duration');
            $table->unsignedInteger('order')->default(0)->after('is_public');
            $table->string('storage_disk')->default('public')->after('order');

            $table->index(['challenge_id', 'type']);
            $table->index(['participation_id', 'type']);
            $table->index(['user_id', 'is_public']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('challenge_media', function (Blueprint $table) {
            $table->dropColumn([
                'original_name',
                'size',
                'mime_type',
                'width',
                'height',
                'duration',
                'is_public',
                'order',
                'storage_disk'
            ]);
        });
    }
};
