@extends('layouts.app')

@section('title', 'Recite Quran - ' . config('app.name'))

@section('styles')
    <style>
        /* Quran Recitation Page - Complete Redesign */
        :root {
            --primary: #2563eb;
            --primary-light: #3b82f6;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
            --light: #f8fafc;
            --dark: #1e293b;
            --gray: #64748b;
            --border: #e2e8f0;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
            --radius: 12px;
            --quran-green: #10b981;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            color: var(--dark);
            font-weight: 700;
            font-size: 2.25rem;
            margin-bottom: 0.5rem;
        }

        .page-header h1 i {
            color: var(--quran-green);
        }

        .page-header p {
            color: var(--gray);
            font-size: 1.1rem;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .stat-card {
            background: white;
            border-radius: var(--radius);
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.total {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
        }

        .stat-icon.ayahs {
            background: linear-gradient(135deg, var(--success), #34d399);
        }

        .stat-icon.meccan {
            background: linear-gradient(135deg, var(--warning), #fbbf24);
        }

        .stat-icon.medinan {
            background: linear-gradient(135deg, var(--info), #22d3ee);
        }

        .stat-content h3 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            color: var(--dark);
        }

        .stat-content p {
            margin: 0;
            color: var(--gray);
            font-size: 0.875rem;
        }

        /* Action Bar */
        .action-bar {
            background: white;
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .action-bar .row {
            align-items: center;
        }

        .filter-group label {
            font-size: 0.875rem;
            color: var(--gray);
            margin-bottom: 0.5rem;
            display: block;
            font-weight: 500;
        }

        .filter-group label i {
            color: var(--primary);
        }

        .form-select {
            border: 2px solid var(--border);
            border-radius: 8px;
            padding: 0.625rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(37, 99, 235, 0.25);
        }

        /* Font Size Controls */
        .font-controls {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .font-controls .btn-group .btn {
            padding: 0.375rem 0.75rem;
            font-weight: 600;
            border-radius: 6px !important;
        }

        .font-controls .btn.active {
            background: var(--quran-green);
            color: white;
            border-color: var(--quran-green);
        }

        /* Ayah Cards Grid */
        .ayah-grid {
            display: grid;
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .ayah-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .ayah-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-5px);
            border-color: var(--quran-green);
        }

        .ayah-card .card-header {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(37, 99, 235, 0.1));
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            position: relative;
        }

        .ayah-number {
            position: absolute;
            top: -12px;
            right: 20px;
            background: linear-gradient(135deg, var(--quran-green), var(--primary));
            color: white;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
            z-index: 1;
        }

        .ayah-text {
            font-family: 'Amiri', serif;
            font-size: 2.25rem;
            /* BIG FONT - 36px */
            line-height: 2.5;
            text-align: right;
            margin: 1.5rem 0;
            color: var(--dark);
            min-height: 100px;
            padding: 1rem;
            background: rgba(16, 185, 129, 0.05);
            border-radius: 8px;
            border-left: 4px solid var(--quran-green);
        }

        /* Font Size Classes */
        .ayah-text.small {
            font-size: 1.75rem;
            /* 28px */
            line-height: 2.2;
            min-height: 80px;
        }

        .ayah-text.medium {
            font-size: 2.25rem;
            /* 36px */
            line-height: 2.5;
            min-height: 100px;
        }

        .ayah-text.large {
            font-size: 2.75rem;
            /* 44px */
            line-height: 3;
            min-height: 120px;
        }

        .ayah-text.xlarge {
            font-size: 3.25rem;
            /* 52px */
            line-height: 3.5;
            min-height: 140px;
        }

        /* Adjust card padding for larger fonts */
        .ayah-card.xlarge-font {
            padding-top: 1rem;
        }

        .ayah-card.xlarge-font .ayah-number {
            width: 50px;
            height: 50px;
            font-size: 1.3rem;
        }

        .ayah-card .card-body {
            flex: 1;
            padding: 1.5rem;
        }

        .ayah-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .btn-practice {
            background: white;
            border: 2px solid var(--border);
            color: var(--dark);
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            text-decoration: none;
            font-size: 1rem;
        }

        .btn-practice:hover {
            background: var(--quran-green);
            color: white;
            border-color: var(--quran-green);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            text-decoration: none;
        }

        .btn-practice.ikhfa:hover {
            background: var(--primary);
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .btn-practice.izhar:hover {
            background: var(--info);
            border-color: var(--info);
            box-shadow: 0 4px 12px rgba(6, 182, 212, 0.3);
        }

        .ayah-card .card-footer {
            background: var(--light);
            border-top: 1px solid var(--border);
            padding: 1.5rem;
        }

        .ayah-card .card-footer .btn {
            padding: 0.625rem 1.25rem;
            font-weight: 500;
            font-size: 1rem;
        }

        /* Surah Navigation */
        .surah-nav {
            background: white;
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .nav-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            text-decoration: none;
            color: var(--primary);
            border: 2px solid var(--border);
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .nav-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            text-decoration: none;
            transform: translateX(-5px);
        }

        .nav-btn.next:hover {
            transform: translateX(5px);
        }

        .nav-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: var(--light);
            color: var(--gray);
            border-color: var(--border);
        }

        .nav-btn.disabled:hover {
            transform: none;
            background: var(--light);
            color: var(--gray);
            border-color: var(--border);
        }

        .surah-counter {
            text-align: center;
            color: var(--gray);
            font-weight: 500;
            font-size: 1.1rem;
        }

        .surah-counter .current {
            color: var(--quran-green);
            font-weight: 700;
            font-size: 1.75rem;
        }

        /* Quick Surah Links */
        .quick-surahs {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-top: 1.5rem;
            justify-content: center;
        }

        .quick-surah-link {
            padding: 0.625rem 1.5rem;
            background: white;
            border: 2px solid var(--border);
            border-radius: 50px;
            font-size: 1rem;
            color: var(--dark);
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .quick-surah-link:hover {
            background: linear-gradient(135deg, var(--quran-green), var(--primary));
            color: white;
            border-color: transparent;
            text-decoration: none;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .quick-surah-link.active {
            background: linear-gradient(135deg, var(--quran-green), var(--primary));
            color: white;
            border-color: transparent;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .empty-icon {
            font-size: 4rem;
            color: var(--gray);
            opacity: 0.5;
            margin-bottom: 1.5rem;
        }

        .empty-state h3 {
            color: var(--dark);
            font-weight: 600;
            margin-bottom: 1rem;
            font-size: 1.75rem;
        }

        .empty-state p {
            color: var(--gray);
            margin-bottom: 2rem;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
            font-size: 1.1rem;
        }

        /* Bottom Actions */
        .bottom-actions {
            padding-top: 2rem;
            margin-top: 2rem;
            border-top: 2px solid var(--border);
        }

        .bottom-actions .btn {
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            font-size: 1rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }

            .ayah-text {
                font-size: 1.75rem;
                line-height: 2.2;
            }

            .ayah-text.medium {
                font-size: 1.75rem;
            }

            .ayah-text.large {
                font-size: 2rem;
            }

            .ayah-text.xlarge {
                font-size: 2.25rem;
            }

            .surah-nav {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .nav-btn {
                min-width: 140px;
                justify-content: center;
            }

            .ayah-actions {
                flex-direction: column;
            }

            .ayah-actions .btn-practice {
                width: 100%;
                justify-content: center;
            }

            .action-bar .row {
                flex-direction: column;
            }

            .action-bar .col-md-4 {
                width: 100%;
                margin-bottom: 1rem;
            }
        }

        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .ayah-text {
                font-size: 1.5rem;
            }

            .ayah-text.medium {
                font-size: 1.5rem;
            }

            .ayah-text.large {
                font-size: 1.75rem;
            }

            .ayah-text.xlarge {
                font-size: 2rem;
            }

            .page-header h1 {
                font-size: 1.75rem;
            }

            .quick-surahs {
                justify-content: center;
            }

            .quick-surah-link {
                font-size: 0.9rem;
                padding: 0.5rem 1rem;
            }

            .bottom-actions .d-flex {
                flex-direction: column;
                width: 100%;
            }

            .bottom-actions .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }
    </style>
@endsection

@section('content')
    <div class="container py-4">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h1>
                        <i class="fas fa-book-quran me-3"></i>Recite Quran
                    </h1>
                    <p class="text-muted">Read and practice Quran with Tajweed guidance</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('tajweed.history') }}" class="btn btn-outline-primary">
                        <i class="fas fa-history me-2"></i>My Recitations
                    </a>
                    <a href="{{ route('home') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-home me-2"></i>Home
                    </a>
                </div>
            </div>
        </div>

        @if($surah)
            <!-- Stats Cards -->
            <div class="stats-grid horizontal">
                <div class="stat-row">
                    <div class="stat-card">
                        <div class="stat-icon total">
                            <i class="fas fa-hashtag"></i>
                        </div>
                        <div class="stat-content">
                            <h3>{{ $surah['number'] }}</h3>
                            <p>Surah Number</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon ayahs">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="stat-content">
                            <h3>{{ $surah['numberOfAyahs'] }}</h3>
                            <p>Total Ayahs</p>
                        </div>
                    </div>
                </div>

                <div class="stat-row">
                    <div class="stat-card">
                        @if($surah['revelationType'] == 'Meccan')
                            <div class="stat-icon meccan">
                                <i class="fas fa-kaaba"></i>
                            </div>
                            <div class="stat-content">
                                <h3>Meccan</h3>
                                <p>Revelation Type</p>
                            </div>
                        @else
                            <div class="stat-icon medinan">
                                <i class="fas fa-mosque"></i>
                            </div>
                            <div class="stat-content">
                                <h3>Medinan</h3>
                                <p>Revelation Type</p>
                            </div>
                        @endif
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon total">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="stat-content">
                            <h3>{{ $surah['englishName'] }}</h3>
                            <p>Surah Name</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Action Bar -->
        <div class="action-bar">
            <div class="row g-3 align-items-center">
                <div class="col-md-4">
                    <div class="filter-group">
                        <label><i class="fas fa-book-open me-2"></i>Select Surah:</label>
                        <select class="form-select" id="surahSelect"
                            onchange="window.location.href='/recite-quran/' + this.value">
                            <option value="">Select Surah...</option>
                            @foreach($allSurahs as $s)
                                <option value="{{ $s['number'] }}" {{ $currentSurah == $s['number'] ? 'selected' : '' }}>
                                    {{ $s['number'] }}. {{ $s['englishName'] }} ({{ $s['name'] }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="font-controls justify-content-center">
                        <label class="me-2"><i class="fas fa-text-height"></i> Font Size:</label>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-outline-secondary" data-font-size="small" title="Small">
                                S
                            </button>
                            <button type="button" class="btn btn-outline-primary active" data-font-size="medium"
                                title="Medium">
                                M
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-font-size="large" title="Large">
                                L
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-font-size="xlarge"
                                title="Extra Large">
                                XL
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <!-- Quick Surah Links -->
                    <div class="quick-surahs">
                        <a href="{{ route('recite.quran', ['surah' => 1]) }}"
                            class="quick-surah-link {{ $currentSurah == 1 ? 'active' : '' }}" title="Al-Fatihah">
                            1
                        </a>
                        <a href="{{ route('recite.quran', ['surah' => 2]) }}"
                            class="quick-surah-link {{ $currentSurah == 2 ? 'active' : '' }}" title="Al-Baqarah">
                            2
                        </a>
                        <a href="{{ route('recite.quran', ['surah' => 36]) }}"
                            class="quick-surah-link {{ $currentSurah == 36 ? 'active' : '' }}" title="Yaseen">
                            36
                        </a>
                        <a href="{{ route('recite.quran', ['surah' => 67]) }}"
                            class="quick-surah-link {{ $currentSurah == 67 ? 'active' : '' }}" title="Al-Mulk">
                            67
                        </a>
                    </div>
                </div>
            </div>
        </div>

        @if(!$surah)
            <!-- Empty State -->
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-book"></i>
                </div>
                <h3>Select a Surah</h3>
                <p>Choose a surah from the dropdown above to start reading</p>
                <a href="{{ route('recite.quran', ['surah' => 1]) }}" class="btn btn-primary">
                    <i class="fas fa-play me-2"></i>Start with Al-Fatihah
                </a>
            </div>
        @else
            <!-- Ayahs Grid -->
            <div class="ayah-grid">
                @if(isset($surah['ayahs']) && count($surah['ayahs']) > 0)
                    @foreach($surah['ayahs'] as $ayah)
                        <div class="ayah-card" id="ayah-{{ $ayah['numberInSurah'] }}">
                            <div class="card-header">
                                <div class="ayah-number">{{ $ayah['numberInSurah'] }}</div>
                                <div class="ayah-text medium">
                                    {{ $ayah['text'] }}
                                </div>
                            </div>

                            <div class="card-body">
                                <div class="ayah-actions">
                                    <a href="{{ route('tajweed.ikhfa-haqiqi') }}?ayah={{ urlencode($ayah['text']) }}"
                                        class="btn-practice">
                                        <i class="fas fa-volume-down me-2"></i>Practice Ikhfa
                                    </a>
                                    <a href="{{ route('tajweed.izhar-halqi') }}?ayah={{ urlencode($ayah['text']) }}"
                                        class="btn-practice">
                                        <i class="fas fa-volume-up me-2"></i>Practice Izhar
                                    </a>
                                </div>
                            </div>

                            <div class="card-footer">
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>Ayah {{ $ayah['numberInSurah'] }}
                                    </small>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle empty-icon"></i>
                        <h3>No Ayahs Found</h3>
                        <p>Unable to load ayahs for this surah. Please try another surah.</p>
                    </div>
                @endif
            </div>

            <!-- Surah Navigation -->
            <div class="surah-nav">
                @if($currentSurah > 1)
                    <a href="{{ route('recite.quran', ['surah' => $currentSurah - 1]) }}" class="nav-btn">
                        <i class="fas fa-chevron-left me-2"></i>
                        Previous Surah
                    </a>
                @else
                    <span class="nav-btn disabled">
                        <i class="fas fa-chevron-left me-2"></i>
                        Previous Surah
                    </span>
                @endif

                <div class="surah-counter">
                    <div class="mb-1">
                        <span class="current">{{ $currentSurah }}</span>
                        <span> of 114</span>
                    </div>
                    <div>
                        <small class="text-muted">{{ $surah['englishName'] }}</small>
                    </div>
                </div>

                @if($currentSurah < 114)
                    <a href="{{ route('recite.quran', ['surah' => $currentSurah + 1]) }}" class="nav-btn next">
                        Next Surah
                        <i class="fas fa-chevron-right ms-2"></i>
                    </a>
                @else
                    <span class="nav-btn next disabled">
                        Next Surah
                        <i class="fas fa-chevron-right ms-2"></i>
                    </span>
                @endif
            </div>
        @endif

        <!-- Bottom Actions -->
        <div class="bottom-actions">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="d-flex gap-2">
                    <a href="{{ route('tajweed.ikhfa-haqiqi') }}" class="btn btn-primary">
                        <i class="fas fa-volume-down me-2"></i>Practice Ikhfa
                    </a>
                    <a href="{{ route('tajweed.izhar-halqi') }}" class="btn btn-success">
                        <i class="fas fa-volume-up me-2"></i>Practice Izhar
                    </a>
                </div>
                <div>
                    <a href="{{ route('tajweed.history') }}" class="btn btn-outline-primary">
                        <i class="fas fa-history me-2"></i>View All Recitations
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Font size control functionality
            const fontSizeButtons = document.querySelectorAll('[data-font-size]');
            const ayahTexts = document.querySelectorAll('.ayah-text');
            const ayahCards = document.querySelectorAll('.ayah-card');

            // Get saved font size from localStorage or use medium as default
            const savedFontSize = localStorage.getItem('quranFontSize') || 'medium';
            setFontSize(savedFontSize);

            // Set active button based on saved size
            fontSizeButtons.forEach(button => {
                if (button.getAttribute('data-font-size') === savedFontSize) {
                    button.classList.remove('btn-outline-secondary');
                    button.classList.add('btn-outline-primary', 'active');
                }
            });

            // Add click events to font size buttons
            fontSizeButtons.forEach(button => {
                button.addEventListener('click', function () {
                    const fontSize = this.getAttribute('data-font-size');
                    setFontSize(fontSize);

                    // Update button styles
                    fontSizeButtons.forEach(btn => {
                        btn.classList.remove('btn-outline-primary', 'active');
                        btn.classList.add('btn-outline-secondary');
                    });
                    this.classList.remove('btn-outline-secondary');
                    this.classList.add('btn-outline-primary', 'active');
                });
            });

            function setFontSize(size) {
                // Save to localStorage
                localStorage.setItem('quranFontSize', size);

                // Apply to all ayah texts
                ayahTexts.forEach(text => {
                    text.classList.remove('small', 'medium', 'large', 'xlarge');
                    text.classList.add(size);
                });

                // Adjust card for very large fonts
                ayahCards.forEach(card => {
                    if (size === 'xlarge') {
                        card.classList.add('xlarge-font');
                    } else {
                        card.classList.remove('xlarge-font');
                    }
                });
            }

            // Auto-scroll to specific ayah if URL hash exists
            const hash = window.location.hash;
            if (hash) {
                const ayahElement = document.querySelector(hash);
                if (ayahElement) {
                    setTimeout(() => {
                        ayahElement.scrollIntoView({ behavior: 'smooth', block: 'center' });

                        // Highlight the ayah briefly
                        ayahElement.style.boxShadow = '0 0 0 4px rgba(16, 185, 129, 0.3)';
                        ayahElement.style.border = '2px solid var(--quran-green)';

                        setTimeout(() => {
                            ayahElement.style.boxShadow = '';
                            ayahElement.style.border = '';
                        }, 3000);
                    }, 500);
                }
            }

            // Keyboard navigation
            document.addEventListener('keydown', function (e) {
                // Left arrow for previous surah
                if (e.key === 'ArrowLeft' && {{ $currentSurah > 1 ? 'true' : 'false' }}) {
                    window.location.href = "{{ route('recite.quran', ['surah' => $currentSurah - 1]) }}";
                }
                // Right arrow for next surah
                else if (e.key === 'ArrowRight' && {{ $currentSurah < 114 ? 'true' : 'false' }}) {
                    window.location.href = "{{ route('recite.quran', ['surah' => $currentSurah + 1]) }}";
                }
                // Space to scroll down
                else if (e.key === ' ' && !e.target.matches('input, textarea, select, button, a')) {
                    e.preventDefault();
                    window.scrollBy({ top: window.innerHeight * 0.8, behavior: 'smooth' });
                }
            });
        });
    </script>
@endsection