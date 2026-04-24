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
        Schema::create('audio_recitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('audio_file_path'); // Firebase Storage path
            $table->enum('tajweed_rule', ['ikhfa', 'izhar']); // Rule type
            $table->string('original_filename');
            $table->integer('duration_seconds')->nullable(); // Audio duration
            $table->string('firebase_url')->nullable(); // Public Firebase URL
            $table->timestamps();
            $table->index('user_id');
            $table->index('tajweed_rule');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audio_recitations');
    }
};
