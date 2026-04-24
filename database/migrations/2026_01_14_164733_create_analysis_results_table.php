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
        Schema::create('analysis_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audio_id')->constrained('audio_recitations')->onDelete('cascade');
            $table->enum('correctness', ['correct', 'incorrect'])->nullable(); // Classification result
            $table->decimal('confidence_score', 5, 4); // 0.0000 to 1.0000
            $table->text('feedback_message')->nullable(); // User feedback
            $table->json('detected_errors')->nullable(); // Array of errors
            $table->json('suggestions')->nullable(); // Improvement suggestions
            $table->string('processing_status')->default('pending'); // pending/processing/completed/failed
            $table->timestamps();
            $table->index('audio_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analysis_results');
    }
};
