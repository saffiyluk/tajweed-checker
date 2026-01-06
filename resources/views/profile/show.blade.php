@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card profile-card">
                <div class="card-header profile-header">
                    <h2 class="mb-0">Profile Details</h2>
                </div>

                <div class="card-body profile-body">
                    <div class="mb-3">
                        <strong>Name:</strong>
                        <p class="form-control-static">{{ Auth::user()->name }}</p>
                    </div>

                    <div class="mb-3">
                        <strong>Email:</strong>
                        <p class="form-control-static">{{ Auth::user()->email }}</p>
                    </div>

                    <div class="mb-3">
                        <strong>Joined On:</strong>
                        <p class="form-control-static">{{ Auth::user()->created_at->format('F j, Y') }}</p>
                    </div>

                    @if(!empty($user->audio_path))
                        <div class="mb-3">
                            <strong>Audio:</strong>
                            <div>
                                <audio controls>
                                    <source src="{{ asset('storage/' . $user->audio_path) }}" type="audio/mpeg">
                                    Your browser does not support the audio element.
                                </audio>
                            </div>
                        </div>
                    @endif

                    <div class="d-flex justify-content-between">
                        <a class="btn btn-primary btn-custom btn-spacing" href="{{ route('home') }}">Back to Home</a>
                        <a class="btn btn-warning btn-custom btn-spacing" href="{{ route('profile.edit', Auth::user()->id) }}">Edit Profile</a>
                        <form action="{{ route('profile.destroy', Auth::user()->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete your account? This cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-custom">Delete Profile</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection