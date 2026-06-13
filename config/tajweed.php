<?php

return [
    'python_binary' => env('PYTHON_BINARY', 'python'),
    'use_firebase_storage' => filter_var(env('TAJWEED_USE_FIREBASE_STORAGE', false), FILTER_VALIDATE_BOOLEAN),
    'enable_service_analysis' => filter_var(env('TAJWEED_ENABLE_SERVICE_ANALYSIS', false), FILTER_VALIDATE_BOOLEAN),
    'whisper_model' => env('WHISPER_MODEL', 'small'),
    'prediction_timeout' => (int) env('TAJWEED_PREDICTION_TIMEOUT', 60),
    'transcription_timeout' => (int) env('WHISPER_TRANSCRIPTION_TIMEOUT', 180),
    'enable_transcription' => filter_var(env('TAJWEED_ENABLE_TRANSCRIPTION', true), FILTER_VALIDATE_BOOLEAN),
    'enable_diacritization' => filter_var(env('TAJWEED_ENABLE_DIACRITIZATION', true), FILTER_VALIDATE_BOOLEAN),
    'enable_quran_matching' => filter_var(env('TAJWEED_ENABLE_QURAN_MATCHING', true), FILTER_VALIDATE_BOOLEAN),
    'quran_match_threshold' => (float) env('TAJWEED_QURAN_MATCH_THRESHOLD', 72),
    'enable_ai_feedback' => filter_var(env('TAJWEED_ENABLE_AI_FEEDBACK', false), FILTER_VALIDATE_BOOLEAN),
    'unrelated_confidence_threshold' => (int) env('TAJWEED_UNRELATED_CONFIDENCE_THRESHOLD', 55),
    'unrelated_margin_threshold' => (int) env('TAJWEED_UNRELATED_MARGIN_THRESHOLD', 10),
    'opposite_rule_confidence_threshold' => (int) env('TAJWEED_OPPOSITE_RULE_CONFIDENCE_THRESHOLD', 45),
    'strong_other_confidence_threshold' => (int) env('TAJWEED_STRONG_OTHER_CONFIDENCE_THRESHOLD', 65),
];
