@extends('layouts.app')

@section('content')
@php
    $user = Auth::user();
@endphp

<div class="profile-page">
    <div class="container py-4 py-lg-5">

        <div class="profile-hero">
            <div class="profile-main">
                <div class="profile-avatar">
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                    <span class="status-dot"></span>
                </div>

                <div>
                    <span class="profile-kicker">My Profile</span>
                    <h1>{{ $user->name }}</h1>
                    <p>{{ $user->email }}</p>

                    <div class="profile-tags">
                        <span>
                            <i class="fas fa-user-graduate me-1"></i>
                            Tajweed Student
                        </span>

                        @if($user->role === 'admin')
                            <span class="admin-tag">
                                <i class="fas fa-shield-alt me-1"></i>
                                Admin
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="hero-actions">
                <a href="{{ route('profile.edit', $user->id) }}" class="btn btn-light-action">
                    <i class="fas fa-edit me-2"></i>Edit Profile
                </a>
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-lg-8">
                <div class="clean-card">
                    <div class="section-header">
                        <div>
                            <span class="section-label">
                                <i class="fas fa-id-card me-2"></i>Account Information
                            </span>
                            <h2>Personal Details</h2>
                        </div>
                    </div>

                    <div class="info-grid">
                        <div class="info-box">
                            <div class="info-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <label>Full Name</label>
                                <p>{{ $user->name }}</p>
                            </div>
                        </div>

                        <div class="info-box">
                            <div class="info-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div>
                                <label>Email Address</label>
                                <p>{{ $user->email }}</p>
                            </div>
                        </div>

                        <div class="info-box">
                            <div class="info-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div>
                                <label>Member Since</label>
                                <p>{{ $user->created_at ? $user->created_at->format('F j, Y') : '-' }}</p>
                            </div>
                        </div>

                        <div class="info-box">
                            <div class="info-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <label>Account Age</label>
                                <p>{{ $user->created_at ? $user->created_at->diffForHumans() : '-' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="clean-card mt-4">
                    <div class="section-header">
                        <div>
                            <span class="section-label">
                                <i class="fas fa-bolt me-2"></i>Quick Actions
                            </span>
                            <h2>Manage Account</h2>
                        </div>
                    </div>

                    <div class="action-grid">
                        <a href="{{ route('home') }}" class="action-card">
                            <div class="action-icon blue">
                                <i class="fas fa-home"></i>
                            </div>
                            <div>
                                <strong>Back to Home</strong>
                                <span>Return to main dashboard</span>
                            </div>
                        </a>

                        <a href="{{ route('report.generate', $user->id) }}" class="action-card">
                            <div class="action-icon gold">
                                <i class="fas fa-file-download"></i>
                            </div>
                            <div>
                                <strong>Progress Report</strong>
                                <span>Download your PDF report</span>
                            </div>
                        </a>

                        @if($user && $user->role === 'admin')
                            <a href="{{ route('admin.dashboard') }}" class="action-card">
                                <div class="action-icon purple">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <div>
                                    <strong>Admin Dashboard</strong>
                                    <span>Manage system data</span>
                                </div>
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="clean-card side-card">
                    <span class="section-label">
                        <i class="fas fa-chart-line me-2"></i>Learning Profile
                    </span>

                    <div class="learning-illustration">
                        <div class="quran-symbol">ق</div>
                    </div>

                    <h2>Continue Your Practice</h2>
                    <p>
                        Keep improving your tajweed pronunciation through regular recitation practice and feedback.
                    </p>

                    <div class="mini-stats">
                        <div>
                            <strong>Rule Focus</strong>
                            <span>Izhar & Ikhfa</span>
                        </div>
                        <div>
                            <strong>Report</strong>
                            <span>Available</span>
                        </div>
                        <div>
                            <strong>Status</strong>
                            <span class="text-success">Active</span>
                        </div>
                    </div>
                </div>

                <div class="danger-card mt-4">
                    <div class="danger-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>

                    <div>
                        <h3>Danger Zone</h3>
                        <p>Deleting your account will permanently remove your profile and related data.</p>
                    </div>

                    <form action="{{ route('profile.destroy', $user->id) }}" method="POST" onsubmit="return confirmDelete()">
                        @csrf
                        @method('DELETE')

                        <button type="submit" class="btn btn-danger-action w-100">
                            <i class="fas fa-trash me-2"></i>Delete Account
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@push('styles')
<style>
    :root {
        --primary: #2563eb;
        --primary-dark: #1d4ed8;
        --gold: #c29950;
        --dark: #0f172a;
        --muted: #64748b;
        --soft: #f8fafc;
        --line: #e2e8f0;
        --danger: #dc2626;
        --success: #16a34a;
        --shadow: 0 18px 50px rgba(15, 23, 42, 0.10);
    }

    body {
        background:
            radial-gradient(circle at top left, rgba(37, 99, 235, 0.10), transparent 32%),
            radial-gradient(circle at bottom right, rgba(194, 153, 80, 0.12), transparent 32%),
            linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%);
        min-height: 100vh;
    }

    .profile-page {
        color: var(--dark);
    }

    .profile-hero {
        background:
            linear-gradient(135deg, rgba(37, 99, 235, 0.97), rgba(29, 78, 216, 0.98));
        color: white;
        border-radius: 28px;
        padding: 2rem;
        box-shadow: var(--shadow);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
        position: relative;
        overflow: hidden;
    }

    .profile-hero::after {
        content: "۞";
        position: absolute;
        right: 2rem;
        top: -1rem;
        font-size: 8rem;
        opacity: 0.08;
        font-family: serif;
    }

    .profile-main {
        display: flex;
        align-items: center;
        gap: 1.3rem;
        position: relative;
        z-index: 1;
    }

    .profile-avatar {
        width: 92px;
        height: 92px;
        border-radius: 26px;
        background: rgba(255, 255, 255, 0.16);
        border: 1px solid rgba(255, 255, 255, 0.26);
        display: grid;
        place-items: center;
        font-size: 2.4rem;
        font-weight: 900;
        position: relative;
        flex-shrink: 0;
    }

    .status-dot {
        position: absolute;
        right: -4px;
        bottom: -4px;
        width: 22px;
        height: 22px;
        background: #22c55e;
        border: 4px solid #2563eb;
        border-radius: 50%;
    }

    .profile-kicker,
    .section-label {
        display: inline-flex;
        align-items: center;
        color: var(--gold);
        font-size: 0.78rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 0.45rem;
    }

    .profile-hero h1 {
        font-size: clamp(1.8rem, 4vw, 2.7rem);
        font-weight: 900;
        letter-spacing: -0.04em;
        margin: 0;
    }

    .profile-hero p {
        color: rgba(255, 255, 255, 0.82);
        margin: 0.35rem 0 0;
    }

    .profile-tags {
        display: flex;
        gap: 0.55rem;
        flex-wrap: wrap;
        margin-top: 0.85rem;
    }

    .profile-tags span {
        background: rgba(255, 255, 255, 0.15);
        border: 1px solid rgba(255, 255, 255, 0.22);
        color: white;
        border-radius: 999px;
        padding: 0.42rem 0.75rem;
        font-size: 0.82rem;
        font-weight: 800;
    }

    .profile-tags .admin-tag {
        background: rgba(194, 153, 80, 0.24);
    }

    .hero-actions {
        display: flex;
        gap: 0.8rem;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
    }

    .btn-light-action,
    .btn-primary-action {
        border-radius: 16px;
        padding: 0.78rem 1rem;
        font-weight: 900;
        border: none;
        text-decoration: none;
        white-space: nowrap;
    }

    .btn-light-action {
        background: rgba(255, 255, 255, 0.16);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.25);
    }

    .btn-light-action:hover {
        background: rgba(255, 255, 255, 0.24);
        color: white;
    }

    .btn-primary-action {
        background: white;
        color: var(--primary-dark);
    }

    .btn-primary-action:hover {
        color: var(--primary-dark);
        transform: translateY(-1px);
    }

    .clean-card {
        background: white;
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 24px;
        padding: 1.5rem;
        box-shadow: 0 14px 38px rgba(15, 23, 42, 0.07);
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }

    .clean-card h2,
    .side-card h2 {
        font-size: 1.35rem;
        font-weight: 900;
        letter-spacing: -0.03em;
        margin-bottom: 0.5rem;
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }

    .info-box {
        background: var(--soft);
        border: 1px solid var(--line);
        border-radius: 18px;
        padding: 1rem;
        display: flex;
        gap: 0.85rem;
        align-items: flex-start;
    }

    .info-icon {
        width: 44px;
        height: 44px;
        border-radius: 15px;
        display: grid;
        place-items: center;
        background: #dbeafe;
        color: var(--primary-dark);
        flex-shrink: 0;
    }

    .info-box label {
        display: block;
        color: var(--muted);
        font-size: 0.78rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-bottom: 0.25rem;
    }

    .info-box p {
        color: var(--dark);
        font-size: 1rem;
        font-weight: 800;
        margin: 0;
        word-break: break-word;
    }

    .action-grid {
        display: grid;
        gap: 0.85rem;
    }

    .action-card {
        background: var(--soft);
        border: 1px solid var(--line);
        border-radius: 18px;
        padding: 1rem;
        display: flex;
        align-items: center;
        gap: 0.85rem;
        text-decoration: none;
        color: var(--dark);
        transition: 0.2s ease;
    }

    .action-card:hover {
        border-color: var(--primary);
        background: #f1f6ff;
        color: var(--dark);
        transform: translateY(-1px);
    }

    .action-icon {
        width: 46px;
        height: 46px;
        border-radius: 16px;
        display: grid;
        place-items: center;
        color: white;
        flex-shrink: 0;
    }

    .action-icon.blue {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
    }

    .action-icon.gold {
        background: linear-gradient(135deg, #c29950, #a8792c);
    }

    .action-icon.purple {
        background: linear-gradient(135deg, #7c3aed, #6d28d9);
    }

    .action-card strong {
        display: block;
        font-weight: 900;
    }

    .action-card span {
        display: block;
        color: var(--muted);
        font-size: 0.9rem;
        margin-top: 0.15rem;
    }

    .side-card {
        text-align: center;
    }

    .learning-illustration {
        width: 120px;
        height: 120px;
        border-radius: 35px;
        margin: 0.5rem auto 1.25rem;
        background:
            radial-gradient(circle, rgba(194, 153, 80, 0.22), transparent 55%),
            linear-gradient(135deg, #eff6ff, #dbeafe);
        display: grid;
        place-items: center;
    }

    .quran-symbol {
        width: 76px;
        height: 76px;
        border-radius: 24px;
        background: white;
        display: grid;
        place-items: center;
        font-size: 2.2rem;
        font-weight: 900;
        color: var(--primary-dark);
        box-shadow: 0 12px 26px rgba(37, 99, 235, 0.14);
    }

    .side-card p {
        color: var(--muted);
        line-height: 1.7;
        margin-bottom: 1.25rem;
    }

    .mini-stats {
        display: grid;
        gap: 0.7rem;
        text-align: left;
        margin-top: 1rem;
    }

    .mini-stats div {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.8rem;
        border-radius: 15px;
        background: var(--soft);
        border: 1px solid var(--line);
        font-size: 0.92rem;
    }

    .mini-stats strong {
        color: var(--dark);
    }

    .mini-stats span {
        color: var(--muted);
        font-weight: 800;
    }

    .danger-card {
        background: #fff7f7;
        border: 1px solid #fecaca;
        border-radius: 24px;
        padding: 1.25rem;
        box-shadow: 0 12px 30px rgba(220, 38, 38, 0.06);
    }

    .danger-icon {
        width: 48px;
        height: 48px;
        border-radius: 16px;
        display: grid;
        place-items: center;
        background: #fee2e2;
        color: #991b1b;
        margin-bottom: 0.9rem;
    }

    .danger-card h3 {
        font-size: 1.1rem;
        font-weight: 900;
        color: #991b1b;
        margin-bottom: 0.4rem;
    }

    .danger-card p {
        color: #7f1d1d;
        font-size: 0.92rem;
        line-height: 1.6;
    }

    .btn-danger-action {
        background: #dc2626;
        color: white;
        border: none;
        border-radius: 16px;
        padding: 0.82rem 1rem;
        font-weight: 900;
    }

    .btn-danger-action:hover {
        background: #b91c1c;
        color: white;
    }

    @media (max-width: 992px) {
        .profile-hero {
            align-items: flex-start;
            flex-direction: column;
        }

        .hero-actions {
            width: 100%;
        }

        .hero-actions .btn {
            flex: 1;
            text-align: center;
        }
    }

    @media (max-width: 768px) {
        .profile-main {
            align-items: flex-start;
            flex-direction: column;
        }

        .info-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 576px) {
        .profile-hero,
        .clean-card,
        .danger-card {
            border-radius: 20px;
            padding: 1.15rem;
        }

        .profile-avatar {
            width: 76px;
            height: 76px;
            border-radius: 22px;
            font-size: 2rem;
        }

        .hero-actions {
            flex-direction: column;
        }

        .hero-actions .btn {
            width: 100%;
        }

        .info-box {
            flex-direction: column;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    function confirmDelete() {
        return confirm(
            '⚠️ Warning: Are you sure you want to delete your account?\n\n' +
            'This action will:\n' +
            '• Permanently delete your profile\n' +
            '• Remove all your recordings\n' +
            '• Delete your learning history\n\n' +
            'This action cannot be undone!'
        );
    }
</script>
@endpush