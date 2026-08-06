<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Panel - {{ config('app.name') }}</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary: #2563eb;
            --secondary: #6c757d;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #17a2b8;
            --dark: #1e293b;
            --light: #f8f9fa;
            --sidebar-width: 250px;
            --sidebar-collapsed: 70px;
            --topbar-height: 76px;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fb;
            color: #333;
            min-height: 100vh;
            overflow-x: hidden;
        }

        body.sidebar-open {
            overflow: hidden;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--dark) 0%, #2d3748 100%);
            color: white;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 3px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed);
        }

        .sidebar-header {
            padding: 1.5rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            min-height: var(--topbar-height);
        }

        .sidebar-header h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
        }

        .sidebar.collapsed .sidebar-header h3 {
            opacity: 0;
        }

        .sidebar-menu {
            padding: 1rem 0;
            height: calc(100vh - 70px);
            overflow-y: auto;
        }

        .nav-item {
            margin-bottom: 0.25rem;
        }

        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            min-height: 48px;
            text-decoration: none;
        }

        .nav-link:hover {
            color: white;
            background: rgba(255,255,255,0.1);
            border-left-color: var(--primary);
        }

        .nav-link.active {
            color: white;
            background: rgba(37, 99, 235, 0.2);
            border-left-color: var(--primary);
        }

        .nav-link i {
            width: 24px;
            text-align: center;
            margin-right: 0.75rem;
            font-size: 1.1rem;
        }

        .sidebar.collapsed .nav-link span {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        .sidebar-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.5);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
            z-index: 1040;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            transition: all 0.3s ease;
            min-height: 100vh;
        }

        .sidebar.collapsed ~ .main-content,
        .main-content.sidebar-collapsed {
            margin-left: var(--sidebar-collapsed);
        }

        .navbar-top {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 999;
            min-height: var(--topbar-height);
            gap: 1rem;
        }

        .mobile-sidebar-toggle {
            display: none;
            min-height: 44px;
            min-width: 44px;
        }

        .page-heading {
            min-width: 0;
        }

        .page-heading h4,
        .page-heading small {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .admin-account {
            min-width: 0;
        }

        .admin-identity {
            min-width: 0;
            max-width: 260px;
        }

        .admin-identity strong {
            display: inline-block;
            max-width: 160px;
            overflow: hidden;
            text-overflow: ellipsis;
            vertical-align: bottom;
            white-space: nowrap;
        }

        .content-wrapper {
            padding: 1.5rem;
        }

        /* Cards */
        .admin-card {
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 1.5rem;
        }

        .admin-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .card,
        .admin-card {
            overflow-wrap: anywhere;
        }

        .card-header {
            background: transparent;
            border-bottom: 1px solid #e2e8f0;
            padding: 1.25rem 1.5rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Stats Cards */
        .stats-card {
            text-align: center;
            padding: 1.5rem;
        }

        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
        }

        .stats-icon.users { background: rgba(37, 99, 235, 0.1); color: var(--primary); }
        .stats-icon.recitations { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .stats-icon.analytics { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .stats-icon.monitoring { background: rgba(23, 162, 184, 0.1); color: var(--info); }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .stats-label {
            color: #64748b;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Tables */
        .admin-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .admin-table table {
            margin: 0;
        }

        .admin-table thead th {
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            padding: 1rem 1.5rem;
            font-weight: 600;
            color: var(--dark);
        }

        .admin-table tbody td {
            padding: 1rem 1.5rem;
            vertical-align: middle;
            border-bottom: 1px solid #e2e8f0;
        }

        .admin-table tbody tr:hover {
            background: #f8fafc;
        }

        .table-responsive {
            -webkit-overflow-scrolling: touch;
        }

        .table th {
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
        }

        .table .btn-group,
        .table form.d-inline {
            white-space: nowrap;
        }

        /* Badges */
        .badge-admin {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary);
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-weight: 500;
        }

        .badge-user {
            background: rgba(108, 117, 125, 0.1);
            color: var(--secondary);
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-weight: 500;
        }

        .badge-status {
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.75rem;
        }

        .badge-status.completed {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .badge-status.pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .badge-status.processing {
            background: rgba(23, 162, 184, 0.1);
            color: var(--info);
        }

        /* Buttons */
        .btn-admin {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-admin-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }

        .btn,
        .form-control,
        .form-select {
            min-height: 40px;
        }

        .btn-sm {
            min-height: 32px;
        }

        /* Bootstrap 4 utility shims used by older admin views */
        .ml-1 { margin-left: 0.25rem !important; }
        .ml-2 { margin-left: 0.5rem !important; }
        .mr-1 { margin-right: 0.25rem !important; }
        .mr-2 { margin-right: 0.5rem !important; }
        .font-weight-bold { font-weight: 700 !important; }
        .btn-default {
            --bs-btn-color: #334155;
            --bs-btn-bg: #f8fafc;
            --bs-btn-border-color: #cbd5e1;
            --bs-btn-hover-color: #1e293b;
            --bs-btn-hover-bg: #e2e8f0;
            --bs-btn-hover-border-color: #cbd5e1;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                max-width: 320px;
                transform: translateX(-100%);
                width: min(86vw, 320px);
                z-index: 1100;
            }

            .sidebar.mobile-open {
                transform: translateX(0);
            }

            body.sidebar-open .sidebar-backdrop {
                opacity: 1;
                pointer-events: auto;
            }

            .sidebar.collapsed {
                width: min(86vw, 320px);
            }

            .main-content {
                margin-left: 0;
            }

            .sidebar.collapsed ~ .main-content,
            .main-content.sidebar-collapsed {
                margin-left: 0;
            }

            .sidebar-header h3,
            .sidebar.collapsed .sidebar-header h3,
            .nav-link span,
            .sidebar.collapsed .nav-link span {
                opacity: 1;
                width: auto;
                overflow: visible;
            }

            .sidebar-menu {
                height: calc(100vh - var(--topbar-height));
            }

            .navbar-top {
                align-items: flex-start !important;
                flex-wrap: wrap;
                padding: 0.75rem 1rem;
            }

            .mobile-sidebar-toggle {
                align-items: center;
                display: inline-flex;
                justify-content: center;
            }

            .navbar-top > .d-flex:first-child {
                flex: 1 1 calc(100% - 60px);
                min-width: 0;
            }

            .page-heading h4 {
                font-size: 1.1rem;
            }

            .page-heading small {
                font-size: 0.8rem;
            }

            .admin-account {
                justify-content: space-between;
                width: 100%;
            }

            .admin-identity {
                display: none;
            }

            .content-wrapper {
                padding: 1rem;
            }

            .admin-card {
                border-radius: 8px;
                margin-bottom: 1rem;
            }

            .admin-card:hover {
                transform: none;
            }

            .card-header,
            .card-body {
                padding: 1rem;
            }

            .card-header.d-flex {
                align-items: stretch !important;
                flex-direction: column;
                gap: 0.75rem;
            }

            .card-header form,
            .card-header .form-inline {
                display: grid;
                gap: 0.5rem;
                width: 100%;
            }

            .card-header .input-group {
                width: 100% !important;
            }

            .stats-card {
                padding: 1rem;
            }

            .stats-number {
                font-size: 2rem;
            }

            .table {
                min-width: 720px;
            }

            .pagination {
                flex-wrap: wrap;
                gap: 0.25rem;
                justify-content: center;
            }
        }

        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .content-wrapper {
                padding: 0.75rem;
            }

            .navbar-top {
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }

            .form-control,
            .form-select {
                width: 100%;
            }

            .btn-group .btn,
            .dropdown .btn,
            .input-group .btn {
                width: auto;
            }
        }

        /* Loading */
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 200px;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
    @stack('styles')
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header d-flex align-items-center justify-content-between">
            <h3>
                <i class="fas fa-crown me-2"></i>
                Admin Panel
            </h3>
            <button class="btn btn-link text-white p-0" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <div class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" 
                       href="{{ route('admin.dashboard') }}">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" 
                       href="{{ route('admin.users.index') }}">
                        <i class="fas fa-users"></i>
                        <span>User Management</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.recitations.*') ? 'active' : '' }}" 
                       href="{{ route('admin.recitations.index') }}">
                        <i class="fas fa-microphone"></i>
                        <span>Review Submissions</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.corrections.*') ? 'active' : '' }}" 
                       href="{{ route('admin.corrections.index') }}">
                        <i class="fas fa-clipboard-check"></i>
                        <span>Corrections</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.monitoring') ? 'active' : '' }}" 
                       href="{{ route('admin.monitoring') }}">
                        <i class="fas fa-server"></i>
                        <span>System Monitoring</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.analytics') ? 'active' : '' }}" 
                       href="{{ route('admin.analytics') }}">
                        <i class="fas fa-chart-line"></i>
                        <span>Analytics</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.logs') ? 'active' : '' }}" 
                       href="{{ route('admin.logs') }}">
                        <i class="fas fa-file-alt"></i>
                        <span>System Logs</span>
                    </a>
                </li>
                
                <li class="nav-item mt-4">
                    <a class="nav-link" href="{{ route('home') }}">
                        <i class="fas fa-home"></i>
                        <span>Back to Site</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link text-danger" href="{{ route('logout') }}"
                       onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Navbar -->
        <nav class="navbar-top d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <button class="btn btn-outline-secondary mobile-sidebar-toggle" id="mobileSidebarToggle" type="button" aria-controls="sidebar" aria-expanded="false" aria-label="Open admin menu">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="page-heading">
                    <h4 class="mb-0">@yield('page-title', 'Dashboard')</h4>
                    <small class="text-muted">@yield('page-subtitle', 'Welcome to Admin Panel')</small>
                </div>
            </div>
            
            <div class="admin-account d-flex align-items-center">
                <div class="admin-identity me-3">
                    <span class="text-muted me-2">Logged in as:</span>
                    <strong>{{ auth()->user()->name }}</strong>
                </div>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" 
                            data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i>
                        Account
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="{{ route('profile.show', auth()->id()) }}">
                                <i class="fas fa-user me-2"></i>Profile
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="{{ route('logout') }}"
                               onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Content -->
        <div class="content-wrapper">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @yield('content')
        </div>
    </div>

    <!-- Logout Form -->
    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
        @csrf
    </form>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
        const sidebarBackdrop = document.getElementById('sidebarBackdrop');

        function isMobileAdmin() {
            return window.innerWidth <= 768;
        }

        function openMobileSidebar() {
            sidebar.classList.add('mobile-open');
            document.body.classList.add('sidebar-open');
            mobileSidebarToggle.setAttribute('aria-expanded', 'true');
        }

        function closeMobileSidebar() {
            sidebar.classList.remove('mobile-open');
            document.body.classList.remove('sidebar-open');
            mobileSidebarToggle.setAttribute('aria-expanded', 'false');
        }

        // Sidebar toggle
        sidebarToggle.addEventListener('click', function() {
            if (isMobileAdmin()) {
                closeMobileSidebar();
                return;
            }

            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('sidebar-collapsed');
        });

        mobileSidebarToggle.addEventListener('click', function() {
            if (sidebar.classList.contains('mobile-open')) {
                closeMobileSidebar();
            } else {
                openMobileSidebar();
            }
        });

        sidebarBackdrop.addEventListener('click', closeMobileSidebar);

        sidebar.querySelectorAll('a.nav-link').forEach(function(link) {
            link.addEventListener('click', function() {
                if (isMobileAdmin()) {
                    closeMobileSidebar();
                }
            });
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeMobileSidebar();
            }
        });

        // Auto-collapse on mobile
        function handleResize() {
            if (isMobileAdmin()) {
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('sidebar-collapsed');
            } else {
                closeMobileSidebar();
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('sidebar-collapsed');
            }
        }

        // Initial check
        handleResize();
        
        // Listen for resize
        window.addEventListener('resize', handleResize);

        // Confirm delete
        function confirmDelete(message = 'Are you sure you want to delete this item?') {
            return confirm(message);
        }

        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>

    @stack('scripts')
</body>
</html>
