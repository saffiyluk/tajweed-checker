{{-- resources/views/reciteQuran.blade.php --}}
@extends('layouts.app')

@section('title', 'Recite Quran - ' . config('app.name'))

@push('styles')
    <style>
        :root {
            --quran-primary: #2563eb;
            --quran-primary-light: #3b82f6;
            --quran-success: #10b981;
            --quran-danger: #ef4444;
            --quran-light: #f8fafc;
            --quran-surface: #ffffff;
            --quran-ink: #1e293b;
            --quran-muted: #64748b;
            --quran-border: #e2e8f0;
            --quran-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --quran-shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
            --radius-card: 12px;
        }

        /* Override main container padding for Quran page */
        .container.py-4 {
            padding-top: 1.5rem !important;
            padding-bottom: 2rem !important;
            max-width: 1280px;
        }

        body .scroll-top {
            display: none !important;
        }

        /* Quran Reader */
        .quran-reader {
            margin: 0 auto;
        }

        /* Header Section */
        .reader-header {
            margin-bottom: 1.5rem;
        }

        .reader-header h1 {
            font-size: 2.25rem;
            font-weight: 600;
            color: var(--quran-ink);
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 0.25rem;
        }

        .reader-header h1 i {
            color: var(--quran-primary);
            font-size: 1.6rem;
        }

        .reader-header p {
            color: var(--quran-muted);
            font-size: 0.9rem;
            margin: 0;
        }

        /* Control Bar */
        .control-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            background: white;
            padding: 0.5rem 1.2rem;
            border-radius: 60px;
            box-shadow: var(--quran-shadow);
            margin-bottom: 2rem;
            border: 1px solid var(--quran-border);
        }

        .surah-select-wrapper {
            flex: 2;
            min-width: 220px;
        }

        .surah-select-custom {
            width: 100%;
            padding: 0.6rem 1rem;
            border-radius: 50px;
            border: 1px solid var(--quran-border);
            background: var(--quran-light);
            font-weight: 500;
            color: var(--quran-ink);
            font-family: 'Poppins', sans-serif;
            outline: none;
            transition: all 0.2s;
            cursor: pointer;
        }

        .surah-select-custom:focus {
            border-color: var(--quran-primary);
            box-shadow: 0 0 0 0.25rem rgba(37, 99, 235, 0.25);
        }

        .right-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .reader-tools {
            align-items: center;
            background: var(--quran-surface);
            border: 1px solid var(--quran-border);
            border-radius: 8px;
            box-shadow: var(--quran-shadow);
            display: grid;
            gap: 1rem;
            grid-template-columns: minmax(180px, 280px) auto;
            margin-bottom: 1.25rem;
            padding: 1rem 1.2rem;
        }

        .reader-tool-label {
            color: var(--quran-muted);
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            margin-bottom: 0.35rem;
            text-transform: uppercase;
        }

        .ayah-select-custom {
            background: var(--quran-light);
            border: 1px solid var(--quran-border);
            border-radius: 8px;
            color: var(--quran-ink);
            font-weight: 500;
            padding: 0.65rem 0.8rem;
            width: 100%;
        }

        .page-chip {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 999px;
            color: #1d4ed8;
            font-size: 0.85rem;
            font-weight: 700;
            padding: 0.6rem 1rem;
            white-space: nowrap;
        }

        .font-badge {
            background: var(--quran-light);
            border-radius: 60px;
            padding: 0.2rem;
            display: inline-flex;
            gap: 4px;
            border: 1px solid var(--quran-border);
        }

        .font-btn {
            background: transparent;
            border: none;
            width: 34px;
            height: 34px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.8rem;
            color: var(--quran-muted);
            transition: all 0.15s;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
        }

        .font-btn.active {
            background: var(--quran-primary);
            color: white;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.22);
        }

        .history-link {
            background: transparent;
            border: 1px solid var(--quran-border);
            padding: 0.45rem 1rem;
            border-radius: 60px;
            color: var(--quran-primary);
            font-weight: 500;
            text-decoration: none;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .history-link:hover {
            background: var(--quran-primary);
            border-color: var(--quran-primary);
            color: white;
            text-decoration: none;
        }

        /* Mushaf Card */
        .mushaf-card {
            background: var(--quran-surface);
            border-radius: var(--radius-card);
            box-shadow: var(--quran-shadow);
            border: 1px solid var(--quran-border);
            overflow: hidden;
        }

        .surah-banner {
            background: var(--quran-light);
            padding: 1.5rem 1rem;
            text-align: center;
            border-bottom: 1px solid var(--quran-border);
        }

        .surah-ar {
            font-family: 'Amiri', serif;
            font-size: 2.6rem;
            color: var(--quran-primary);
            font-weight: 700;
            letter-spacing: 1px;
        }

        .surah-en {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--quran-ink);
            margin-top: 0.25rem;
        }

        .surah-meta {
            display: flex;
            justify-content: center;
            gap: 1.2rem;
            margin-top: 0.6rem;
            font-size: 0.8rem;
            color: var(--quran-muted);
        }

        .ayah-container {
            padding: 2rem;
            direction: rtl;
            background: var(--quran-surface);
            min-height: 560px;
        }

        .ayah-grid {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .ayah-token {
            background: transparent;
            border: none;
            font-family: 'Amiri', 'Times New Roman', serif;
            color: var(--quran-ink);
            cursor: pointer;
            padding: 0.75rem 1rem;
            border-radius: 16px;
            transition: all 0.2s ease;
            line-height: 2.15;
            display: block;
            width: 100%;
            text-align: right;
        }

        .ayah-token:hover {
            background: rgba(37, 99, 235, 0.06);
        }

        .ayah-token.is-selected {
            background: rgba(37, 99, 235, 0.1);
            color: var(--quran-primary);
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }

        .tajweed-mark {
            border-radius: 0.35em;
            padding: 0 0.12em;
        }

        .tajweed-mark.ikhfa {
            background: rgba(16, 185, 129, 0.14);
            color: #047857;
        }

        .tajweed-mark.izhar {
            background: rgba(239, 68, 68, 0.14);
            color: #dc2626;
        }

        /* Font Sizes */
        .font-small .ayah-token { font-size: 1.5rem; }
        .font-medium .ayah-token { font-size: 1.8rem; }
        .font-large .ayah-token { font-size: 2.1rem; }
        .font-xlarge .ayah-token { font-size: 2.5rem; }

        .ayah-end-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.7em;
            height: 1.7em;
            background: rgba(37, 99, 235, 0.08);
            border-radius: 50%;
            font-size: 0.55em;
            font-weight: 500;
            color: var(--quran-primary);
            margin-right: 0.2rem;
            margin-left: 0.1rem;
            vertical-align: middle;
            font-family: 'Poppins', sans-serif;
        }

        /* Navigation */
        .surah-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 2rem 1.5rem;
            background: var(--quran-light);
            border-top: 1px solid var(--quran-border);
            flex-wrap: wrap;
            gap: 0.8rem;
        }

        .nav-btn {
            background: white;
            border: 1px solid var(--quran-border);
            padding: 0.5rem 1.3rem;
            border-radius: 60px;
            font-weight: 500;
            color: var(--quran-primary);
            transition: 0.2s;
            text-decoration: none;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .nav-btn i {
            font-size: 0.85rem;
            line-height: 1;
        }

        .nav-btn:hover:not(.disabled) {
            background: var(--quran-primary);
            border-color: var(--quran-primary);
            color: white;
            text-decoration: none;
        }

        .nav-btn.disabled {
            opacity: 0.4;
            pointer-events: none;
        }

        .surah-counter {
            background: var(--quran-light);
            padding: 0.4rem 1.2rem;
            border-radius: 60px;
            font-size: 0.85rem;
            color: var(--quran-ink);
            font-weight: 500;
        }

        .page-summary {
            color: var(--quran-muted);
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        /* Tajweed Drawer */
        .tajweed-drawer {
            position: fixed;
            bottom: 0;
            right: 0;
            left: 0;
            background: white;
            border-radius: 28px 28px 0 0;
            box-shadow: var(--quran-shadow-lg);
            max-width: 520px;
            margin: 0 auto;
            transform: translateY(100%);
            transition: transform 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            z-index: 1060;
            border-top: 1px solid var(--quran-border);
            overflow: hidden;
        }

        .tajweed-drawer.open {
            transform: translateY(0);
        }

        .drawer-handle {
            width: 50px;
            height: 5px;
            background: var(--quran-border);
            border-radius: 10px;
            margin: 12px auto 8px;
        }

        .drawer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 1.5rem 0.2rem;
        }

        .drawer-title {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--quran-ink);
        }

        .drawer-close {
            background: var(--quran-light);
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 30px;
            cursor: pointer;
            color: var(--quran-muted);
            transition: 0.2s;
        }

        .drawer-close:hover {
            background: var(--quran-border);
        }

        .drawer-body {
            padding: 0.5rem 1.5rem 1.5rem;
            max-height: 65vh;
            overflow-y: auto;
        }

        .ayah-preview {
            background: var(--quran-light);
            border-radius: 20px;
            padding: 1rem;
            direction: rtl;
            font-family: 'Amiri', serif;
            font-size: 1.6rem;
            border: 1px solid var(--quran-border);
            margin: 1rem 0;
        }

        .rule-item {
            background: var(--quran-light);
            border-radius: 16px;
            padding: 0.8rem;
            margin-bottom: 0.8rem;
            border-left: 4px solid var(--quran-primary);
        }

        .rule-item.ikhfa { border-left-color: var(--quran-success); }
        .rule-item.izhar { border-left-color: var(--quran-danger); }

        .rule-name {
            font-weight: 700;
            margin-bottom: 4px;
            font-size: 0.95rem;
        }

        .rule-desc {
            font-size: 0.8rem;
            color: var(--quran-muted);
        }

        .drawer-actions {
            display: flex;
            gap: 12px;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .practice-link {
            background: var(--quran-light);
            border-radius: 40px;
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--quran-primary);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: 0.2s;
        }

        .practice-link:hover {
            background: var(--quran-primary);
            color: white;
            text-decoration: none;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--quran-muted);
        }

        @media (max-width: 768px) {
            .control-bar {
                border-radius: 28px;
                padding: 0.8rem 1rem;
            }
            .reader-tools {
                grid-template-columns: 1fr;
            }
            .ayah-container {
                padding: 1rem;
                min-height: 460px;
            }
            .surah-nav {
                padding: 1rem;
            }
            .surah-ar {
                font-size: 2rem;
            }
            .font-small .ayah-token { font-size: 1.3rem; }
            .font-medium .ayah-token { font-size: 1.6rem; }
            .font-large .ayah-token { font-size: 1.9rem; }
            .font-xlarge .ayah-token { font-size: 2.2rem; }
        }
    </style>
