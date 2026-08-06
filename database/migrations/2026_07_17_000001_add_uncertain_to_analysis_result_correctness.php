<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE analysis_results MODIFY correctness ENUM('correct', 'incorrect', 'uncertain') NULL"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('analysis_results')
            ->where('correctness', 'uncertain')
            ->update(['correctness' => null]);

        DB::statement(
            "ALTER TABLE analysis_results MODIFY correctness ENUM('correct', 'incorrect') NULL"
        );
    }
};
