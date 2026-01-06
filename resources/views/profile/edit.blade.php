@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card profile-card">
                <div class="card-header profile-header">
                    <h2 class="mb-0">Update Profile</h2>
                </div>

                <div class="card-body profile-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <strong>Whoops!</strong> There were some problems with your input.<br><br>
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('profile.update', Auth::user()->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <input type="hidden" name="id" value="{{ Auth::user()->id }}">

                        <div class="mb-3">
                            <label for="name" class="form-label"><strong>Name:</strong></label>
                            <input type="text" name="name" value="{{ Auth::user()->name }}" class="form-control" placeholder="Name">
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label"><strong>Email:</strong></label>
                            <input type="email" class="form-control" name="email" value="{{ Auth::user()->email }}" placeholder="Email">
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label"><strong>Password:</strong></label>
                            <input type="password" class="form-control" name="password" placeholder="Password">
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label"><strong>Confirm Password:</strong></label>
                            <input type="password" class="form-control" name="password_confirmation" placeholder="Confirm Password">
                        </div>

                        <div class="mb-3">
                            <label for="audio" class="form-label"><strong>Profile Audio (optional):</strong></label>
                            <input type="file" class="form-control" name="audio" accept="audio/*">
                            <small class="text-muted">Allowed types: mp3, wav, ogg, m4a. Max 50MB.</small>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a class="btn btn-secondary btn-custom btn-spacing" href="{{ route('profile.show', Auth::user()->id) }}">Back</a>
                            <button type="submit" class="btn btn-primary btn-custom">Update Profile</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection