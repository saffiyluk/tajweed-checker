@extends('layouts.admin')

@section('page-title', 'ML Evaluation')
@section('page-subtitle', 'Latest classifier metrics, confusion matrices, and dataset balance')

@php
    $formatPercent = fn ($value) => is_numeric($value) ? number_format($value * 100, 2) . '%' : 'N/A';
    $renderMatrixClass = function (array $matrix) {
        $max = 0;
        foreach ($matrix as $row) {
            foreach ($row as $cell) {
                $max = max($max, (int) $cell);
            }
        }

        return function (int $value) use ($max) {
            if ($max <= 0) {
                return 'background: rgba(37, 99, 235, 0.06);';
            }

            $opacity = 0.08 + (0.42 * ($value / $max));
            return 'background: rgba(37, 99, 235, ' . number_format($opacity, 3, '.', '') . ');';
        };
    };
@endphp

@section('content')
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="admin-card stats-card h-100">
            <div class="stats-icon analytics"><i class="fas fa-wave-square"></i></div>
            <div class="stats-number">{{ $summaryCards['dataset_size'] }}</div>
            <div class="stats-label">Dataset Files</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card stats-card h-100">
            <div class="stats-icon users"><i class="fas fa-layer-group"></i></div>
            <div class="stats-number">{{ $summaryCards['class_count'] }}</div>
            <div class="stats-label">Active Classes</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card stats-card h-100">
            <div class="stats-icon recitations"><i class="fas fa-sliders-h"></i></div>
            <div class="stats-number">{{ $formatPercent($summaryCards['feature_accuracy']) }}</div>
            <div class="stats-label">Feature Accuracy</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card stats-card h-100">
            <div class="stats-icon monitoring"><i class="fas fa-brain"></i></div>
            <div class="stats-number">{{ $formatPercent($summaryCards['cnn_accuracy']) }}</div>
            <div class="stats-label">CNN Accuracy</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    @foreach($classes as $label => $data)
        <div class="col-md-4">
            <div class="admin-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0 text-capitalize">{{ $label }}</h6>
                        <span class="badge bg-light text-dark">{{ $data['count'] }} files</span>
                    </div>
                    <div class="small text-muted">
                        Latest file:
                        <strong>{{ optional($data['latest']->first())->getFilename() ?? 'N/A' }}</strong>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-3">
    @foreach (['Feature Model' => $featureMetrics, 'CNN Model' => $cnnMetrics] as $title => $metrics)
        <div class="col-12">
            <div class="admin-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">{{ $title }}</h5>
                        <small class="text-muted">
                            @if($metrics)
                                Updated {{ $metrics['updated_at'] ?? 'N/A' }}
                            @else
                                No evaluation file found yet
                            @endif
                        </small>
                    </div>
                    @if($metrics)
                        <span class="badge bg-primary">{{ $formatPercent($metrics['accuracy'] ?? null) }}</span>
                    @endif
                </div>
                <div class="card-body">
                    @if(!$metrics)
                        <p class="text-muted mb-0">Run model training to generate evaluation metrics for this model.</p>
                    @else
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-muted small">Test accuracy</div>
                                    <div class="fs-4 fw-semibold">{{ $formatPercent($metrics['accuracy'] ?? null) }}</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-muted small">Validation peak</div>
                                    <div class="fs-4 fw-semibold">{{ $formatPercent($metrics['best_val_accuracy'] ?? null) }}</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-muted small">Train split</div>
                                    <div class="fs-4 fw-semibold">{{ $metrics['train_size'] ?? 'N/A' }}</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-muted small">Test split</div>
                                    <div class="fs-4 fw-semibold">{{ $metrics['test_size'] ?? 'N/A' }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-lg-6">
                                <h6 class="mb-3">Per-Class Metrics</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle">
                                        <thead>
                                            <tr>
                                                <th>Class</th>
                                                <th>Precision</th>
                                                <th>Recall</th>
                                                <th>F1</th>
                                                <th>Support</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach(($metrics['classes'] ?? []) as $class)
                                                @php $row = $metrics['classification_report'][$class] ?? null; @endphp
                                                <tr>
                                                    <td class="text-capitalize">{{ $class }}</td>
                                                    <td>{{ $row ? number_format($row['precision'], 2) : 'N/A' }}</td>
                                                    <td>{{ $row ? number_format($row['recall'], 2) : 'N/A' }}</td>
                                                    <td>{{ $row ? number_format($row['f1-score'], 2) : 'N/A' }}</td>
                                                    <td>{{ $row['support'] ?? 'N/A' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <h6 class="mb-3">Confusion Matrix</h6>
                                @php
                                    $classesForMatrix = $metrics['classes'] ?? [];
                                    $matrix = $metrics['confusion_matrix'] ?? [];
                                    $cellStyle = $renderMatrixClass($matrix);
                                @endphp
                                <div class="table-responsive">
                                    <table class="table table-sm text-center align-middle">
                                        <thead>
                                            <tr>
                                                <th>Actual \ Predicted</th>
                                                @foreach($classesForMatrix as $class)
                                                    <th class="text-capitalize">{{ $class }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($matrix as $rowIndex => $row)
                                                <tr>
                                                    <th class="text-capitalize">{{ $classesForMatrix[$rowIndex] ?? 'Unknown' }}</th>
                                                    @foreach($row as $value)
                                                        <td style="{{ $cellStyle((int) $value) }}">{{ $value }}</td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection
