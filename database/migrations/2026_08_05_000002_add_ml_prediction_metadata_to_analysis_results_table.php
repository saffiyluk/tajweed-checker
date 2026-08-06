<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_results', function (Blueprint $table) {
            $table->string('predicted_rule')->nullable()->after('correctness');
            $table->string('classification_status')->nullable()->after('predicted_rule');
            $table->string('classification_method')->nullable()->after('classification_status');
            $table->json('class_probabilities')->nullable()->after('classification_method');
            $table->json('model_predictions')->nullable()->after('class_probabilities');
        });
    }

    public function down(): void
    {
        Schema::table('analysis_results', function (Blueprint $table) {
            $table->dropColumn([
                'predicted_rule',
                'classification_status',
                'classification_method',
                'class_probabilities',
                'model_predictions',
            ]);
        });
    }
};
