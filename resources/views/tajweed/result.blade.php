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
        $textHasDiacritics = (bool) preg_match('/[\x{064B}-\x{065F}\x{0670}]/u', $text);
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

            if (!$isTanween && (!$isNoon || ($textHasDiacritics && !$hasSukun))) {
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

    [$highlightedTranscription, $tajweedMatchCount] = $hasTranscription
        ? $highlightTajweed($transcribedText, $rule)
        : [null, 0];
@endphp

@section('content')
    <style>
        .result-page {
            max-width: 980px;
            margin: 0 auto;
        }

        .result-panel {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .result-header-panel {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: flex-start;
            padding: 1.5rem;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .result-title {
            margin: 0;
            font-size: 1.75rem;
            font-weight: 700;
            color: #0f172a;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.45rem 0.8rem;
            border-radius: 999px;
            background: #e0f2fe;
            color: #075985;
            font-weight: 600;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            border-bottom: 1px solid #e2e8f0;
        }

        .summary-item {
            padding: 1.25rem 1.5rem;
            border-right: 1px solid #e2e8f0;
        }

        .summary-item:last-child {
            border-right: 0;
        }

        .summary-label {
            color: #64748b;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: 0.35rem;
        }

        .summary-value {
            color: #0f172a;
            font-size: 1.35rem;
            font-weight: 700;
        }

        .result-section {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .result-section:last-child {
            border-bottom: 0;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            margin: 0 0 0.9rem;
            color: #1e293b;
            font-size: 1rem;
            font-weight: 700;
        }

        .transcription-box {
            direction: rtl;
            text-align: right;
            font-family: 'Amiri', 'Scheherazade New', serif;
            font-size: 1.55rem;
            line-height: 2;
            color: #111827;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 1rem 1.25rem;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .tajweed-highlight {
            border-radius: 6px;
            box-decoration-break: clone;
            -webkit-box-decoration-break: clone;
            color: inherit;
            padding: 0.08rem 0.18rem;
        }

        .tajweed-highlight-ikhfa {
            background: #dbeafe;
            box-shadow: inset 0 -0.16em 0 #60a5fa;
        }

        .tajweed-highlight-izhar {
            background: #dcfce7;
            box-shadow: inset 0 -0.16em 0 #4ade80;
        }

        .tajweed-legend {
            align-items: center;
            color: #64748b;
            display: flex;
            flex-wrap: wrap;
            font-size: 0.9rem;
            gap: 0.75rem;
            margin-top: 0.75rem;
        }

        .tajweed-legend-swatch {
            border-radius: 4px;
            display: inline-block;
            height: 0.9rem;
            width: 1.4rem;
        }

        .empty-transcription {
            direction: ltr;
            text-align: left;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            line-height: 1.6;
            color: #92400e;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 1rem 1.25rem;
        }

        .feedback-box {
            color: #334155;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem 1.25rem;
            margin: 0;
        }

        .correction-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }

        .correction-field label {
            color: #334155;
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 0.4rem;
        }

        .correction-field.full {
            grid-column: 1 / -1;
        }

        .correction-field textarea {
            min-height: 96px;
        }

        .correction-status {
            align-items: center;
            background: #eef2ff;
            border: 1px solid #c7d2fe;
            border-radius: 8px;
            color: #3730a3;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
            padding: 0.85rem 1rem;
        }

        .result-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            padding: 1.5rem;
            background: #ffffff;
        }

        @media (max-width: 768px) {
            .result-header-panel {
                flex-direction: column;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .summary-item {
                border-right: 0;
                border-bottom: 1px solid #e2e8f0;
            }

            .summary-item:last-child {
                border-bottom: 0;
            }

            .transcription-box {
                font-size: 1.3rem;
            }

            .correction-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="result-page">
        <div class="result-panel">
            <div class="result-header-panel">
                <div>
                    <h1 class="result-title">Tajweed Analysis Result</h1>
                    @if($rule)
                        <div class="text-muted mt-2">Rule: {{ ucfirst($rule) }}</div>
                    @endif
                </div>

                <div class="status-badge">
                    <i class="fas fa-circle-check"></i>
                    {{ ucfirst($status) }}
                </div>
            </div>

            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-label">Duration</div>
                    <div class="summary-value">
                        {{ $duration !== null ? $duration . 's' : 'N/A' }}
                    </div>
                </div>

                <div class="summary-item">
                    <div class="summary-label">Confidence</div>
                    <div class="summary-value">{{ $confidencePercent }}%</div>
                </div>

                <div class="summary-item">
                    <div class="summary-label">Correctness</div>
                    <div class="summary-value">
                        {{ $isUnrelated ? 'Unrelated Audio' : ($correctness ? ucfirst($correctness) : 'N/A') }}
                    </div>
                </div>
            </div>

            <div class="result-section">
                <h2 class="section-title">
                    <i class="fas fa-language"></i>
                    What The Audio Says
                </h2>

                @if($hasTranscription)
                    <div class="transcription-box" dir="rtl" lang="ar">{!! $highlightedTranscription !!}</div>
                    @if($tajweedMatchCount > 0)
                        <div class="tajweed-legend">
                            <span class="tajweed-legend-swatch tajweed-highlight-{{ $rule }}"></span>
                            <span>{{ $tajweedMatchCount }} {{ ucfirst($rule) }}
                                {{ \Illuminate\Support\Str::plural('spot', $tajweedMatchCount) }} highlighted.</span>
                        </div>
                    @else
                        <div class="text-muted mt-2">
                            <i class="fas fa-circle-info me-1"></i>
                            No {{ ucfirst((string) $rule) }} trigger was detected in this transcription.
                        </div>
                    @endif
                    @if($hasDiacritics)
                        <div class="text-muted mt-2">
                            <i class="fas fa-circle-info me-1"></i>
                            Diacritical marks are shown from the selected ayah when available, or from AI diacritization for direct
                            uploads.
                        </div>
                    @endif
                @else
                    <div class="empty-transcription">
                        {{ $transcriptionFailed ? 'The app tried to transcribe this recording, but no Arabic text was detected clearly enough.' : 'No ayah was selected for this recording, so there is no ayah text to display.' }}
                    </div>
                @endif
            </div>

            <div class="result-section">
                <h2 class="section-title">
                    <i class="fas fa-comment-dots"></i>
                    Feedback
                </h2>
                <p class="feedback-box">{{ $feedback }}</p>
            </div>

            @if($audioId)
                <div class="result-section">
                    <h2 class="section-title">
                        <i class="fas fa-pen-to-square"></i>
                        Help Improve This Result
                    </h2>

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
                        <div class="alert alert-danger">
                            <i class="fas fa-circle-exclamation me-1"></i>
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

                        <button type="submit" class="btn btn-primary mt-3">
                            <i class="fas fa-paper-plane"></i>
                            Submit Correction
                        </button>
                    </form>
                </div>
            @endif

            <div class="result-actions">
                <div class="dropdown">
                    <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown"
                        aria-expanded="false">
                        <i class="fas fa-microphone"></i>Tajweed Practice
                    </button>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item" href="{{ route('tajweed.ikhfa-haqiqi') }}" class="btn btn-primary">
                                <i class="fas fa-microphone"></i>
                                Ikhfa Haqiqi Practice
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ route('tajweed.izhar-halqi') }}" class="btn btn-primary">
                                <i class="fas fa-microphone"></i>
                                Izhar Halqi Practice
                            </a>
                        </li>
                    </ul>


                </div>

                <a href="{{ route('tajweed.history') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-clock-rotate-left"></i>
                    View History
                </a>

                @if($audioId)
                    <form method="POST" action="{{ route('tajweed.reanalyze', $audioId) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-warning">
                            <i class="fas fa-rotate-right"></i>
                            Re-run Analysis
                        </button>
                    </form>
                @endif

                @if($audioId)
                    <a href="{{ route('tajweed.play-audio', $audioId) }}" class="btn btn-outline-primary" target="_blank">
                        <i class="fas fa-play"></i>
                        Play Audio
                    </a>
                @endif
            </div>
        </div>
    </div>
@endsection
