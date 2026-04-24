@extends('layouts.app')

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="profile-card">
                    <!-- Profile Header -->
                    <div class="profile-header text-center mb-5">
                        <div class="avatar-wrapper">
                            <div class="avatar">
                                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                            </div>
                            <span class="status-indicator online"></span>
                        </div>
                        <h1 class="profile-name mt-3">{{ Auth::user()->name }}</h1>
                        <p class="profile-role text-muted">Tajweed Student</p>
                    </div>

                    <!-- Profile Info -->
                    <div class="profile-info">
                        <div class="info-section">
                            <h3 class="section-title">Account Information</h3>

                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="info-content">
                                    <label>Full Name</label>
                                    <p>{{ Auth::user()->name }}</p>
                                </div>
                            </div>

                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="info-content">
                                    <label>Email Address</label>
                                    <p>{{ Auth::user()->email }}</p>
                                </div>
                            </div>

                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div class="info-content">
                                    <label>Member Since</label>
                                    <p>{{ Auth::user()->created_at->format('F j, Y') }}</p>
                                </div>
                            </div>

                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="info-content">
                                    <label>Account Age</label>
                                    <p>{{ Auth::user()->created_at->diffForHumans() }}</p>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fa fa-file" aria-hidden="true"></i>
                                </div>
                                <div class="info-content">
                                    <label>Download Report</label>
                                    <p>
                                        <a href="{{ route('report.generate', Auth::user()->id) }}"
                                            class="btn btn-sm btn-outline-primary">
                                            <i class="fa fa-download me-1"></i> Download PDF Report
                                        </a>
                                    </p>
                                </div>
                            </div>

                            @if(Auth::user() && Auth::user()->role === 'admin')
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-home me-2" aria-hidden="true"></i>
                                    </div>
                                    <div class="info-content">
                                        <label>Enter Admin Page</label>
                                        <div class="col-md-4">
                                            <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-outline-primary">
                                                Go to Admin page
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <!-- Action Buttons -->
                            <div class="action-buttons mt-5">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <a href="{{ route('home') }}" class="btn btn-outline-primary w-100">
                                            <i class="fas fa-home me-2"></i>Home
                                        </a>
                                    </div>
                                    <div class="col-md-4">
                                        <a href="{{ route('profile.edit', Auth::user()->id) }}"
                                            class="btn btn-primary w-100">
                                            <i class="fas fa-edit me-2"></i>Edit Profile
                                        </a>
                                    </div>
                                    <div class="col-md-4">
                                        <form action="{{ route('profile.destroy', Auth::user()->id) }}" method="POST"
                                            onsubmit="return confirmDelete()" class="w-100">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger w-100">
                                                <i class="fas fa-trash me-2"></i>Delete Account
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            :root {
                --primary-color: #2563eb;
                --primary-light: #3b82f6;
                --secondary-color: #10b981;
                --danger-color: #ef4444;
                --warning-color: #f59e0b;
                --light-color: #f8fafc;
                --dark-color: #1e293b;
                --gray-color: #64748b;
                --border-color: #e2e8f0;
                --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
                --radius: 12px;
            }

            body {
                background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                min-height: 100vh;
            }

            .profile-card {
                background: white;
                border-radius: var(--radius);
                box-shadow: var(--shadow-lg);
                padding: 2.5rem;
                position: relative;
                overflow: hidden;
            }

            .profile-card::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            }

            /* Profile Header */
            .profile-header {
                padding-bottom: 1.5rem;
                border-bottom: 1px solid var(--border-color);
            }

            .avatar-wrapper {
                position: relative;
                display: inline-block;
            }

            .avatar {
                width: 120px;
                height: 120px;
                background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
                color: white;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 3rem;
                font-weight: 600;
                margin: 0 auto;
                box-shadow: var(--shadow);
            }

            .status-indicator {
                position: absolute;
                bottom: 10px;
                right: 10px;
                width: 20px;
                height: 20px;
                border: 3px solid white;
                border-radius: 50%;
            }

            .status-indicator.online {
                background: var(--secondary-color);
            }

            .status-indicator.offline {
                background: var(--gray-color);
            }

            .profile-name {
                font-size: 2rem;
                font-weight: 700;
                color: var(--dark-color);
                margin: 1rem 0 0.5rem;
            }

            .profile-role {
                font-size: 1rem;
                color: var(--gray-color);
            }

            /* Profile Info */
            .info-section {
                margin-bottom: 2rem;
            }

            .section-title {
                font-size: 1.25rem;
                font-weight: 600;
                color: var(--dark-color);
                margin-bottom: 1.5rem;
                padding-bottom: 0.75rem;
                border-bottom: 2px solid var(--border-color);
                display: flex;
                align-items: center;
            }

            .section-title::before {
                content: '';
                width: 4px;
                height: 20px;
                background: var(--primary-color);
                margin-right: 0.75rem;
                border-radius: 2px;
            }

            .info-item {
                display: flex;
                align-items: flex-start;
                padding: 1rem;
                border-radius: var(--radius);
                background: var(--light-color);
                margin-bottom: 1rem;
                transition: all 0.3s ease;
            }

            .info-item:hover {
                background: white;
                box-shadow: var(--shadow);
                transform: translateY(-2px);
            }

            .info-icon {
                width: 40px;
                height: 40px;
                background: white;
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-right: 1rem;
                color: var(--primary-color);
                font-size: 1.1rem;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }

            .info-content {
                flex: 1;
            }

            .info-content label {
                display: block;
                font-size: 0.875rem;
                color: var(--gray-color);
                font-weight: 500;
                margin-bottom: 0.25rem;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .info-content p {
                margin: 0;
                font-size: 1.1rem;
                font-weight: 500;
                color: var(--dark-color);
            }

            /* Audio Player */
            .audio-player {
                background: white;
                border-radius: var(--radius);
                border: 1px solid var(--border-color);
                overflow: hidden;
            }

            .audio-header {
                background: linear-gradient(90deg, var(--primary-color), var(--primary-light));
                color: white;
                padding: 1rem 1.5rem;
                display: flex;
                align-items: center;
                font-weight: 500;
            }

            .audio-header i {
                margin-right: 0.75rem;
                font-size: 1.2rem;
            }

            .audio-controls {
                padding: 1.5rem;
            }

            .audio-controls audio {
                height: 40px;
                border-radius: 8px;
            }

            .audio-controls audio::-webkit-media-controls-panel {
                background: var(--light-color);
            }

            /* Stats Grid */
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 1rem;
            }

            .stat-card {
                background: white;
                border: 1px solid var(--border-color);
                border-radius: var(--radius);
                padding: 1.5rem;
                text-align: center;
                transition: all 0.3s ease;
            }

            .stat-card:hover {
                border-color: var(--primary-color);
                box-shadow: var(--shadow);
                transform: translateY(-2px);
            }

            .stat-icon {
                width: 60px;
                height: 60px;
                background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
                color: white;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 1rem;
                font-size: 1.5rem;
            }

            .stat-content h4 {
                font-size: 0.9rem;
                color: var(--gray-color);
                font-weight: 500;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 0.5rem;
            }

            .stat-number {
                font-size: 1.75rem;
                font-weight: 700;
                color: var(--dark-color);
                margin: 0;
            }

            /* Action Buttons */
            .action-buttons .btn {
                padding: 0.875rem 1.5rem;
                font-weight: 500;
                border-radius: var(--radius);
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .action-buttons .btn:hover {
                transform: translateY(-2px);
                box-shadow: var(--shadow);
            }

            .btn-outline-primary {
                border: 2px solid var(--primary-color);
                color: var(--primary-color);
            }

            .btn-outline-primary:hover {
                background: var(--primary-color);
                color: white;
            }

            .btn-primary {
                background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
                border: none;
                color: white;
            }

            .btn-primary:hover {
                background: linear-gradient(135deg, var(--primary-light), var(--primary-color));
                color: white;
            }

            .btn-outline-danger {
                border: 2px solid var(--danger-color);
                color: var(--danger-color);
            }

            .btn-outline-danger:hover {
                background: var(--danger-color);
                color: white;
            }

            /* Responsive Design */
            @media (max-width: 768px) {
                .profile-card {
                    padding: 1.5rem;
                }

                .avatar {
                    width: 100px;
                    height: 100px;
                    font-size: 2.5rem;
                }

                .profile-name {
                    font-size: 1.75rem;
                }

                .stats-grid {
                    grid-template-columns: 1fr;
                }

                .action-buttons .col-md-4 {
                    margin-bottom: 0.5rem;
                }
            }

            @media (max-width: 576px) {
                .info-item {
                    flex-direction: column;
                    text-align: center;
                }

                .info-icon {
                    margin-right: 0;
                    margin-bottom: 1rem;
                }

                .avatar {
                    width: 80px;
                    height: 80px;
                    font-size: 2rem;
                }
            }

            /* Confirmation Dialog Styling */
            .confirm-dialog {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1050;
                opacity: 0;
                visibility: hidden;
                transition: all 0.3s ease;
            }

            .confirm-dialog.show {
                opacity: 1;
                visibility: visible;
            }

            .confirm-content {
                background: white;
                border-radius: var(--radius);
                padding: 2rem;
                max-width: 400px;
                width: 90%;
                box-shadow: var(--shadow-lg);
            }

            .confirm-header {
                margin-bottom: 1.5rem;
                text-align: center;
            }

            .confirm-header i {
                font-size: 3rem;
                color: var(--danger-color);
                margin-bottom: 1rem;
            }

            .confirm-body {
                margin-bottom: 1.5rem;
                text-align: center;
            }

            .confirm-footer {
                display: flex;
                gap: 1rem;
                justify-content: center;
            }

            .confirm-footer .btn {
                min-width: 100px;
            }
        </style>

        <script>
            function confirmDelete() {
                const confirmed = confirm('⚠️ Warning: Are you sure you want to delete your account?\n\nThis action will:\n• Permanently delete your profile\n• Remove all your recordings\n• Delete your learning history\n\nThis action cannot be undone!');
                return confirmed;
            }
        </script>
@endsection