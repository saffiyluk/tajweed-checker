@extends('layouts.admin')

@section('page-title', 'Analytics')
@section('page-subtitle', 'Usage and recitation trends')

@section('content')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="admin-card h-100">
            <div class="card-header"><h5 class="mb-0">Rule Mix</h5></div>
            <div class="card-body">
                @forelse($recitationsByRule as $row)
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>{{ ucfirst($row->tajweed_rule) }}</span>
                        <strong>{{ $row->count }}</strong>
                    </div>
                @empty
                    <p class="text-muted mb-0">No recitations yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-card h-100">
            <div class="card-header"><h5 class="mb-0">Analysis Results</h5></div>
            <div class="card-body">
                @forelse($analysisResults as $row)
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>{{ ucfirst($row->correctness) }}</span>
                        <strong>{{ $row->count }}</strong>
                    </div>
                @empty
                    <p class="text-muted mb-0">No completed results yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-card h-100">
            <div class="card-header"><h5 class="mb-0">Top Users</h5></div>
            <div class="card-body">
                @forelse($topUsers as $user)
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>{{ $user->name }}</span>
                        <strong>{{ $user->audio_recitations_count }}</strong>
                    </div>
                @empty
                    <p class="text-muted mb-0">No users yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
