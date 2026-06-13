<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Tajweed') }}</title>

    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Poppins:300,400,500,600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    @stack('styles')

    <style>
        /* Global Styles with Tajweed Essence */
        :root {
            --tajweed-primary: #0d6efd;
            --tajweed-green: #198754;
            --tajweed-gold: #c1963c;
            --tajweed-dark: #1f2a3e;
            --tajweed-light-bg: #f8f9fa;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9edf2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
            /* FIX: Added proper padding-top to account for fixed navbar */
            padding-top: 80px;
        }

        /* Subtle Islamic pattern overlay */
        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 800" opacity="0.02"><path fill="none" stroke="%238B7355" stroke-width="1.2" d="M400 50 L500 150 L400 250 L300 150 Z M400 250 L550 400 L400 550 L250 400 Z M400 550 L500 650 L400 750 L300 650 Z M250 400 L150 500 L250 600 L350 500 Z M550 400 L650 500 L550 600 L450 500 Z"/><circle cx="400" cy="400" r="100" stroke="%238B7355" fill="none" stroke-width="0.8"/><circle cx="400" cy="400" r="180" stroke="%238B7355" fill="none" stroke-width="0.6"/></svg>');
            background-repeat: repeat;
            background-size: 160px;
            pointer-events: none;
            z-index: -1;
        }

        /* Navbar Styles */
        .navbar {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            padding: 0.75rem 0;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1030;
            border-bottom: 1px solid rgba(13, 110, 253, 0.1);
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--tajweed-primary) !important;
            font-size: 1.5rem;
            letter-spacing: -0.3px;
        }

        .navbar-brand i {
            color: var(--tajweed-gold);
        }

        .nav-link {
            font-weight: 500;
            transition: all 0.2s ease;
            position: relative;
            padding: 0.5rem 1rem !important;
        }

        .nav-link:hover {
            color: var(--tajweed-primary) !important;
            transform: translateY(-1px);
        }

        .nav-link.active {
            color: var(--tajweed-primary) !important;
            background: rgba(13, 110, 253, 0.08);
            border-radius: 40px;
        }

        .dropdown-menu {
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border-radius: 16px;
            padding: 0.5rem;
            margin-top: 0.5rem;
        }

        .dropdown-item {
            border-radius: 12px;
            transition: all 0.2s;
            padding: 0.5rem 1rem;
        }

        .dropdown-item:hover {
            background: rgba(13, 110, 253, 0.08);
            transform: translateX(3px);
        }

        .dropdown-item.active {
            background: var(--tajweed-primary);
            color: white;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--tajweed-primary), #0b5ed7);
            color: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .navbar-toggler {
            border: none;
            padding: 0.5rem;
        }

        .navbar-toggler i {
            font-size: 1.5rem;
            color: var(--tajweed-primary);
        }

        /* Main Content */
        main {
            flex: 1 0 auto;
        }

        /* Footer */
        footer {
            background: linear-gradient(135deg, #1a2634 0%, #1f2a3a 100%);
            color: #e0e0e0;
            padding: 2rem 0 1.5rem;
            margin-top: auto;
            flex-shrink: 0;
            position: relative;
            border-top: 1px solid rgba(193, 150, 60, 0.2);
        }

        footer::before {
            content: "۞";
            position: absolute;
            bottom: 10px;
            right: 20px;
            font-size: 4rem;
            opacity: 0.03;
            font-family: serif;
            pointer-events: none;
        }

        footer h5 {
            color: var(--tajweed-gold);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        footer h5 i {
            margin-right: 0.5rem;
        }

        footer .text-muted {
            color: #9aaebf !important;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            body {
                padding-top: 70px;
            }
            
            footer {
                text-align: center;
            }
            
            footer .col-md-6:last-child {
                margin-top: 0.75rem;
                text-align: center !important;
            }
        }

        /* Dropdown animation */
        .dropdown-menu {
            display: block;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s ease;
        }

        .dropdown-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        /* Scroll to top button */
        .scroll-top {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--tajweed-primary);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: all 0.3s;
            z-index: 99;
        }

        .scroll-top.visible {
            opacity: 1;
        }
    </style>