@endpush

@section('content')
<div class="quran-reader">
    {{-- Header --}}
    <div class="reader-header">
        <h1>
            <i class="fas fa-book-quran"></i> 
            Recite Quran
        </h1>
        <p>Tap any verse to reveal tajweed insights & practice rules</p>
    </div>

    {{-- Control Bar --}}
    <div class="control-bar">
        <div class="surah-select-wrapper">
            <select class="surah-select-custom" id="surahSelect">
                <option value="">— Select a Surah —</option>
                @foreach($allSurahs as $surahOption)
                    <option value="{{ $surahOption['number'] }}" {{ (isset($currentSurah) && $currentSurah == $surahOption['number']) ? 'selected' : '' }}>
                        {{ $surahOption['number'] }}. {{ $surahOption['englishName'] }} ({{ $surahOption['name'] }})
                    </option>
                @endforeach
            </select>
        </div>
        <div class="right-actions">
            <div class="font-badge">
                <button class="font-btn" data-size="small">ص</button>
                <button class="font-btn" data-size="medium">م</button>
                <button class="font-btn" data-size="large">ك</button>
                <button class="font-btn" data-size="xlarge">ك+</button>
            </div>
            <a href="{{ route('tajweed.history') }}" class="history-link">
                <i class="fas fa-history"></i> History
            </a>
        </div>
    </div>

    {{-- Main Mushaf Card --}}
    @if(isset($surah) && $surah)
        <div class="reader-tools">
            <div>
                <label class="reader-tool-label" for="ayahSelect">Jump to Ayah</label>
                <select class="ayah-select-custom" id="ayahSelect">
                    @for($ayahNumber = 1; $ayahNumber <= $totalAyahs; $ayahNumber++)
                        <option value="{{ $ayahNumber }}" @selected($selectedAyah == $ayahNumber)>
                            Ayah {{ $ayahNumber }}
                        </option>
                    @endfor
                </select>
            </div>
            <div class="page-chip">
                Page {{ $currentPage }} / {{ $totalPages }}
            </div>
        </div>

        <div class="mushaf-card">
            <div class="surah-banner">
                <div class="surah-ar">{{ $surah['name'] ?? '' }}</div>
                <div class="surah-en">{{ $surah['englishName'] ?? 'Surah' }}</div>
                <div class="surah-meta">
                    <span>Surah {{ $surah['number'] }}</span>
                    <span>{{ $surah['numberOfAyahs'] }} Ayahs</span>
                    <span>{{ $surah['revelationType'] }}</span>
                </div>
            </div>

            <div class="ayah-container" id="ayahContainer">
                @if(isset($pagedAyahs) && count($pagedAyahs) > 0)
                    <div class="ayah-grid" id="ayahGrid">
                        @foreach($pagedAyahs as $ayah)
                            <button type="button"
                                id="ayah-{{ $ayah['numberInSurah'] }}"
                                class="ayah-token {{ $selectedAyah == $ayah['numberInSurah'] ? 'is-selected' : '' }}"
                                data-surah-number="{{ $surah['number'] }}"
                                data-surah-name="{{ $surah['englishName'] ?? 'Surah' }}"
                                data-ayah-number="{{ $ayah['numberInSurah'] }}"
                                data-ayah-text="{{ e($ayah['text']) }}">
                                {{ $ayah['text'] }}<span class="ayah-end-number">{{ $ayah['numberInSurah'] }}</span>
                            </button>
                        @endforeach
                    </div>
                @else
                    <div class="empty-state">
                        <i class="fas fa-info-circle fa-3x mb-3"></i>
                        <h3>No Ayahs Loaded</h3>
                        <p>Please try another surah.</p>
                    </div>
                @endif
            </div>

            <div class="surah-nav">
                @if($currentPage > 1)
                    <a href="{{ route('recite.quran', ['surah' => $currentSurah, 'page' => $currentPage - 1, 'ayah' => max(1, $pageStart - $ayahsPerPage)]) }}" class="nav-btn">
                        <i class="fas fa-arrow-right"></i> Previous Page
                    </a>
                @elseif($currentSurah > 1)
                    <a href="{{ route('recite.quran', ['surah' => $currentSurah - 1]) }}" class="nav-btn">
                        <i class="fas fa-arrow-right"></i> Previous Surah
                    </a>
                @else
                    <span class="nav-btn disabled"><i class="fas fa-arrow-right"></i> Previous</span>
                @endif

                <div class="surah-counter">
                    <strong>{{ $surah['englishName'] ?? '' }}</strong>
                    <span class="ms-1">Surah {{ $currentSurah }} / 114</span>
                    <div class="page-summary">Ayah {{ $pageStart }}-{{ $pageEnd }} of {{ $totalAyahs }}</div>
                </div>

                @if($currentPage < $totalPages)
                    <a href="{{ route('recite.quran', ['surah' => $currentSurah, 'page' => $currentPage + 1, 'ayah' => min($totalAyahs, $pageEnd + 1)]) }}" class="nav-btn">
                        Next Page <i class="fas fa-arrow-left"></i>
                    </a>
                @elseif($currentSurah < 114)
                    <a href="{{ route('recite.quran', ['surah' => $currentSurah + 1]) }}" class="nav-btn">
                        Next Surah <i class="fas fa-arrow-left"></i>
                    </a>
                @else
                    <span class="nav-btn disabled">Next <i class="fas fa-arrow-left"></i></span>
                @endif
            </div>
        </div>
    @else
        <div class="mushaf-card">
            <div class="empty-state" style="padding: 4rem;">
                <i class="fas fa-quran fa-4x mb-3" style="color: var(--quran-primary);"></i>
                <h3>Begin Your Reading</h3>
                <p>Select a Surah from the dropdown above to start.</p>
            </div>
        </div>
    @endif
