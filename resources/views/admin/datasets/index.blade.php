@extends('layouts.admin')

@section('page-title', 'Datasets & Model')
@section('page-subtitle', 'Upload training audio and retrain the Tajweed classifier')

@section('content')
<div class="row g-3 mb-4">
    @foreach($classes as $label => $data)
        <div class="col-md-4">
            <div class="admin-card stats-card h-100">
                <div class="stats-icon {{ $label === 'ikhfa' ? 'users' : ($label === 'izhar' ? 'recitations' : 'analytics') }}">
                    <i class="fas fa-folder-open"></i>
                </div>
                <div class="stats-number">{{ $data['count'] }}</div>
                <div class="stats-label">{{ ucfirst($label) }} Files</div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="admin-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-upload me-2"></i>Upload Dataset Files</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.datasets.upload') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label for="tajweed_rule" class="form-label">Dataset Class</label>
                        <select id="tajweed_rule" name="tajweed_rule" class="form-select @error('tajweed_rule') is-invalid @enderror" required>
                            <option value="ikhfa">Ikhfa</option>
                            <option value="izhar">Izhar</option>
                            <option value="other">Other</option>
                        </select>
                        @error('tajweed_rule')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="dataset_files" class="form-label">Audio Files</label>
                        <input id="dataset_files" name="dataset_files[]" type="file" class="form-control @error('dataset_files') is-invalid @enderror @error('dataset_files.*') is-invalid @enderror" accept="audio/*" multiple required>
                        <div class="form-text">Accepted audio files: wav, mp3, webm, m4a. Max 50 MB each.</div>
                        @error('dataset_files')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @error('dataset_files.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload me-2"></i>Upload Files
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="admin-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-brain me-2"></i>Model Training</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>Keras model</span>
                        <strong>{{ $modelInfo['keras_exists'] ? 'Available' : 'Missing' }}</strong>
                    </div>
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>H5 model</span>
                        <strong>{{ $modelInfo['h5_exists'] ? 'Available' : 'Missing' }}</strong>
                    </div>
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>Last trained</span>
                        <strong>{{ $modelInfo['updated_at'] ?? 'N/A' }}</strong>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.model.retrain') }}" onsubmit="return confirm('Retraining may take several minutes. Continue?');">
                    @csrf
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-rotate me-2"></i>Retrain Model
                    </button>
                </form>

                <a href="{{ route('admin.evaluation') }}" class="btn btn-outline-primary mt-3">
                    <i class="fas fa-chart-bar me-2"></i>View ML Evaluation
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
