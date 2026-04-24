@extends('layouts.admin')

@section('title', 'Manage Recitations')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 text-gray-800">
            <i class="fas fa-file-audio me-2"></i>Audio Recitations
        </h1>
    </div>

    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.recitations.index') }}" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Search by filename or user..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <select name="rule" class="form-control">
                        <option value="">All Rules</option>
                        <option value="ikhfa" {{ request('rule') == 'ikhfa' ? 'selected' : '' }}>Ikhfa</option>
                        <option value="izhar" {{ request('rule') == 'izhar' ? 'selected' : '' }}>Izhar</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i>Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Recitations Table -->
    <div class="card shadow">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Rule</th>
                            <th>Filename</th>
                            <th>Analysis Result</th>
                            <th>Duration</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recitations as $recitation)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-info text-white d-flex align-items-center justify-content-center me-2" style="width: 30px; height: 30px;">
                                        {{ substr($recitation->user->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="font-weight-bold">{{ $recitation->user->name }}</div>
                                        <small class="text-muted">{{ $recitation->user->email }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-primary">{{ $recitation->tajweed_rule }}</span>
                            </td>
                            <td>
                                <small>{{ $recitation->original_filename }}</small>
                            </td>
                            <td>
                                @if($recitation->analysisResult)
                                    @if($recitation->analysisResult->processing_status == 'completed')
                                        @if($recitation->analysisResult->correctness >= 80)
                                            <span class="badge bg-success">{{ $recitation->analysisResult->correctness }}%</span>
                                        @elseif($recitation->analysisResult->correctness >= 60)
                                            <span class="badge bg-warning">{{ $recitation->analysisResult->correctness }}%</span>
                                        @else
                                            <span class="badge bg-danger">{{ $recitation->analysisResult->correctness }}%</span>
                                        @endif
                                    @else
                                        <span class="badge bg-secondary">{{ ucfirst($recitation->analysisResult->processing_status) }}</span>
                                    @endif
                                @else
                                    <span class="badge bg-secondary">No Analysis</span>
                                @endif
                            </td>
                            <td>
                                <small>{{ $recitation->duration ?? 'N/A' }}</small>
                            </td>
                            <td>
                                <small>{{ $recitation->created_at->diffForHumans() }}</small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary play-audio" 
                                            data-audio="{{ Storage::url($recitation->audio_file_path) }}"
                                            title="Play">
                                        <i class="fas fa-play"></i>
                                    </button>
                                    <a href="#" class="btn btn-outline-info" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <form action="{{ route('admin.recitations.destroy', $recitation) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger" 
                                                title="Delete" 
                                                onclick="return confirm('Are you sure you want to delete this recitation?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="d-flex justify-content-center mt-4">
                {{ $recitations->links() }}
            </div>
        </div>
    </div>

    <!-- Audio Player Modal -->
    <div class="modal fade" id="audioPlayerModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Audio Player</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <audio id="audioPlayer" controls class="w-100"></audio>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Audio player functionality
    const audioPlayer = document.getElementById('audioPlayer');
    const audioPlayerModal = new bootstrap.Modal(document.getElementById('audioPlayerModal'));
    
    document.querySelectorAll('.play-audio').forEach(button => {
        button.addEventListener('click', function() {
            const audioUrl = this.getAttribute('data-audio');
            audioPlayer.src = audioUrl;
            audioPlayerModal.show();
            audioPlayer.play();
        });
    });
});
</script>
@endsection