</div>

{{-- Tajweed Drawer Panel --}}
<div class="tajweed-drawer" id="tajweedDrawer">
    <div class="drawer-handle"></div>
    <div class="drawer-header">
        <span class="drawer-title" id="drawerTitle">Ayah Details</span>
        <button class="drawer-close" id="closeDrawerBtn"><i class="fas fa-times"></i></button>
    </div>
    <div class="drawer-body" id="drawerBody">
        <div class="ayah-preview" id="ayahPreviewText" dir="rtl">—</div>
        <div id="rulesContainer"></div>
        <div class="drawer-actions" id="drawerActions"></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // DOM Elements
        const surahSelect = document.getElementById('surahSelect');
        const ayahSelect = document.getElementById('ayahSelect');
        const fontBtns = document.querySelectorAll('.font-btn');
        const ayahTokens = document.querySelectorAll('.ayah-token');
        const drawer = document.getElementById('tajweedDrawer');
        const closeDrawerBtn = document.getElementById('closeDrawerBtn');
        const drawerTitle = document.getElementById('drawerTitle');
        const ayahPreviewText = document.getElementById('ayahPreviewText');
        const rulesContainer = document.getElementById('rulesContainer');
        const drawerActions = document.getElementById('drawerActions');
        const currentSurahNumber = @json($currentSurah ?? 1);
        const ayahsPerPage = @json($ayahsPerPage ?? 8);

        // Tajweed Rules Data
        const tajweedRules = [
            { key: 'ikhfa', name: 'Ikhfa Haqiqi', letters: ['\u062a','\u062b','\u062c','\u062f','\u0630','\u0632','\u0633','\u0634','\u0635','\u0636','\u0637','\u0638','\u0641','\u0642','\u0643'], description: 'Hide the noon sakinah or tanween sound with a light ghunnah before one of the Ikhfa letters.' },
            { key: 'izhar', name: 'Izhar Halqi', letters: ['\u0621','\u0647','\u0639','\u062d','\u063a','\u062e'], description: 'Pronounce the noon sakinah or tanween clearly before one of the throat letters.' }
        ];

        const arabicLetterPattern = /[\u0621-\u064A]/;
        const arabicMarkPattern = /[\u064B-\u065F\u0670\u06D6-\u06ED]/;
        const tanweenMarks = ['\u064B', '\u064C', '\u064D'];
        const vowelMarks = ['\u064B', '\u064C', '\u064D', '\u064E', '\u064F', '\u0650', '\u0651'];
        const sukunMark = '\u0652';

        // Helper: normalize only hamza variants needed for rule matching.
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

        function renderHighlightedAyah(token) {
            const ayahText = token.dataset.ayahText || '';
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

                const escapedChar = escapeHtml(ayahText[i]);
                html += escapedChar;
            }

            if (boundaries.has(ayahText.length)) {
                html += '</span>';
            }

            token.innerHTML = `${html}<span class="ayah-end-number">${token.dataset.ayahNumber}</span>`;
        }

        // Detect Tajweed rules in ayah text
        function detectRules(ayahText) {
            const matches = findTajweedMatches(ayahText);

            // Remove duplicates
            return matches.filter((match, index, self) => 
                index === self.findIndex(m => m.key === match.key && m.nextLetter === match.nextLetter)
            );
        }

        // Render rules in drawer
        function renderRules(matches) {
            if (!matches.length) {
                rulesContainer.innerHTML = `
                    <div class="rule-item" style="border-left-color: var(--quran-muted);">
                        <div class="rule-name">No visible Ikhfa/Izhar</div>
                        <div class="rule-desc">Focus on madd, qalqalah or other tajweed aspects in this ayah.</div>
                    </div>
                `;
                return;
            }
            rulesContainer.innerHTML = matches.map(m => `
                <div class="rule-item ${m.key}">
                    <div class="rule-name">${m.name}</div>
                    <div class="rule-desc">${m.description} <strong>(Trigger: ${m.nextLetter})</strong></div>
                </div>
            `).join('');
        }

        // Render practice actions
        function renderActions(ayahText, surahNumber, ayahNumber, matches) {
            const encodedAyah = encodeURIComponent(ayahText);
            const baseParams = `ayah=${encodedAyah}&surah=${surahNumber}&ayah_number=${ayahNumber}`;
            const hasIkhfa = matches.some(m => m.key === 'ikhfa');
            const hasIzhar = matches.some(m => m.key === 'izhar');
            
            let actionsHtml = '';
            if (hasIkhfa || matches.length === 0) {
                actionsHtml += `<a href="{{ route('tajweed.ikhfa-haqiqi') }}?${baseParams}" class="practice-link"><i class="fas fa-microphone-alt"></i> Practice Ikhfa</a>`;
            }
            if (hasIzhar || matches.length === 0) {
                actionsHtml += `<a href="{{ route('tajweed.izhar-halqi') }}?${baseParams}" class="practice-link"><i class="fas fa-volume-up"></i> Practice Izhar</a>`;
            }
            drawerActions.innerHTML = actionsHtml || '<span class="text-muted small">Select a practice session from the Test Tajweed menu</span>';
        }

        // Open drawer with ayah details
        function openDrawer(ayahElement) {
            // Remove selected class from all and add to current
            document.querySelectorAll('.ayah-token').forEach(el => el.classList.remove('is-selected'));
            ayahElement.classList.add('is-selected');

            const surahNumber = ayahElement.dataset.surahNumber;
            const surahName = ayahElement.dataset.surahName;
            const ayahNumber = ayahElement.dataset.ayahNumber;
            const ayahText = ayahElement.dataset.ayahText;

            if (ayahSelect) {
                ayahSelect.value = ayahNumber;
            }

            drawerTitle.textContent = `${surahName} ${surahNumber}:${ayahNumber}`;
            ayahPreviewText.textContent = ayahText;

            const rules = detectRules(ayahText);
            renderRules(rules);
            renderActions(ayahText, surahNumber, ayahNumber, rules);

            drawer.classList.add('open');
        }

        // Close drawer
        function closeDrawer() {
            drawer.classList.remove('open');
            document.querySelectorAll('.ayah-token').forEach(el => el.classList.remove('is-selected'));
        }

        // Font size management
        function setFontSize(size) {
            const container = document.getElementById('ayahContainer');
            if (!container) return;
            container.classList.remove('font-small', 'font-medium', 'font-large', 'font-xlarge');
            container.classList.add(`font-${size}`);
            localStorage.setItem('quranFontSize', size);
            fontBtns.forEach(btn => {
                if (btn.dataset.size === size) btn.classList.add('active');
                else btn.classList.remove('active');
            });
        }

        // Surah select change
        if (surahSelect) {
            surahSelect.addEventListener('change', function() {
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
            ayahSelect.addEventListener('change', function() {
                goToAyah(this.value);
            });
        }

        // Font buttons
        fontBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                setFontSize(this.dataset.size);
            });
        });

        // Paint tajweed highlights inside each displayed ayah.
        ayahTokens.forEach(renderHighlightedAyah);

        // Ayah click handlers
        ayahTokens.forEach(token => {
            token.addEventListener('click', function(e) {
                e.stopPropagation();
                openDrawer(this);
            });
        });

        // Close drawer handlers
        if (closeDrawerBtn) {
            closeDrawerBtn.addEventListener('click', closeDrawer);
        }

        // Close on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && drawer.classList.contains('open')) {
                closeDrawer();
            }
        });

        // Close on click outside (but not on ayah tokens)
        document.addEventListener('click', function(e) {
            if (drawer.classList.contains('open') && 
                !drawer.contains(e.target) && 
                !e.target.closest('.ayah-token')) {
                closeDrawer();
            }
        });

        // Load saved font size
        const savedFont = localStorage.getItem('quranFontSize') || 'medium';
        setFontSize(savedFont);

        // Handle hash scroll if present
        if (window.location.hash) {
            const target = document.querySelector(window.location.hash);
            if (target) {
                setTimeout(() => target.scrollIntoView({ behavior: 'smooth', block: 'center' }), 250);
            }
        }
    });
</script>
@endpush
