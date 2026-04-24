{{-- resources/views/tajweed/ikhfaHaqiqi.blade.php --}}
@extends('layouts.app')

@section('title', 'Tajweed - Ikhfa Haqiqi')

@section('content')
    <div class="container py-4">
        <!-- Header -->
        <div class="page-header">
            <h1 class="mb-3">
                <i class="fas fa-volume-down me-2"></i>Ikhfa Haqiqi
            </h1>
            <p class="lead">Master the Art of True Concealment in Tajweed</p>
            <div class="alert alert-warning">
                <i class="fas fa-lightbulb me-2"></i>
                <strong>Rule Insight:</strong> Ikhfa occurs when a <strong>noon sakinah</strong> or <strong>tanween</strong> 
                is followed by one of the <strong>15 Ikhfa letters</strong>
            </div>
        </div>

        <!-- Ikhfa Letters Section -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-list-check me-2"></i>15 Ikhfa Letters (أحرف الإخفاء)
            </div>
            <div class="card-body">
                <p class="text-muted mb-4">The letters that trigger the Ikhfa rule</p>
                
                <!-- Memory Aid -->
                <div class="bg-light p-4 rounded mb-4">
                    <h6 class="fw-bold mb-3">Memory Aid Phrase</h6>
                    <p class="arabic-text text-center mb-2" style="font-size: 1.3rem;">
                        صِفْ ذَا ثَنَا كَمْ جَادَ شَخْصٌ قَدْ سَمَا دُمْ طَيِّبًا زِدْ فِي تُقًى ضَعْ ظَالِمَا
                    </p>
                    <small class="text-muted text-center d-block">
                        Pronounce: <em>"Sif tha thana kam jada shakhsun qad sama dum tayyiban zid fi tuqan da' thalima"</em>
                    </small>
                </div>

                <!-- Letters Grid -->
                <div class="letters-container">
                    @foreach([
                        ['letter' => 'ت', 'name' => 'Taa', 'makhraj' => 'Tip of tongue', 'example' => 'أَنْتُمْ'],
                        ['letter' => 'ث', 'name' => 'Thaa', 'makhraj' => 'Tip of tongue', 'example' => 'مَنْ ثَمَرَةٍ'],
                        ['letter' => 'ج', 'name' => 'Jeem', 'makhraj' => 'Middle of tongue', 'example' => 'مِنْ جِدَارٍ'],
                        ['letter' => 'د', 'name' => 'Daal', 'makhraj' => 'Tip of tongue', 'example' => 'عِنْدَ'],
                        ['letter' => 'ذ', 'name' => 'Dhaal', 'makhraj' => 'Tip of tongue', 'example' => 'مِنْ ذَلِكَ'],
                        ['letter' => 'ز', 'name' => 'Zaa', 'makhraj' => 'Tip of tongue', 'example' => 'مَنْزِلًا'],
                        ['letter' => 'س', 'name' => 'Seen', 'makhraj' => 'Tip of tongue', 'example' => 'أَن سَمِعَ'],
                        ['letter' => 'ش', 'name' => 'Sheen', 'makhraj' => 'Middle of tongue', 'example' => 'مِنْ شَرِّ'],
                        ['letter' => 'ص', 'name' => 'Saad', 'makhraj' => 'Tip of tongue', 'example' => 'مِنْ صَلَبٍ'],
                        ['letter' => 'ض', 'name' => 'Daad', 'makhraj' => 'Side of tongue', 'example' => 'مِنْ ضَعْفٍ'],
                        ['letter' => 'ط', 'name' => 'Taa', 'makhraj' => 'Tip of tongue', 'example' => 'مِنْ طَيِّبَاتِ'],
                        ['letter' => 'ظ', 'name' => 'Dhaa', 'makhraj' => 'Tip of tongue', 'example' => 'مِنْ ظَهِيرٍ'],
                        ['letter' => 'ف', 'name' => 'Faa', 'makhraj' => 'Inner lips', 'example' => 'مِنْ فَوْقِ'],
                        ['letter' => 'ق', 'name' => 'Qaaf', 'makhraj' => 'Deep throat', 'example' => 'مَنْقُورًا'],
                        ['letter' => 'ك', 'name' => 'Kaaf', 'makhraj' => 'Deep throat', 'example' => 'مِنْ كُلِّ'],
                    ] as $letter)
                        <div class="letter-card">
                            <div class="arabic">{{ $letter['letter'] }}</div>
                            <div class="name">{{ $letter['name'] }}</div>
                            <div class="makhraj">{{ $letter['makhraj'] }}</div>
                            <div class="example arabic-text">{{ $letter['example'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Pronunciation Guide & Audio Examples -->
        <div class="row mb-4">
            <!-- How to Pronounce -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="fas fa-microphone-alt me-2"></i>How to Pronounce Ikhfa
                    </div>
                    <div class="card-body">
                        <div class="step">
                            <div class="step-number">1</div>
                            <div class="step-content">
                                <h5>Partial Nasalization</h5>
                                <p>Pronounce the noon sound with a light ghunnah (nasal sound) for 2 harakah (beats).</p>
                            </div>
                        </div>
                        <div class="step">
                            <div class="step-number">2</div>
                            <div class="step-content">
                                <h5>Tongue Preparation</h5>
                                <p>Prepare your tongue for the following letter without touching the articulation point.</p>
                            </div>
                        </div>
                        <div class="step mb-0">
                            <div class="step-number">3</div>
                            <div class="step-content">
                                <h5>Smooth Transition</h5>
                                <p>Transition smoothly from the nasal sound to the following letter.</p>
                            </div>
                        </div>
                        
                        <div class="mt-4 pt-3 border-top">
                            <h6><i class="fas fa-clock me-2"></i>Timing</h6>
                            <p class="mb-0">Ikhfa ghunnah lasts approximately <strong>2 harakah</strong> (twice as long as a regular letter sound).</p>
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
                            ['arabic' => 'مَنْ تَابَ', 'translation' => 'Whoever repents', 'audio' => '#'],
                            ['arabic' => 'أَنْصَارَ', 'translation' => 'Helpers', 'audio' => '#'],
                            ['arabic' => 'مِنْ قَبْلِ', 'translation' => 'From before', 'audio' => '#'],
                            ['arabic' => 'عَلِيمٌ ذُو', 'translation' => 'All-Knowing, Owner of', 'audio' => '#'],
                        ] as $example)
                            <div class="audio-example">
                                <div class="content">
                                    <div class="arabic-text">{{ $example['arabic'] }}</div>
                                    <div class="translation">{{ $example['translation'] }}</div>
                                </div>
                                <audio id="audio-{{ $loop->index }}" style="display: none;">
                                    <source src="{{ $example['audio'] }}" type="audio/mpeg">
                                </audio>
                                <button class="btn-play" onclick="playAudio('audio-{{ $loop->index }}', this)">
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
                <i class="fas fa-balance-scale me-2"></i>Tajweed Rules Comparison
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Rule</th>
                                <th>Letters</th>
                                <th>Sound</th>
                                <th>Duration</th>
                                <th>Example</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr style="background: rgba(37, 99, 235, 0.05);">
                                <td><strong>Ikhfa</strong></td>
                                <td><span class="badge bg-primary">15 letters</span></td>
                                <td>Light ghunnah</td>
                                <td>2 harakah</td>
                                <td class="arabic-text">مَنْ تَابَ</td>
                            </tr>
                            <tr>
                                <td>Izhar</td>
                                <td><span class="badge bg-secondary">6 letters</span></td>
                                <td>Clear, no ghunnah</td>
                                <td>Normal</td>
                                <td class="arabic-text">مَنْ آمَنَ</td>
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
                        <button class="nav-link active" id="upload-tab" data-bs-toggle="tab" data-bs-target="#upload" type="button">
                            <i class="fas fa-cloud-upload-alt me-2"></i>Upload File
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="record-tab" data-bs-toggle="tab" data-bs-target="#record" type="button">
                            <i class="fas fa-microphone me-2"></i>Record Audio
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- Upload Tab -->
                    <div class="tab-pane fade show active" id="upload" role="tabpanel">
                        <form id="uploadForm" method="POST" action="{{ route('tajweed.upload') }}" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="tajweed_rule" value="ikhfa">
                            
                            <div class="file-upload mb-4">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <h5 class="mb-2">Drag & drop or click to upload</h5>
                                <p class="text-muted mb-3">Supports MP3, WAV, WEBM files</p>
                                <input type="file" name="audio" accept="audio/*" class="form-control" required id="audioFile">
                                <div id="fileName" class="mt-2 text-success fw-semibold"></div>
                                <small class="text-muted d-block mt-2">Maximum file size: 10MB</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" id="submitUpload">
                                <i class="fas fa-paper-plane me-2"></i>Submit for Analysis
                            </button>
                        </form>
                    </div>

                    <!-- Record Tab -->
                    <div class="tab-pane fade" id="record" role="tabpanel">
                        <div class="recording-section">
                            <div class="text-center mb-4">
                                <div id="timer" class="timer">00:00</div>
                                <p id="statusText" class="text-muted">Ready to record</p>
                            </div>
                            
                            <!-- Recording Visualization -->
                            <div id="visualizer" class="mb-4" style="display: none;">
                                <canvas id="waveform" width="400" height="80"></canvas>
                            </div>
                            
                            <div class="d-flex justify-content-center gap-3 mb-4">
                                <button class="btn btn-success btn-record" id="startBtn">
                                    <i class="fas fa-microphone me-2"></i>Start Recording
                                </button>
                                <button class="btn btn-danger btn-record" id="stopBtn" disabled>
                                    <i class="fas fa-stop me-2"></i>Stop
                                </button>
                                <button class="btn btn-warning btn-record" id="pauseBtn" disabled style="display: none;">
                                    <i class="fas fa-pause me-2"></i>Pause
                                </button>
                            </div>
                            
                            <!-- Recording Preview -->
                            <div id="recordingPreview" class="mb-4" style="display: none;">
                                <h6>Recording Preview:</h6>
                                <audio id="recordedAudio" controls class="w-100"></audio>
                                <button id="submitRecording" class="btn btn-primary mt-2">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Recording
                                </button>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Make sure you're in a quiet environment and speak clearly.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <div class="d-flex justify-content-between mt-4 pt-4 border-top">
            <div>
                <a href="{{ route('home') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-home me-2"></i> Home
                </a>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    /* Additional styles for recording functionality */
    #waveform {
        width: 100%;
        height: 80px;
        background: #f8f9fa;
        border-radius: 8px;
    }
    
    .recording-active {
        animation: pulse 1.5s infinite;
    }
    
    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.7; }
        100% { opacity: 1; }
    }
    
    #recordingPreview audio {
        height: 40px;
    }
    
    /* File upload preview */
    #fileName {
        font-size: 0.9rem;
    }
</style>
@endpush

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
    
    // File upload preview
    document.getElementById('audioFile')?.addEventListener('change', function(e) {
        const fileName = document.getElementById('fileName');
        if (this.files.length > 0) {
            fileName.textContent = `Selected: ${this.files[0].name}`;
        } else {
            fileName.textContent = '';
        }
    });
    
    // Recording functionality
    document.addEventListener('DOMContentLoaded', function() {
        const startBtn = document.getElementById('startBtn');
        const stopBtn = document.getElementById('stopBtn');
        const pauseBtn = document.getElementById('pauseBtn');
        const timer = document.getElementById('timer');
        const statusText = document.getElementById('statusText');
        const visualizer = document.getElementById('visualizer');
        const recordingPreview = document.getElementById('recordingPreview');
        const recordedAudio = document.getElementById('recordedAudio');
        const submitRecording = document.getElementById('submitRecording');
        const canvas = document.getElementById('waveform');
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
            ctx.strokeStyle = '#2563eb';
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
        
        // Submit recording - FIXED VERSION
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
                ruleInput.value = 'ikhfa'; // Change to 'izhar' for Izhar Halqi page
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
        
        // Handle form submission
        document.getElementById('uploadForm')?.addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitUpload');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Uploading...';
        });
    });
</script>
@endpush