@extends('layouts.admin')

@section('page-title', 'Dashboard')
@section('page-subtitle', 'Admin overview for users, submissions, corrections, and system status')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="admin-card stats-card h-100">
            <div class="stats-icon users"><i class="fas fa-users"></i></div>
            <div class="stats-number">{{ $stats['totalUsers'] }}</div>
            <div class="stats-label">Users</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card stats-card h-100">
            <div class="stats-icon recitations"><i class="fas fa-microphone"></i></div>
            <div class="stats-number">{{ $stats['totalRecitations'] }}</div>
            <div class="stats-label">Submissions</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card stats-card h-100">
            <div class="stats-icon analytics"><i class="fas fa-check-circle"></i></div>
            <div class="stats-number">{{ $stats['completedAnalyses'] }}</div>
            <div class="stats-label">Completed</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card stats-card h-100">
            <div class="stats-icon monitoring"><i class="fas fa-clock"></i></div>
            <div class="stats-number">{{ $stats['pendingAnalyses'] }}</div>
            <div class="stats-label">Pending</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <a class="btn btn-primary w-100 py-3" href="{{ route('admin.recitations.index') }}">
            <i class="fas fa-microphone me-2"></i>Review Submissions
        </a>
    </div>
    <div class="col-md-3">
        <a class="btn btn-outline-primary w-100 py-3" href="{{ route('admin.users.index') }}">
            <i class="fas fa-user-shield me-2"></i>Monitor Users
        </a>
    </div>
    <div class="col-md-3">
        <a class="btn btn-outline-success w-100 py-3" href="{{ route('admin.analytics') }}">
            <i class="fas fa-chart-line me-2"></i>View Analytics
        </a>
    </div>
    <div class="col-md-3">
        <a class="btn btn-outline-info w-100 py-3" href="{{ route('admin.corrections.index', ['status' => 'pending']) }}">
            <i class="fas fa-clipboard-check me-2"></i>Review Corrections
            @if(($stats['pendingCorrections'] ?? 0) > 0)
                <span class="badge bg-warning text-dark ms-2">{{ $stats['pendingCorrections'] }}</span>
            @endif
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="admin-card">
            <div class="card-header">
                <h5 class="mb-0">Recent Submissions</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Rule</th>
                                <th>Status</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentRecitations as $recitation)
                                <tr>
                                    <td>{{ $recitation->user->name ?? 'Deleted user' }}</td>
                                    <td>{{ ucfirst($recitation->tajweed_rule) }}</td>
                                    <td>{{ ucfirst(optional($recitation->analysisResult)->processing_status ?? 'No analysis') }}</td>
                                    <td>{{ $recitation->created_at->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">No submissions yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="admin-card">
            <div class="card-header">
                <h5 class="mb-0">Recent Users</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentUsers as $user)
                                <tr>
                                    <td>{{ $user->name }}</td>
                                    <td><span class="badge {{ $user->is_admin ? 'bg-primary' : 'bg-secondary' }}">{{ ucfirst($user->role) }}</span></td>
                                    <td>{{ $user->created_at->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-4">No users yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
