{{-- resources/views/reciteQuran.blade.php --}}
@extends('layouts.app')

@section('title', 'Recite Quran - ' . config('app.name'))

@push('styles')
<style>
    :root {
        --quran-primary: #2563eb;
        --quran-primary-dark: #1d4ed8;
        --quran-gold: #c29950;
        --quran-dark: #0f172a;
        --quran-muted: #64748b;
        --quran-soft: #f8fafc;
        --quran-line: #e2e8f0;
        --quran-success: #16a34a;
        --quran-danger: #dc2626;
        --quran-shadow: 0 18px 50px rgba(15, 23, 42, 0.10);
    }

    .quran-page {
        color: var(--quran-dark);
    }

    .quran-shell {
        max-width: 1180px;
        margin: 0 auto;
        padding: 2rem 1rem 4rem;
    }

    .quran-hero {
        background: linear-gradient(135deg, var(--quran-primary), var(--quran-primary-dark));
        color: white;
        border-radius: 28px;
        padding: 2rem;
        box-shadow: var(--quran-shadow);
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 1.5rem;
        position: relative;
        overflow: hidden;
        margin-bottom: 1.5rem;
    }

    .quran-hero::after {
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

    .quran-hero h1 {
        margin: 0;
        font-size: clamp(2rem, 4vw, 3rem);
        font-weight: 900;
        letter-spacing: -0.04em;
    }

    .quran-hero p {
        margin: 0.75rem 0 0;
        color: rgba(255, 255, 255, 0.84);
        line-height: 1.7;
        max-width: 650px;
    }

    .hero-actions {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .btn-hero,
    .btn-main,
    .btn-soft,
    .nav-btn,
    .practice-link {
        border-radius: 16px;
        padding: 0.78rem 1rem;
        font-weight: 900;
        text-decoration: none;
        border: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
    }

    .btn-hero {
        background: rgba(255, 255, 255, 0.15);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.25);
    }

    .btn-hero:hover {
        background: rgba(255, 255, 255, 0.25);
        color: white;
    }

    .btn-main {
        background: linear-gradient(135deg, var(--quran-primary), var(--quran-primary-dark));
        color: white;
        box-shadow: 0 12px 26px rgba(37, 99, 235, 0.20);
    }

    .btn-main:hover {
        color: white;
        transform: translateY(-1px);
    }

    .btn-soft {
        background: #eff6ff;
        color: var(--quran-primary-dark);
        border: 1px solid #dbeafe;
    }

    .btn-soft:hover {
        background: #dbeafe;
        color: var(--quran-primary-dark);
    }

    .reader-controls {
        background: white;
        border: 1px solid var(--quran-line);
        border-radius: 24px;
        padding: 1rem;
        box-shadow: 0 14px 38px rgba(15, 23, 42, 0.07);
        display: grid;
        grid-template-columns: 1.4fr 0.8fr auto;
        gap: 1rem;
        align-items: end;
        margin-bottom: 1.5rem;
    }

    .control-group label {
        display: block;
        color: var(--quran-muted);
        font-size: 0.78rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-bottom: 0.45rem;
    }

    .select-clean {
        width: 100%;
        min-height: 50px;
        border-radius: 16px;
        border: 1px solid #dbe3ef;
        background: var(--quran-soft);
        color: var(--quran-dark);
        padding: 0.75rem 1rem;
        font-weight: 750;
        outline: none;
    }

    .select-clean:focus {
        background: white;
        border-color: var(--quran-primary);
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
    }

    .reader-options {
        display: flex;
        gap: 0.75rem;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: wrap;
    }

    .font-switcher {
        background: var(--quran-soft);
        border: 1px solid var(--quran-line);
        border-radius: 999px;
        padding: 0.3rem;
        display: inline-flex;
        gap: 0.25rem;
    }

    .font-btn {
        width: 38px;
        height: 38px;
        border: none;
        border-radius: 50%;
        background: transparent;
        color: var(--quran-muted);
        font-weight: 900;
        cursor: pointer;
    }

    .font-btn.active {
        background: var(--quran-primary);
        color: white;
        box-shadow: 0 8px 18px rgba(37, 99, 235, 0.18);
    }

    .translation-toggle {
        background: var(--quran-soft);
        border: 1px solid var(--quran-line);
        color: var(--quran-dark);
        border-radius: 999px;
        height: 44px;
        padding: 0 0.9rem;
        font-weight: 850;
        cursor: pointer;
    }

    .translation-toggle.active {
        background: #eff6ff;
        color: var(--quran-primary-dark);
        border-color: #bfdbfe;
    }

    .mushaf-card {
        background: white;
        border: 1px solid var(--quran-line);
        border-radius: 28px;
        box-shadow: 0 18px 50px rgba(15, 23, 42, 0.08);
        overflow: hidden;
    }

    .surah-banner {
        padding: 2rem 1rem;
        text-align: center;
        background:
            radial-gradient(circle at center, rgba(194, 153, 80, 0.13), transparent 50%),
            linear-gradient(135deg, #f8fafc, #eff6ff);
        border-bottom: 1px solid var(--quran-line);
    }

    .surah-ar {
        font-family: "Amiri", "Scheherazade New", serif;
        font-size: clamp(2.3rem, 5vw, 4rem);
        color: var(--quran-primary-dark);
        font-weight: 700;
        line-height: 1.3;
    }

    .surah-en {
        margin-top: 0.45rem;
        font-size: 1.25rem;
        font-weight: 900;
        color: var(--quran-dark);
    }

    .surah-translation-name {
        color: var(--quran-muted);
        font-size: 0.95rem;
        margin-top: 0.2rem;
    }

    .surah-meta {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 0.55rem;
        margin-top: 1rem;
    }

    .surah-meta span {
        background: white;
        border: 1px solid var(--quran-line);
        color: var(--quran-muted);
        border-radius: 999px;
        padding: 0.45rem 0.8rem;
        font-size: 0.82rem;
        font-weight: 850;
    }

    .ayah-container {
        padding: 1.5rem;
    }

    .ayah-grid {
        display: grid;
        gap: 1rem;
    }

    .ayah-card {
        width: 100%;
        background: var(--quran-soft);
        border: 1px solid var(--quran-line);
        border-radius: 22px;
        padding: 1.25rem;
        text-align: left;
        cursor: pointer;
        transition: 0.2s ease;
        position: relative;
    }

    .ayah-card:hover {
        background: #f1f6ff;
        border-color: #bfdbfe;
        transform: translateY(-1px);
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.07);
    }

    .ayah-card.is-selected {
        border-color: var(--quran-primary);
        background: #eff6ff;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.10);
    }

    .ayah-topline {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 0.85rem;
    }

    .ayah-number-pill {
        background: white;
        color: var(--quran-primary-dark);
        border: 1px solid #bfdbfe;
        border-radius: 999px;
        padding: 0.35rem 0.7rem;
        font-size: 0.8rem;
        font-weight: 900;
        flex-shrink: 0;
    }

    .tap-hint {
        color: var(--quran-muted);
        font-size: 0.8rem;
        font-weight: 750;
    }

    .ayah-arabic {
        font-family: "Amiri", "Scheherazade New", serif;
        color: var(--quran-dark);
        direction: rtl;
        text-align: right;
        line-height: 2.25;
        margin-bottom: 1rem;
    }

    .font-small .ayah-arabic { font-size: 1.55rem; }
    .font-medium .ayah-arabic { font-size: 1.9rem; }
    .font-large .ayah-arabic { font-size: 2.25rem; }
    .font-xlarge .ayah-arabic { font-size: 2.65rem; }

    .translation-block {
        border-top: 1px solid var(--quran-line);
        padding-top: 0.9rem;
        color: #475569;
        line-height: 1.7;
        font-size: 0.95rem;
    }

    .translation-block strong {
        color: var(--quran-primary-dark);
        font-weight: 900;
        margin-right: 0.35rem;
    }

    .quran-page.hide-translations .translation-block {
        display: none;
    }

    .tajweed-mark {
        border-radius: 0.35em;
        padding: 0 0.12em;
    }

    .tajweed-mark.ikhfa {
        background: rgba(22, 163, 74, 0.14);
        color: #047857;
    }

    .tajweed-mark.izhar {
        background: rgba(220, 38, 38, 0.14);
        color: #dc2626;
    }

    .surah-nav {
        background: #f8fafc;
        border-top: 1px solid var(--quran-line);
        padding: 1rem 1.5rem;
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        gap: 1rem;
        align-items: center;
    }

    .nav-btn {
        background: white;
        color: var(--quran-primary-dark);
        border: 1px solid #dbeafe;
    }

    .nav-btn:hover:not(.disabled) {
        background: var(--quran-primary);
        color: white;
    }

    .nav-btn.disabled {
        opacity: 0.45;
        pointer-events: none;
    }

    .nav-right {
        justify-self: end;
    }

    .surah-counter {
        text-align: center;
        color: var(--quran-dark);
        font-weight: 900;
    }

    .page-summary {
        color: var(--quran-muted);
        font-size: 0.82rem;
        font-weight: 700;
        margin-top: 0.15rem;
    }

    .empty-state {
        text-align: center;
        padding: 4rem 1rem;
        color: var(--quran-muted);
    }

    .empty-state i {
        color: var(--quran-primary);
        margin-bottom: 1rem;
    }

    .empty-state h3 {
        color: var(--quran-dark);
        font-weight: 900;
    }

    .tajweed-drawer {
        position: fixed;
        bottom: 0;
        right: 0;
        left: 0;
        max-width: 620px;
        margin: 0 auto;
        background: white;
        border-radius: 28px 28px 0 0;
        box-shadow: 0 -20px 60px rgba(15, 23, 42, 0.18);
        border: 1px solid var(--quran-line);
        transform: translateY(105%);
        transition: transform 0.28s ease;
        z-index: 1060;
        overflow: hidden;
    }

    .tajweed-drawer.open {
        transform: translateY(0);
    }

    .drawer-handle {
        width: 52px;
        height: 5px;
        background: var(--quran-line);
        border-radius: 99px;
        margin: 12px auto 8px;
    }

    .drawer-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.6rem 1.4rem 0.4rem;
    }

    .drawer-title {
        font-weight: 900;
        color: var(--quran-dark);
    }

    .drawer-close {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: none;
        background: var(--quran-soft);
        color: var(--quran-muted);
    }

    .drawer-body {
        padding: 0.75rem 1.4rem 1.5rem;
        max-height: 70vh;
        overflow-y: auto;
    }

    .ayah-preview {
        background: var(--quran-soft);
        border: 1px solid var(--quran-line);
        border-radius: 20px;
        padding: 1rem;
        direction: rtl;
        text-align: right;
        font-family: "Amiri", "Scheherazade New", serif;
        font-size: 1.75rem;
        line-height: 2;
        margin-bottom: 0.9rem;
    }

    .drawer-translation {
        background: #eff6ff;
        border: 1px solid #dbeafe;
        border-radius: 18px;
        padding: 0.9rem 1rem;
        color: #1e3a8a;
        line-height: 1.7;
        margin-bottom: 1rem;
        font-size: 0.95rem;
    }

    .rule-item {
        background: var(--quran-soft);
        border: 1px solid var(--quran-line);
        border-left: 4px solid var(--quran-primary);
        border-radius: 16px;
        padding: 0.85rem 1rem;
        margin-bottom: 0.75rem;
    }

    .rule-item.ikhfa {
        border-left-color: var(--quran-success);
    }

    .rule-item.izhar {
        border-left-color: var(--quran-danger);
    }

    .rule-name {
        font-weight: 900;
        margin-bottom: 0.25rem;
    }

    .rule-desc {
        color: var(--quran-muted);
        font-size: 0.88rem;
        line-height: 1.55;
    }

    .drawer-actions {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
        margin-top: 1rem;
    }

    .practice-link {
        background: #eff6ff;
        color: var(--quran-primary-dark);
        border: 1px solid #dbeafe;
        font-size: 0.88rem;
    }

    .practice-link:hover {
        background: var(--quran-primary);
        color: white;
    }

    @media (max-width: 992px) {
        .quran-hero {
            align-items: flex-start;
            flex-direction: column;
        }

        .reader-controls {
            grid-template-columns: 1fr;
        }

        .reader-options {
            justify-content: flex-start;
        }

        .surah-nav {
            grid-template-columns: 1fr;
            text-align: center;
        }

        .nav-right {
            justify-self: stretch;
        }

        .surah-nav .nav-btn {
            width: 100%;
        }
    }

    @media (max-width: 576px) {
        .quran-shell {
            padding: 1rem 0.85rem 3rem;
        }

        .quran-hero,
        .reader-controls,
        .mushaf-card,
        .ayah-card {
            border-radius: 20px;
        }

        .quran-hero {
            padding: 1.2rem;
        }

        .ayah-container {
            padding: 1rem;
        }

        .ayah-card {
            padding: 1rem;
        }

        .font-small .ayah-arabic { font-size: 1.35rem; }
        .font-medium .ayah-arabic { font-size: 1.6rem; }
        .font-large .ayah-arabic { font-size: 1.85rem; }
        .font-xlarge .ayah-arabic { font-size: 2.1rem; }

        .tap-hint {
            display: none;
        }

        .hero-actions .btn {
            width: 100%;
        }
    }
</style>
@endpush

@section('content')
<div class="quran-page" id="quranPage">
    <div class="quran-shell">

        <div class="quran-hero">
            <div class="hero-content">
                <span class="hero-kicker">
                    <i class="fas fa-book-quran me-2"></i>Quran Reader
                </span>
                <h1>Recite Quran</h1>
                <p>
                    Read Quran ayahs with English translation. Tap any ayah to view detected Izhar and Ikhfa tajweed hints.
                </p>
            </div>

            <div class="hero-actions">
                <a href="{{ route('tajweed.history') }}" class="btn btn-hero">
                    <i class="fas fa-clock-rotate-left"></i> History
                </a>
            </div>
        </div>

        <div class="reader-controls">
            <div class="control-group">
                <label for="surahSelect">Select Surah</label>
                <select class="select-clean" id="surahSelect">
                    <option value="">— Select a Surah —</option>
                    @foreach($allSurahs as $surahOption)
                        <option value="{{ $surahOption['number'] }}" {{ (isset($currentSurah) && $currentSurah == $surahOption['number']) ? 'selected' : '' }}>
                            {{ $surahOption['number'] }}. {{ $surahOption['englishName'] }} ({{ $surahOption['name'] }})
                        </option>
                    @endforeach
                </select>
            </div>

            @if(isset($surah) && $surah)
                <div class="control-group">
                    <label for="ayahSelect">Jump to Ayah</label>
                    <select class="select-clean" id="ayahSelect">
                        @for($ayahNumber = 1; $ayahNumber <= $totalAyahs; $ayahNumber++)
                            <option value="{{ $ayahNumber }}" @selected($selectedAyah == $ayahNumber)>
                                Ayah {{ $ayahNumber }}
                            </option>
                        @endfor
                    </select>
                </div>
            @endif

            <div class="reader-options">
                <div class="font-switcher">
                    <button type="button" class="font-btn" data-size="small">ص</button>
                    <button type="button" class="font-btn" data-size="medium">م</button>
                    <button type="button" class="font-btn" data-size="large">ك</button>
                    <button type="button" class="font-btn" data-size="xlarge">ك+</button>
                </div>

                <button type="button" class="translation-toggle active" id="translationToggle">
                    <i class="fas fa-language me-1"></i> Translation
                </button>
            </div>
        </div>

        @if(isset($surah) && $surah)
            <div class="mushaf-card">
                <div class="surah-banner">
                    <div class="surah-ar">{{ $surah['name'] ?? '' }}</div>
                    <div class="surah-en">{{ $surah['englishName'] ?? 'Surah' }}</div>

                    @if(!empty($surah['englishNameTranslation']))
                        <div class="surah-translation-name">{{ $surah['englishNameTranslation'] }}</div>
                    @endif

                    <div class="surah-meta">
                        <span>Surah {{ $surah['number'] ?? $currentSurah }}</span>
                        <span>{{ $surah['numberOfAyahs'] ?? $totalAyahs }} Ayahs</span>
                        <span>{{ $surah['revelationType'] ?? '-' }}</span>
                        <span>Page {{ $currentPage }} / {{ $totalPages }}</span>
                    </div>
                </div>

                <div class="ayah-container font-medium" id="ayahContainer">
                    @if(isset($pagedAyahs) && count($pagedAyahs) > 0)
                        <div class="ayah-grid" id="ayahGrid">
                            @foreach($pagedAyahs as $ayah)
                                <button type="button"
                                    id="ayah-{{ $ayah['numberInSurah'] }}"
                                    class="ayah-card {{ $selectedAyah == $ayah['numberInSurah'] ? 'is-selected' : '' }}"
                                    data-surah-number="{{ $surah['number'] }}"
                                    data-surah-name="{{ $surah['englishName'] ?? 'Surah' }}"
                                    data-ayah-number="{{ $ayah['numberInSurah'] }}"
                                    data-ayah-text="{{ e($ayah['text']) }}"
                                    data-ayah-translation="{{ e($ayah['translation'] ?? '') }}">

                                    <div class="ayah-topline">
                                        <span class="ayah-number-pill">
                                            {{ $surah['englishName'] ?? 'Surah' }} : {{ $ayah['numberInSurah'] }}
                                        </span>
                                        <span class="tap-hint">
                                            <i class="fas fa-hand-pointer me-1"></i>Tap for tajweed
                                        </span>
                                    </div>

                                    <div class="ayah-arabic" data-arabic-text>
                                        {{ $ayah['text'] }}
                                    </div>

                                    <div class="translation-block">
                                        <strong>Translation:</strong>
                                        {{ !empty($ayah['translation']) ? $ayah['translation'] : 'Translation not available for this ayah.' }}
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    @else
                        <div class="empty-state">
                            <i class="fas fa-info-circle fa-3x"></i>
                            <h3>No Ayahs Loaded</h3>
                            <p>Please try another surah.</p>
                        </div>
                    @endif
                </div>

                <div class="surah-nav">
                    <div>
                        @if($currentPage > 1)
                            <a href="{{ route('recite.quran', ['surah' => $currentSurah, 'page' => $currentPage - 1, 'ayah' => max(1, $pageStart - $ayahsPerPage)]) }}" class="nav-btn">
                                <i class="fas fa-arrow-left"></i> Previous Page
                            </a>
                        @elseif($currentSurah > 1)
                            <a href="{{ route('recite.quran', ['surah' => $currentSurah - 1]) }}" class="nav-btn">
                                <i class="fas fa-arrow-left"></i> Previous Surah
                            </a>
                        @else
                            <span class="nav-btn disabled">
                                <i class="fas fa-arrow-left"></i> Previous
                            </span>
                        @endif
                    </div>

                    <div class="surah-counter">
                        {{ $surah['englishName'] ?? '' }}
                        <div class="page-summary">
                            Ayah {{ $pageStart }}-{{ $pageEnd }} of {{ $totalAyahs }}
                        </div>
                    </div>

                    <div class="nav-right">
                        @if($currentPage < $totalPages)
                            <a href="{{ route('recite.quran', ['surah' => $currentSurah, 'page' => $currentPage + 1, 'ayah' => min($totalAyahs, $pageEnd + 1)]) }}" class="nav-btn">
                                Next Page <i class="fas fa-arrow-right"></i>
                            </a>
                        @elseif($currentSurah < 114)
                            <a href="{{ route('recite.quran', ['surah' => $currentSurah + 1]) }}" class="nav-btn">
                                Next Surah <i class="fas fa-arrow-right"></i>
                            </a>
                        @else
                            <span class="nav-btn disabled">
                                Next <i class="fas fa-arrow-right"></i>
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        @else
            <div class="mushaf-card">
                <div class="empty-state">
                    <i class="fas fa-book-quran fa-4x"></i>
                    <h3>Begin Your Reading</h3>
                    <p>Select a Surah from the dropdown above to start.</p>
                </div>
            </div>
        @endif

    </div>