</head>
<body>
    @php
        $tajweedRoutes = ['tajweed.ikhfa-haqiqi', 'tajweed.izhar-halqi'];
    @endphp

    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="{{ route('home') }}">
                <i class="fas fa-quran me-2"></i>{{ config('app.name', 'Tajweed') }}
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <i class="fas fa-bars"></i>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    @auth
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">
                                <i class="fas fa-home me-1"></i> Home
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('tajweed.history') ? 'active' : '' }}"
                                href="{{ route('tajweed.history') }}">
                                <i class="fas fa-history me-1"></i> My Recitations
                            </a>
                        </li>

                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle {{ request()->routeIs($tajweedRoutes) ? 'active' : '' }}"
                                href="#" id="testDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-microphone me-1"></i> Test Tajweed
                            </a>

                            <div class="dropdown-menu" aria-labelledby="testDropdown">
                                <a class="dropdown-item {{ request()->routeIs('tajweed.ikhfa-haqiqi') ? 'active' : '' }}"
                                    href="{{ route('tajweed.ikhfa-haqiqi') }}">
                                    <i class="fas fa-volume-down me-2"></i> Ikhfa Haqiqi
                                </a>

                                <a class="dropdown-item {{ request()->routeIs('tajweed.izhar-halqi') ? 'active' : '' }}"
                                    href="{{ route('tajweed.izhar-halqi') }}">
                                    <i class="fas fa-volume-up me-2"></i> Izhar Halqi
                                </a>
                            </div>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('recite.quran') ? 'active' : '' }}"
                                href="{{ route('recite.quran') }}">
                                <i class="fas fa-book-quran me-1"></i> Recite Quran
                            </a>
                        </li>

                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                                data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="d-flex align-items-center">
                                    <span class="user-avatar me-2">{{ substr(Auth::user()->name, 0, 1) }}</span>
                                    <small class="d-none d-md-block">{{ Auth::user()->name }}</small>
                                </span>
                            </a>

                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="{{ route('profile.show', Auth::user()->id) }}">
                                    <i class="fas fa-user me-2"></i> Profile
                                </a>

                                <div class="dropdown-divider"></div>

                                <a class="dropdown-item" href="{{ route('logout') }}"
                                    onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                                </a>
                            </div>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('login') }}">
                                <i class="fas fa-sign-in-alt me-1"></i> Login
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('register') }}">
                                <i class="fas fa-user-plus me-1"></i> Register
                            </a>
                        </li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>

    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
        @csrf
    </form>

    <main class="container py-4">
        @yield('content')
    </main>

    <footer>
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5><i class="fas fa-quran me-2"></i>{{ config('app.name', 'Tajweed') }}</h5>
                    <p class="text-muted mb-0">AI-Powered Tajweed Mastery | Perfect Your Recitation</p>
                </div>

                <div class="col-md-6 text-md-end">
                    <p class="text-muted mb-0">
                        <i class="fas fa-heart me-1" style="color: #c1963c;"></i>
                        &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scroll to top button -->
    <div class="scroll-top" id="scrollTop">
        <i class="fas fa-arrow-up"></i>
    </div>

    @stack('scripts')

    <script>
        // Scroll to top functionality
        document.addEventListener('DOMContentLoaded', function() {
            const scrollBtn = document.getElementById('scrollTop');
            
            window.addEventListener('scroll', function() {
                if (window.scrollY > 300) {
                    scrollBtn.classList.add('visible');
                } else {
                    scrollBtn.classList.remove('visible');
                }
            });
            
            scrollBtn.addEventListener('click', function() {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });
    </script>
</body>
</html>