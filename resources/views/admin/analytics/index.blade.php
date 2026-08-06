@extends('layouts.admin')

@section('page-title', 'Analytics')
@section('page-subtitle', 'Usage and recitation trends')

@section('content')
@php
    // The controller's legacy correctness-only aggregate cannot distinguish a
    // failed row that still carries correctness="uncertain". Build this small
    // display aggregate from policy-safe states instead.
    $completedUncertain = \App\Models\AnalysisResult::query()
        ->where('processing_status', 'completed')
        ->where('correctness', 'uncertain')
        ->get([
            'id',
            'processing_status',
            'correctness',
            'classification_status',
            'classification_method',
            'model_predictions',
            'detected_errors',
        ]);
    $validUncertainCount = $completedUncertain
        ->filter(fn ($analysis) => $analysis->displayOutcomeKey() === 'uncertain')
        ->count();
    $legacyUnavailableCount = $completedUncertain->count() - $validUncertainCount;

    $analysisDisplayRows = collect([
        ['label' => 'Correct', 'count' => \App\Models\AnalysisResult::query()
            ->where('processing_status', 'completed')->where('correctness', 'correct')->count()],
        ['label' => 'Needs Practice', 'count' => \App\Models\AnalysisResult::query()
            ->where('processing_status', 'completed')->where('correctness', 'incorrect')->count()],
        ['label' => 'Not Enough Evidence', 'count' => $validUncertainCount],
        ['label' => 'Analysis Failed', 'count' => \App\Models\AnalysisResult::query()
            ->where('processing_status', 'failed')->count()],
        ['label' => 'Unavailable', 'count' => $legacyUnavailableCount + \App\Models\AnalysisResult::query()
            ->where('processing_status', 'completed')
            ->where(function ($query) {
                $query->whereNull('correctness')
                    ->orWhereNotIn('correctness', ['correct', 'incorrect', 'uncertain']);
            })
            ->count()],
    ])->filter(fn (array $row) => $row['count'] > 0);
@endphp
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
                @forelse($analysisDisplayRows as $row)
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>{{ $row['label'] }}</span>
                        <strong>{{ $row['count'] }}</strong>
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
