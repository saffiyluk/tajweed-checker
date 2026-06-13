@extends('layouts.admin')

@section('page-title', 'System Logs')
@section('page-subtitle', 'Recent Laravel log entries')

@section('content')
<div class="admin-card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Logs</h5>
    </div>
    <div class="card-body">
        @forelse($logs as $log)
            <div class="border-bottom py-3">
                <div class="text-muted small mb-1">{{ $log['timestamp'] }}</div>
                <pre class="mb-0 text-wrap">{{ $log['message'] }}</pre>
            </div>
        @empty
            <p class="text-muted mb-0">No log entries found.</p>
        @endforelse
    </div>
</div>
@endsection
