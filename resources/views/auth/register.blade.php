@extends('layouts.app')

@section('content')
<style>
    body {
        background:
            radial-gradient(circle at top left, rgba(13, 110, 253, 0.12), transparent 35%),
            radial-gradient(circle at bottom right, rgba(194, 153, 80, 0.12), transparent 35%),
            linear-gradient(135deg, #f7f9fc 0%, #eef2f7 100%);
        min-height: 100vh;
    }

    .navbar {
        background: rgba(255, 255, 255, 0.9) !important;
        backdrop-filter: blur(14px);
        box-shadow: 0 8px 25px rgba(15, 23, 42, 0.06);
        min-height: 72px;
        z-index: 10;
    }

    .navbar-brand {
        font-size: 0;
        font-weight: 800;
        color: #1d4ed8 !important;
        letter-spacing: -0.5px;
    }

    .navbar-brand::after {
        content: " Tajweed Checker";
        font-size: 1.25rem;
    }

    .auth-wrapper {
        min-height: calc(100vh - 180px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 4rem 1rem 5rem;
        position: relative;
        overflow: hidden;
    }

    .auth-wrapper::before {
        content: "";
        position: absolute;
        width: 420px;
        height: 420px;
        border-radius: 50%;
        background: rgba(13, 110, 253, 0.08);
        top: 40px;
        left: -120px;
        filter: blur(5px);
    }

    .auth-wrapper::after {
        content: "۞";
        position: absolute;
        right: 8%;
        bottom: 8%;
        font-size: 8rem;
        color: rgba(194, 153, 80, 0.08);
        font-family: serif;
    }

    .auth-card {
        width: 100%;
        max-width: 1020px;
        display: grid;
        grid-template-columns: 1fr 1.08fr;
        background: #ffffff;
        border-radius: 28px;
        overflow: hidden;
        box-shadow: 0 30px 80px rgba(15, 23, 42, 0.14);
        position: relative;
        z-index: 1;
    }

    .auth-left {
        background:
            linear-gradient(135deg, rgba(13, 110, 253, 0.96), rgba(30, 64, 175, 0.98)),
            url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='140' height='140' viewBox='0 0 140 140'%3E%3Cpath d='M70 10 L95 35 L70 60 L45 35 Z M70 80 L95 105 L70 130 L45 105 Z M10 70 L35 45 L60 70 L35 95 Z M80 70 L105 45 L130 70 L105 95 Z' fill='none' stroke='white' stroke-opacity='0.16' stroke-width='2'/%3E%3C/svg%3E");
        color: white;
        padding: 3.2rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 560px;
    }

    .brand-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.65rem;
        font-weight: 800;
        font-size: 1.1rem;
        letter-spacing: -0.3px;
    }

    .brand-icon {
        width: 42px;
        height: 42px;
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.16);
        display: grid;
        place-items: center;
        font-size: 1.35rem;
        border: 1px solid rgba(255, 255, 255, 0.22);
    }

    .auth-left h1 {
        font-size: 2.35rem;
        line-height: 1.15;
        font-weight: 800;
        letter-spacing: -1px;
        margin: 2.8rem 0 1rem;
    }

    .auth-left p {
        color: rgba(255, 255, 255, 0.82);
        font-size: 1rem;
        line-height: 1.7;
        margin: 0;
    }

    .feature-list {
        display: grid;
        gap: 0.85rem;
        margin-top: 2rem;
    }

    .feature-item {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        color: rgba(255, 255, 255, 0.9);
        font-size: 0.95rem;
    }

    .feature-dot {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: grid;
        place-items: center;
        background: rgba(255, 255, 255, 0.16);
        font-size: 0.85rem;
    }

    .auth-right {
        padding: 3.2rem;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .form-title {
        margin-bottom: 2rem;
    }

    .form-title span {
        display: inline-block;
        color: #c29950;
        font-weight: 700;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        margin-bottom: 0.5rem;
    }

    .form-title h2 {
        color: #0f172a;
        font-weight: 800;
        letter-spacing: -0.7px;
        margin: 0;
    }

    .form-title p {
        color: #64748b;
        margin-top: 0.65rem;
        margin-bottom: 0;
    }

    .form-group-custom {
        margin-bottom: 1.05rem;
    }

    .form-label-custom {
        color: #334155;
        font-weight: 700;
        font-size: 0.92rem;
        margin-bottom: 0.5rem;
    }

    .form-control {
        height: 52px;
        border-radius: 15px;
        border: 1px solid #dbe3ef;
        background: #f8fafc;
        padding: 0.75rem 1rem;
        color: #0f172a;
        transition: all 0.2s ease;
    }

    .form-control:focus {
        background: #ffffff;
        border-color: #2563eb;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
    }

    .register-actions {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
        margin-top: 1.5rem;
    }

    .btn-register {
        border: none;
        min-width: 150px;
        height: 50px;
        border-radius: 15px;
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: white;
        font-weight: 800;
        box-shadow: 0 12px 24px rgba(37, 99, 235, 0.22);
        transition: all 0.2s ease;
    }

    .btn-register:hover {
        transform: translateY(-2px);
        box-shadow: 0 16px 30px rgba(37, 99, 235, 0.28);
        color: white;
    }

    .login-note {
        margin-top: 2rem;
        padding-top: 1.4rem;
        border-top: 1px solid #eef2f7;
        color: #64748b;
        font-size: 0.92rem;
    }

    .login-note a {
        color: #2563eb;
        font-weight: 800;
        text-decoration: none;
    }

    .login-note a:hover {
        text-decoration: underline;
    }

    .invalid-feedback {
        font-weight: 600;
        margin-top: 0.45rem;
    }

    @media (max-width: 992px) {
        .auth-card {
            grid-template-columns: 1fr;
            max-width: 620px;
        }

        .auth-left {
            min-height: auto;
            padding: 2.4rem;
        }

        .auth-left h1 {
            font-size: 2rem;
            margin-top: 2rem;
        }

        .auth-right {
            padding: 2.4rem;
        }
    }

    @media (max-width: 576px) {
        .auth-wrapper {
            padding: 2rem 0.9rem 4rem;
            align-items: flex-start;
        }

        .auth-card {
            border-radius: 22px;
        }

        .auth-left,
        .auth-right {
            padding: 1.6rem;
        }

        .auth-left h1 {
            font-size: 1.75rem;
        }

        .register-actions {
            flex-direction: column;
            align-items: stretch;
        }

        .btn-register {
            width: 100%;
        }
    }
</style>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-left">
            <div>
                <div class="brand-badge">
                    <div class="brand-icon">ق</div>
                    <div>Tajweed Checker</div>
                </div>

                <h1>Start your Quran recitation journey with AI support.</h1>
                <p>
                    Create an account to practise your recitation, upload recordings,
                    and receive tajweed feedback for Izhar and Ikhfa rules.
                </p>

                <div class="feature-list">
                    <div class="feature-item">
                        <div class="feature-dot">✓</div>
                        <div>Save your recitation history</div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-dot">✓</div>
                        <div>Track your tajweed practice progress</div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-dot">✓</div>
                        <div>Access AI-powered recitation checking</div>
                    </div>
                </div>
            </div>

            <p class="mt-4">
                “Practise with intention. Improve with feedback.”
            </p>
        </div>

        <div class="auth-right">
            <div class="form-title">
                <span>Create account</span>
                <h2>Register new account</h2>
                <p>Fill in your details to start using Tajweed Checker.</p>
            </div>

            <form method="POST" action="{{ route('register') }}">
                @csrf

                <div class="form-group-custom">
                    <label for="name" class="form-label-custom">{{ __('Name') }}</label>
                    <input
                        id="name"
                        type="text"
                        class="form-control @error('name') is-invalid @enderror"
                        name="name"
                        value="{{ old('name') }}"
                        required
                        autocomplete="name"
                        autofocus
                        placeholder="Enter your full name"
                    >

                    @error('name')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <div class="form-group-custom">
                    <label for="email" class="form-label-custom">{{ __('Email Address') }}</label>
                    <input
                        id="email"
                        type="email"
                        class="form-control @error('email') is-invalid @enderror"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autocomplete="email"
                        placeholder="Enter your email"
                    >

                    @error('email')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <div class="form-group-custom">
                    <label for="password" class="form-label-custom">{{ __('Password') }}</label>
                    <input
                        id="password"
                        type="password"
                        class="form-control @error('password') is-invalid @enderror"
                        name="password"
                        required
                        autocomplete="new-password"
                        placeholder="Create a password"
                    >

                    @error('password')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <div class="form-group-custom">
                    <label for="password-confirm" class="form-label-custom">{{ __('Confirm Password') }}</label>
                    <input
                        id="password-confirm"
                        type="password"
                        class="form-control"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="Confirm your password"
                    >
                </div>

                <div class="register-actions">
                    <button type="submit" class="btn btn-register">
                        {{ __('Register') }}
                    </button>
                </div>

                @if (Route::has('login'))
                    <div class="login-note">
                        Already have an account?
                        <a href="{{ route('login') }}">Login here</a>
                    </div>
                @endif
            </form>
        </div>
    </div>
</div>
@endsection