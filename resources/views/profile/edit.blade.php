@extends('layouts.app')

@section('content')
@php
    $user = Auth::user();
@endphp

<style>
    body {
        background:
            radial-gradient(circle at top left, rgba(37, 99, 235, 0.10), transparent 32%),
            radial-gradient(circle at bottom right, rgba(194, 153, 80, 0.12), transparent 32%),
            linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%);
        min-height: 100vh;
    }

    .edit-profile-page {
        padding: 2rem 0 4rem;
        color: #0f172a;
    }

    .edit-shell {
        max-width: 1050px;
        margin: 0 auto;
    }

    .edit-hero {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: white;
        border-radius: 28px;
        padding: 2rem;
        box-shadow: 0 22px 60px rgba(37, 99, 235, 0.20);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1.5rem;
        position: relative;
        overflow: hidden;
        margin-bottom: 1.5rem;
    }

    .edit-hero::after {
        content: "۞";
        position: absolute;
        right: 2rem;
        top: -1.2rem;
        font-size: 8rem;
        opacity: 0.08;
        font-family: serif;
    }

    .hero-content,
    .hero-action {
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

    .edit-hero h1 {
        margin: 0;
        font-size: clamp(2rem, 4vw, 2.8rem);
        font-weight: 900;
        letter-spacing: -0.04em;
    }

    .edit-hero p {
        margin: 0.65rem 0 0;
        color: rgba(255, 255, 255, 0.82);
        line-height: 1.7;
    }

    .btn-hero {
        background: rgba(255, 255, 255, 0.16);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.25);
        border-radius: 16px;
        padding: 0.78rem 1rem;
        font-weight: 900;
        text-decoration: none;
        white-space: nowrap;
    }

    .btn-hero:hover {
        background: rgba(255, 255, 255, 0.24);
        color: white;
    }

    .edit-layout {
        display: grid;
        grid-template-columns: 0.85fr 1.4fr;
        gap: 1.5rem;
        align-items: start;
    }

    .clean-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 24px;
        padding: 1.5rem;
        box-shadow: 0 14px 38px rgba(15, 23, 42, 0.07);
    }

    .profile-preview {
        text-align: center;
        position: sticky;
        top: 90px;
    }

    .avatar-preview {
        width: 110px;
        height: 110px;
        border-radius: 32px;
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: white;
        display: grid;
        place-items: center;
        font-size: 2.8rem;
        font-weight: 900;
        margin: 0 auto 1rem;
        box-shadow: 0 18px 35px rgba(37, 99, 235, 0.24);
    }

    .profile-preview h2 {
        font-size: 1.35rem;
        font-weight: 900;
        letter-spacing: -0.03em;
        margin-bottom: 0.35rem;
        word-break: break-word;
    }

    .profile-preview p {
        color: #64748b;
        margin-bottom: 1.25rem;
        word-break: break-word;
    }

    .mini-info {
        display: grid;
        gap: 0.7rem;
        text-align: left;
        margin-top: 1.25rem;
    }

    .mini-info div {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 0.85rem;
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        font-size: 0.9rem;
    }

    .mini-info strong {
        color: #0f172a;
    }

    .mini-info span {
        color: #64748b;
        font-weight: 800;
    }

    .form-header {
        margin-bottom: 1.5rem;
    }

    .form-header h2 {
        font-size: 1.5rem;
        font-weight: 900;
        letter-spacing: -0.04em;
        margin: 0;
    }

    .form-header p {
        color: #64748b;
        margin: 0.45rem 0 0;
        line-height: 1.6;
    }

    .alert-clean {
        border: none;
        border-radius: 18px;
        padding: 1rem 1.1rem;
        background: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }

    .alert-clean strong {
        display: block;
        margin-bottom: 0.5rem;
    }

    .alert-clean ul {
        margin-bottom: 0;
        padding-left: 1.2rem;
    }

    .form-grid {
        display: grid;
        gap: 1rem;
    }

    .form-group-custom {
        display: grid;
        gap: 0.45rem;
    }

    .form-group-custom label {
        color: #334155;
        font-weight: 900;
        font-size: 0.9rem;
    }

    .input-wrapper {
        position: relative;
    }

    .input-wrapper i {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: #64748b;
        pointer-events: none;
    }

    .form-control-custom {
        width: 100%;
        min-height: 54px;
        border-radius: 16px;
        border: 1px solid #dbe3ef;
        background: #f8fafc;
        color: #0f172a;
        padding: 0.8rem 1rem 0.8rem 2.8rem;
        font-weight: 700;
        outline: none;
        transition: 0.2s ease;
    }

    .form-control-custom:focus {
        background: white;
        border-color: #2563eb;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
    }

    .helper-text {
        color: #64748b;
        font-size: 0.84rem;
        line-height: 1.5;
    }

    .password-note {
        background: #eff6ff;
        border: 1px solid #dbeafe;
        color: #1e40af;
        border-radius: 18px;
        padding: 0.9rem 1rem;
        font-size: 0.9rem;
        font-weight: 700;
        line-height: 1.6;
        margin: 0.25rem 0 0.5rem;
    }

    .form-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-top: 1.5rem;
        padding-top: 1.25rem;
        border-top: 1px solid #e2e8f0;
    }

    .btn-main,
    .btn-soft {
        border-radius: 16px;
        padding: 0.82rem 1rem;
        font-weight: 900;
        text-decoration: none;
        border: none;
        min-width: 145px;
        text-align: center;
    }

    .btn-main {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: white;
        box-shadow: 0 14px 28px rgba(37, 99, 235, 0.22);
    }

    .btn-main:hover {
        color: white;
        transform: translateY(-1px);
    }

    .btn-soft {
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #dbeafe;
    }

    .btn-soft:hover {
        background: #dbeafe;
        color: #1d4ed8;
    }

    @media (max-width: 992px) {
        .edit-hero {
            align-items: flex-start;
            flex-direction: column;
        }

        .edit-layout {
            grid-template-columns: 1fr;
        }

        .profile-preview {
            position: static;
        }
    }

    @media (max-width: 576px) {
        .edit-profile-page {
            padding-top: 1rem;
        }

        .edit-hero,
        .clean-card {
            border-radius: 20px;
            padding: 1.15rem;
        }

        .form-actions {
            flex-direction: column-reverse;
            align-items: stretch;
        }

        .btn-main,
        .btn-soft,
        .btn-hero {
            width: 100%;
        }

        .mini-info div {
            flex-direction: column;
            gap: 0.25rem;
        }
    }
</style>

<div class="edit-profile-page">
    <div class="container">
        <div class="edit-shell">

            <div class="edit-hero">
                <div class="hero-content">
                    <span class="hero-kicker">
                        <i class="fas fa-user-edit me-2"></i>Profile Settings
                    </span>
                    <h1>Update Profile</h1>
                    <p>Manage your account details and update your login information securely.</p>
                </div>

                <div class="hero-action">
                    <a class="btn btn-hero" href="{{ route('profile.show', $user->id) }}">
                        <i class="fas fa-arrow-left me-2"></i>Back to Profile
                    </a>
                </div>
            </div>

            <div class="edit-layout">
                <div class="clean-card profile-preview">
                    <div class="avatar-preview">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </div>

                    <h2>{{ $user->name }}</h2>
                    <p>{{ $user->email }}</p>

                    <div class="mini-info">
                        <div>
                            <strong>Account</strong>
                            <span>Active</span>
                        </div>
                        <div>
                            <strong>Role</strong>
                            <span>{{ ucfirst($user->role ?? 'User') }}</span>
                        </div>
                        <div>
                            <strong>Joined</strong>
                            <span>{{ $user->created_at ? $user->created_at->format('M Y') : '-' }}</span>
                        </div>
                    </div>
                </div>

                <div class="clean-card">
                    <div class="form-header">
                        <span class="section-label">
                            <i class="fas fa-id-card me-2"></i>Account Details
                        </span>
                        <h2>Edit your information</h2>
                        <p>Update your name or email. Leave password fields blank if you do not want to change your password.</p>
                    </div>

                    @if ($errors->any())
                        <div class="alert-clean mb-4">
                            <strong>
                                <i class="fas fa-exclamation-circle me-2"></i>
                                There were some problems with your input.
                            </strong>

                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('profile.update', $user->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <input type="hidden" name="id" value="{{ $user->id }}">

                        <div class="form-grid">
                            <div class="form-group-custom">
                                <label for="name">Full Name</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-user"></i>
                                    <input
                                        id="name"
                                        type="text"
                                        name="name"
                                        value="{{ old('name', $user->name) }}"
                                        class="form-control-custom"
                                        placeholder="Enter your full name"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="form-group-custom">
                                <label for="email">Email Address</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-envelope"></i>
                                    <input
                                        id="email"
                                        type="email"
                                        name="email"
                                        value="{{ old('email', $user->email) }}"
                                        class="form-control-custom"
                                        placeholder="Enter your email address"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="password-note">
                                <i class="fas fa-lock me-2"></i>
                                Password is optional. Fill in the password fields only if you want to change it.
                            </div>

                            <div class="form-group-custom">
                                <label for="password">New Password</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-key"></i>
                                    <input
                                        id="password"
                                        type="password"
                                        class="form-control-custom"
                                        name="password"
                                        placeholder="Enter new password"
                                        autocomplete="new-password"
                                    >
                                </div>
                                <div class="helper-text">Use at least 8 characters for better security.</div>
                            </div>

                            <div class="form-group-custom">
                                <label for="password_confirmation">Confirm New Password</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-check-circle"></i>
                                    <input
                                        id="password_confirmation"
                                        type="password"
                                        class="form-control-custom"
                                        name="password_confirmation"
                                        placeholder="Confirm new password"
                                        autocomplete="new-password"
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <a class="btn btn-soft" href="{{ route('profile.show', $user->id) }}">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>

                            <button type="submit" class="btn btn-main">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection