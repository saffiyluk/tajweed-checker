@extends('layouts.admin')

@section('page-title', 'System Monitoring')
@section('page-subtitle', 'Server, database, storage, and recent log health')

@section('content')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="admin-card h-100">
            <div class="card-header"><h5 class="mb-0">Server</h5></div>
            <div class="card-body">
                @foreach($serverInfo as $label => $value)
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>{{ ucwords(str_replace('_', ' ', $label)) }}</span>
                        <strong>{{ $value }}</strong>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-card h-100">
            <div class="card-header"><h5 class="mb-0">Database</h5></div>
            <div class="card-body">
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span>Connection</span>
                    <strong>{{ $databaseInfo['connection'] }}</strong>
                </div>
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span>Name</span>
                    <strong>{{ $databaseInfo['name'] }}</strong>
                </div>
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span>Size</span>
                    <strong>{{ $databaseInfo['size'] }}</strong>
                </div>
                @foreach($databaseInfo['tables'] as $table => $count)
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>{{ $table }}</span>
                        <strong>{{ $count }}</strong>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-card h-100">
            <div class="card-header"><h5 class="mb-0">Storage</h5></div>
            <div class="card-body">
                <div class="progress mb-3" style="height: 18px;">
                    <div class="progress-bar" style="width: {{ $storageInfo['usage_percentage'] }}%">{{ $storageInfo['usage_percentage'] }}%</div>
                </div>
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span>Used</span>
                    <strong>{{ round($storageInfo['used'] / 1024 / 1024 / 1024, 2) }} GB</strong>
                </div>
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span>Free</span>
                    <strong>{{ round($storageInfo['free'] / 1024 / 1024 / 1024, 2) }} GB</strong>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="admin-card mt-4">
    <div class="card-header"><h5 class="mb-0">Recent Logs</h5></div>
    <div class="card-body">
        <pre class="bg-dark text-light p-3 rounded mb-0" style="max-height: 420px; overflow: auto;">{{ implode("\n", $recentLogs) ?: 'No recent logs found.' }}</pre>
    </div>
</div>
@endsection
