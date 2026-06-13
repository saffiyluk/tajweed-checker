@extends('layouts.app')

@section('content')
<style>
    /* Tajweed-inspired background with subtle pattern - keeping original color palette */
    body {
        background: linear-gradient(135deg, #f5f7fa 0%, #e9edf2 100%);
        position: relative;
    }
    
    /* Subtle Islamic geometric pattern overlay - very light */
    body::before {
        content: "";
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 800" opacity="0.03"><path fill="none" stroke="%238B7355" stroke-width="1.2" d="M400 50 L500 150 L400 250 L300 150 Z M400 250 L550 400 L400 550 L250 400 Z M400 550 L500 650 L400 750 L300 650 Z M250 400 L150 500 L250 600 L350 500 Z M550 400 L650 500 L550 600 L450 500 Z"/><circle cx="400" cy="400" r="100" stroke="%238B7355" fill="none" stroke-width="0.8"/><circle cx="400" cy="400" r="180" stroke="%238B7355" fill="none" stroke-width="0.6"/></svg>');
        background-repeat: repeat;
        background-size: 160px;
        pointer-events: none;
    }
    
    .container {
        padding-top: 2rem;
        padding-bottom: 2rem;
    }
    
    /* Card with subtle shadow and border - keeping original colors */
    .card {
        border-radius: 20px;
        overflow: hidden;
        border: none;
        box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        background: #ffffff;
        margin: 0 auto;
        margin-top: auto;
        margin-bottom: auto;
    }
    
    .card:hover {
        transform: translateY(-3px);
        box-shadow: 0 25px 45px -12px rgba(0, 0, 0, 0.15);
    }
    
    /* Card header with original Bootstrap primary color */
    .card-header {
        background: #0d6efd;
        border-bottom: none;
        padding: 1.25rem 1.5rem;
        position: relative;
        overflow: hidden;
    }
    
    /* Decorative arabesque in header */
    .card-header::before {
        content: "۞";
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 3.5rem;
        opacity: 0.08;
        color: white;
        font-family: serif;
        pointer-events: none;
    }
    
    .card-header h4 {
        margin: 0;
        font-weight: 600;
        letter-spacing: 0.3px;
        position: relative;
        z-index: 1;
    }
    
    .card-header h4 i {
        margin-right: 8px;
    }
    
    .card-body {
        padding: 2rem 1.8rem;
    }
    
    /* Form labels - keep original styling */
    .col-form-label {
        font-weight: 500;
    }
    
    /* Input fields with subtle focus effect */
    .form-control {
        border-radius: 12px;
        border: 1px solid #dee2e6;
        padding: 0.6rem 1rem;
        transition: all 0.2s ease;
    }
    
    .form-control:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.12);
    }
    
    /* Keep original button styling with slight hover enhancement */
    .btn-primary {
        border-radius: 40px;
        padding: 0.6rem 1.5rem;
        font-weight: 500;
        transition: all 0.2s ease;
    }
    
    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(13, 110, 253, 0.25);
    }
    
    .btn-primary:active {
        transform: translateY(0);
    }
    
    /* Link styling */
    .btn-link {
        text-decoration: none;
        font-weight: 500;
        padding: 0.6rem 1rem;
    }
    
    .btn-link:hover {
        text-decoration: underline;
    }
    
    /* Checkbox styling - keep original */
    .form-check-input:checked {
        background-color: #0d6efd;
        border-color: #0d6efd;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .card-body {
            padding: 1.5rem;
        }
    }
</style>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">{{ __('Login') }}</h4>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('login') }}">
                        @csrf

                        <div class="row mb-3">
                            <label for="email" class="col-md-4 col-form-label text-md-end">{{ __('Email Address') }}</label>

                            <div class="col-md-6">
                                <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>

                                @error('email')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="password" class="col-md-4 col-form-label text-md-end">{{ __('Password') }}</label>

                            <div class="col-md-6">
                                <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">

                                @error('password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6 offset-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>

                                    <label class="form-check-label" for="remember">
                                        {{ __('Remember Me') }}
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-0">
                            <div class="col-md-8 offset-md-4">
                                <button type="submit" class="btn btn-primary">
                                    {{ __('Login') }}
                                </button>

                                @if (Route::has('password.request'))
                                    <a class="btn btn-link" href="{{ route('password.request') }}">
                                        {{ __('Forgot Your Password?') }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection