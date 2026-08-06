@extends('layouts.admin')

@section('title', 'Correction Review')
@section('page-title', 'Correction Review')
@section('page-subtitle', 'Review user feedback and corrected transcripts for model improvement')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        @foreach(['pending' => 'Pending', 'reviewed' => 'Reviewed', 'used' => 'Used', 'rejected' => 'Rejected'] as $status => $label)
            <div class="col-md-3">
                <div class="admin-card stats-card">
                    <div class="stats-number">{{ $statusCounts[$status] ?? 0 }}</div>
                    <div class="stats-label">{{ $label }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="admin-card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-filter me-2"></i>
                Filters
            </h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.corrections.index') }}" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Search user, filename, transcript, note..."
                        value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        @foreach(['pending' => 'Pending', 'reviewed' => 'Reviewed', 'used' => 'Used', 'rejected' => 'Rejected'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="rule" class="form-select">
                        <option value="">All Rules</option>
                        @foreach(['ikhfa' => 'Ikhfa', 'izhar' => 'Izhar', 'other' => 'Other'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('rule') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i>
                        Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="admin-table">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Submitted</th>
                    <th>User</th>
                    <th>Audio</th>
                    <th>Original Result</th>
                    <th>User Correction</th>
                    <th>Admin Review</th>
                </tr>
            </thead>
            <tbody>
                @forelse($corrections as $correction)
                    @php
                        $audio = $correction->audioRecitation;
                        $statusClass = match($correction->correction_review_status) {
                            'used' => 'completed',
                            'reviewed' => 'processing',
                            'rejected' => 'pending',
                            default => 'pending',
                        };
                    @endphp
                    <tr>
                        <td>
                            <div>{{ optional($correction->correction_submitted_at)->format('d M Y') }}</div>
                            <small class="text-muted">{{ optional($correction->correction_submitted_at)->format('h:i A') }}</small>
                        </td>
                        <td>
                            <strong>{{ optional($audio?->user)->name ?? 'Unknown User' }}</strong>
                            <div class="text-muted small">{{ optional($audio?->user)->email }}</div>
                            <div class="text-muted small">{{ $audio?->original_filename ?? 'No filename' }}</div>
                        </td>
                        <td style="min-width: 230px;">
                            @if($audio)
                                <audio controls preload="none" class="w-100">
                                    <source src="{{ route('tajweed.play-audio', $audio) }}">
                                </audio>
                                <a href="{{ route('admin.recitations.show', $audio) }}" class="btn btn-sm btn-outline-secondary mt-2">
                                    <i class="fas fa-eye me-1"></i>
                                    Details
                                </a>
                            @else
                                <span class="text-muted small">Audio unavailable</span>
                            @endif
                        </td>
                        <td>
                            <div>
                                <span class="badge bg-primary">{{ ucfirst($audio?->tajweed_rule ?? 'unknown') }}</span>
                                @php
                                    $outcomeKey = $correction->displayOutcomeKey();
                                    $correctnessBadge = match ($outcomeKey) {
                                        'correct' => ['bg-success', 'Correct'],
                                        'incorrect' => ['bg-danger', 'Needs Practice'],
                                        'uncertain' => ['bg-secondary', 'Not Enough Evidence'],
                                        'analysis_failed' => ['bg-danger', 'Analysis Failed'],
                                        default => ['bg-secondary', 'Unavailable'],
                                    };
                                @endphp
                                <span class="badge {{ $correctnessBadge[0] }}">
                                    {{ $correctnessBadge[1] }}
                                </span>
                            </div>
                            <div class="small text-muted mt-1">
                                Rule model confidence:
                                {{ is_null($correction->confidence_score) ? 'N/A' : round($correction->confidence_score) . '%' }}
                            </div>
                            @if($correction->transcribed_text)
                                <div class="small mt-2" dir="rtl" lang="ar">{{ \Illuminate\Support\Str::limit($correction->transcribed_text, 120) }}</div>
                            @endif
                        </td>
                        <td style="min-width: 260px;">
                            <div class="mb-1">
                                Prediction:
                                <strong>{{ ucfirst($correction->prediction_feedback ?? 'N/A') }}</strong>
                            </div>
                            <div class="mb-1">
                                Transcript:
                                <strong>{{ ucfirst($correction->transcription_feedback ?? 'N/A') }}</strong>
                            </div>
                            @if($correction->corrected_rule)
                                <div class="mb-1">
                                    Correct rule:
                                    <span class="badge bg-info">{{ ucfirst($correction->corrected_rule) }}</span>
                                </div>
                            @endif
                            @if($correction->corrected_transcription)
                                <div class="border rounded p-2 bg-light mt-2" dir="rtl" lang="ar">
                                    {{ $correction->corrected_transcription }}
                                </div>
                            @endif
                            @if($correction->correction_note)
                                <div class="small text-muted mt-2">{{ $correction->correction_note }}</div>
                            @endif
                        </td>
                        <td style="min-width: 260px;">
                            <span class="badge-status {{ $statusClass }}">
                                {{ ucfirst($correction->correction_review_status ?? 'pending') }}
                            </span>

                            <form method="POST" action="{{ route('admin.corrections.update', $correction) }}" class="mt-3">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="{{ request('status') }}">
                                <input type="hidden" name="rule" value="{{ request('rule') }}">
                                <input type="hidden" name="search" value="{{ request('search') }}">

                                <select name="correction_review_status" class="form-select form-select-sm mb-2">
                                    @foreach(['pending' => 'Pending', 'reviewed' => 'Reviewed', 'used' => 'Used for Dataset', 'rejected' => 'Rejected'] as $value => $label)
                                        <option value="{{ $value }}" @selected($correction->correction_review_status === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <label class="form-label small mb-1" for="expert_target_label_{{ $correction->id }}">
                                    Expert pronunciation label
                                </label>
                                <select id="expert_target_label_{{ $correction->id }}" name="expert_target_label" class="form-select form-select-sm mb-2">
                                    <option value="">Select before using for dataset</option>
                                    @foreach([
                                        'ikhfa_correct' => 'Ikhfa - correct',
                                        'ikhfa_weak_ghunnah' => 'Ikhfa - weak/short ghunnah',
                                        'izhar_correct' => 'Izhar - correct',
                                        'izhar_with_ghunnah' => 'Izhar - unwanted ghunnah',
                                        'other' => 'Other / unusable target',
                                    ] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('expert_target_label', $correction->expert_target_label) === $value)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text mb-2">Required when status is "Used for Dataset".</div>
                                <textarea name="correction_admin_note" class="form-control form-control-sm mb-2" rows="2"
                                    placeholder="Admin note">{{ $correction->correction_admin_note }}</textarea>
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="fas fa-save me-1"></i>
                                    Save Review
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="fas fa-inbox fa-2x mb-3 d-block"></i>
                            No corrections submitted yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-center mt-4">
        {{ $corrections->links() }}
    </div>
</div>
@endsection
