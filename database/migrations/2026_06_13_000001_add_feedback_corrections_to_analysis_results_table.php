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
        Schema::table('analysis_results', function (Blueprint $table) {
            $table->string('prediction_feedback')->nullable()->after('processing_status');
            $table->string('transcription_feedback')->nullable()->after('prediction_feedback');
            $table->string('corrected_rule')->nullable()->after('transcription_feedback');
            $table->text('corrected_transcription')->nullable()->after('corrected_rule');
            $table->text('correction_note')->nullable()->after('corrected_transcription');
            $table->string('correction_review_status')->default('pending')->after('correction_note');
            $table->text('correction_admin_note')->nullable()->after('correction_review_status');
            $table->foreignId('correction_submitted_by')->nullable()->after('correction_admin_note')->constrained('users')->nullOnDelete();
            $table->foreignId('correction_reviewed_by')->nullable()->after('correction_submitted_by')->constrained('users')->nullOnDelete();
            $table->timestamp('correction_submitted_at')->nullable()->after('correction_reviewed_by');
            $table->timestamp('correction_reviewed_at')->nullable()->after('correction_submitted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analysis_results', function (Blueprint $table) {
            $table->dropForeign(['correction_submitted_by']);
            $table->dropForeign(['correction_reviewed_by']);

            $table->dropColumn([
                'prediction_feedback',
                'transcription_feedback',
                'corrected_rule',
                'corrected_transcription',
                'correction_note',
                'correction_review_status',
                'correction_admin_note',
                'correction_submitted_by',
                'correction_reviewed_by',
                'correction_submitted_at',
                'correction_reviewed_at',
            ]);
        });
    }
};
