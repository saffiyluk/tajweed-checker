@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Dashboard') }}</div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    {{ __('You are logged in!') }}
                    <hr>

                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    <div class="mb-3">
                        <h5>Your Profile Audio</h5>
                        @if(Auth::user() && !empty(Auth::user()->audio_path))
                            <audio controls>
                                <source src="{{ asset('storage/' . Auth::user()->audio_path) }}" type="audio/mpeg">
                                Your browser does not support the audio element.
                            </audio>
                        @else
                            <p class="text-muted">No audio uploaded yet.</p>
                        @endif
                    </div>

                    <form action="{{ route('profile.audio', Auth::user()->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label for="audio" class="form-label">Upload audio</label>
                            <input type="file" class="form-control" name="audio" accept="audio/*" required>
                            <small class="text-muted">Allowed: mp3, wav, ogg, m4a. Max 50MB.</small>
                        </div>
                        <button type="submit" class="btn btn-primary">Upload Audio</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
