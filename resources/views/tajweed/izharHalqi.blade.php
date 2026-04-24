@extends('layouts.app')

@section('title', 'Tajweed - Izhar Halqi')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <!-- Header Section -->
            <div class="page-header mb-5">
                <h1 class="mb-3">
                    <i class="fas fa-volume-up me-3"></i>Izhar Halqi
                </h1>
                <p class="lead">Master the Art of Clear Throat Pronunciation in Tajweed</p>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Rule Insight:</strong> Izhar Halqi occurs when a <strong>noon sakinah</strong> or <strong>tanween</strong> 
                    is followed by one of the <strong>6 throat letters</strong>
                </div>
            </div>

            <!-- Throat Letters Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-throat me-2"></i>6 Throat Letters (حروف الحلق)
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">The letters that trigger the Izhar Halqi rule</p>

                    <!-- Throat Diagram Info -->
                    <div class="bg-light p-4 rounded mb-4">
                        <h6 class="fw-bold mb-3">Throat Articulation Points</h6>
                        <div class="row text-center">
                            <div class="col-md-4 mb-3">
                                <div class="throat-position deep">
                                    <h5>Deep Throat</h5>
                                    <p class="mb-1">خ (Khaa)</p>
                                    <p class="mb-0">غ (Ghayn)</p>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="throat-position middle">
                                    <h5>Middle Throat</h5>
                                    <p class="mb-1">ع (Ayn)</p>
                                    <p class="mb-0">ح (Haa)</p>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="throat-position upper">
                                    <h5>Upper Throat</h5>
                                    <p class="mb-1">ه (Haa)</p>
                                    <p class="mb-0">ء (Hamzah)</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Letters Grid -->
                    <div class="letters-container">
                        @foreach([
                            ['letter' => 'ء', 'name' => 'Hamzah', 'position' => 'Beginning of throat', 'example' => 'مَنْ آمَنَ', 'level' => 'upper'],
                            ['letter' => 'ه', 'name' => 'Haa', 'position' => 'Beginning of throat', 'example' => 'مِنْ هَادٍ', 'level' => 'upper'],
                            ['letter' => 'ع', 'name' => 'Ayn', 'position' => 'Middle of throat', 'example' => 'مِنْ عِلْمٍ', 'level' => 'middle'],
                            ['letter' => 'ح', 'name' => 'Haa', 'position' => 'Middle of throat', 'example' => 'مِنْ حَيْثُ', 'level' => 'middle'],
                            ['letter' => 'غ', 'name' => 'Ghayn', 'position' => 'Deep throat', 'example' => 'مَنْ غَفَرَ', 'level' => 'deep'],
                            ['letter' => 'خ', 'name' => 'Khaa', 'position' => 'Deep throat', 'example' => 'مِنْ خَيْرٍ', 'level' => 'deep'],
                        ] as $letter)
                            <div class="letter-card">
                                <div class="arabic">{{ $letter['letter'] }}</div>
                                <div class="name">{{ $letter['name'] }}</div>
                                <div class="makhraj">
                                    <span class="position-badge {{ $letter['level'] }}">
                                        {{ $letter['position'] }}
                                    </span>
                                </div>
                                <div class="example arabic-text">{{ $letter['example'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Examples Section -->
            <div class="row mb-4">
                <!-- Noon Sakinah Examples -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <i class="fas fa-check-circle me-2"></i>Examples with Noon Sakinah
                        </div>
                        <div class="card-body">
                            <div class="example-list">
                                @foreach([
                                    ['arabic' => 'مَنْ آمَنَ', 'translation' => 'Whoever believes', 'pronunciation' => 'Man aamana'],
                                    ['arabic' => 'مِنْهُمْ', 'translation' => 'From them', 'pronunciation' => 'Min hum'],
                                    ['arabic' => 'أَنْعَمْتَ', 'translation' => 'You bestowed favor', 'pronunciation' => 'An\'amta'],
                                    ['arabic' => 'مِنْ حَيْثُ', 'translation' => 'From where', 'pronunciation' => 'Min haithu'],
                                ] as $example)
                                    <div class="example-item">
                                        <div class="arabic-text fs-5 mb-1">{{ $example['arabic'] }}</div>
                                        <div class="d-flex justify-content-between">
                                            <small class="text-muted">{{ $example['pronunciation'] }}</small>
                                            <small>{{ $example['translation'] }}</small>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tanween Examples -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <i class="fas fa-check-circle me-2"></i>Examples with Tanween
                        </div>
                        <div class="card-body">
                            <div class="example-list">
                                @foreach([
                                    ['arabic' => 'عَلِيْمٌ حَكِيْمٌ', 'translation' => 'All-Knowing, All-Wise', 'pronunciation' => 'Aleemun Hakeem'],
                                    ['arabic' => 'كِتَابٌ عَزِيزٌ', 'translation' => 'A mighty Book', 'pronunciation' => 'Kitabun Azeez'],
                                    ['arabic' => 'نَذِيْرٌ هُدَى', 'translation' => 'A warner of guidance', 'pronunciation' => 'Natheerun Huda'],
                                    ['arabic' => 'رَحْمَةٌ غَفُوْرٌ', 'translation' => 'Merciful, Forgiving', 'pronunciation' => 'Rahmatun Ghafoor'],
                                ] as $example)
                                    <div class="example-item">
                                        <div class="arabic-text fs-5 mb-1">{{ $example['arabic'] }}</div>
                                        <div class="d-flex justify-content-between">
                                            <small class="text-muted">{{ $example['pronunciation'] }}</small>
                                            <small>{{ $example['translation'] }}</small>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pronunciation Guide & Audio Examples -->
            <div class="row mb-4">
                <!-- How to Pronounce -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <i class="fas fa-microphone-alt me-2"></i>How to Pronounce Izhar
                        </div>
                        <div class="card-body">
                            <div class="step">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <h5>Clear Pronunciation</h5>
                                    <p>Pronounce the noon sound clearly without any nasalization (ghunnah).</p>
                                </div>
                            </div>
                            <div class="step">
                                <div class="step-number">2</div>
                                <div class="step-content">
                                    <h5>Throat Articulation</h5>
                                    <p>Focus on the correct throat position for each letter (upper, middle, or deep).</p>
                                </div>
                            </div>
                            <div class="step mb-0">
                                <div class="step-number">3</div>
                                <div class="step-content">
                                    <h5>Distinct Separation</h5>
                                    <p>Maintain a clear separation between the noon sound and the throat letter.</p>
                                </div>
                            </div>
                            
                            <div class="mt-4 pt-3 border-top">
                                <h6><i class="fas fa-clock me-2"></i>Important Note</h6>
                                <p class="mb-0">Izhar requires <strong>no ghunnah</strong> - pronounce the noon clearly without nasal sound.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Audio Examples -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <i class="fas fa-file-audio me-2"></i>Audio Examples
                        </div>
                        <div class="card-body">
                            @foreach([
                                ['arabic' => 'مَنْ آمَنَ', 'translation' => 'Whoever believes', 'audio' => '#'],
                                ['arabic' => 'عَلِيْمٌ حَكِيْمٌ', 'translation' => 'All-Knowing, All-Wise', 'audio' => '#'],
                                ['arabic' => 'مِنْ خَيْرٍ', 'translation' => 'From good', 'audio' => '#'],
                                ['arabic' => 'كِتَابٌ عَزِيزٌ', 'translation' => 'A mighty Book', 'audio' => '#'],
                            ] as $example)
                                <div class="audio-example">
                                    <div class="content">
                                        <div class="arabic-text">{{ $example['arabic'] }}</div>
                                        <div class="translation">{{ $example['translation'] }}</div>
                                    </div>
                                    <audio id="audio-izhar-{{ $loop->index }}" style="display: none;">
                                        <source src="{{ $example['audio'] }}" type="audio/mpeg">
                                    </audio>
                                    <button class="btn-play" onclick="playAudio('audio-izhar-{{ $loop->index }}', this)">
                                        <i class="fas fa-play"></i>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Comparison Table -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-balance-scale me-2"></i>Izhar vs Other Rules
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Rule</th>
                                    <th>Letters</th>
                                    <th>Sound Quality</th>
                                    <th>Duration</th>
                                    <th>Example</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr style="background: rgba(23, 162, 184, 0.05);">
                                    <td><strong>Izhar Halqi</strong></td>
                                    <td><span class="badge bg-info">6 letters</span></td>
                                    <td>Clear, no ghunnah</td>
                                    <td>Normal</td>
                                    <td class="arabic-text">مَنْ آمَنَ</td>
                                </tr>
                                <tr>
                                    <td>Ikhfa</td>
                                    <td><span class="badge bg-primary">15 letters</span></td>
                                    <td>Light ghunnah</td>
                                    <td>2 harakah</td>
                                    <td class="arabic-text">مَنْ تَابَ</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Upload / Record Section -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-file-upload me-2"></i>Practice & Record
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    
                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <!-- Tabs -->
                    <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="upload-tab-izhar" data-bs-toggle="tab" data-bs-target="#upload-izhar" type="button">
                                <i class="fas fa-cloud-upload-alt me-2"></i>Upload File
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="record-tab-izhar" data-bs-toggle="tab" data-bs-target="#record-izhar" type="button">
                                <i class="fas fa-microphone me-2"></i>Record Audio
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- Upload Tab -->
                        <div class="tab-pane fade show active" id="upload-izhar" role="tabpanel">
                            <form id="uploadFormIzhar" method="POST" action="{{ route('tajweed.upload') }}" enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="tajweed_rule" value="izhar">
                                
                                <div class="file-upload mb-4">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <h5 class="mb-2">Drag & drop or click to upload</h5>
                                    <p class="text-muted mb-3">Supports MP3, WAV, WEBM files</p>
                                    <input type="file" name="audio" accept="audio/*" class="form-control" required id="audioFileIzhar">
                                    <div id="fileNameIzhar" class="mt-2 text-success fw-semibold"></div>
                                    <small class="text-muted d-block mt-2">Maximum file size: 10MB</small>
                                </div>
                                
                                <button type="submit" class="btn btn-primary" id="submitUploadIzhar">
                                    <i class="fas fa-paper-plane me-2"></i>Submit for Analysis
                                </button>
                            </form>
                        </div>

                        <!-- Record Tab -->
                        <div class="tab-pane fade" id="record-izhar" role="tabpanel">
                            <div class="recording-section">
                                <div class="text-center mb-4">
                                    <div id="timerIzhar" class="timer">00:00</div>
                                    <p id="statusTextIzhar" class="text-muted">Ready to record</p>
                                </div>
                                
                                <!-- Recording Visualization -->
                                <div id="visualizerIzhar" class="visualizer-box mb-4" style="display: none;">
                                    <canvas id="waveformIzhar" width="400" height="80"></canvas>
                                </div>
                                
                                <div class="d-flex justify-content-center gap-3 mb-4">
                                    <button class="btn btn-success btn-record" id="startBtnIzhar">
                                        <i class="fas fa-microphone me-2"></i>Start Recording
                                    </button>
                                    <button class="btn btn-danger btn-record" id="stopBtnIzhar" disabled>
                                        <i class="fas fa-stop me-2"></i>Stop
                                    </button>
                                    <button class="btn btn-warning btn-record" id="pauseBtnIzhar" disabled style="display: none;">
                                        <i class="fas fa-pause me-2"></i>Pause
                                    </button>
                                </div>
                                
                                <!-- Recording Preview -->
                                <div id="recordingPreviewIzhar" class="mb-4" style="display: none;">
                                    <h6>Recording Preview:</h6>
                                    <audio id="recordedAudioIzhar" controls class="w-100"></audio>
                                    <button id="submitRecordingIzhar" class="btn btn-primary mt-2">
                                        <i class="fas fa-paper-plane me-2"></i>Submit Recording
                                    </button>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Focus on clear throat articulation without nasal sounds for Izhar.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rules Summary -->
            <div class="rules-summary mt-4">
                <h4 class="text-info mb-3">
                    <i class="fas fa-graduation-cap me-2"></i>Key Rules Summary
                </h4>
                <div class="row">
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded">
                            <h6 class="text-success mb-3">What to Do:</h6>
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Pronounce noon sound clearly
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Use correct throat position for each letter
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Maintain distinct separation between sounds
                                </li>
                                <li>
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Articulate from the appropriate throat level
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded">
                            <h6 class="text-danger mb-3">What to Avoid:</h6>
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2">
                                    <i class="fas fa-times-circle text-danger me-2"></i>
                                    Adding nasal sound (ghunnah)
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-times-circle text-danger me-2"></i>
                                    Merging the sounds together
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-times-circle text-danger me-2"></i>
                                    Hiding the noon sound
                                </li>
                                <li>
                                    <i class="fas fa-times-circle text-danger me-2"></i>
                                    Using wrong throat position
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <div class="d-flex justify-content-between mt-5 pt-4 border-top">
                <a href="{{ route('tajweed.ikhfa-haqiqi') }}" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i> Previous: Ikhfa Haqiqi
                </a>
                <div>
                    <a href="{{ route('home') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-home me-2"></i> Home
                    </a>
                </div>
                <a href="{{ route('tajweed.history') }}" class="btn btn-outline-primary">
                    View Recitations <i class="fas fa-arrow-right ms-2"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
:root {
    --izhar-color: #17a2b8;  /* Info color for Izhar */
    --deep-throat: #6f42c1;  /* Purple for deep throat */
    --middle-throat: #20c997; /* Teal for middle throat */
    --upper-throat: #0d6efd;  /* Blue for upper throat */
}

/* Throat Position Styling */
.throat-position {
    padding: 1.5rem;
    border-radius: var(--radius);
    margin-bottom: 1rem;
    color: white;
    text-align: center;
}

.throat-position.deep {
    background: linear-gradient(135deg, var(--deep-throat), #5a32a3);
}

.throat-position.middle {
    background: linear-gradient(135deg, var(--middle-throat), #17a589);
}

.throat-position.upper {
    background: linear-gradient(135deg, var(--upper-throat), #0b5ed7);
}

.throat-position h5 {
    font-size: 1.1rem;
    margin-bottom: 0.75rem;
    font-weight: 600;
}

.throat-position p {
    margin: 0;
    font-size: 0.9rem;
    opacity: 0.9;
}

/* Position Badges in Letter Cards */
.position-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.position-badge.deep {
    background: rgba(111, 66, 193, 0.1);
    color: var(--deep-throat);
    border: 1px solid rgba(111, 66, 193, 0.2);
}

.position-badge.middle {
    background: rgba(32, 201, 151, 0.1);
    color: var(--middle-throat);
    border: 1px solid rgba(32, 201, 151, 0.2);
}

.position-badge.upper {
    background: rgba(13, 110, 253, 0.1);
    color: var(--upper-throat);
    border: 1px solid rgba(13, 110, 253, 0.2);
}

/* Izhar-specific Colors */
.alert-info {
    background: rgba(23, 162, 184, 0.1);
    border: 1px solid rgba(23, 162, 184, 0.2);
    color: #0c5460;
}

.card-header {
    background: var(--izhar-color);
    color: white;
}

.btn-primary {
    background: var(--izhar-color);
    border-color: var(--izhar-color);
}

.btn-primary:hover {
    background: #138496;
    border-color: #117a8b;
}

.btn-outline-primary {
    color: var(--izhar-color);
    border-color: var(--izhar-color);
}

.btn-outline-primary:hover {
    background: var(--izhar-color);
    border-color: var(--izhar-color);
}

/* Example List Styling */
.example-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.example-item {
    padding: 1rem;
    border-radius: var(--radius);
    background: var(--light);
    border-left: 4px solid var(--izhar-color);
    transition: all 0.2s ease;
}

.example-item:hover {
    background: white;
    box-shadow: var(--shadow);
    transform: translateX(5px);
}

.example-item .arabic-text {
    font-weight: 600;
    color: var(--dark);
}

/* Rules Summary Styling */
.rules-summary {
    margin-top: 2rem;
}

.rules-summary .bg-light {
    background: #f8f9fa !important;
    border: 1px solid #e9ecef;
}

.rules-summary h6 {
    font-weight: 600;
    font-size: 1.1rem;
}

.rules-summary li {
    padding: 0.5rem 0;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}

.rules-summary li:last-child {
    border-bottom: none;
}

/* Audio Player Styles */
.audio-example {
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 1rem;
    margin-bottom: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.2s ease;
}

.audio-example:hover {
    border-color: var(--izhar-color);
    background: rgba(23, 162, 184, 0.05);
}

.audio-example .content {
    flex: 1;
}

.audio-example .arabic-text {
    font-size: 1.3rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.audio-example .translation {
    color: var(--gray);
    font-size: 0.9rem;
}

.btn-play {
    background: var(--izhar-color);
    color: white;
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.btn-play:hover {
    background: #138496;
    transform: scale(1.1);
}

/* File Upload */
.file-upload {
    border: 2px dashed var(--border);
    border-radius: var(--radius);
    padding: 3rem 2rem;
    text-align: center;
    background: white;
    cursor: pointer;
    transition: all 0.2s ease;
}

.file-upload:hover {
    border-color: var(--izhar-color);
    background: rgba(23, 162, 184, 0.05);
}

.file-upload i {
    font-size: 3rem;
    color: var(--gray);
    margin-bottom: 1rem;
}

/* Recording Section */
.recording-section .timer {
    font-family: monospace;
    font-size: 2rem;
    font-weight: 600;
    color: var(--izhar-color);
    text-align: center;
    margin: 1rem 0;
}

.btn-record {
    padding: 0.75rem 2rem;
    font-weight: 500;
}

/* Visualization */
.visualizer-box {
    background: #f8f9fa;
    border-radius: var(--radius);
    padding: 1rem;
    border: 1px solid var(--border);
}

#waveformIzhar {
    width: 100%;
    height: 80px;
}

/* Badge Styling in Table */
.badge.bg-info {
    background-color: var(--izhar-color) !important;
}

/* Card Header Icon Colors */
.card-header i {
    color: white;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .letters-container {
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    }
    
    .throat-position {
        padding: 1rem;
    }
    
    .throat-position h5 {
        font-size: 1rem;
    }
}

@media (max-width: 576px) {
    .letters-container {
        grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
    }
    
    .throat-position {
        margin-bottom: 0.5rem;
    }
    
    .rules-summary .col-md-6 {
        margin-bottom: 1rem;
    }
}
</style>

@push('scripts')
<script>
    // Audio playback function for examples
    function playAudio(audioId, button) {
        const audio = document.getElementById(audioId);
        const icon = button.querySelector('i');
        
        if (audio.paused) {
            // Stop all other audio
            document.querySelectorAll('audio').forEach(a => {
                if (a.id !== audioId) {
                    a.pause();
                    a.currentTime = 0;
                }
            });
            
            // Update all buttons
            document.querySelectorAll('.btn-play i').forEach(i => {
                i.className = 'fas fa-play';
            });
            
            // Play this audio
            audio.play();
            icon.className = 'fas fa-pause';
            
            audio.onended = function() {
                icon.className = 'fas fa-play';
            };
        } else {
            audio.pause();
            icon.className = 'fas fa-play';
        }
    }
    
    // File upload preview for Izhar
    document.getElementById('audioFileIzhar')?.addEventListener('change', function(e) {
        const fileName = document.getElementById('fileNameIzhar');
        if (this.files.length > 0) {
            fileName.textContent = `Selected: ${this.files[0].name}`;
        } else {
            fileName.textContent = '';
        }
    });
    
    // Recording functionality for Izhar
    document.addEventListener('DOMContentLoaded', function() {
        const startBtn = document.getElementById('startBtnIzhar');
        const stopBtn = document.getElementById('stopBtnIzhar');
        const pauseBtn = document.getElementById('pauseBtnIzhar');
        const timer = document.getElementById('timerIzhar');
        const statusText = document.getElementById('statusTextIzhar');
        const visualizer = document.getElementById('visualizerIzhar');
        const recordingPreview = document.getElementById('recordingPreviewIzhar');
        const recordedAudio = document.getElementById('recordedAudioIzhar');
        const submitRecording = document.getElementById('submitRecordingIzhar');
        const canvas = document.getElementById('waveformIzhar');
        const ctx = canvas?.getContext('2d');
        
        let mediaRecorder;
        let audioChunks = [];
        let audioStream;
        let seconds = 0;
        let timerInterval;
        let isRecording = false;
        let isPaused = false;
        let audioContext;
        let analyser;
        let dataArray;
        let animationId;
        
        // Initialize visualizer
        function initVisualizer() {
            if (!ctx || !canvas) return;
            
            ctx.fillStyle = '#f8f9fa';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
        }
        
        // Draw waveform visualization
        function drawWaveform() {
            if (!ctx || !canvas || !analyser) return;
            
            animationId = requestAnimationFrame(drawWaveform);
            
            analyser.getByteTimeDomainData(dataArray);
            
            ctx.fillStyle = '#f8f9fa';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            ctx.lineWidth = 2;
            ctx.strokeStyle = '#17a2b8'; // Izhar color
            ctx.beginPath();
            
            const sliceWidth = canvas.width * 1.0 / dataArray.length;
            let x = 0;
            
            for(let i = 0; i < dataArray.length; i++) {
                const v = dataArray[i] / 128.0;
                const y = v * canvas.height / 2;
                
                if(i === 0) {
                    ctx.moveTo(x, y);
                } else {
                    ctx.lineTo(x, y);
                }
                
                x += sliceWidth;
            }
            
            ctx.lineTo(canvas.width, canvas.height / 2);
            ctx.stroke();
        }
        
        // Start recording
        startBtn?.addEventListener('click', async function() {
            try {
                // Request microphone access
                audioStream = await navigator.mediaDevices.getUserMedia({ 
                    audio: {
                        echoCancellation: true,
                        noiseSuppression: true,
                        sampleRate: 44100
                    }
                });
                
                // Initialize audio context for visualization
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
                analyser = audioContext.createAnalyser();
                const source = audioContext.createMediaStreamSource(audioStream);
                source.connect(analyser);
                analyser.fftSize = 256;
                const bufferLength = analyser.frequencyBinCount;
                dataArray = new Uint8Array(bufferLength);
                
                // Initialize visualizer
                initVisualizer();
                visualizer.style.display = 'block';
                drawWaveform();
                
                // Create media recorder
                mediaRecorder = new MediaRecorder(audioStream);
                audioChunks = [];
                
                mediaRecorder.ondataavailable = (event) => {
                    if (event.data.size > 0) {
                        audioChunks.push(event.data);
                    }
                };
                
                mediaRecorder.onstop = () => {
                    // Create audio blob
                    const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                    const audioUrl = URL.createObjectURL(audioBlob);
                    
                    // Show preview
                    recordedAudio.src = audioUrl;
                    recordingPreview.style.display = 'block';
                    
                    // Stop visualization
                    cancelAnimationFrame(animationId);
                    visualizer.style.display = 'none';
                    
                    // Clean up audio context
                    if (audioContext) {
                        audioContext.close();
                    }
                };
                
                // Start recording
                mediaRecorder.start();
                isRecording = true;
                
                // Update UI
                startBtn.disabled = true;
                startBtn.classList.remove('btn-success');
                startBtn.classList.add('btn-secondary');
                stopBtn.disabled = false;
                pauseBtn.style.display = 'block';
                pauseBtn.disabled = false;
                
                statusText.textContent = 'Recording...';
                statusText.className = 'text-danger fw-bold';
                
                // Start timer
                seconds = 0;
                timerInterval = setInterval(() => {
                    seconds++;
                    const mins = Math.floor(seconds / 60);
                    const secs = seconds % 60;
                    timer.textContent = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
                }, 1000);
                
            } catch (error) {
                console.error('Error accessing microphone:', error);
                alert('Unable to access microphone. Please check your browser permissions and try again.');
            }
        });
        
        // Stop recording
        stopBtn?.addEventListener('click', function() {
            if (mediaRecorder && isRecording) {
                mediaRecorder.stop();
                audioStream?.getTracks().forEach(track => track.stop());
                
                isRecording = false;
                
                // Update UI
                startBtn.disabled = false;
                startBtn.classList.remove('btn-secondary');
                startBtn.classList.add('btn-success');
                startBtn.innerHTML = '<i class="fas fa-microphone me-2"></i>Start Recording';
                stopBtn.disabled = true;
                pauseBtn.style.display = 'none';
                pauseBtn.disabled = true;
                pauseBtn.innerHTML = '<i class="fas fa-pause me-2"></i>Pause';
                
                statusText.textContent = 'Recording complete';
                statusText.className = 'text-success fw-bold';
                
                // Stop timer
                clearInterval(timerInterval);
            }
        });
        
        // Pause/Resume recording
        pauseBtn?.addEventListener('click', function() {
            if (!mediaRecorder) return;
            
            if (!isPaused) {
                // Pause recording
                mediaRecorder.pause();
                isPaused = true;
                pauseBtn.innerHTML = '<i class="fas fa-play me-2"></i>Resume';
                statusText.textContent = 'Paused';
                statusText.className = 'text-warning fw-bold';
                
                // Pause timer
                clearInterval(timerInterval);
                
                // Pause visualization
                cancelAnimationFrame(animationId);
            } else {
                // Resume recording
                mediaRecorder.resume();
                isPaused = false;
                pauseBtn.innerHTML = '<i class="fas fa-pause me-2"></i>Pause';
                statusText.textContent = 'Recording...';
                statusText.className = 'text-danger fw-bold';
                
                // Resume timer
                timerInterval = setInterval(() => {
                    seconds++;
                    const mins = Math.floor(seconds / 60);
                    const secs = seconds % 60;
                    timer.textContent = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
                }, 1000);
                
                // Resume visualization
                drawWaveform();
            }
        });
        
        // Submit recording - FIXED for Izhar
        submitRecording?.addEventListener('click', function() {
            if (audioChunks.length === 0) {
                alert('No recording to submit. Please record first.');
                return;
            }
            
            // Create form data
            const formData = new FormData();
            const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
            
            // Convert blob to base64 for proper submission
            const reader = new FileReader();
            reader.readAsDataURL(audioBlob);
            
            reader.onloadend = function() {
                const base64Audio = reader.result;
                
                // Create a hidden form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route("tajweed.upload") }}';
                form.style.display = 'none';
                
                // Add CSRF token
                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = '_token';
                csrfInput.value = csrfToken;
                form.appendChild(csrfInput);
                
                // Add tajweed rule
                const ruleInput = document.createElement('input');
                ruleInput.type = 'hidden';
                ruleInput.name = 'tajweed_rule';
                ruleInput.value = 'izhar'; // Changed to 'izhar' for Izhar Halqi page
                form.appendChild(ruleInput);
                
                // Add audio as base64
                const audioInput = document.createElement('input');
                audioInput.type = 'hidden';
                audioInput.name = 'audio_base64';
                audioInput.value = base64Audio;
                form.appendChild(audioInput);
                
                // Add to document and submit
                document.body.appendChild(form);
                form.submit();
            };
        });
        
        // Handle form submission for Izhar upload
        document.getElementById('uploadFormIzhar')?.addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitUploadIzhar');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Uploading...';
            }
        });
    });
</script>
@endpush