</div>

<div class="tajweed-drawer" id="tajweedDrawer">
    <div class="drawer-handle"></div>

    <div class="drawer-header">
        <span class="drawer-title" id="drawerTitle">Ayah Details</span>
        <button type="button" class="drawer-close" id="closeDrawerBtn">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div class="drawer-body" id="drawerBody">
        <div class="ayah-preview" id="ayahPreviewText" dir="rtl">—</div>
        <div class="drawer-translation" id="drawerTranslation">Translation will appear here.</div>
        <div id="rulesContainer"></div>
        <div class="drawer-actions" id="drawerActions"></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const quranPage = document.getElementById('quranPage');
    const surahSelect = document.getElementById('surahSelect');
    const ayahSelect = document.getElementById('ayahSelect');
    const fontBtns = document.querySelectorAll('.font-btn');
    const ayahCards = document.querySelectorAll('.ayah-card');
    const drawer = document.getElementById('tajweedDrawer');
    const closeDrawerBtn = document.getElementById('closeDrawerBtn');
    const drawerTitle = document.getElementById('drawerTitle');
    const ayahPreviewText = document.getElementById('ayahPreviewText');
    const drawerTranslation = document.getElementById('drawerTranslation');
    const rulesContainer = document.getElementById('rulesContainer');
    const drawerActions = document.getElementById('drawerActions');
    const translationToggle = document.getElementById('translationToggle');

    const currentSurahNumber = @json($currentSurah ?? 1);
    const ayahsPerPage = @json($ayahsPerPage ?? 8);

    const tajweedRules = [
        {
            key: 'ikhfa',
            name: 'Ikhfa Haqiqi',
            letters: ['ت','ث','ج','د','ذ','ز','س','ش','ص','ض','ط','ظ','ف','ق','ك'],
            description: 'Hide the Noon Sakinah or Tanween sound with a light ghunnah before one of the Ikhfa letters.'
        },
        {
            key: 'izhar',
            name: 'Izhar Halqi',
            letters: ['ء','ه','ع','ح','غ','خ'],
            description: 'Pronounce the Noon Sakinah or Tanween clearly before one of the throat letters.'
        }
    ];

    const arabicLetterPattern = /[\u0621-\u064A]/;
    const arabicMarkPattern = /[\u064B-\u065F\u0670\u06D6-\u06ED]/;
    const tanweenMarks = ['\u064B', '\u064C', '\u064D'];
    const vowelMarks = ['\u064B', '\u064C', '\u064D', '\u064E', '\u064F', '\u0650', '\u0651'];
    const sukunMark = '\u0652';

    function normalizeArabicLetter(letter) {
        return letter.replace(/[\u0623\u0625\u0624\u0626]/g, '\u0621');
    }

    function getMarksAfter(text, letterIndex) {
        let end = letterIndex + 1;
        let marks = '';

        while (end < text.length && arabicMarkPattern.test(text[end])) {
            marks += text[end];
            end++;
        }

        return { marks, end };
    }

    function getNextConsonantWithIndex(text, startIdx) {
        for (let i = startIdx; i < text.length; i++) {
            if (arabicMarkPattern.test(text[i])) continue;

            const normalized = normalizeArabicLetter(text[i]);

            if (arabicLetterPattern.test(normalized)) {
                return { letter: normalized, index: i };
            }
        }

        return null;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function findTajweedMatches(ayahText) {
        const matches = [];

        for (let i = 0; i < ayahText.length; i++) {
            const currentLetter = normalizeArabicLetter(ayahText[i]);
            if (!arabicLetterPattern.test(currentLetter)) continue;

            const { marks, end } = getMarksAfter(ayahText, i);
            const hasTanween = tanweenMarks.some(mark => marks.includes(mark));
            const hasExplicitSukun = marks.includes(sukunMark);
            const hasVowel = vowelMarks.some(mark => marks.includes(mark));
            const hasNoonSakin = currentLetter === '\u0646' && (hasExplicitSukun || !hasVowel);

            if (!hasTanween && !hasNoonSakin) continue;

            const next = getNextConsonantWithIndex(ayahText, end);
            if (!next) continue;

            const rule = tajweedRules.find(item => item.letters.includes(next.letter));

            if (rule) {
                matches.push({
                    key: rule.key,
                    name: rule.name,
                    description: rule.description,
                    nextLetter: ayahText[next.index],
                    start: i,
                    end
                });
            }
        }

        return matches;
    }

    function renderHighlightedArabic(card) {
        const arabicBox = card.querySelector('[data-arabic-text]');
        if (!arabicBox) return;

        const ayahText = card.dataset.ayahText || '';
        const matches = findTajweedMatches(ayahText);
        const boundaries = new Map();
        let html = '';

        matches.forEach(match => {
            boundaries.set(match.start, { type: 'start', key: match.key });
            boundaries.set(match.end, { type: 'end' });
        });

        for (let i = 0; i < ayahText.length; i++) {
            const boundary = boundaries.get(i);

            if (boundary?.type === 'start') {
                html += `<span class="tajweed-mark ${boundary.key}">`;
            }

            if (boundary?.type === 'end') {
                html += '</span>';
            }

            html += escapeHtml(ayahText[i]);
        }

        if (boundaries.has(ayahText.length)) {
            html += '</span>';
        }

        arabicBox.innerHTML = html;
    }

    function detectRules(ayahText) {
        const matches = findTajweedMatches(ayahText);

        return matches.filter((match, index, self) =>
            index === self.findIndex(m => m.key === match.key && m.nextLetter === match.nextLetter)
        );
    }

    function renderRules(matches) {
        if (!matches.length) {
            rulesContainer.innerHTML = `
                <div class="rule-item">
                    <div class="rule-name">No visible Ikhfa/Izhar detected</div>
                    <div class="rule-desc">
                        This ayah may contain other tajweed rules such as madd, qalqalah, idgham, or iqlab.
                    </div>
                </div>
            `;
            return;
        }

        rulesContainer.innerHTML = matches.map(m => `
            <div class="rule-item ${m.key}">
                <div class="rule-name">${m.name}</div>
                <div class="rule-desc">
                    ${m.description} <strong>Trigger letter: ${escapeHtml(m.nextLetter)}</strong>
                </div>
            </div>
        `).join('');
    }

    function renderActions(ayahText, surahNumber, ayahNumber, matches) {
        const encodedAyah = encodeURIComponent(ayahText);
        const baseParams = `ayah=${encodedAyah}&surah=${surahNumber}&ayah_number=${ayahNumber}`;
        let actionsHtml = '';

        if (matches.some(m => ['ikhfa', 'izhar'].includes(m.key)) || matches.length === 0) {
            actionsHtml += `<a href="{{ route('tajweed.checker') }}?${baseParams}" class="practice-link">
                <i class="fas fa-wand-magic-sparkles"></i> Check Ikhfa & Izhar
            </a>`;
        }

        drawerActions.innerHTML = actionsHtml;
    }

    function openDrawer(card) {
        document.querySelectorAll('.ayah-card').forEach(el => el.classList.remove('is-selected'));
        card.classList.add('is-selected');

        const surahNumber = card.dataset.surahNumber;
        const surahName = card.dataset.surahName;
        const ayahNumber = card.dataset.ayahNumber;
        const ayahText = card.dataset.ayahText || '';
        const translation = card.dataset.ayahTranslation || 'Translation not available for this ayah.';

        if (ayahSelect) {
            ayahSelect.value = ayahNumber;
        }

        drawerTitle.textContent = `${surahName} ${surahNumber}:${ayahNumber}`;
        ayahPreviewText.textContent = ayahText;
        drawerTranslation.textContent = translation;

        const rules = detectRules(ayahText);
        renderRules(rules);
        renderActions(ayahText, surahNumber, ayahNumber, rules);

        drawer.classList.add('open');
    }

    function closeDrawer() {
        drawer.classList.remove('open');
        document.querySelectorAll('.ayah-card').forEach(el => el.classList.remove('is-selected'));
    }

    function setFontSize(size) {
        const container = document.getElementById('ayahContainer');
        if (!container) return;

        container.classList.remove('font-small', 'font-medium', 'font-large', 'font-xlarge');
        container.classList.add(`font-${size}`);

        localStorage.setItem('quranFontSize', size);

        fontBtns.forEach(btn => {
            btn.classList.toggle('active', btn.dataset.size === size);
        });
    }

    if (surahSelect) {
        surahSelect.addEventListener('change', function () {
            if (this.value) {
                window.location.href = `/recite-quran/${this.value}`;
            }
        });
    }

    function goToAyah(ayahNumber) {
        const ayah = Math.max(1, Number(ayahNumber) || 1);
        const page = Math.max(1, Math.ceil(ayah / ayahsPerPage));
        window.location.href = `/recite-quran/${currentSurahNumber}?page=${page}&ayah=${ayah}#ayah-${ayah}`;
    }

    if (ayahSelect) {
        ayahSelect.addEventListener('change', function () {
            goToAyah(this.value);
        });
    }

    fontBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            setFontSize(this.dataset.size);
        });
    });

    ayahCards.forEach(card => {
        renderHighlightedArabic(card);

        card.addEventListener('click', function (event) {
            event.stopPropagation();
            openDrawer(this);
        });
    });

    if (closeDrawerBtn) {
        closeDrawerBtn.addEventListener('click', closeDrawer);
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && drawer.classList.contains('open')) {
            closeDrawer();
        }
    });

    document.addEventListener('click', function (event) {
        if (
            drawer.classList.contains('open') &&
            !drawer.contains(event.target) &&
            !event.target.closest('.ayah-card')
        ) {
            closeDrawer();
        }
    });

    if (translationToggle && quranPage) {
        const savedTranslation = localStorage.getItem('quranTranslationVisible');

        if (savedTranslation === 'false') {
            quranPage.classList.add('hide-translations');
            translationToggle.classList.remove('active');
        }

        translationToggle.addEventListener('click', function () {
            quranPage.classList.toggle('hide-translations');
            const visible = !quranPage.classList.contains('hide-translations');
            translationToggle.classList.toggle('active', visible);
            localStorage.setItem('quranTranslationVisible', visible ? 'true' : 'false');
        });
    }

    const savedFont = localStorage.getItem('quranFontSize') || 'medium';
    setFontSize(savedFont);

    if (window.location.hash) {
        const target = document.querySelector(window.location.hash);

        if (target) {
            setTimeout(() => {
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 250);
        }
    }
});
</script>
@endpush
