@extends('layouts.app')

@section('title', 'Tajweed Analysis Result')

@php
    $status = data_get($result, 'processing_status', data_get($result, 'status', 'completed'));
    $duration = data_get($result, 'audio.duration_seconds');
    $confidenceRaw = data_get($result, 'confidence_score', data_get($result, 'confidence', 0));
    $confidence = is_numeric($confidenceRaw) ? (float) $confidenceRaw : 0;
    $confidencePercent = $confidence <= 1 ? round($confidence * 100) : round($confidence);
    $feedback = data_get($result, 'feedback_message', data_get($result, 'feedback', 'No feedback available.'));
    $correctness = data_get($result, 'correctness');
    $isUnrelated = collect(data_get($result, 'detected_errors', []))->contains(fn($error) => data_get($error, 'type') === 'unrelated_audio');
    $transcribedText = trim((string) data_get($result, 'transcribed_text', ''));
    $hasTranscription = $transcribedText !== '' && $transcribedText !== 'Unable to transcribe audio';
    $transcriptionFailed = $transcribedText === 'Unable to transcribe audio';
    $audioId = data_get($result, 'audio.id', data_get($result, 'audio_id'));
    $rule = data_get($result, 'audio.tajweed_rule');
    $predictionFeedback = data_get($result, 'prediction_feedback');
    $transcriptionFeedback = data_get($result, 'transcription_feedback');
    $correctedRule = data_get($result, 'corrected_rule');
    $correctedTranscription = data_get($result, 'corrected_transcription');
    $correctionNote = data_get($result, 'correction_note');
    $correctionStatus = data_get($result, 'correction_review_status');
    $correctionSubmittedAt = data_get($result, 'correction_submitted_at');
    $hasDiacritics = (bool) preg_match('/[\x{064B}-\x{065F}\x{0670}]/u', $transcribedText);
    $targetAnalysis = collect(data_get($result, 'detected_errors', []))
        ->first(fn($error) => data_get($error, 'type') === 'target_analysis');
    $targetResults = collect(data_get($targetAnalysis, 'targets', []))
        ->filter(fn($target) => is_array($target))
        ->values();

    $normalizeArabicLetter = function (string $letter): string {
        return strtr($letter, [
            'أ' => 'ا',
            'إ' => 'ا',
            'آ' => 'ا',
            'ٱ' => 'ا',
            'ؤ' => 'ء',
            'ئ' => 'ء',
        ]);
    };

    $isArabicLetter = fn(string $char): bool => (bool) preg_match('/[\x{0621}-\x{064A}\x{0671}]/u', $char);
    $isArabicMark = fn(string $char): bool => (bool) preg_match('/[\x{064B}-\x{065F}\x{0670}\x{0640}\x{06D6}-\x{06ED}]/u', $char);
    $isWhitespace = fn(string $char): bool => (bool) preg_match('/\s/u', $char);

    $tajweedLetters = [
        'ikhfa' => ['ت', 'ث', 'ج', 'د', 'ذ', 'ز', 'س', 'ش', 'ص', 'ض', 'ط', 'ظ', 'ف', 'ق', 'ك'],
        'izhar' => ['ء', 'ا', 'ه', 'ع', 'ح', 'غ', 'خ'],
    ];

    $findPreviousArabicLetter = function (array $chars, int $index) use ($isArabicLetter): ?int {
        for ($i = $index; $i >= 0; $i--) {
            if ($isArabicLetter($chars[$i])) {
                return $i;
            }
        }

        return null;
    };

    $findNextArabicLetter = function (array $chars, int $index) use ($isArabicLetter, $isArabicMark, $isWhitespace): ?int {
        $count = count($chars);

        for ($i = $index; $i < $count; $i++) {
            if ($isArabicLetter($chars[$i])) {
                return $i;
            }

            if (!$isArabicMark($chars[$i]) && !$isWhitespace($chars[$i])) {
                continue;
            }
        }

        return null;
    };

    $highlightTajweed = function (string $text, ?string $rule) use ($tajweedLetters, $normalizeArabicLetter, $isArabicMark, $findPreviousArabicLetter, $findNextArabicLetter): array {
        if (!isset($tajweedLetters[$rule])) {
            return [new \Illuminate\Support\HtmlString(e($text)), 0];
        }

        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $targetLetters = array_flip(array_map($normalizeArabicLetter, $tajweedLetters[$rule]));
        $highlight = [];
        $matchCount = 0;

        foreach ($chars as $index => $char) {
            $isTanween = in_array($char, ['ً', 'ٌ', 'ٍ'], true);
            $isNoon = $normalizeArabicLetter($char) === 'ن';
            $hasSukun = false;
            $markEnd = $index;

            if ($isNoon) {
                for ($i = $index + 1; $i < count($chars) && $isArabicMark($chars[$i]); $i++) {
                    $markEnd = $i;
                    $hasSukun = $hasSukun || $chars[$i] === 'ْ';
                }
            }

            if (!$isTanween && (!$isNoon || !$hasSukun)) {
                continue;
            }

            $nextIndex = $findNextArabicLetter($chars, $markEnd + 1);

            if ($nextIndex === null) {
                continue;
            }

            $nextLetter = $normalizeArabicLetter($chars[$nextIndex]);

            if (!isset($targetLetters[$nextLetter])) {
                continue;
            }

            $start = $isTanween ? ($findPreviousArabicLetter($chars, $index - 1) ?? $index) : $index;

            for ($i = $start; $i <= $nextIndex; $i++) {
                $highlight[$i] = true;
            }

            $matchCount++;
        }

        if ($matchCount === 0) {
            return [new \Illuminate\Support\HtmlString(e($text)), 0];
        }

        $html = '';
        $isOpen = false;

        foreach ($chars as $index => $char) {
            if (isset($highlight[$index]) && !$isOpen) {
                $html .= '<mark class="tajweed-highlight tajweed-highlight-' . e((string) $rule) . '">';
                $isOpen = true;
            }

            if (!isset($highlight[$index]) && $isOpen) {
                $html .= '</mark>';
                $isOpen = false;
            }

            $html .= e($char);
        }

        if ($isOpen) {
            $html .= '</mark>';
        }

        return [new \Illuminate\Support\HtmlString($html), $matchCount];
    };

    $highlightTargetResults = function (string $text, $targets): array {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $classes = [];
        $matchCount = 0;

        foreach ($targets as $target) {
            $start = (int) data_get($target, 'position', -1);
            $end = (int) data_get($target, 'end_position', $start);

            if ($start < 0 || $end < $start) {
                continue;
            }

            $rule = data_get($target, 'rule', 'tajweed');
            $status = data_get($target, 'status', 'unknown');
            $class = 'tajweed-highlight tajweed-target-' . e((string) $status) . ' tajweed-rule-' . e((string) $rule);

            for ($i = $start; $i <= $end && $i < count($chars); $i++) {
                $classes[$i] = $class;
            }

            $matchCount++;
        }

        if ($matchCount === 0) {
            return [new \Illuminate\Support\HtmlString(e($text)), 0];
        }

        $html = '';
        $openClass = null;

        foreach ($chars as $index => $char) {
            $class = $classes[$index] ?? null;

            if ($class !== $openClass) {
                if ($openClass !== null) {
                    $html .= '</mark>';
                }

                if ($class !== null) {
                    $html .= '<mark class="' . $class . '">';
                }

                $openClass = $class;
            }

            $html .= e($char);
        }

        if ($openClass !== null) {
            $html .= '</mark>';
        }

        return [new \Illuminate\Support\HtmlString($html), $matchCount];
    };

    [$highlightedTranscription, $tajweedMatchCount] = $hasTranscription
        ? ($targetResults->isNotEmpty()
            ? $highlightTargetResults($transcribedText, $targetResults)
            : $highlightTajweed($transcribedText, $rule))
        : [null, 0];
