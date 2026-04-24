<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Poppins:300,400,500,600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons & FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <style>
        :root {
            --primary: #2563eb;
            --primary-light: #3b82f6;
            --secondary: #10b981;
            --light: #f8fafc;
            --dark: #1e293b;
            --gray: #64748b;
            --gray-light: #e2e8f0;
            --border-radius: 8px;
            --shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--light);
            color: var(--dark);
            padding-top: 76px;
        }

        .arabic-text {
            font-family: 'Amiri', serif;
            direction: rtl;
            font-size: 1.2em;
        }

        /* Navbar */
        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 1rem 0;
        }

        .navbar-brand {
            font-weight: 600;
            color: var(--primary);
            font-size: 1.25rem;
        }

        .navbar-brand i {
            color: var(--primary);
        }

        .nav-link {
            color: var(--dark);
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            border-radius: var(--border-radius);
            margin: 0 0.25rem;
        }

        .nav-link:hover,
        .nav-link.active {
            color: var(--primary);
            background: rgba(37, 99, 235, 0.1);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
            font-size: 0.9rem;
        }

        /* Main Content */
        .container {
            max-width: 1200px;
        }

        /* Header Section */
        .page-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .page-header h1 {
            color: var(--dark);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .page-header h1 i {
            color: var(--primary);
        }

        .page-header .lead {
            color: var(--gray);
            max-width: 600px;
            margin: 0 auto 1.5rem;
        }

        .alert-warning {
            background: #fff8e1;
            border: 1px solid #ffecb3;
            color: #5d4037;
            border-radius: var(--border-radius);
            max-width: 700px;
            margin: 0 auto;
        }

        /* Card Styles */
        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-header {
            background: var(--primary);
            color: white;
            border: none;
            padding: 1.25rem 1.5rem;
            font-weight: 600;
        }

        .card-header i {
            margin-right: 0.5rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Letters Grid */
        .letters-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }

        .letter-card {
            background: white;
            border: 2px solid var(--gray-light);
            border-radius: var(--border-radius);
            padding: 1.5rem 1rem;
            text-align: center;
            transition: all 0.2s ease;
        }

        .letter-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .letter-card .arabic {
            font-size: 2.5rem;
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .letter-card .name {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .letter-card .makhraj {
            font-size: 0.8rem;
            color: var(--gray);
            margin-bottom: 0.5rem;
        }

        .letter-card .example {
            font-size: 0.9rem;
            color: var(--primary);
            font-weight: 500;
        }

        /* Pronunciation Steps */
        .step {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .step-number {
            width: 36px;
            height: 36px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .step-content h5 {
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .step-content p {
            color: var(--gray);
            margin: 0;
        }

        /* Audio Examples */
        .audio-example {
            border: 1px solid var(--gray-light);
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s ease;
        }

        .audio-example:hover {
            border-color: var(--primary);
            background: rgba(37, 99, 235, 0.05);
        }

        .audio-example .content {
            flex: 1;
        }

        .audio-example .arabic-text {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .audio-example .translation {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .btn-play {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .btn-play:hover {
            background: var(--primary-light);
            transform: scale(1.1);
        }

        /* Table */
        .table {
            margin: 0;
        }

        .table thead th {
            background: #f1f5f9;
            border-bottom: 2px solid var(--gray-light);
            color: var(--dark);
            font-weight: 600;
            padding: 1rem;
        }

        .table tbody tr {
            border-bottom: 1px solid var(--gray-light);
        }

        .table tbody tr:hover {
            background: rgba(37, 99, 235, 0.05);
        }

        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
        }

        .badge {
            padding: 0.375rem 0.75rem;
            font-weight: 500;
        }

        /* Recording Section */
        .recording-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--shadow);
        }

        .btn-record {
            padding: 0.75rem 2rem;
            font-weight: 500;
        }

        .timer {
            font-family: monospace;
            font-size: 2rem;
            font-weight: 600;
            color: var(--primary);
            text-align: center;
            margin: 1rem 0;
        }

        /* File Upload */
        .file-upload {
            border: 2px dashed var(--gray-light);
            border-radius: var(--border-radius);
            padding: 3rem 2rem;
            text-align: center;
            background: white;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .file-upload:hover {
            border-color: var(--primary);
            background: rgba(37, 99, 235, 0.05);
        }

        .file-upload i {
            font-size: 3rem;
            color: var(--gray);
            margin-bottom: 1rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .letters-container {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            }

            .navbar-nav {
                padding: 1rem 0;
            }

            .nav-link {
                margin: 0.25rem 0;
            }
        }

        /* Footer */
        footer {
            background: var(--dark);
            color: white;
            padding: 2rem 0;
            margin-top: 4rem;
        }

        footer a {
            color: #94a3b8;
            text-decoration: none;
        }

        footer a:hover {
            color: white;
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="{{ url('home') }}">
                <i class="fas fa-quran me-2"></i>{{ config('app.name', 'Tajweed') }}
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
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
                            <a class="nav-link dropdown-toggle {{ request()->routeIs(['tajweed.ikhfa-haqiqi', 'tajweed.izhar-halqi']) ? 'active' : '' }}"
                                href="#" id="testDropdown" data-bs-toggle="dropdown">
                                <i class="fas fa-microphone me-1"></i> Test Tajweed
                            </a>
                            <div class="dropdown-menu">
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
                         @auth
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('recite.quran') ? 'active' : '' }}"
                                    href="{{ route('recite.quran') }}">
                                    <i class="fas fa-book-quran me-1"></i> Recite Quran
                                </a>
                            </li>
                        @endauth 
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" data-bs-toggle="dropdown">
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar me-2">
                                        {{ substr(Auth::user()->name, 0, 1) }}
                                    </div>
                                    <div class="d-none d-md-block">
                                        <small class="d-block">{{ Auth::user()->name }}</small>
                                    </div>
                                </div>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end">
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

    <!-- Logout Form -->
    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
        @csrf
    </form>

    <!-- Main Content -->
    <main class="container py-4">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-quran me-2"></i>{{ config('app.name') }}</h5>
                    <p class="text-muted mb-0">AI-Powered Tajweed Mastery</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-muted mb-0">&copy; 2026 {{ config('app.name') }}. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    @stack('scripts')
</body>

</html>