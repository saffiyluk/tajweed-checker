@extends('layouts.app')

@section('title', 'My Recitations')

@section('content')
@php
    $items = $recitations->getCollection();

    $correctCount = $items->filter(fn($r) => $r->analysisResult && $r->analysisResult->correctness === 'correct')->count();
    $incorrectCount = $items->filter(fn($r) => $r->analysisResult && $r->analysisResult->correctness === 'incorrect')->count();
    $pendingCount = $items->filter(fn($r) => !$r->analysisResult || in_array($r->analysisResult->processing_status, ['pending', 'processing']))->count();
@endphp

<style>
    body {
        background:
            radial-gradient(circle at top left, rgba(37, 99, 235, 0.10), transparent 32%),
            radial-gradient(circle at bottom right, rgba(194, 153, 80, 0.12), transparent 32%),
            linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%);
        min-height: 100vh;
    }

    .history-page {
        color: #0f172a;
    }

    .history-hero {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: white;
        border-radius: 28px;
        padding: 2rem;
        box-shadow: 0 22px 60px rgba(37, 99, 235, 0.18);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1.5rem;
        position: relative;
        overflow: hidden;
        margin-bottom: 1.5rem;
    }

    .history-hero::after {
        content: "۞";
        position: absolute;
        right: 2rem;
        top: -1.2rem;
        font-size: 8rem;
        opacity: 0.08;
        font-family: serif;
    }

    .hero-content,
    .hero-actions {
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

    .history-hero h1 {
        margin: 0;
        font-size: clamp(2rem, 4vw, 3rem);
        font-weight: 900;
        letter-spacing: -0.04em;
    }

    .history-hero p {
        margin: 0.75rem 0 0;
        color: rgba(255,255,255,0.82);
        line-height: 1.7;
    }

    .hero-actions {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .btn-hero-primary,
    .btn-hero-light,
    .btn-main,
    .btn-soft,
    .btn-danger-soft {
        border-radius: 16px;
        padding: 0.78rem 1rem;
        font-weight: 900;
        text-decoration: none;
        border: none;
    }

    .btn-hero-primary {
        background: white;
        color: #1d4ed8;
    }

    .btn-hero-primary:hover {
        background: #eff6ff;
        color: #1d4ed8;
    }

    .btn-hero-light {
        background: rgba(255,255,255,0.14);
        color: white;
        border: 1px solid rgba(255,255,255,0.25);
    }

    .btn-hero-light:hover {
        background: rgba(255,255,255,0.22);
        color: white;
    }

    .btn-main {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: white;
        box-shadow: 0 12px 26px rgba(37, 99, 235, 0.20);
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

    .btn-danger-soft {
        background: #fff1f2;
        color: #be123c;
        border: 1px solid #fecdd3;
    }

    .btn-danger-soft:hover {
        background: #e11d48;
        color: white;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .stat-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 22px;
        padding: 1.15rem;
        box-shadow: 0 12px 34px rgba(15, 23, 42, 0.06);
        display: flex;
        align-items: center;
        gap: 0.9rem;
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 16px;
        display: grid;
        place-items: center;
        color: white;
        flex-shrink: 0;
    }

    .stat-icon.total { background: linear-gradient(135deg, #2563eb, #1d4ed8); }
    .stat-icon.correct { background: linear-gradient(135deg, #16a34a, #15803d); }
    .stat-icon.improve { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .stat-icon.pending { background: linear-gradient(135deg, #06b6d4, #0891b2); }

    .stat-card h3 {
        margin: 0;
        font-size: 1.65rem;
        font-weight: 900;
        letter-spacing: -0.04em;
    }

    .stat-card p {
        margin: 0;
        color: #64748b;
        font-size: 0.85rem;
        font-weight: 700;
    }

    .clean-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 24px;
        padding: 1.35rem;
        box-shadow: 0 14px 38px rgba(15, 23, 42, 0.07);
    }

    .filter-card {
        margin-bottom: 1.5rem;
    }

    .filter-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
    }

    .filter-group label {
        display: block;
        color: #64748b;
        font-size: 0.82rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-bottom: 0.45rem;
    }

    .form-select {
        border-radius: 15px;
        border: 1px solid #dbe3ef;
        min-height: 48px;
        font-weight: 700;
        color: #0f172a;
    }

    .empty-state {
        text-align: center;
        padding: 4rem 1rem;
    }

    .empty-icon {
        width: 96px;
        height: 96px;
        border-radius: 30px;
        display: grid;
        place-items: center;
        margin: 0 auto 1.25rem;
        background: #eff6ff;
        color: #2563eb;
        font-size: 2.5rem;
    }

    .empty-state h3 {
        font-weight: 900;
        letter-spacing: -0.03em;
    }

    .empty-state p {
        color: #64748b;
    }

    .recitation-list {
        display: grid;
        gap: 1rem;
    }

    .recitation-item {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 24px;
        padding: 1.15rem;
        box-shadow: 0 12px 34px rgba(15, 23, 42, 0.06);
        transition: 0.2s ease;
    }

    .recitation-item:hover {
        border-color: #bfdbfe;
        box-shadow: 0 18px 42px rgba(15, 23, 42, 0.09);
    }

    .recitation-top {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        align-items: flex-start;
        margin-bottom: 1rem;
    }

    .file-info {
        display: flex;
        align-items: flex-start;
        gap: 0.85rem;
        min-width: 0;
    }

    .file-icon {
        width: 48px;
        height: 48px;
        border-radius: 16px;
        display: grid;
        place-items: center;
        background: #eff6ff;
        color: #1d4ed8;
        flex-shrink: 0;
    }

    .file-info h5 {
        margin: 0;
        font-size: 1rem;
        font-weight: 900;
        color: #0f172a;
        word-break: break-word;
    }

    .file-meta {
        display: flex;
        gap: 0.7rem;
        flex-wrap: wrap;
        margin-top: 0.4rem;
        color: #64748b;
        font-size: 0.85rem;
        font-weight: 700;
    }

    .recitation-actions {
        display: flex;
        gap: 0.5rem;
        flex-shrink: 0;
    }

    .icon-btn {
        width: 42px;
        height: 42px;
        border-radius: 14px;
        display: grid;
        place-items: center;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        color: #334155;
    }

    .icon-btn:hover {
        background: #eff6ff;
        color: #1d4ed8;
    }

    .audio-row {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        padding: 0.85rem;
        margin-bottom: 1rem;
    }

    .audio-row audio {
        width: 100%;
        height: 42px;
    }

    .storage-label {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        color: #64748b;
        font-size: 0.8rem;
        font-weight: 700;
        margin-top: 0.4rem;
    }

    .recitation-bottom {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 1rem;
        align-items: center;
    }

    .badges-row {
        display: flex;
        gap: 0.55rem;
        flex-wrap: wrap;
        align-items: center;
    }

    .rule-badge,
    .duration-badge,
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        border-radius: 999px;
        padding: 0.45rem 0.75rem;
        font-size: 0.78rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .rule-badge.ikhfa {
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
    }

    .rule-badge.izhar {
        background: #ecfeff;
        color: #0e7490;
        border: 1px solid #a5f3fc;
    }

    .duration-badge {
        background: #f8fafc;
        color: #64748b;
        border: 1px solid #e2e8f0;
    }

    .status-correct {
        background: #f0fdf4;
        color: #15803d;
        border: 1px solid #bbf7d0;
    }

    .status-incorrect {
        background: #fffbeb;
        color: #b45309;
        border: 1px solid #fde68a;
    }

    .status-processing {
        background: #ecfeff;
        color: #0e7490;
        border: 1px solid #a5f3fc;
    }

    .status-failed {
        background: #fef2f2;
        color: #b91c1c;
        border: 1px solid #fecaca;
    }

    .status-pending {
        background: #f8fafc;
        color: #64748b;
        border: 1px solid #e2e8f0;
    }

    .feedback-box {
        margin-top: 0.9rem;
        background: #f8fafc;
        border-left: 4px solid #2563eb;
        border-radius: 14px;
        padding: 0.85rem 1rem;
        color: #475569;
        font-size: 0.9rem;
        line-height: 1.6;
    }

    .result-action {
        display: flex;
        gap: 0.55rem;
        align-items: center;
    }

    .pagination-wrapper {
        margin-top: 1.5rem;
        display: flex;
        justify-content: center;
    }

    .bottom-actions {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        margin-top: 1.5rem;
    }

    .modal-content {
        border: none;
        border-radius: 24px;
        overflow: hidden;
        box-shadow: 0 24px 60px rgba(15, 23, 42, 0.18);
    }

    .modal-header {
        border-bottom: 1px solid #e2e8f0;
        padding: 1.25rem 1.5rem;
    }

    .modal-title {
        font-weight: 900;
    }

    .warning-circle {
        width: 74px;
        height: 74px;
        border-radius: 24px;
        display: grid;
        place-items: center;
        background: #fff7ed;
        color: #d97706;
        margin: 0 auto 1rem;
        font-size: 2rem;
    }

    @media (max-width: 992px) {
        .history-hero {
            align-items: flex-start;
            flex-direction: column;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .filter-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .recitation-bottom {
            grid-template-columns: 1fr;
        }

        .result-action {
            width: 100%;
            flex-direction: column;
        }

        .result-action .btn {
            width: 100%;
        }
    }

    @media (max-width: 576px) {
        .history-hero,
        .clean-card,
        .recitation-item {
            border-radius: 20px;
            padding: 1.1rem;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }

        .recitation-top {
            flex-direction: column;
        }

        .recitation-actions {
            width: 100%;
        }

        .icon-btn {
            flex: 1;
            width: auto;
        }

        .bottom-actions .btn,
        .hero-actions .btn {
            width: 100%;
        }
    }
</style>

<div class="history-page">
    <div class="container py-4 py-lg-5">

        <div class="history-hero">
            <div class="hero-content">
                <span class="hero-kicker">
                    <i class="fas fa-microphone-alt me-2"></i>My Recitations
                </span>
                <h1>Recitation History</h1>
                <p>Review your recordings, listen again, and check your AI tajweed analysis results.</p>
            </div>

            <div class="hero-actions">
                <a href="{{ route('home') }}" class="btn btn-hero-light">
                    <i class="fas fa-home me-2"></i>Home
                </a>
            </div>
        </div>

        @if($recitations->isEmpty())
            <div class="clean-card empty-state">
                <div class="empty-icon">
                    <i class="fas fa-inbox"></i>
                </div>
                <h3>No Recitations Yet</h3>
                <p>Start by recording or uploading your first tajweed practice audio.</p>

                <div class="d-flex gap-2 justify-content-center flex-wrap mt-4">
                    <a href="{{ route('tajweed.ikhfa-haqiqi') }}" class="btn btn-main">
                        <i class="fas fa-volume-down me-2"></i>Practice Ikhfa
                    </a>
                    <a href="{{ route('tajweed.izhar-halqi') }}" class="btn btn-soft">
                        <i class="fas fa-volume-up me-2"></i>Practice Izhar
                    </a>
                </div>
            </div>
        @else
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon total"><i class="fas fa-music"></i></div>
                    <div>
                        <h3>{{ $recitations->total() }}</h3>
                        <p>Total Recitations</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon correct"><i class="fas fa-check-circle"></i></div>
                    <div>
                        <h3>{{ $correctCount }}</h3>
                        <p>Correct</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon improve"><i class="fas fa-exclamation-circle"></i></div>
                    <div>
                        <h3>{{ $incorrectCount }}</h3>
                        <p>Needs Practice</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon pending"><i class="fas fa-clock"></i></div>
                    <div>
                        <h3>{{ $pendingCount }}</h3>
                        <p>Pending / Processing</p>
                    </div>
                </div>
            </div>

            <div class="clean-card filter-card">
                <span class="section-label">
                    <i class="fas fa-filter me-2"></i>Filter Records
                </span>

                <div class="filter-grid">
                    <div class="filter-group">
                        <label for="ruleFilter">Rule</label>
                        <select class="form-select" id="ruleFilter">
                            <option value="">All Rules</option>
                            <option value="ikhfa">Ikhfa Haqiqi</option>
                            <option value="izhar">Izhar Halqi</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="statusFilter">Status</label>
                        <select class="form-select" id="statusFilter">
                            <option value="">All Status</option>
                            <option value="correct">Correct</option>
                            <option value="incorrect">Needs Practice</option>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="sortFilter">Sort</label>
                        <select class="form-select" id="sortFilter">
                            <option value="newest">Newest First</option>
                            <option value="oldest">Oldest First</option>
                            <option value="correct">Correct First</option>
                            <option value="incorrect">Needs Practice First</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="recitation-list" id="recitationsList">
                @foreach($recitations as $recitation)
                    @php
                        $analysis = $recitation->analysisResult;
                        $status = 'pending';

                        if ($analysis) {
                            if ($analysis->processing_status === 'completed') {
                                $status = $analysis->correctness === 'correct' ? 'correct' : 'incorrect';
                            } elseif ($analysis->processing_status === 'processing') {
                                $status = 'processing';
                            } elseif ($analysis->processing_status === 'failed') {
                                $status = 'failed';
                            }
                        }

                        $confidence = null;
                        if ($analysis && $analysis->confidence_score !== null) {
                            $raw = (float) $analysis->confidence_score;
                            $confidence = $raw <= 1 ? round($raw * 100) : round($raw);
                        }

                        $ruleLabel = $recitation->tajweed_rule === 'ikhfa'
                            ? 'Ikhfa Haqiqi'
                            : ($recitation->tajweed_rule === 'izhar' ? 'Izhar Halqi' : $recitation->tajweed_rule);

                        $audioType = 'audio/mpeg';

                        if ($recitation->firebase_url) {
                            $ext = pathinfo(parse_url($recitation->firebase_url, PHP_URL_PATH), PATHINFO_EXTENSION);
                            $audioType = match (strtolower($ext)) {
                                'mp3' => 'audio/mpeg',
                                'wav' => 'audio/wav',
                                'webm' => 'audio/webm',
                                default => 'audio/mpeg',
                            };
                        }
                    @endphp

                    <div class="recitation-item"
                         data-rule="{{ $recitation->tajweed_rule }}"
                         data-status="{{ $status }}"
                         data-created="{{ $recitation->created_at->timestamp }}"
                         data-correctness="{{ $status }}">

                        <div class="recitation-top">
                            <div class="file-info">
                                <div class="file-icon">
                                    <i class="fas fa-file-audio"></i>
                                </div>

                                <div>
                                    <h5>{{ Str::limit($recitation->original_filename, 48) }}</h5>
                                    <div class="file-meta">
                                        <span><i class="fas fa-calendar me-1"></i>{{ $recitation->created_at->format('M d, Y') }}</span>
                                        <span><i class="fas fa-clock me-1"></i>{{ $recitation->created_at->format('h:i A') }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="recitation-actions">
                                <a href="{{ route('tajweed.download', $recitation->id) }}" class="icon-btn" title="Download">
                                    <i class="fas fa-download"></i>
                                </a>

                                <button class="icon-btn text-danger"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteModal"
                                        onclick="setDeleteUrl('{{ route('tajweed.delete', $recitation->id) }}')"
                                        title="Delete">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </div>

                        @if($recitation->firebase_url)
                            <div class="audio-row">
                                <audio controls data-recitation-id="{{ $recitation->id }}" onerror="handleAudioError(this, {{ $recitation->id }})">
                                    <source src="{{ $recitation->firebase_url }}" type="{{ $audioType }}">
                                    Your browser does not support audio.
                                </audio>

                                <span class="storage-label">
                                    <i class="fas fa-cloud"></i> Firebase Storage
                                </span>
                            </div>
                        @elseif($recitation->audio_file_path)
                            @php
                                $localPath = $recitation->audio_file_path;
                                if (strpos($localPath, 'public/') === 0) {
                                    $localPath = substr($localPath, 7);
                                }
                            @endphp

                            <div class="audio-row">
                                <audio controls data-recitation-id="{{ $recitation->id }}" onerror="handleAudioError(this, {{ $recitation->id }})">
                                    @if(Storage::disk('public')->exists($localPath))
                                        <source src="{{ Storage::disk('public')->url($localPath) }}" type="audio/mpeg">
                                    @endif
                                    Your browser does not support audio.
                                </audio>

                                <span class="storage-label">
                                    <i class="fas fa-hdd"></i> Local Storage
                                </span>
                            </div>
                        @endif

                        <div class="recitation-bottom">
                            <div>
                                <div class="badges-row">
                                    <span class="rule-badge {{ $recitation->tajweed_rule }}">
                                        {{ $ruleLabel }}
                                    </span>

                                    @if($recitation->duration_seconds)
                                        <span class="duration-badge">
                                            <i class="fas fa-clock"></i>{{ gmdate('i:s', $recitation->duration_seconds) }}
                                        </span>
                                    @endif

                                    @if($status === 'correct')
                                        <span class="status-badge status-correct">
                                            <i class="fas fa-check-circle"></i>
                                            Correct {{ $confidence !== null ? $confidence . '%' : '' }}
                                        </span>
                                    @elseif($status === 'incorrect')
                                        <span class="status-badge status-incorrect">
                                            <i class="fas fa-exclamation-circle"></i>
                                            Needs Practice {{ $confidence !== null ? $confidence . '%' : '' }}
                                        </span>
                                    @elseif($status === 'processing')
                                        <span class="status-badge status-processing">
                                            <i class="fas fa-spinner fa-spin"></i>
                                            Analyzing
                                        </span>
                                    @elseif($status === 'failed')
                                        <span class="status-badge status-failed">
                                            <i class="fas fa-times-circle"></i>
                                            Failed
                                        </span>
                                    @else
                                        <span class="status-badge status-pending">
                                            <i class="fas fa-hourglass-start"></i>
                                            Pending
                                        </span>
                                    @endif
                                </div>

                                @if($analysis && $analysis->feedback_message)
                                    <div class="feedback-box">
                                        <strong>Feedback:</strong>
                                        {{ Str::limit($analysis->feedback_message, 140) }}
                                    </div>
                                @endif
                            </div>

                            <div class="result-action">
                                @if($analysis && $analysis->processing_status === 'completed')
                                    <a href="{{ route('tajweed.result', $recitation->id) }}" class="btn btn-main">
                                        <i class="fas fa-chart-line me-2"></i>View Analysis
                                    </a>
                                @else
                                    <button class="btn btn-soft" disabled>
                                        <i class="fas fa-lock me-2"></i>In Progress
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($recitations->hasPages())
                <div class="pagination-wrapper">
                    {{ $recitations->links('pagination::bootstrap-4') }}
                </div>
            @endif

            <div class="bottom-actions">
                <a href="{{ route('home') }}" class="btn btn-soft">
                    <i class="fas fa-home me-2"></i>Dashboard
                </a>

                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('tajweed.ikhfa-haqiqi') }}" class="btn btn-main">
                        <i class="fas fa-volume-down me-2"></i>Practice Ikhfa
                    </a>
                    <a href="{{ route('tajweed.izhar-halqi') }}" class="btn btn-soft">
                        <i class="fas fa-volume-up me-2"></i>Practice Izhar
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-trash-alt text-danger me-2"></i>Delete Recording
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body text-center p-4">
                <div class="warning-circle">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>

                <h5 class="fw-bold">Are you sure?</h5>
                <p class="text-muted mb-0">
                    This recording and its analysis result will be permanently deleted.
                    This action cannot be undone.
                </p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-soft" data-bs-dismiss="modal">
                    Cancel
                </button>

                <form id="deleteForm" method="POST">
                    @csrf
                    @method('DELETE')

                    <button type="submit" class="btn btn-danger-soft">
                        <i class="fas fa-trash-alt me-2"></i>Delete Recording
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function setDeleteUrl(url) {
        document.getElementById('deleteForm').action = url;
    }

    function handleAudioError(audioElement, recitationId) {
        const parent = audioElement.closest('.audio-row');

        if (!parent) return;

        parent.innerHTML = `
            <div class="alert alert-warning mb-0">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Cannot play audio.
                <button class="btn btn-sm btn-outline-warning ms-2" onclick="retryAudio(${recitationId})">
                    <i class="fas fa-redo me-1"></i>Retry
                </button>
            </div>
        `;
    }

    function retryAudio(recitationId) {
        fetch(`/tajweed/audio-url/${recitationId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.url) {
                    window.location.reload();
                } else {
                    alert('Unable to load audio. Please try again later.');
                }
            })
            .catch(error => {
                console.error('Error fetching audio URL:', error);
                alert('Error loading audio.');
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const ruleFilter = document.getElementById('ruleFilter');
        const statusFilter = document.getElementById('statusFilter');
        const sortFilter = document.getElementById('sortFilter');
        const list = document.getElementById('recitationsList');

        function applyFilters() {
            if (!list) return;

            const items = Array.from(list.querySelectorAll('.recitation-item'));
            const ruleValue = ruleFilter ? ruleFilter.value : '';
            const statusValue = statusFilter ? statusFilter.value : '';
            const sortValue = sortFilter ? sortFilter.value : 'newest';

            items.forEach(item => {
                const matchRule = !ruleValue || item.dataset.rule === ruleValue;
                const matchStatus = !statusValue || item.dataset.status === statusValue;

                item.style.display = matchRule && matchStatus ? '' : 'none';
            });

            items.sort((a, b) => {
                if (sortValue === 'oldest') {
                    return Number(a.dataset.created) - Number(b.dataset.created);
                }

                if (sortValue === 'correct') {
                    return (b.dataset.status === 'correct') - (a.dataset.status === 'correct');
                }

                if (sortValue === 'incorrect') {
                    return (b.dataset.status === 'incorrect') - (a.dataset.status === 'incorrect');
                }

                return Number(b.dataset.created) - Number(a.dataset.created);
            });

            items.forEach(item => list.appendChild(item));
        }

        if (ruleFilter) ruleFilter.addEventListener('change', applyFilters);
        if (statusFilter) statusFilter.addEventListener('change', applyFilters);
        if (sortFilter) sortFilter.addEventListener('change', applyFilters);

        document.querySelectorAll('audio').forEach(audio => {
            audio.addEventListener('play', function () {
                document.querySelectorAll('audio').forEach(otherAudio => {
                    if (otherAudio !== this && !otherAudio.paused) {
                        otherAudio.pause();
                    }
                });
            });
        });

        applyFilters();
    });
</script>
@endsection