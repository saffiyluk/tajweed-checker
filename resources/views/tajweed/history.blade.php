@extends('layouts.app')

@section('title', 'My Recitations')

@section('content')
    <div class="container py-4">
        <!-- Page Header -->
        <div class="page-header mb-5">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h1 class="mb-2">
                        <i class="fas fa-microphone-alt me-3"></i>My Recitations
                    </h1>
                    <p class="text-muted mb-0">Review and listen to all your recordings</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('tajweed.ikhfa-haqiqi') }}" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>New Recording
                    </a>
                </div>
            </div>

            @if($recitations->isEmpty())
                <!-- Empty State -->
                <div class="empty-state text-center py-5">
                    <div class="empty-icon mb-4">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <h3 class="mb-3">No Recitations Yet</h3>
                    <p class="text-muted mb-4">Start by recording or uploading your first audio file.</p>
                    <div class="d-flex gap-3 justify-content-center">
                        <a href="{{ route('tajweed.ikhfa-haqiqi') }}" class="btn btn-primary">
                            <i class="fas fa-volume-down me-2"></i>Ikhfa Haqiqi
                        </a>
                        <a href="{{ route('tajweed.izhar-halqi') }}" class="btn btn-success">
                            <i class="fas fa-volume-up me-2"></i>Izhar Halqi
                        </a>
                    </div>
                </div>
            @else
                <!-- Statistics Cards -->
                <div class="stats-grid mb-5">
                    <div class="stat-card">
                        <div class="stat-icon total">
                            <i class="fas fa-music"></i>
                        </div>
                        <div class="stat-content">
                            <h3>{{ $recitations->total() }}</h3>
                            <p>Total Recitations</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon correct">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3>{{ $recitations->getCollection()->filter(fn($r) => $r->analysisResult && $r->analysisResult->correctness === 'correct')->count() }}
                            </h3>
                            <p>Correct Pronunciations</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon improve">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3>{{ $recitations->getCollection()->filter(fn($r) => $r->analysisResult && $r->analysisResult->correctness === 'incorrect')->count() }}
                            </h3>
                            <p>Needs Improvement</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon pending">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <h3>{{ $recitations->getCollection()->filter(fn($r) => !$r->analysisResult || $r->analysisResult->processing_status === 'pending')->count() }}
                            </h3>
                            <p>Pending Analysis</p>
                        </div>
                    </div>
                </div>

                <!-- Filter & Actions Bar -->
                <div class="action-bar mb-4">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-4">
                            <div class="filter-group">
                                <label><i class="fas fa-filter me-2"></i>Filter by Rule:</label>
                                <select class="form-select" id="ruleFilter">
                                    <option value="">All Rules</option>
                                    <option value="ikhfa">Ikhfa Haqiqi</option>
                                    <option value="izhar">Izhar Halqi</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="filter-group">
                                <label><i class="fas fa-tag me-2"></i>Filter by Status:</label>
                                <select class="form-select" id="statusFilter">
                                    <option value="">All Status</option>
                                    <option value="correct">Correct</option>
                                    <option value="incorrect">Needs Improvement</option>
                                    <option value="pending">Pending</option>
                                    <option value="processing">Processing</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="filter-group">
                                <label><i class="fas fa-sort me-2"></i>Sort by:</label>
                                <select class="form-select" id="sortFilter">
                                    <option value="newest">Newest First</option>
                                    <option value="oldest">Oldest First</option>
                                    <option value="correct">Correct First</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Delete Confirmation Modal -->
                <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-trash-alt text-danger me-2"></i>Delete Recording
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="warning-icon text-center mb-3">
                                    <i class="fas fa-exclamation-triangle fa-3x text-warning"></i>
                                </div>
                                <p class="text-center mb-3">Are you sure you want to delete this recording?</p>
                                <div class="alert alert-warning">
                                    <i class="fas fa-info-circle me-2"></i>
                                    This action cannot be undone. The recording and its analysis will be permanently deleted.
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                <form id="deleteForm" method="POST" style="display: inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fas fa-trash-alt me-2"></i>Delete Recording
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recitations Grid -->
                <div class="row g-4 mb-5" id="recitationsGrid">
                    @foreach($recitations as $recitation)
                        <div class="col-lg-6 col-xl-4">
                            <div class="recitation-card">
                                <!-- Card Header -->
                                <div class="card-header">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="flex-grow-1">
                                            <h6 class="card-title">
                                                <i class="fas fa-file-audio me-2"></i>
                                                {{ Str::limit($recitation->original_filename, 25) }}
                                            </h6>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                {{ $recitation->created_at->format('M d, Y') }}
                                            </small>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" type="button"
                                                data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="#" data-bs-toggle="modal"
                                                        data-bs-target="#deleteModal"
                                                        onclick="setDeleteUrl('{{ route('tajweed.delete', $recitation->id) }}')">
                                                        <i class="fas fa-trash-alt me-2"></i>Delete
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item"
                                                        href="{{ route('tajweed.download', $recitation->id) }}">
                                                        <i class="fas fa-download me-2"></i>Download
                                                    </a>
                                                </li>
                                                @if($recitation->analysisResult)
                                                    <li>
                                                        <a class="dropdown-item" href="{{ route('tajweed.result', $recitation->id) }}">
                                                            <i class="fas fa-chart-line me-2"></i>View Analysis
                                                        </a>
                                                    </li>
                                                @endif
                                            </ul>
                                        </div>
                                    </div>

                                    <!-- Audio Player -->
                                    @if($recitation->firebase_url)
                                        @php
                                            $ext = pathinfo($recitation->firebase_url, PATHINFO_EXTENSION);
                                            $type = match (strtolower($ext)) {
                                                'mp3' => 'audio/mpeg',
                                                'wav' => 'audio/wav',
                                                'webm' => 'audio/webm',
                                                default => 'audio/mpeg',
                                            };
                                        @endphp
                                        <div class="audio-player mb-2">
                                            <audio controls onerror="handleAudioError(this, {{ $recitation->id }})">
                                                <source src="{{ $recitation->firebase_url }}" type="{{ $type }}">
                                                Your browser does not support audio.
                                            </audio>
                                            <small class="text-muted d-block mt-1">
                                                <i class="fas fa-link me-1"></i>Firebase Storage
                                            </small>
                                        </div>
                                    @elseif($recitation->audio_file_path)
                                        <div class="audio-player mb-2">
                                            @php
                                                $localPath = $recitation->audio_file_path;
                                                if (strpos($localPath, 'public/') === 0) {
                                                    $localPath = substr($localPath, 7); // Remove 'public/' prefix
                                                }
                                            @endphp
                                            <audio controls onerror="handleAudioError(this, {{ $recitation->id }})">
                                                @if(Storage::disk('public')->exists($localPath))
                                                    <source src="{{ Storage::disk('public')->url($localPath) }}" type="audio/mpeg">
                                                    <!-- Remove the second source if it's the same URL -->
                                                @endif
                                                Your browser does not support audio.
                                            </audio>
                                            <small class="text-muted d-block mt-1">
                                                <i class="fas fa-hdd me-1"></i>Local Storage
                                            </small>
                                        </div>
                                    @endif

                                    <!-- Rule Badge -->
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="rule-badge {{ $recitation->tajweed_rule }}">
                                            @if($recitation->tajweed_rule === 'ikhfa')
                                                Ikhfa Haqiqi
                                            @elseif($recitation->tajweed_rule === 'izhar')
                                                Izhar Halqi
                                            @else
                                                {{ $recitation->tajweed_rule }}
                                            @endif
                                        </span>
                                        @if($recitation->duration_seconds)
                                            <span class="duration-badge">
                                                <i class="fas fa-clock me-1"></i>{{ gmdate('i:s', $recitation->duration_seconds) }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <!-- Card Body -->
                                <div class="card-body">
                                    <!-- Analysis Status -->
                                    @if($recitation->analysisResult)
                                        <div class="analysis-status mb-3">
                                            <small class="text-muted mb-2 d-block">Analysis Status:</small>
                                            @if($recitation->analysisResult->processing_status === 'completed')
                                                @if($recitation->analysisResult->correctness === 'correct')
                                                    <div class="status-correct">
                                                        <i class="fas fa-check-circle me-2"></i>
                                                        <span>Correct Pronunciation</span>
                                                        <span
                                                            class="confidence-score">{{ round($recitation->analysisResult->confidence_score * 100/100) }}%</span>
                                                    </div>
                                                @else
                                                    <div class="status-incorrect">
                                                        <i class="fas fa-times-circle me-2"></i>
                                                        <span>Needs Improvement</span>
                                                        <span
                                                            class="confidence-score">{{ round($recitation->analysisResult->confidence_score * 100/100) }}%</span>
                                                    </div>
                                                @endif
                                            @elseif($recitation->analysisResult->processing_status === 'processing')
                                                <div class="status-processing">
                                                    <i class="fas fa-spinner fa-spin me-2"></i>
                                                    <span>Analyzing...</span>
                                                </div>
                                            @elseif($recitation->analysisResult->processing_status === 'failed')
                                                <div class="status-failed">
                                                    <i class="fas fa-times me-2"></i>
                                                    <span>Analysis Failed</span>
                                                </div>
                                            @else
                                                <div class="status-pending">
                                                    <i class="fas fa-hourglass-start me-2"></i>
                                                    <span>Pending Analysis</span>
                                                </div>
                                            @endif
                                        </div>

                                        <!-- Feedback Preview -->
                                        @if($recitation->analysisResult->feedback_message)
                                            <div class="feedback-preview">
                                                <small class="text-muted mb-2 d-block">Feedback:</small>
                                                <p class="feedback-text">
                                                    {{ Str::limit($recitation->analysisResult->feedback_message, 100) }}
                                                </p>
                                            </div>
                                        @endif
                                    @else
                                        <div class="alert alert-secondary">
                                            <i class="fas fa-info-circle me-2"></i>Analysis pending
                                        </div>
                                    @endif
                                </div>

                                <!-- Card Footer -->
                                <div class="card-footer">
                                    <div class="d-flex gap-2">
                                        @if($recitation->analysisResult && $recitation->analysisResult->processing_status === 'completed')
                                            <a href="{{ route('tajweed.result', $recitation->id) }}"
                                                class="btn btn-primary flex-grow-1">
                                                <i class="fas fa-eye me-2"></i>View Results
                                            </a>
                                        @else
                                            <button class="btn btn-secondary flex-grow-1" disabled>
                                                <i class="fas fa-lock me-2"></i>In Progress
                                            </button>
                                        @endif
                                        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal"
                                            onclick="setDeleteUrl('{{ route('tajweed.delete', $recitation->id) }}')">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Pagination -->
                @if($recitations->hasPages())
                    <div class="pagination-wrapper mb-4">
                        {{ $recitations->links('pagination::bootstrap-4') }}
                    </div>
                @endif
            @endif

            <!-- Bottom Actions -->
            <div class="bottom-actions mt-5 pt-4 border-top">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <a href="{{ route('home') }}" class="btn btn-outline-primary">
                            <i class="fas fa-home me-2"></i>Dashboard
                        </a>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('tajweed.ikhfa-haqiqi') }}" class="btn btn-primary">
                            <i class="fas fa-volume-down me-2"></i>Ikhfa Haqiqi
                        </a>
                        <a href="{{ route('tajweed.izhar-halqi') }}" class="btn btn-success">
                            <i class="fas fa-volume-up me-2"></i>Izhar Halqi
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        :root {
            --primary: #2563eb;
            --primary-light: #3b82f6;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
            --light: #f8fafc;
            --dark: #1e293b;
            --gray: #64748b;
            --border: #e2e8f0;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
            --radius: 12px;
        }

        /* Page Header */
        .page-header h1 {
            color: var(--dark);
            font-weight: 700;
            font-size: 2.25rem;
        }

        .page-header h1 i {
            color: var(--primary);
        }

        /* Empty State */
        .empty-state {
            background: white;
            border-radius: var(--radius);
            padding: 4rem 2rem;
            box-shadow: var(--shadow);
        }

        .empty-icon {
            font-size: 4rem;
            color: var(--gray);
            opacity: 0.5;
        }

        .empty-state h3 {
            color: var(--dark);
            font-weight: 600;
        }

        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: var(--radius);
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.total {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
        }

        .stat-icon.correct {
            background: linear-gradient(135deg, var(--success), #34d399);
        }

        .stat-icon.improve {
            background: linear-gradient(135deg, var(--warning), #fbbf24);
        }

        .stat-icon.pending {
            background: linear-gradient(135deg, var(--info), #22d3ee);
        }

        .stat-content h3 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            color: var(--dark);
        }

        .stat-content p {
            margin: 0;
            color: var(--gray);
            font-size: 0.875rem;
        }

        /* Action Bar */
        .action-bar {
            background: white;
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .filter-group label {
            font-size: 0.875rem;
            color: var(--gray);
            margin-bottom: 0.5rem;
            display: block;
            font-weight: 500;
        }

        .filter-group label i {
            color: var(--primary);
        }

        /* Recitation Cards */
        .recitation-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .recitation-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-5px);
        }

        .recitation-card .card-header {
            background: var(--light);
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
        }

        .card-title {
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }

        .card-title i {
            color: var(--primary);
        }

        /* Audio Player */
        .audio-player {
            margin: 1rem 0;
        }

        .audio-player audio {
            width: 100%;
            height: 40px;
            border-radius: 8px;
        }

        .audio-player audio::-webkit-media-controls-panel {
            background: white;
            border-radius: 8px;
        }

        /* Badges */
        .rule-badge {
            padding: 0.375rem 1rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .rule-badge.ikhfa {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary);
            border: 1px solid rgba(37, 99, 235, 0.2);
        }

        .rule-badge.izhar {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .duration-badge {
            color: var(--gray);
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Analysis Status */
        .analysis-status {
            margin: 1rem 0;
        }

        .status-correct,
        .status-incorrect,
        .status-processing,
        .status-failed,
        .status-pending {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            border-radius: var(--radius);
            font-weight: 500;
        }

        .status-correct {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .status-incorrect {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .status-processing {
            background: rgba(6, 182, 212, 0.1);
            color: var(--info);
            border: 1px solid rgba(6, 182, 212, 0.2);
        }

        .status-failed {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .status-pending {
            background: rgba(100, 116, 139, 0.1);
            color: var(--gray);
            border: 1px solid rgba(100, 116, 139, 0.2);
        }

        .confidence-score {
            margin-left: auto;
            font-weight: 700;
            font-size: 1.1rem;
        }

        /* Feedback Preview */
        .feedback-preview {
            background: var(--light);
            border-radius: var(--radius);
            padding: 1rem;
            margin-top: 1rem;
            border-left: 4px solid var(--primary);
        }

        .feedback-text {
            margin: 0;
            font-size: 0.875rem;
            line-height: 1.5;
            color: var(--dark);
        }

        /* Card Footer */
        .recitation-card .card-body {
            flex: 1;
            padding: 1.5rem;
        }

        .recitation-card .card-footer {
            background: var(--light);
            border-top: 1px solid var(--border);
            padding: 1.5rem;
        }

        .recitation-card .card-footer .btn {
            padding: 0.625rem 1.25rem;
            font-weight: 500;
        }

        /* Modal */
        .modal-header {
            border-bottom: 2px solid var(--border);
        }

        .modal-title i {
            color: var(--danger);
        }

        .warning-icon {
            margin: 1rem 0;
        }

        /* Pagination */
        .pagination-wrapper .pagination {
            justify-content: center;
        }

        .pagination-wrapper .page-link {
            color: var(--primary);
            border: 1px solid var(--border);
            margin: 0 0.25rem;
            border-radius: 8px;
        }

        .pagination-wrapper .page-item.active .page-link {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .pagination-wrapper .page-item.disabled .page-link {
            color: var(--gray);
        }

        /* Bottom Actions */
        .bottom-actions {
            padding-top: 2rem;
        }

        .bottom-actions .btn {
            padding: 0.75rem 1.5rem;
            font-weight: 500;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }

            .page-header {
                flex-direction: column;
                text-align: center;
            }

            .action-bar .row {
                flex-direction: column;
            }

            .action-bar .col-md-4 {
                width: 100%;
                margin-bottom: 1rem;
            }
        }

        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .recitation-card .card-footer .d-flex {
                flex-direction: column;
            }

            .bottom-actions .d-flex {
                flex-direction: column;
                width: 100%;
            }

            .bottom-actions .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }

        /* Delete Button Animation */
        .btn-outline-danger:hover {
            background: var(--danger);
            color: white;
            transform: scale(1.05);
        }

        /* Dropdown Menu */
        .dropdown-menu {
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
        }

        .dropdown-item {
            padding: 0.625rem 1rem;
            font-size: 0.875rem;
        }

        .dropdown-item:hover {
            background: var(--light);
            color: var(--primary);
        }

        .dropdown-item i {
            width: 20px;
            text-align: center;
        }

        /* Alert Styles */
        .alert {
            border-radius: var(--radius);
            border: none;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .alert-secondary {
            background: rgba(100, 116, 139, 0.1);
            color: var(--gray);
            border: 1px solid rgba(100, 116, 139, 0.2);
        }
    </style>

    <script>
        // Set delete URL for the modal
        function setDeleteUrl(url) {
            document.getElementById('deleteForm').action = url;
        }

        // Filter functionality
        document.addEventListener('DOMContentLoaded', function () {
            const ruleFilter = document.getElementById('ruleFilter');
            const statusFilter = document.getElementById('statusFilter');
            const sortFilter = document.getElementById('sortFilter');
            const cards = document.querySelectorAll('.col-lg-6.col-xl-4');

            function applyFilters() {
                const ruleValue = ruleFilter.value;
                const statusValue = statusFilter.value;
                const sortValue = sortFilter.value;

                cards.forEach(card => {
                    const ruleBadge = card.querySelector('.rule-badge');
                    const ruleClass = ruleBadge ? ruleBadge.classList.contains(ruleValue) : false;
                    const statusElement = card.querySelector('.analysis-status');

                    let showCard = true;

                    // Rule filter
                    if (ruleValue && !ruleClass) {
                        showCard = false;
                    }

                    // Status filter
                    if (statusValue && statusElement) {
                        const statusText = statusElement.textContent.toLowerCase();
                        if (statusValue === 'correct' && !statusText.includes('correct')) showCard = false;
                        if (statusValue === 'incorrect' && !statusText.includes('needs')) showCard = false;
                        if (statusValue === 'pending' && !statusText.includes('pending')) showCard = false;
                        if (statusValue === 'processing' && !statusText.includes('analyzing')) showCard = false;
                    }

                    // Show/hide card
                    card.style.display = showCard ? 'block' : 'none';
                });
            }

            // Add event listeners
            if (ruleFilter) ruleFilter.addEventListener('change', applyFilters);
            if (statusFilter) statusFilter.addEventListener('change', applyFilters);
            if (sortFilter) sortFilter.addEventListener('change', applyFilters);

            // Initialize filters
            applyFilters();
        });
    </script>

    @push('scripts')
        <script>
            // Function to handle audio playback errors
            function handleAudioError(audioElement, recitationId) {
                console.error('Audio playback error for recitation ID:', recitationId);

                // Show error message
                const parent = audioElement.parentElement;
                parent.innerHTML = `
                                                        <div class="alert alert-warning">
                                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                                            Cannot play audio. 
                                                            <button class="btn btn-sm btn-outline-warning ms-2" onclick="retryAudio(${recitationId})">
                                                                <i class="fas fa-redo me-1"></i>Retry
                                                            </button>
                                                        </div>
                                                    `;
            }

            // Function to retry audio loading
            function retryAudio(recitationId) {
                fetch(`/tajweed/audio-url/${recitationId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.url) {
                            // Reload the page to try again
                            window.location.reload();
                        } else {
                            alert('Unable to load audio. Please try again later.');
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching audio URL:', error);
                        alert('Error loading audio.');
                    });
            }

            // Add click event to play buttons
            document.addEventListener('DOMContentLoaded', function () {
                // Add event listeners for all audio elements
                document.querySelectorAll('audio').forEach(audio => {
                    audio.addEventListener('error', function () {
                        const recitationId = this.getAttribute('data-recitation-id');
                        handleAudioError(this, recitationId);
                    });

                    audio.addEventListener('play', function () {
                        // Pause all other audio players
                        document.querySelectorAll('audio').forEach(otherAudio => {
                            if (otherAudio !== this && !otherAudio.paused) {
                                otherAudio.pause();
                            }
                        });
                    });
                });
            });
        </script>
    @endpush

@endsection