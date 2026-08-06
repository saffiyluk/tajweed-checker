@extends('layouts.admin')

@section('title', 'Recitation Details')
@section('page-title', 'Recitation Details')
@section('page-subtitle', 'Review one submitted recording and its analysis output')

@section('content')
@php
    $analysis = $audioRecitation->analysisResult;
    $confidence = $analysis?->confidence_score;
    $confidencePercent = is_null($confidence) ? null : ($confidence <= 1 ? round($confidence * 100) : round($confidence));
    $outcomeKey = $analysis?->displayOutcomeKey();
    $statusClass = match($analysis?->processing_status) {
        'completed' => 'completed',
        'processing' => 'processing',
        default => 'pending',
    };
    $processingLabel = match($analysis?->processing_status) {
        'completed' => 'Completed',
        'processing' => 'Processing',
        'pending' => 'Pending',
        'failed' => 'Analysis Failed',
        default => 'Unavailable',
    };
    $correctnessLabel = match($outcomeKey) {
        'correct', 'incorrect', 'uncertain', 'analysis_failed' => $analysis->displayOutcomeLabel(),
        default => 'Unavailable',
    };
    $correctionStatusClass = match($analysis?->correction_review_status) {
        'used' => 'completed',
        'reviewed' => 'processing',
        'rejected' => 'pending',
        default => 'pending',
    };
@endphp

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="{{ route('admin.recitations.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>
            Back to Recitations
        </a>

        <form action="{{ route('admin.recitations.destroy', $audioRecitation) }}" method="POST">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger"
                onclick="return confirm('Are you sure you want to delete this recitation?')">
                <i class="fas fa-trash me-1"></i>
                Delete
            </button>
        </form>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="admin-card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-user me-2"></i>User & Audio</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-muted small">Submitted By</div>
                        <div class="fw-bold">{{ $audioRecitation->user->name ?? 'Deleted user' }}</div>
                        <div class="text-muted">{{ $audioRecitation->user->email ?? 'No email' }}</div>
                    </div>

                    <div class="mb-3">
                        <div class="text-muted small">Selected Rule</div>
                        <span class="badge bg-primary">{{ ucfirst($audioRecitation->tajweed_rule) }}</span>
                    </div>

                    <div class="mb-3">
                        <div class="text-muted small">Filename</div>
                        <div class="text-break">{{ $audioRecitation->original_filename ?? 'N/A' }}</div>
                    </div>

                    <div class="mb-3">
                        <div class="text-muted small">Duration</div>
                        <div>{{ $audioRecitation->duration_seconds ? $audioRecitation->duration_seconds . 's' : 'N/A' }}</div>
                    </div>

                    <div class="mb-3">
                        <div class="text-muted small">Submitted</div>
                        <div>{{ $audioRecitation->created_at->format('d M Y, h:i A') }}</div>
                        <div class="text-muted small">{{ $audioRecitation->created_at->diffForHumans() }}</div>
                    </div>

                    <audio controls class="w-100 mt-2">
                        <source src="{{ route('tajweed.play-audio', $audioRecitation) }}">
                    </audio>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="admin-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>ML Analysis</h5>
                    @if($analysis)
                        <span class="badge-status {{ $statusClass }}">{{ $processingLabel }}</span>
                    @endif
                </div>
                <div class="card-body">
                    @if($analysis)
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-muted small">Correctness</div>
                                    <div class="h5 mb-0">
                                        {{ $correctnessLabel }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-muted small">Rule Model Confidence</div>
                                    <div class="h5 mb-0">{{ is_null($confidencePercent) ? 'N/A' : $confidencePercent . '%' }}</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-muted small">Analysis ID</div>
                                    <div class="h5 mb-0">#{{ $analysis->id }}</div>
                                </div>
                            </div>
                        </div>

                        <h6 class="fw-bold">Transcript</h6>
                        <div class="border rounded p-3 bg-light mb-4 text-break" dir="rtl" lang="ar" style="font-size: 1.35rem; line-height: 2;">
                            {{ $analysis->transcribed_text ?: 'No transcript available.' }}
                        </div>

                        <h6 class="fw-bold">Feedback Message</h6>
                        <div class="border rounded p-3 mb-4">
                            {{ $analysis->feedback_message ?: 'No feedback message available.' }}
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <h6 class="fw-bold">Detected Errors</h6>
                                <pre class="border rounded p-3 bg-light small mb-0">{{ json_encode($analysis->detected_errors ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                            <div class="col-md-6">
                                <h6 class="fw-bold">Suggestions</h6>
                                <pre class="border rounded p-3 bg-light small mb-0">{{ json_encode($analysis->suggestions ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                        </div>
                    @else
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-circle-info fa-2x mb-3 d-block"></i>
                            No analysis result exists for this recording.
                        </div>
                    @endif
                </div>
            </div>

            @if($analysis?->correction_submitted_at)
                <div class="admin-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>User Correction</h5>
                        <span class="badge-status {{ $correctionStatusClass }}">
                            {{ ucfirst($analysis->correction_review_status ?? 'pending') }}
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="text-muted small">Prediction Feedback</div>
                                <div class="fw-bold">{{ ucfirst($analysis->prediction_feedback ?? 'N/A') }}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small">Transcript Feedback</div>
                                <div class="fw-bold">{{ ucfirst($analysis->transcription_feedback ?? 'N/A') }}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small">Corrected Rule</div>
                                <div class="fw-bold">{{ ucfirst($analysis->corrected_rule ?? 'N/A') }}</div>
                            </div>
                        </div>

                        <h6 class="fw-bold">Corrected Transcript</h6>
                        <div class="border rounded p-3 bg-light mb-4 text-break" dir="rtl" lang="ar" style="font-size: 1.35rem; line-height: 2;">
                            {{ $analysis->corrected_transcription ?: 'No corrected transcript provided.' }}
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="text-muted small">User Note</div>
                                <div>{{ $analysis->correction_note ?: 'No note.' }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small">Submitted</div>
                                <div>{{ $analysis->correction_submitted_at->format('d M Y, h:i A') }}</div>
                                <div class="text-muted small">
                                    By {{ $analysis->correctionSubmitter->name ?? 'Unknown user' }}
                                </div>
                            </div>
                        </div>

                        <hr>

                        <form method="POST" action="{{ route('admin.corrections.update', $analysis) }}" class="row g-3">
                            @csrf
                            @method('PATCH')
                            <div class="col-md-4">
                                <label class="form-label">Review Status</label>
                                <select name="correction_review_status" class="form-select">
                                    @foreach(['pending' => 'Pending', 'reviewed' => 'Reviewed', 'used' => 'Used for Dataset', 'rejected' => 'Rejected'] as $value => $label)
                                        <option value="{{ $value }}" @selected($analysis->correction_review_status === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Admin Note</label>
                                <input name="correction_admin_note" class="form-control" value="{{ $analysis->correction_admin_note }}"
                                    placeholder="Optional review note">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>
                                    Save Correction Review
                                </button>
                                <a href="{{ route('admin.corrections.index', ['search' => $audioRecitation->original_filename]) }}" class="btn btn-outline-secondary">
                                    Open in Correction Queue
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
