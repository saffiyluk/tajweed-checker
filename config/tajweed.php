<?php

$defaultWhisperModel = is_dir(base_path('python/models/tarteel-ai-whisper-base-ar-quran'))
    ? base_path('python/models/tarteel-ai-whisper-base-ar-quran')
    : 'small';
$elongationThresholdMs = (float) env('TAJWEED_ELONGATION_THRESHOLD_MS', 50);
$elongationLocalWindowSeconds = (float) env('TAJWEED_ELONGATION_LOCAL_WINDOW_SECONDS', 0.60);

return [
    'python_binary' => env('PYTHON_BINARY', 'python'),
    'use_firebase_storage' => filter_var(env('TAJWEED_USE_FIREBASE_STORAGE', false), FILTER_VALIDATE_BOOLEAN),
    'firebase_connect_timeout' => (int) env('TAJWEED_FIREBASE_CONNECT_TIMEOUT', 3),
    'firebase_download_timeout' => (int) env('TAJWEED_FIREBASE_DOWNLOAD_TIMEOUT', 10),
    'enable_firestore_sync' => filter_var(env('TAJWEED_ENABLE_FIRESTORE_SYNC', false), FILTER_VALIDATE_BOOLEAN),
    'enable_service_analysis' => filter_var(env('TAJWEED_ENABLE_SERVICE_ANALYSIS', false), FILTER_VALIDATE_BOOLEAN),
    'whisper_model' => env('WHISPER_MODEL') ?: $defaultWhisperModel,
    'memorization_whisper_model' => env('HAFALAN_WHISPER_MODEL', env('WHISPER_MODEL', 'tiny')),
    'prediction_timeout' => (int) env('TAJWEED_PREDICTION_TIMEOUT', 60),
    'enable_quran_pronunciation_model' => filter_var(env('TAJWEED_ENABLE_QURAN_PRONUNCIATION_MODEL', true), FILTER_VALIDATE_BOOLEAN),
    'quran_pronunciation_model' => env('QURAN_MUAALEM_MODEL', base_path('python/models/muaalem-model-v3_2')),
    'quran_pronunciation_device' => env('QURAN_MUAALEM_DEVICE', ''),
    'quran_pronunciation_timeout' => (int) env('QURAN_MUAALEM_TIMEOUT', 120),
    'quran_pronunciation_min_model_confidence' => (float) env('QURAN_MUAALEM_MIN_MODEL_CONFIDENCE', 0.72),
    'quran_pronunciation_high_target_confidence' => (float) env('QURAN_MUAALEM_HIGH_TARGET_CONFIDENCE', 0.82),
    'quran_pronunciation_min_target_alignment' => (float) env('QURAN_MUAALEM_MIN_TARGET_ALIGNMENT_COVERAGE', 0.85),
    'quran_pronunciation_max_content_per' => (float) env('QURAN_MUAALEM_MAX_CONTENT_PER', 0.35),
    'quran_pronunciation_max_correct_target_per' => (float) env('QURAN_MUAALEM_MAX_CORRECT_TARGET_PER', 0.10),
    'quran_pronunciation_audio_cleaning' => filter_var(env('QURAN_MUAALEM_ENABLE_AUDIO_CLEANING', true), FILTER_VALIDATE_BOOLEAN),
    'quran_pronunciation_noise_reduction_amount' => (float) env('QURAN_MUAALEM_NOISE_REDUCTION_AMOUNT', 0.18),
    'quran_pronunciation_target_rms' => (float) env('QURAN_MUAALEM_TARGET_RMS', 0.08),
    'enable_hybrid_rule_audio_fallback' => filter_var(env('TAJWEED_ENABLE_HYBRID_RULE_AUDIO_FALLBACK', true), FILTER_VALIDATE_BOOLEAN),
    'hybrid_rule_audio_min_confidence' => (float) env('TAJWEED_HYBRID_RULE_AUDIO_MIN_CONFIDENCE', 68),
    'target_ml_trust_threshold' => (float) env('TAJWEED_TARGET_ML_TRUST_THRESHOLD', 0.78),
    'target_ml_strong_threshold' => (float) env('TAJWEED_TARGET_ML_STRONG_THRESHOLD', 0.88),
    // Izhar verdicts require calibrated target-local model evidence. Whole-audio
    // nasal energy and acoustic fallback rules remain diagnostics only.
    'hybrid_rule_audio_fallback_rules' => ['ikhfa'],
    'use_noisereduce_library' => filter_var(env('TAJWEED_USE_NOISEREDUCE_LIBRARY', false), FILTER_VALIDATE_BOOLEAN),
    'librosa_trim_top_db' => (float) env('TAJWEED_LIBROSA_TRIM_TOP_DB', 28),
    'transcription_timeout' => (int) env('WHISPER_TRANSCRIPTION_TIMEOUT', 180),
    'enable_transcription' => filter_var(env('TAJWEED_ENABLE_TRANSCRIPTION', true), FILTER_VALIDATE_BOOLEAN),
    'min_browser_transcript_letters' => (int) env('TAJWEED_MIN_BROWSER_TRANSCRIPT_LETTERS', 8),
    'enable_diacritization' => filter_var(env('TAJWEED_ENABLE_DIACRITIZATION', true), FILTER_VALIDATE_BOOLEAN),
    'enable_quran_matching' => filter_var(env('TAJWEED_ENABLE_QURAN_MATCHING', true), FILTER_VALIDATE_BOOLEAN),
    'quran_match_threshold' => (float) env('TAJWEED_QURAN_MATCH_THRESHOLD', 72),
    // Actual browser/Whisper speech is compared separately with the selected
    // reference. Direct similarity may verify a match, but a mismatch requires
    // a high-score, high-margin Quran corpus coordinate match to another ayah.
    'min_selected_ayah_match_letters' => (int) env('TAJWEED_MIN_SELECTED_AYAH_MATCH_LETTERS', 8),
    'selected_ayah_match_threshold' => (float) env('TAJWEED_SELECTED_AYAH_MATCH_THRESHOLD', 62),
    'selected_ayah_near_exact_match_threshold' => (float) env('TAJWEED_SELECTED_AYAH_NEAR_EXACT_MATCH_THRESHOLD', 90),
    'selected_ayah_coordinate_mismatch_min_score' => (float) env('TAJWEED_SELECTED_AYAH_COORDINATE_MISMATCH_MIN_SCORE', 85),
    'selected_ayah_coordinate_mismatch_min_margin' => (float) env('TAJWEED_SELECTED_AYAH_COORDINATE_MISMATCH_MIN_MARGIN', 5),
    'enable_ai_feedback' => filter_var(env('TAJWEED_ENABLE_AI_FEEDBACK', false), FILTER_VALIDATE_BOOLEAN),
    'enable_rule_based_analysis' => filter_var(env('TAJWEED_ENABLE_RULE_BASED_ANALYSIS', true), FILTER_VALIDATE_BOOLEAN),
    'unrelated_confidence_threshold' => (int) env('TAJWEED_UNRELATED_CONFIDENCE_THRESHOLD', 55),
    'unrelated_margin_threshold' => (int) env('TAJWEED_UNRELATED_MARGIN_THRESHOLD', 10),
    'opposite_rule_confidence_threshold' => (int) env('TAJWEED_OPPOSITE_RULE_CONFIDENCE_THRESHOLD', 45),
    'strong_other_confidence_threshold' => (int) env('TAJWEED_STRONG_OTHER_CONFIDENCE_THRESHOLD', 65),

    // Target-window diagnostic / timing-aligned acoustic fallback boundary:
    // Ikhfa below it is short; Izhar above it is long. Equality passes.
    'elongation_threshold_ms' => $elongationThresholdMs,
    'elongation_local_window_seconds' => $elongationLocalWindowSeconds,

    'ikhfa_min_ghunnah_ms' => (int) env('TAJWEED_IKHFA_MIN_GHUNNAH_MS', 80),
    'ikhfa_min_local_ghunnah_ms' => (int) env('TAJWEED_IKHFA_MIN_LOCAL_GHUNNAH_MS', $elongationThresholdMs),
    'ikhfa_ml_confidence_threshold' => (int) env('TAJWEED_IKHFA_ML_CONFIDENCE_THRESHOLD', 75),

    'izhar_max_ghunnah_ms' => (int) env('TAJWEED_IZHAR_MAX_GHUNNAH_MS', $elongationThresholdMs),
    'izhar_strong_error_min_ghunnah_ms' => (float) env('TAJWEED_IZHAR_STRONG_ERROR_MIN_GHUNNAH_MS', 70),
    'izhar_strong_error_min_ratio' => (float) env('TAJWEED_IZHAR_STRONG_ERROR_MIN_RATIO', 0.04),
    'izhar_strong_error_min_strength' => (float) env('TAJWEED_IZHAR_STRONG_ERROR_MIN_STRENGTH', 0.20),
    'izhar_strong_error_min_score' => (float) env('TAJWEED_IZHAR_STRONG_ERROR_MIN_SCORE', 0.22),
    'ikhfa_strong_error_max_ghunnah_ms' => (float) env('TAJWEED_IKHFA_STRONG_ERROR_MAX_GHUNNAH_MS', 30),
    'ikhfa_strong_error_max_ratio' => (float) env('TAJWEED_IKHFA_STRONG_ERROR_MAX_RATIO', 0.008),
    'ikhfa_strong_error_max_strength' => (float) env('TAJWEED_IKHFA_STRONG_ERROR_MAX_STRENGTH', 0.10),
    'ikhfa_strong_error_max_score' => (float) env('TAJWEED_IKHFA_STRONG_ERROR_MAX_SCORE', 0.10),

    'target_match_window_ms' => (int) env('TAJWEED_TARGET_MATCH_WINDOW_MS', 1400),
];
