@extends('layouts.app')

@section('title', 'Home')

@section('content')
@php
    $user = auth()->user();
    $totalRecitations = 0;
    $correctRecitations = 0;
    $needsPractice = 0;
    $pendingAnalysis = 0;
    $latestRecitations = collect();

    if ($user) {
        $recitationsQuery = $user->audioRecitations();

        $totalRecitations = (clone $recitationsQuery)->count();
        $correctRecitations = (clone $recitationsQuery)
            ->whereHas('analysisResult', fn ($query) => $query
                ->where('processing_status', 'completed')
                ->where('correctness', 'correct'))
            ->count();
        $needsPractice = (clone $recitationsQuery)
            ->whereHas('analysisResult', fn ($query) => $query
                ->where('processing_status', 'completed')
                ->where('correctness', 'incorrect'))
            ->count();
        $pendingAnalysis = (clone $recitationsQuery)
            ->where(function ($query) {
                $query->whereDoesntHave('analysisResult')
                    ->orWhereHas('analysisResult', fn ($analysisQuery) => $analysisQuery->whereIn('processing_status', ['pending', 'processing']));
            })
            ->count();
        $latestRecitations = (clone $recitationsQuery)
            ->with('analysisResult')
            ->latest()
            ->take(3)
            ->get();
    }
@endphp