@endphp

@section('content')
@php
    $statusClass = match($status) {
        'completed' => 'completed',
        'processing' => 'processing',
        'failed' => 'failed',
        default => 'pending',
    };

    $correctnessClass = $isUnrelated
        ? 'unrelated'
        : (($correctness === 'correct') ? 'correct' : (($correctness === 'incorrect') ? 'incorrect' : 'neutral'));

    $ruleLabel = $targetResults->isNotEmpty() ? 'Ikhfa & Izhar' : ($rule ? ucfirst($rule) : 'N/A');
@endphp

<style>
    .analysis-page {
        color: #0f172a;
    }

    .analysis-shell {
        max-width: 1180px;
        margin: 0 auto;
        padding: 2rem 1rem 4rem;
    }

    .result-hero {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: white;
        border-radius: 28px;
        padding: 2rem;
        box-shadow: 0 22px 60px rgba(37, 99, 235, 0.18);
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 1.5rem;
        position: relative;
        overflow: hidden;
        margin-bottom: 1.5rem;
    }

    .result-hero::after {
        content: "۞";
        position: absolute;
        right: 2rem;
        top: -1.2rem;
        font-size: 8rem;
        opacity: 0.08;
        font-family: serif;
    }

    .hero-content,
    .hero-status {
        position: relative;
        z-index: 1;
    }

    .hero-kicker,
    .section-label {
        display: inline-flex;
        align-items: center;
        color: #facc15;
        font-size: 0.78rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 0.55rem;
    }

    .result-hero h1 {
        margin: 0;
        font-size: clamp(2rem, 4vw, 3rem);
        font-weight: 900;
        letter-spacing: -0.04em;
    }

    .result-hero p {
        margin: 0.75rem 0 0;
        color: rgba(255, 255, 255, 0.84);
        line-height: 1.7;
        max-width: 680px;
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.55rem;
        border-radius: 999px;
        padding: 0.75rem 1rem;
        font-weight: 900;
        white-space: nowrap;
        border: 1px solid rgba(255, 255, 255, 0.25);
        background: rgba(255, 255, 255, 0.15);
    }

    .status-pill.completed {
        background: rgba(34, 197, 94, 0.18);
    }

    .status-pill.processing {
        background: rgba(6, 182, 212, 0.18);
    }

    .status-pill.failed {
        background: rgba(239, 68, 68, 0.18);
    }

    .overview-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .metric-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 22px;
        padding: 1.15rem;
        box-shadow: 0 12px 34px rgba(15, 23, 42, 0.06);
        display: flex;
        align-items: center;
        gap: 0.9rem;
    }

    .metric-icon {
        width: 48px;
        height: 48px;
        border-radius: 16px;
        display: grid;
        place-items: center;
        color: white;
        flex-shrink: 0;
    }

    .metric-icon.rule { background: linear-gradient(135deg, #2563eb, #1d4ed8); }
    .metric-icon.duration { background: linear-gradient(135deg, #7c3aed, #6d28d9); }
    .metric-icon.confidence { background: linear-gradient(135deg, #c29950, #a8792c); }
    .metric-icon.correct { background: linear-gradient(135deg, #16a34a, #15803d); }
    .metric-icon.incorrect { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .metric-icon.unrelated { background: linear-gradient(135deg, #dc2626, #b91c1c); }
    .metric-icon.neutral { background: linear-gradient(135deg, #64748b, #475569); }

    .metric-card span {
        display: block;
        color: #64748b;
        font-size: 0.78rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-bottom: 0.2rem;
    }

    .metric-card strong {
        display: block;
        color: #0f172a;
        font-size: 1.1rem;
        font-weight: 900;
        word-break: break-word;
    }

    .analysis-layout {
        display: grid;
        grid-template-columns: 1.4fr 0.85fr;
        gap: 1.5rem;
        align-items: start;
    }

    .clean-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 24px;
        padding: 1.5rem;
        box-shadow: 0 14px 38px rgba(15, 23, 42, 0.07);
        margin-bottom: 1.5rem;
    }

    .clean-card h2 {
        font-size: 1.35rem;
        font-weight: 900;
        letter-spacing: -0.03em;
        margin-bottom: 1rem;
    }

    .transcription-box {
        direction: rtl;
        text-align: right;
        font-family: "Amiri", "Scheherazade New", serif;
        font-size: 2rem;
        line-height: 2.25;
        color: #111827;
        background:
            radial-gradient(circle at top left, rgba(194, 153, 80, 0.10), transparent 35%),
            #fffaf0;
        border: 1px solid #fde68a;
        border-radius: 22px;
        padding: 1.25rem;
        white-space: pre-wrap;
        word-break: break-word;
    }

    .tajweed-highlight {
        border-radius: 8px;
        box-decoration-break: clone;
        -webkit-box-decoration-break: clone;
        color: inherit;
        padding: 0.08rem 0.18rem;
    }

    .tajweed-highlight-ikhfa {
        background: #dbeafe;
        box-shadow: inset 0 -0.18em 0 #60a5fa;
    }

    .tajweed-highlight-izhar {
        background: #dcfce7;
        box-shadow: inset 0 -0.18em 0 #4ade80;
    }

    .tajweed-target-correct {
        background: #dcfce7;
        box-shadow: inset 0 -0.2em 0 #22c55e;
    }

    .tajweed-target-incorrect {
        background: #fee2e2;
        box-shadow: inset 0 -0.2em 0 #ef4444;
    }

    .tajweed-rule-ikhfa {
        outline: 1px solid rgba(37, 99, 235, 0.22);
    }

    .tajweed-rule-izhar {
        outline: 1px solid rgba(20, 184, 166, 0.22);
    }

    .legend-box {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        flex-wrap: wrap;
        margin-top: 1rem;
        color: #64748b;
        font-weight: 750;
        font-size: 0.92rem;
    }

    .legend-swatch {
        width: 1.7rem;
        height: 1rem;
        border-radius: 6px;
        display: inline-block;
    }

    .info-note {
        background: #eff6ff;
        border: 1px solid #dbeafe;
        color: #1e40af;
        border-radius: 16px;
        padding: 0.85rem 1rem;
        margin-top: 0.85rem;
        font-size: 0.9rem;
        font-weight: 700;
        line-height: 1.6;
    }

    .empty-transcription {
        background: #fffbeb;
        color: #92400e;
        border: 1px solid #fde68a;
        border-radius: 18px;
        padding: 1rem 1.15rem;
        line-height: 1.7;
        font-weight: 700;
    }

    .feedback-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-left: 5px solid #2563eb;
        border-radius: 18px;
        padding: 1rem 1.15rem;
        color: #334155;
        line-height: 1.75;
        margin: 0;
        font-weight: 650;
    }

    .side-card {
        position: static;
    }

    .result-summary-list {
        display: grid;
        gap: 0.75rem;
    }

    .result-summary-list div {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 0.85rem;
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        font-size: 0.92rem;
    }

    .result-summary-list strong {
        color: #0f172a;
    }

    .result-summary-list span {
        color: #64748b;
        font-weight: 800;
        text-align: right;
    }

    .correction-status {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        background: #eef2ff;
        border: 1px solid #c7d2fe;
        border-radius: 18px;
        color: #3730a3;
        padding: 0.9rem 1rem;
        margin-bottom: 1rem;
        line-height: 1.6;
        font-weight: 700;
    }

    .alert-clean {
        border: none;
        border-radius: 18px;
        padding: 0.9rem 1rem;
        background: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca;
        font-weight: 700;
    }

    .correction-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }

    .correction-field.full {
        grid-column: 1 / -1;
    }

    .correction-field label {
        display: block;
        color: #334155;
        font-size: 0.88rem;
        font-weight: 900;
        margin-bottom: 0.45rem;
    }

    .form-select,
    .form-control {
        border-radius: 16px;
        border: 1px solid #dbe3ef;
        min-height: 50px;
        background-color: #f8fafc;
        font-weight: 700;
    }

    .form-select:focus,
    .form-control:focus {
        background-color: white;
        border-color: #2563eb;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
    }

    textarea.form-control {
        min-height: 120px;
        direction: rtl;
        text-align: right;
        font-family: "Amiri", "Scheherazade New", serif;
        font-size: 1.25rem;
        line-height: 1.8;
    }

    .btn-main,
    .btn-soft,
    .btn-warning-soft,
    .btn-danger-soft {
        border-radius: 16px;
        padding: 0.82rem 1rem;
        font-weight: 900;
        text-decoration: none;
        border: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
    }

    .btn-main {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: white;
        box-shadow: 0 14px 28px rgba(37, 99, 235, 0.20);
    }

    .btn-main:hover {
        color: white;
        transform: translateY(-1px);
    }

    .btn-soft {
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #dbeafe;
    }

    .btn-soft:hover {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .btn-warning-soft {
        background: #fffbeb;
        color: #b45309;
        border: 1px solid #fde68a;
    }

    .btn-warning-soft:hover {
        background: #f59e0b;
        color: white;
    }

    .action-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 24px;
        padding: 1.25rem;
        box-shadow: 0 14px 38px rgba(15, 23, 42, 0.07);
    }

    .action-grid {
        display: grid;
        gap: 0.75rem;
    }

    .dropdown-menu {
        border: 1px solid #e2e8f0;
        box-shadow: 0 20px 50px rgba(15, 23, 42, 0.14);
        border-radius: 18px;
        padding: 0.5rem;
    }

    .dropdown-item {
        border-radius: 14px;
        padding: 0.7rem 0.85rem;
        font-weight: 800;
    }

    @media (max-width: 992px) {
        .result-hero {
            align-items: flex-start;
            flex-direction: column;
        }

        .overview-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .analysis-layout {
            grid-template-columns: 1fr;
        }

        .side-card {
            position: static;
        }
    }

    @media (max-width: 768px) {
        .correction-grid {
            grid-template-columns: 1fr;
        }

        .transcription-box {
            font-size: 1.55rem;
        }
    }

    @media (max-width: 576px) {
        .analysis-shell {
            padding: 1rem 0.85rem 3rem;
        }

        .result-hero,
        .clean-card,
        .action-card {
            border-radius: 20px;
            padding: 1.15rem;
        }

        .overview-grid {
            grid-template-columns: 1fr;
        }

        .result-summary-list div {
            flex-direction: column;
            gap: 0.25rem;
        }

        .result-summary-list span {
            text-align: left;
        }

        .btn-main,
        .btn-soft,
        .btn-warning-soft {
            width: 100%;
        }
    }
</style>

<div class="analysis-page">
    <div class="analysis-shell">

        <div class="result-hero">
            <div class="hero-content">
                <span class="hero-kicker">
                    <i class="fas fa-chart-line me-2"></i>Analysis Result
                </span>
                <h1>Tajweed Analysis Result</h1>
                <p>
                    Review your recitation result, transcription, tajweed feedback, and submit corrections if needed.
                </p>
            </div>

            <div class="hero-status">
                <span class="status-pill {{ $statusClass }}">
                    @if($status === 'processing')
                        <i class="fas fa-spinner fa-spin"></i>
                    @elseif($status === 'failed')
                        <i class="fas fa-circle-xmark"></i>
                    @else
                        <i class="fas fa-circle-check"></i>
                    @endif
                    {{ ucfirst($status) }}
                </span>
            </div>
        </div>

        <div class="overview-grid">
            <div class="metric-card">
                <div class="metric-icon rule">
                    <i class="fas fa-book-quran"></i>
                </div>
                <div>
                    <span>Rule</span>
                    <strong>{{ $ruleLabel }}</strong>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-icon duration">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <span>Duration</span>
                    <strong>{{ $duration !== null ? $duration . 's' : 'N/A' }}</strong>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-icon confidence">
                    <i class="fas fa-gauge-high"></i>
                </div>
                <div>
                    <span>Confidence</span>
                    <strong>{{ $confidencePercent }}%</strong>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-icon {{ $correctnessClass }}">
                    @if($isUnrelated)
                        <i class="fas fa-triangle-exclamation"></i>
                    @elseif($correctness === 'correct')
                        <i class="fas fa-check"></i>
                    @elseif($correctness === 'incorrect')
                        <i class="fas fa-arrow-trend-up"></i>
                    @else
                        <i class="fas fa-circle-info"></i>
                    @endif
                </div>
                <div>
                    <span>Correctness</span>
                    <strong>{{ $isUnrelated ? 'Unrelated Audio' : ($correctness ? ucfirst($correctness) : 'N/A') }}</strong>
                </div>
            </div>
        </div>

        <div class="analysis-layout">
            <div>
                <div class="clean-card">
                    <span class="section-label">
                        <i class="fas fa-language me-2"></i>Transcription
                    </span>
                    <h2>What the audio says</h2>

                    @if($hasTranscription)
                        <div class="transcription-box" dir="rtl" lang="ar">
                            {!! $highlightedTranscription !!}
                        </div>

                        @if($tajweedMatchCount > 0)
                            <div class="legend-box">
                                @if($targetResults->isNotEmpty())
                                    <span class="legend-swatch tajweed-target-correct"></span>
                                    <span>Correct target</span>
                                    <span class="legend-swatch tajweed-target-incorrect"></span>
                                    <span>Incorrect target</span>
                                    <span>{{ $tajweedMatchCount }} Ikhfa/Izhar {{ \Illuminate\Support\Str::plural('target', $tajweedMatchCount) }} highlighted.</span>
                                @else
                                    <span class="legend-swatch tajweed-highlight-{{ $rule }}"></span>
                                    <span>
                                        {{ $tajweedMatchCount }} {{ ucfirst((string) $rule) }}
                                        {{ \Illuminate\Support\Str::plural('spot', $tajweedMatchCount) }} highlighted.
                                    </span>
                                @endif
                            </div>
                        @else
                            <div class="info-note">
                                <i class="fas fa-circle-info me-2"></i>
                                No Ikhfa or Izhar trigger was detected in this transcription.
                            </div>
                        @endif

                        @if($hasDiacritics)
                            <div class="info-note">
                                <i class="fas fa-circle-info me-2"></i>
                                Diacritical marks are shown from the selected ayah when available, or from AI diacritization for direct uploads.
                            </div>
                        @endif
                    @else
                        <div class="empty-transcription">
                            <i class="fas fa-circle-info me-2"></i>
                            {{ $transcriptionFailed ? 'The app tried to transcribe this recording, but no Arabic text was detected clearly enough.' : 'No ayah was selected for this recording, so there is no ayah text to display.' }}
                        </div>
                    @endif
                </div>

                <div class="clean-card">
                    <span class="section-label">
                        <i class="fas fa-comment-dots me-2"></i>Feedback
                    </span>
                    <h2>AI Feedback</h2>
                    <p class="feedback-box">{{ $feedback }}</p>
                </div>

                @if($audioId)
                    <div class="clean-card">
                        <span class="section-label">
                            <i class="fas fa-pen-to-square me-2"></i>Correction
                        </span>
                        <h2>Help improve this result</h2>

                        @if($correctionSubmittedAt)
                            <div class="correction-status">
                                <i class="fas fa-circle-check"></i>
                                <span>
                                    Correction submitted {{ $correctionSubmittedAt->diffForHumans() }}.
                                    Review status: <strong>{{ ucfirst($correctionStatus ?? 'pending') }}</strong>
                                </span>
                            </div>
                        @endif

                        @if($errors->any())
                            <div class="alert-clean mb-4">
                                <i class="fas fa-circle-exclamation me-2"></i>
                                Please check the correction form and try again.
                            </div>
                        @endif

                        <form method="POST" action="{{ route('tajweed.correction.store', $audioId) }}">
                            @csrf

                            <div class="correction-grid">
                                <div class="correction-field">
                                    <label for="prediction_feedback">Was the Tajweed prediction correct?</label>
                                    <select id="prediction_feedback" name="prediction_feedback" class="form-select" required>
                                        <option value="">Choose one</option>
                                        @foreach(['correct' => 'Correct', 'incorrect' => 'Incorrect', 'unsure' => 'Not sure'] as $value => $label)
                                            <option value="{{ $value }}" @selected(old('prediction_feedback', $predictionFeedback) === $value)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="correction-field">
                                    <label for="transcription_feedback">Was the transcript correct?</label>
                                    <select id="transcription_feedback" name="transcription_feedback" class="form-select" required>
                                        <option value="">Choose one</option>
                                        @foreach(['correct' => 'Correct', 'incorrect' => 'Incorrect', 'unsure' => 'Not sure'] as $value => $label)
                                            <option value="{{ $value }}" @selected(old('transcription_feedback', $transcriptionFeedback) === $value)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="correction-field">
                                    <label for="corrected_rule">Correct Tajweed rule</label>
                                    <select id="corrected_rule" name="corrected_rule" class="form-select">
                                        <option value="">Keep current rule</option>
                                        @foreach(['ikhfa' => 'Ikhfa', 'izhar' => 'Izhar', 'other' => 'Other'] as $value => $label)
                                            <option value="{{ $value }}" @selected(old('corrected_rule', $correctedRule) === $value)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="correction-field">
                                    <label for="correction_note">Short note</label>
                                    <input id="correction_note" name="correction_note" class="form-control"
                                        value="{{ old('correction_note', $correctionNote) }}"
                                        placeholder="Example: transcript missed the first word">
                                </div>

                                <div class="correction-field full">
                                    <label for="corrected_transcription">Correct transcript</label>
                                    <textarea id="corrected_transcription" name="corrected_transcription" class="form-control"
                                        dir="rtl" lang="ar"
                                        placeholder="Paste or type the corrected Arabic transcript here">{{ old('corrected_transcription', $correctedTranscription) }}</textarea>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-main mt-3">
                                <i class="fas fa-paper-plane"></i>
                                Submit Correction
                            </button>
                        </form>
                    </div>
                @endif
            </div>

            <div>
                <div class="clean-card side-card">
                    <span class="section-label">
                        <i class="fas fa-circle-info me-2"></i>Result Summary
                    </span>
                    <h2>Details</h2>

                    <div class="result-summary-list">
                        <div>
                            <strong>Status</strong>
                            <span>{{ ucfirst($status) }}</span>
                        </div>
                        <div>
                            <strong>Rule</strong>
                            <span>{{ $ruleLabel }}</span>
                        </div>
                        <div>
                            <strong>Confidence</strong>
                            <span>{{ $confidencePercent }}%</span>
                        </div>
                        <div>
                            <strong>Match Count</strong>
                            <span>{{ $tajweedMatchCount }}</span>
                        </div>
                        <div>
                            <strong>Transcription</strong>
                            <span>{{ $hasTranscription ? 'Available' : 'Not available' }}</span>
                        </div>
                    </div>
                </div>

                <div class="action-card">
                    <span class="section-label">
                        <i class="fas fa-bolt me-2"></i>Actions
                    </span>
                    <h2>Next Step</h2>

                    <div class="action-grid">
                        <div class="dropdown">
                            <button class="btn btn-main dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-microphone"></i>
                                Practice Again
                            </button>

                            <ul class="dropdown-menu w-100">
                                <li>
                                    <a class="dropdown-item" href="{{ route('tajweed.checker') }}">
                                        <i class="fas fa-wand-magic-sparkles me-2"></i>
                                        Combined Checker
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('tajweed.ikhfa-haqiqi') }}">
                                        <i class="fas fa-wave-square me-2"></i>
                                        Ikhfa Haqiqi Practice
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('tajweed.izhar-halqi') }}">
                                        <i class="fas fa-volume-up me-2"></i>
                                        Izhar Halqi Practice
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <a href="{{ route('tajweed.history') }}" class="btn btn-soft">
                            <i class="fas fa-clock-rotate-left"></i>
                            View History
                        </a>

                        @if($audioId)
                            <form method="POST" action="{{ route('tajweed.reanalyze', $audioId) }}">
                                @csrf
                                <button type="submit" class="btn btn-warning-soft">
                                    <i class="fas fa-rotate-right"></i>
                                    Re-run Analysis
                                </button>
                            </form>

                            <a href="{{ route('tajweed.play-audio', $audioId) }}" class="btn btn-soft" target="_blank">
                                <i class="fas fa-play"></i>
                                Play Audio
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
