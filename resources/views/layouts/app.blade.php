<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>
        @hasSection('title')
            @yield('title') | {{ config('app.name', 'Tajweed Checker') }}
        @else
            {{ config('app.name', 'Tajweed Checker') }}
        @endif
    </title>

    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Poppins:300,400,500,600,700,800,900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Scheherazade+New:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    @vite(['resources/sass/app.scss', 'resources/js/app.js'])

    <style>
        :root {
            --app-primary: #2563eb;
            --app-primary-dark: #1d4ed8;
            --app-gold: #c29950;
            --app-dark: #0f172a;
            --app-muted: #64748b;
            --app-soft: #f8fafc;
            --app-line: #e2e8f0;
            --app-success: #16a34a;
            --app-danger: #dc2626;
            --app-warning: #d97706;
            --app-shadow: 0 18px 50px rgba(15, 23, 42, 0.10);
            --app-radius: 22px;
            --navbar-height: 78px;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-family: 'Poppins', sans-serif;
            color: var(--app-dark);
            background:
                radial-gradient(circle at top left, rgba(37, 99, 235, 0.10), transparent 32%),
                radial-gradient(circle at bottom right, rgba(194, 153, 80, 0.12), transparent 34%),
                linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%);
            padding-top: var(--navbar-height);
            overflow-x: hidden;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='180' height='180' viewBox='0 0 180 180'%3E%3Cpath d='M90 18 L120 48 L90 78 L60 48 Z M90 102 L120 132 L90 162 L60 132 Z M18 90 L48 60 L78 90 L48 120 Z M102 90 L132 60 L162 90 L132 120 Z' fill='none' stroke='%23c29950' stroke-opacity='0.08' stroke-width='1.4'/%3E%3Ccircle cx='90' cy='90' r='28' fill='none' stroke='%232563eb' stroke-opacity='0.05' stroke-width='1.2'/%3E%3C/svg%3E");
            background-size: 180px;
            pointer-events: none;
            z-index: -1;
        }

        a {
            transition: 0.2s ease;
        }

        .app-navbar {
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.85);
            box-shadow: 0 10px 35px rgba(15, 23, 42, 0.06);
            min-height: var(--navbar-height);
            padding: 0.65rem 0;
        }

        .app-navbar .container {
            max-width: 1180px;
        }

        .app-brand {
            display: inline-flex;
            align-items: center;
            gap: 0.65rem;
            text-decoration: none;
            color: var(--app-dark) !important;
        }

        .brand-mark {
            width: 44px;
            height: 44px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--app-primary), var(--app-primary-dark));
            color: white;
            display: grid;
            place-items: center;
            box-shadow: 0 12px 24px rgba(37, 99, 235, 0.24);
            flex-shrink: 0;
        }

        .brand-mark span {
            font-family: 'Amiri', serif;
            font-size: 1.45rem;
            font-weight: 700;
            line-height: 1;
        }

        .brand-copy {
            display: flex;
            flex-direction: column;
            line-height: 1.05;
        }

        .brand-copy strong {
            font-size: 1.05rem;
            font-weight: 900;
            letter-spacing: -0.04em;
        }

        .brand-copy small {
            margin-top: 0.18rem;
            color: var(--app-muted);
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .navbar-nav {
            align-items: center;
            gap: 0.25rem;
        }

        .app-nav-link {
            color: #475569 !important;
            font-weight: 800;
            font-size: 0.92rem;
            border-radius: 999px;
            padding: 0.62rem 0.9rem !important;
            display: inline-flex;
            align-items: center;
            gap: 0.42rem;
            text-decoration: none;
        }

        .app-nav-link:hover {
            color: var(--app-primary-dark) !important;
            background: #eff6ff;
            transform: translateY(-1px);
        }

        .app-nav-link.active {
            color: var(--app-primary-dark) !important;
            background: #eff6ff;
            box-shadow: inset 0 0 0 1px #dbeafe;
        }

        .app-nav-link.nav-cta {
            background: linear-gradient(135deg, var(--app-primary), var(--app-primary-dark));
            color: white !important;
            box-shadow: 0 10px 24px rgba(37, 99, 235, 0.22);
        }

        .app-nav-link.nav-cta:hover {
            color: white !important;
            background: linear-gradient(135deg, var(--app-primary-dark), #1e40af);
        }

        .app-dropdown {
            border: 1px solid var(--app-line);
            box-shadow: 0 22px 55px rgba(15, 23, 42, 0.14);
            border-radius: 22px;
            padding: 0.55rem;
            margin-top: 0.75rem;
            min-width: 260px;
            background: white;
        }

        .app-dropdown .dropdown-item {
            border-radius: 16px;
            padding: 0.75rem 0.85rem;
            font-weight: 750;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            transition: 0.2s ease;
        }

        .app-dropdown .dropdown-item:hover,
        .app-dropdown .dropdown-item.active {
            background: #f8fafc;
            color: var(--app-primary-dark);
            transform: translateX(2px);
        }

        .dropdown-icon {
            width: 36px;
            height: 36px;
            border-radius: 13px;
            display: grid;
            place-items: center;
            background: #eff6ff;
            color: var(--app-primary-dark);
            flex-shrink: 0;
        }

        .dropdown-item.text-danger .dropdown-icon {
            background: #fef2f2;
            color: #b91c1c;
        }

        .dropdown-title {
            display: block;
            font-weight: 900;
            line-height: 1.15;
        }

        .dropdown-subtitle {
            display: block;
            color: var(--app-muted);
            font-size: 0.78rem;
            margin-top: 0.15rem;
            font-weight: 650;
        }

        .dropdown-divider {
            margin: 0.45rem 0;
            border-color: var(--app-line);
        }

        .user-toggle {
            background: #f8fafc;
            border: 1px solid var(--app-line);
            border-radius: 999px;
            padding: 0.35rem 0.55rem !important;
        }

        .user-toggle:hover,
        .user-toggle.show {
            background: #eff6ff;
            border-color: #dbeafe;
        }

        .user-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: inline-grid;
            place-items: center;
            background: linear-gradient(135deg, var(--app-primary), var(--app-primary-dark));
            color: white;
            font-size: 0.9rem;
            font-weight: 900;
            text-transform: uppercase;
            flex-shrink: 0;
        }

        .user-name {
            max-width: 130px;
            color: var(--app-dark);
            font-weight: 850;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .navbar-toggler {
            border: 1px solid #dbeafe;
            border-radius: 15px;
            min-height: 44px;
            min-width: 44px;
            padding: 0.5rem;
            background: #eff6ff;
        }

        .navbar-toggler i {
            font-size: 1.25rem;
            color: var(--app-primary-dark);
        }

        .navbar-toggler:focus {
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
        }

        .app-main {
            flex: 1 0 auto;
            width: 100%;
        }

        .global-alert-wrap {
            max-width: 1180px;
            margin: 1rem auto 0;
            padding: 0 0.75rem;
        }

        .global-alert {
            border: 0;
            border-radius: 18px;
            box-shadow: 0 14px 38px rgba(15, 23, 42, 0.08);
            font-weight: 650;
        }

        .app-footer {
            margin-top: 3rem;
            flex-shrink: 0;
            background: linear-gradient(135deg, #0f172a, #111827);
            color: #cbd5e1;
            position: relative;
            overflow: hidden;
        }

        .app-footer::before {
            content: "۞";
            position: absolute;
            right: 3rem;
            bottom: -1.5rem;
            font-size: 10rem;
            color: rgba(194, 153, 80, 0.06);
            font-family: serif;
            pointer-events: none;
        }

        .footer-inner {
            max-width: 1180px;
            margin: 0 auto;
            padding: 2rem 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1.25rem;
            position: relative;
            z-index: 1;
        }

        .footer-brand {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .footer-mark {
            width: 42px;
            height: 42px;
            border-radius: 15px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            display: grid;
            place-items: center;
            color: var(--app-gold);
            font-family: 'Amiri', serif;
            font-size: 1.35rem;
        }

        .footer-brand strong {
            color: white;
            display: block;
            font-weight: 900;
            letter-spacing: -0.03em;
        }

        .footer-brand span {
            color: #94a3b8;
            font-size: 0.88rem;
        }

        .footer-links {
            display: flex;
            gap: 0.65rem;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .footer-links a {
            color: #cbd5e1;
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 700;
            padding: 0.5rem 0.7rem;
            border-radius: 999px;
        }

        .footer-links a:hover {
            color: white;
            background: rgba(255, 255, 255, 0.08);
        }

        @media (max-width: 991px) {
            :root {
                --navbar-height: 72px;
            }

            .app-navbar {
                padding: 0.55rem 0;
            }

            .navbar-collapse {
                background: white;
                border: 1px solid var(--app-line);
                border-radius: 24px;
                margin-top: 0.85rem;
                padding: 0.75rem;
                box-shadow: 0 18px 45px rgba(15, 23, 42, 0.10);
                max-height: calc(100vh - 95px);
                overflow-y: auto;
            }

            .navbar-nav {
                align-items: stretch;
                gap: 0.35rem;
            }

            .app-nav-link {
                border-radius: 16px;
                justify-content: flex-start;
                padding: 0.85rem 1rem !important;
                width: 100%;
            }

            .app-nav-link.nav-cta {
                justify-content: center;
            }

            .app-dropdown {
                min-width: 100%;
                box-shadow: none;
                border-radius: 18px;
                margin-top: 0.35rem;
                background: #f8fafc;
            }

            .user-toggle {
                justify-content: flex-start;
                border-radius: 16px;
                padding: 0.65rem 0.75rem !important;
            }

            .user-name {
                display: inline-block !important;
                max-width: 220px;
            }

            .brand-copy small {
                display: none;
            }

            .footer-inner {
                flex-direction: column;
                align-items: flex-start;
            }

            .footer-links {
                justify-content: flex-start;
            }
        }

        @media (max-width: 576px) {
            .brand-mark {
                width: 40px;
                height: 40px;
                border-radius: 15px;
            }

            .brand-copy strong {
                font-size: 0.98rem;
            }

            .global-alert-wrap {
                padding: 0 1rem;
            }

            .footer-inner {
                padding: 1.5rem 1rem;
            }
        }
    </style>

    @stack('styles')
</head>

<body>
    <div id="app">
        <nav class="navbar navbar-expand-lg fixed-top app-navbar">
            <div class="container">
                <a class="app-brand" href="{{ Auth::check() ? route('home') : url('/') }}">
                    <span class="brand-mark">
                        <span>ق</span>
                    </span>

                    <span class="brand-copy">
                        <strong>{{ config('app.name', 'Tajweed Checker') }}</strong>
                        <small>AI Recitation</small>
                    </span>
                </a>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#appNavbar"
                    aria-controls="appNavbar" aria-expanded="false" aria-label="Toggle navigation">
                    <i class="fas fa-bars"></i>
                </button>

                <div class="collapse navbar-collapse" id="appNavbar">
                    <ul class="navbar-nav ms-auto">
                        @auth
                            <li class="nav-item">
                                <a class="nav-link app-nav-link {{ request()->routeIs('home') ? 'active' : '' }}"
                                   href="{{ route('home') }}">
                                    <i class="fas fa-house"></i>
                                    Home
                                </a>
                            </li>

                            @if(Route::has('recite.quran'))
                                <li class="nav-item">
                                    <a class="nav-link app-nav-link {{ request()->routeIs('recite.quran') ? 'active' : '' }}"
                                       href="{{ route('recite.quran') }}">
                                        <i class="fas fa-book-quran"></i>
                                        Quran
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('memorize.quran'))
                                <li class="nav-item">
                                    <a class="nav-link app-nav-link {{ request()->routeIs('memorize.quran') ? 'active' : '' }}"
                                       href="{{ route('memorize.quran') }}">
                                        <i class="fas fa-brain"></i>
                                        Memorize
                                    </a>
                                </li>
                            @endif

                            <li class="nav-item dropdown">
                                <a class="nav-link app-nav-link dropdown-toggle {{ request()->routeIs('tajweed.checker', 'tajweed.ikhfa-haqiqi', 'tajweed.izhar-halqi') ? 'active' : '' }}"
                                   href="#" id="practiceDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-microphone-lines"></i>
                                    Practice
                                </a>

                                <div class="dropdown-menu app-dropdown" aria-labelledby="practiceDropdown">
                                    @if(Route::has('tajweed.checker'))
                                        <a class="dropdown-item {{ request()->routeIs('tajweed.checker') ? 'active' : '' }}"
                                           href="{{ route('tajweed.checker') }}">
                                            <span class="dropdown-icon">
                                                <i class="fas fa-wand-magic-sparkles"></i>
                                            </span>
                                            <span>
                                                <span class="dropdown-title">Tajweed Checker</span>
                                                <span class="dropdown-subtitle">Check Ikhfa and Izhar together</span>
                                            </span>
                                        </a>
                                    @endif

                                    @if(Route::has('tajweed.ikhfa-haqiqi'))
                                        <a class="dropdown-item {{ request()->routeIs('tajweed.ikhfa-haqiqi') ? 'active' : '' }}"
                                           href="{{ route('tajweed.ikhfa-haqiqi') }}">
                                            <span class="dropdown-icon">
                                                <i class="fas fa-wave-square"></i>
                                            </span>
                                            <span>
                                                <span class="dropdown-title">Ikhfa Haqiqi</span>
                                                <span class="dropdown-subtitle">Hidden sound with ghunnah</span>
                                            </span>
                                        </a>
                                    @endif

                                    @if(Route::has('tajweed.izhar-halqi'))
                                        <a class="dropdown-item {{ request()->routeIs('tajweed.izhar-halqi') ? 'active' : '' }}"
                                           href="{{ route('tajweed.izhar-halqi') }}">
                                            <span class="dropdown-icon">
                                                <i class="fas fa-volume-high"></i>
                                            </span>
                                            <span>
                                                <span class="dropdown-title">Izhar Halqi</span>
                                                <span class="dropdown-subtitle">Clear throat pronunciation</span>
                                            </span>
                                        </a>
                                    @endif
                                </div>
                            </li>

                            @if(Route::has('tajweed.history'))
                                <li class="nav-item">
                                    <a class="nav-link app-nav-link {{ request()->routeIs('tajweed.history') ? 'active' : '' }}"
                                       href="{{ route('tajweed.history') }}">
                                        <i class="fas fa-clock-rotate-left"></i>
                                        History
                                    </a>
                                </li>
                            @endif

                            @if(Auth::user()->role === 'admin' && Route::has('admin.dashboard'))
                                <li class="nav-item">
                                    <a class="nav-link app-nav-link {{ request()->routeIs('admin.*') ? 'active' : '' }}"
                                       href="{{ route('admin.dashboard') }}">
                                        <i class="fas fa-shield-halved"></i>
                                        Admin
                                    </a>
                                </li>
                            @endif

                            <li class="nav-item dropdown">
                                <a class="nav-link app-nav-link user-toggle dropdown-toggle" href="#" id="userDropdown"
                                   role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <span class="user-avatar me-2">
                                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                                    </span>
                                    <span class="user-name d-none d-md-inline">
                                        {{ Auth::user()->name }}
                                    </span>
                                </a>

                                <div class="dropdown-menu dropdown-menu-end app-dropdown" aria-labelledby="userDropdown">
                                    @if(Route::has('profile.show'))
                                        <a class="dropdown-item {{ request()->routeIs('profile.show') ? 'active' : '' }}"
                                           href="{{ route('profile.show', Auth::user()->id) }}">
                                            <span class="dropdown-icon">
                                                <i class="fas fa-user"></i>
                                            </span>
                                            <span>
                                                <span class="dropdown-title">My Profile</span>
                                                <span class="dropdown-subtitle">View account details</span>
                                            </span>
                                        </a>
                                    @endif

                                    @if(Route::has('profile.edit'))
                                        <a class="dropdown-item {{ request()->routeIs('profile.edit') ? 'active' : '' }}"
                                           href="{{ route('profile.edit', Auth::user()->id) }}">
                                            <span class="dropdown-icon">
                                                <i class="fas fa-user-pen"></i>
                                            </span>
                                            <span>
                                                <span class="dropdown-title">Edit Profile</span>
                                                <span class="dropdown-subtitle">Update account info</span>
                                            </span>
                                        </a>
                                    @endif

                                    @if(Route::has('report.generate'))
                                        <a class="dropdown-item"
                                           href="{{ route('report.generate', Auth::user()->id) }}">
                                            <span class="dropdown-icon">
                                                <i class="fas fa-file-pdf"></i>
                                            </span>
                                            <span>
                                                <span class="dropdown-title">Progress Report</span>
                                                <span class="dropdown-subtitle">Open PDF report</span>
                                            </span>
                                        </a>
                                    @endif

                                    <div class="dropdown-divider"></div>

                                    <a class="dropdown-item text-danger" href="{{ route('logout') }}"
                                       onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                        <span class="dropdown-icon">
                                            <i class="fas fa-right-from-bracket"></i>
                                        </span>
                                        <span>
                                            <span class="dropdown-title">Logout</span>
                                            <span class="dropdown-subtitle">End current session</span>
                                        </span>
                                    </a>
                                </div>
                            </li>
                        @else
                            @if(Route::has('login'))
                                <li class="nav-item">
                                    <a class="nav-link app-nav-link {{ request()->routeIs('login') ? 'active' : '' }}"
                                       href="{{ route('login') }}">
                                        <i class="fas fa-right-to-bracket"></i>
                                        Login
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('register'))
                                <li class="nav-item">
                                    <a class="nav-link app-nav-link nav-cta {{ request()->routeIs('register') ? 'active' : '' }}"
                                       href="{{ route('register') }}">
                                        <i class="fas fa-user-plus"></i>
                                        Register
                                    </a>
                                </li>
                            @endif
                        @endauth
                    </ul>
                </div>
            </div>
        </nav>

        @if(session('success') || session('error') || session('warning') || session('status'))
            <div class="global-alert-wrap">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show global-alert" role="alert">
                        <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show global-alert" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if(session('warning'))
                    <div class="alert alert-warning alert-dismissible fade show global-alert" role="alert">
                        <i class="fas fa-triangle-exclamation me-2"></i>{{ session('warning') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if(session('status'))
                    <div class="alert alert-info alert-dismissible fade show global-alert" role="alert">
                        <i class="fas fa-circle-info me-2"></i>{{ session('status') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
            </div>
        @endif

        <main class="app-main">
            @yield('content')
        </main>

        <footer class="app-footer">
            <div class="footer-inner">
                <div class="footer-brand">
                    <div class="footer-mark">ق</div>
                    <div>
                        <strong>{{ config('app.name', 'Tajweed Checker') }}</strong>
                        <span>AI-based tajweed recitation practice system</span>
                    </div>
                </div>

                <div class="footer-links">
                    @auth
                        @if(Route::has('home'))
                            <a href="{{ route('home') }}">Dashboard</a>
                        @endif

                        @if(Route::has('recite.quran'))
                            <a href="{{ route('recite.quran') }}">Quran</a>
                        @endif

                        @if(Route::has('memorize.quran'))
                            <a href="{{ route('memorize.quran') }}">Memorize</a>
                        @endif

                        @if(Route::has('tajweed.history'))
                            <a href="{{ route('tajweed.history') }}">History</a>
                        @endif
                    @else
                        @if(Route::has('login'))
                            <a href="{{ route('login') }}">Login</a>
                        @endif

                        @if(Route::has('register'))
                            <a href="{{ route('register') }}">Register</a>
                        @endif
                    @endauth

                    <a href="#">© {{ date('Y') }}</a>
                </div>
            </div>
        </footer>

        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
            @csrf
        </form>
    </div>

    @stack('scripts')
</body>
</html>