<div class="home-dashboard">
    <section class="welcome-panel">
        <div class="welcome-copy">
            <span class="eyebrow">Assalamu alaikum{{ $user ? ', ' . $user->name : '' }}</span>
            <h1>{{ $user ? 'Choose a rule, recite, and review your Tajweed feedback.' : 'Practice Tajweed with focused recitation feedback.' }}</h1>
            <p>
                {{ $user
                    ? 'Practice focused recitation for Ikhfa Haqiqi and Izhar Halqi, then use your saved results to understand what is already clear and what still needs repetition.'
                    : 'Create an account to record or upload recitations, test Ikhfa Haqiqi and Izhar Halqi, and keep your practice history in one place.' }}
            </p>

            <div class="primary-actions">
                @auth
                    <a href="{{ route('tajweed.ikhfa-haqiqi') }}" class="btn btn-primary btn-lg">
                        <i class="fas fa-volume-down me-2"></i>Practice Ikhfa
                    </a>
                    <a href="{{ route('tajweed.izhar-halqi') }}" class="btn btn-success btn-lg">
                        <i class="fas fa-volume-up me-2"></i>Practice Izhar
                    </a>
                @else
                    <a href="{{ route('register') }}" class="btn btn-primary btn-lg">
                        <i class="fas fa-user-plus me-2"></i>Create Account
                    </a>
                    <a href="{{ route('login') }}" class="btn btn-success btn-lg">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </a>
                @endauth
                <a href="{{ route('recite.quran') }}" class="btn btn-outline-dark btn-lg">
                    <i class="fas fa-book-quran me-2"></i>Read Quran
                </a>
            </div>
        </div>

        <div class="practice-card">
            <div class="practice-card-header">
                <div>
                    <span>Today&apos;s Focus</span>
                    <h2>Short, careful practice</h2>
                </div>
                <i class="fas fa-microphone-lines"></i>
            </div>

            <div class="practice-steps">
                <div>
                    <strong>1</strong>
                    <span>Select a Tajweed rule.</span>
                </div>
                <div>
                    <strong>2</strong>
                    <span>Record or upload your recitation.</span>
                </div>
                <div>
                    <strong>3</strong>
                    <span>Review the feedback and repeat.</span>
                </div>
            </div>
        </div>
    </section>

    <section class="metric-grid" aria-label="Learning progress">
        <div class="metric-card">
            <span class="metric-icon blue"><i class="fas fa-music"></i></span>
            <div>
                <strong>{{ $totalRecitations }}</strong>
                <span>Total recitations</span>
            </div>
        </div>
        <div class="metric-card">
            <span class="metric-icon green"><i class="fas fa-check"></i></span>
            <div>
                <strong>{{ $correctRecitations }}</strong>
                <span>Marked correct</span>
            </div>
        </div>
        <div class="metric-card">
            <span class="metric-icon amber"><i class="fas fa-repeat"></i></span>
            <div>
                <strong>{{ $needsPractice }}</strong>
                <span>Need practice</span>
            </div>
        </div>
        <div class="metric-card">
            <span class="metric-icon slate"><i class="fas fa-chart-simple"></i></span>
            <div>
                <strong>{{ $pendingAnalysis }}</strong>
                <span>Pending analysis</span>
            </div>
        </div>
    </section>

    <section class="content-grid">
        <div class="panel rule-panel">
            <div class="panel-heading">
                <div>
                    <span class="eyebrow">Practice Paths</span>
                    <h2>Start with the rule you want to improve</h2>
                </div>
            </div>

            <div class="rule-list">
                <a href="{{ route('tajweed.ikhfa-haqiqi') }}" class="rule-row">
                    <span class="rule-mark ikhfa"><i class="fas fa-volume-down"></i></span>
                    <div>
                        <h3>Ikhfa Haqiqi</h3>
                        <p>Practice concealing noon sakinah or tanween before the Ikhfa letters.</p>
                    </div>
                    <i class="fas fa-arrow-right"></i>
                </a>

                <a href="{{ route('tajweed.izhar-halqi') }}" class="rule-row">
                    <span class="rule-mark izhar"><i class="fas fa-volume-up"></i></span>
                    <div>
                        <h3>Izhar Halqi</h3>
                        <p>Practice clear pronunciation before the six throat letters.</p>
                    </div>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>

        <div class="panel recent-panel">
            <div class="panel-heading compact">
                <div>
                    <span class="eyebrow">{{ $user ? 'Recent Work' : 'Practice Flow' }}</span>
                    <h2>{{ $user ? 'Your latest recitations' : 'What happens after login' }}</h2>
                </div>
                @auth
                    <a href="{{ route('tajweed.history') }}" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-history me-1"></i>History
                    </a>
                @endauth
            </div>

            @guest
                <div class="empty-state">
                    <i class="fas fa-microphone-alt"></i>
                    <h3>Record, analyze, review</h3>
                    <p>After signing in, your latest recitations and feedback will appear here.</p>
                </div>
            @elseif($latestRecitations->isEmpty())
                <div class="empty-state">
                    <i class="fas fa-file-audio"></i>
                    <h3>No recordings yet</h3>
                    <p>Start with one short recitation so your progress has something to build on.</p>
                </div>
            @else
                <div class="recent-list">
                    @foreach($latestRecitations as $recitation)
                        @php
                            $result = $recitation->analysisResult;
                            $statusClass = 'pending';
                            $statusLabel = 'Pending';
                            $outcomeKey = $result?->displayOutcomeKey();

                            if ($result) {
                                $statusClass = match ($outcomeKey) {
                                    'incorrect' => 'improve',
                                    'analysis_failed' => 'failed',
                                    default => $outcomeKey,
                                };
                                $statusLabel = $result->displayOutcomeLabel();
                            }

                            $hasViewableResult = $result
                                && !in_array($outcomeKey, ['pending', 'processing'], true);
                        @endphp

                        <a href="{{ $hasViewableResult ? route('tajweed.result', $recitation->id) : route('tajweed.history') }}" class="recent-row">
                            <div>
                                <strong>{{ $recitation->tajweed_rule === 'ikhfa' ? 'Ikhfa Haqiqi' : 'Izhar Halqi' }}</strong>
                                <span>{{ $recitation->created_at->format('M d, Y') }}</span>
                            </div>
                            <span class="status-pill {{ $statusClass }}">{{ $statusLabel }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="next-panel">
        <div>
            <span class="eyebrow">Next Step</span>
            <h2>Keep the loop simple: practice, listen, correct.</h2>
            <p>
                Use the Quran reader when you want context, then return to the Tajweed tests for focused feedback
                on the rules this system currently supports.
            </p>
        </div>
        <div class="secondary-actions">
            <a href="{{ route('recite.quran') }}" class="btn btn-dark">
                <i class="fas fa-book-open me-2"></i>Open Quran Reader
            </a>
            @auth
                <a href="{{ route('tajweed.history') }}" class="btn btn-outline-dark">
                    <i class="fas fa-list-check me-2"></i>Review Results
                </a>
            @else
                <a href="{{ route('register') }}" class="btn btn-outline-dark">
                    <i class="fas fa-user-plus me-2"></i>Start Practicing
                </a>
            @endauth
        </div>
    </section>
</div>

<style>
    :root {
        --home-ink: #172033;
        --home-muted: #64748b;
        --home-border: #dce3ec;
        --home-surface: #ffffff;
        --home-soft: #f7fafc;
        --home-blue: #2563eb;
        --home-green: #168a63;
        --home-amber: #b7791f;
        --home-teal: #0f766e;
        --home-shadow: 0 18px 45px rgba(23, 32, 51, 0.08);
    }

    .home-dashboard {
        display: grid;
        gap: 1.5rem;
    }

    .welcome-panel,
    .panel,
    .next-panel,
    .metric-card {
        background: rgba(255, 255, 255, 0.94);
        border: 1px solid rgba(220, 227, 236, 0.9);
        border-radius: 8px;
        box-shadow: var(--home-shadow);
    }

    .welcome-panel {
        display: grid;
        grid-template-columns: minmax(0, 1.35fr) minmax(280px, 0.65fr);
        gap: 1.5rem;
        min-height: 420px;
        overflow: hidden;
        padding: 2.25rem;
        position: relative;
    }

    .welcome-panel::before {
        background:
            linear-gradient(135deg, rgba(37, 99, 235, 0.12), rgba(22, 138, 99, 0.08)),
            repeating-linear-gradient(45deg, rgba(183, 121, 31, 0.08) 0 1px, transparent 1px 18px);
        content: "";
        inset: 0;
        position: absolute;
        pointer-events: none;
    }

    .welcome-copy,
    .practice-card {
        position: relative;
        z-index: 1;
    }

    .eyebrow {
        color: var(--home-teal);
        display: block;
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        margin-bottom: 0.55rem;
        text-transform: uppercase;
    }

    .welcome-copy {
        align-self: center;
        max-width: 760px;
    }

    .welcome-copy h1 {
        color: var(--home-ink);
        font-size: clamp(2.1rem, 4vw, 4.3rem);
        font-weight: 800;
        letter-spacing: 0;
        line-height: 1.05;
        margin-bottom: 1.1rem;
    }

    .welcome-copy p {
        color: var(--home-muted);
        font-size: 1.08rem;
        line-height: 1.8;
        margin-bottom: 1.5rem;
        max-width: 680px;
    }

    .primary-actions,
    .secondary-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .primary-actions .btn,
    .secondary-actions .btn {
        border-radius: 8px;
        font-weight: 700;
        min-height: 44px;
    }

    .practice-card {
        align-self: stretch;
        background: #172033;
        color: #fff;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 1.5rem;
    }

    .practice-card-header {
        align-items: flex-start;
        display: flex;
        justify-content: space-between;
        gap: 1rem;
    }

    .practice-card-header span {
        color: #9fd6c9;
        font-size: 0.8rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .practice-card-header h2 {
        font-size: 1.55rem;
        font-weight: 800;
        margin: 0.35rem 0 0;
    }

    .practice-card-header i {
        color: #f5cf72;
        font-size: 2rem;
    }

    .practice-steps {
        display: grid;
        gap: 0.85rem;
        margin-top: 2rem;
    }

    .practice-steps div {
        align-items: center;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        display: flex;
        gap: 0.8rem;
        padding: 0.9rem;
    }

    .practice-steps strong {
        align-items: center;
        background: #f5cf72;
        border-radius: 50%;
        color: #172033;
        display: inline-flex;
        flex: 0 0 30px;
        height: 30px;
        justify-content: center;
    }

    .metric-grid {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .metric-card {
        align-items: center;
        display: flex;
        gap: 1rem;
        min-height: 110px;
        padding: 1.15rem;
    }

    .metric-icon {
        align-items: center;
        border-radius: 8px;
        display: inline-flex;
        flex: 0 0 48px;
        height: 48px;
        justify-content: center;
    }

    .metric-icon.blue {
        background: rgba(37, 99, 235, 0.12);
        color: var(--home-blue);
    }

    .metric-icon.green {
        background: rgba(22, 138, 99, 0.12);
        color: var(--home-green);
    }

    .metric-icon.amber {
        background: rgba(183, 121, 31, 0.14);
        color: var(--home-amber);
    }

    .metric-icon.slate {
        background: rgba(23, 32, 51, 0.1);
        color: var(--home-ink);
    }

    .metric-card strong {
        color: var(--home-ink);
        display: block;
        font-size: 2rem;
        line-height: 1;
    }

    .metric-card span:last-child {
        color: var(--home-muted);
        display: block;
        font-size: 0.9rem;
        margin-top: 0.3rem;
    }

    .content-grid {
        display: grid;
        gap: 1.5rem;
        grid-template-columns: minmax(0, 1.1fr) minmax(320px, 0.9fr);
    }

    .panel {
        padding: 1.35rem;
    }

    .panel-heading {
        align-items: flex-start;
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .panel-heading.compact {
        align-items: center;
    }

    .panel-heading h2,
    .next-panel h2 {
        color: var(--home-ink);
        font-size: 1.45rem;
        font-weight: 800;
        margin: 0;
    }

    .rule-list,
    .recent-list {
        display: grid;
        gap: 0.85rem;
    }

    .rule-row,
    .recent-row {
        align-items: center;
        background: var(--home-soft);
        border: 1px solid var(--home-border);
        border-radius: 8px;
        color: inherit;
        display: flex;
        gap: 1rem;
        padding: 1rem;
        text-decoration: none;
        transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .rule-row:hover,
    .recent-row:hover {
        border-color: rgba(37, 99, 235, 0.35);
        box-shadow: 0 12px 26px rgba(23, 32, 51, 0.08);
        color: inherit;
        text-decoration: none;
        transform: translateY(-2px);
    }

    .rule-mark {
        align-items: center;
        border-radius: 8px;
        display: inline-flex;
        flex: 0 0 52px;
        height: 52px;
        justify-content: center;
    }

    .rule-mark.ikhfa {
        background: rgba(37, 99, 235, 0.12);
        color: var(--home-blue);
    }

    .rule-mark.izhar {
        background: rgba(22, 138, 99, 0.12);
        color: var(--home-green);
    }

    .rule-row h3 {
        color: var(--home-ink);
        font-size: 1.05rem;
        font-weight: 800;
        margin: 0 0 0.25rem;
    }

    .rule-row p {
        color: var(--home-muted);
        line-height: 1.55;
        margin: 0;
    }

    .rule-row > i {
        color: var(--home-muted);
        margin-left: auto;
    }

    .empty-state {
        background: var(--home-soft);
        border: 1px dashed var(--home-border);
        border-radius: 8px;
        padding: 2rem 1rem;
        text-align: center;
    }

    .empty-state i {
        color: var(--home-blue);
        font-size: 2rem;
        margin-bottom: 0.75rem;
    }

    .empty-state h3 {
        color: var(--home-ink);
        font-size: 1.1rem;
        font-weight: 800;
    }

    .empty-state p {
        color: var(--home-muted);
        margin: 0 auto;
        max-width: 320px;
    }

    .recent-row {
        justify-content: space-between;
    }

    .recent-row strong,
    .recent-row span {
        display: block;
    }

    .recent-row strong {
        color: var(--home-ink);
        font-size: 0.98rem;
    }

    .recent-row div span {
        color: var(--home-muted);
        font-size: 0.86rem;
        margin-top: 0.25rem;
    }

    .status-pill {
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 800;
        padding: 0.4rem 0.65rem;
        white-space: nowrap;
    }

    .status-pill.correct {
        background: rgba(22, 138, 99, 0.12);
        color: var(--home-green);
    }

    .status-pill.improve {
        background: rgba(183, 121, 31, 0.14);
        color: var(--home-amber);
    }

    .status-pill.uncertain {
        background: rgba(100, 116, 139, 0.14);
        color: #475569;
    }

    .status-pill.failed {
        background: rgba(185, 28, 28, 0.10);
        color: #b91c1c;
    }

    .status-pill.unavailable {
        background: rgba(100, 116, 139, 0.10);
        color: #64748b;
    }

    .status-pill.processing,
    .status-pill.pending {
        background: rgba(37, 99, 235, 0.1);
        color: var(--home-blue);
    }

    .next-panel {
        align-items: center;
        display: flex;
        gap: 1.5rem;
        justify-content: space-between;
        padding: 1.5rem;
    }

    .next-panel p {
        color: var(--home-muted);
        line-height: 1.7;
        margin: 0.75rem 0 0;
        max-width: 720px;
    }

    @media (max-width: 992px) {
        .welcome-panel,
        .content-grid,
        .next-panel {
            grid-template-columns: 1fr;
        }

        .metric-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .next-panel {
            align-items: flex-start;
            display: grid;
        }
    }

    @media (max-width: 576px) {
        .welcome-panel,
        .panel,
        .next-panel {
            padding: 1rem;
        }

        .welcome-copy h1 {
            font-size: 2.05rem;
        }

        .primary-actions .btn,
        .secondary-actions .btn {
            width: 100%;
        }

        .metric-grid {
            grid-template-columns: 1fr;
        }

        .rule-row,
        .recent-row {
            align-items: flex-start;
        }

        .recent-row {
            flex-direction: column;
        }
    }
</style>
@endsection
