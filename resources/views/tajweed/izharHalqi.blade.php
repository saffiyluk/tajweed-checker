@extends('layouts.app')

@section('title', 'Tajweed - Izhar Halqi')

@section('content')
@php
    $selectedAyah = request('ayah');
    $sourceSurah = request('surah');
    $sourceAyah = request('ayah_number');

    $izharLetters = [
        ['letter' => 'ء', 'name' => 'Hamzah', 'position' => 'Upper throat', 'example' => 'مَنْ آمَنَ', 'level' => 'upper'],
        ['letter' => 'ه', 'name' => 'Haa', 'position' => 'Upper throat', 'example' => 'مِنْ هَادٍ', 'level' => 'upper'],
        ['letter' => 'ع', 'name' => 'Ayn', 'position' => 'Middle throat', 'example' => 'مِنْ عِلْمٍ', 'level' => 'middle'],
        ['letter' => 'ح', 'name' => 'Haa', 'position' => 'Middle throat', 'example' => 'مِنْ حَيْثُ', 'level' => 'middle'],
        ['letter' => 'غ', 'name' => 'Ghayn', 'position' => 'Deep throat', 'example' => 'مَنْ غَفَرَ', 'level' => 'deep'],
        ['letter' => 'خ', 'name' => 'Khaa', 'position' => 'Deep throat', 'example' => 'مِنْ خَيْرٍ', 'level' => 'deep'],
    ];

    $izharAudioFiles = [
        '079031_RAgaIHli.wav',
        '083005_Vl2eRWWI.wav',
        '059003_YiG1IQ63.wav',
        '085011_aeaX3bHZ.wav',
    ];

    $audioExamples = [
        ['arabic' => 'مِنْهَا', 'translation' => 'from it'],
        ['arabic' => 'لِيَوْمٍ عَظِيمٍ', 'translation' => 'for a great day'],
        ['arabic' => 'ٱلدُّنۡيَاۖ', 'translation' => 'world'],
        ['arabic' => 'أنْهَارُ', 'translation' => 'rivers'],
    ];

    $noonExamples = [
        ['arabic' => 'مَنْ آمَنَ', 'translation' => 'Whoever believes', 'pronunciation' => 'Man aamana'],
        ['arabic' => 'مِنْهُمْ', 'translation' => 'From them', 'pronunciation' => 'Min hum'],
        ['arabic' => 'أَنْعَمْتَ', 'translation' => 'You bestowed favor', 'pronunciation' => 'An’amta'],
        ['arabic' => 'مِنْ حَيْثُ', 'translation' => 'From where', 'pronunciation' => 'Min haithu'],
    ];

    $tanweenExamples = [
        ['arabic' => 'عَلِيْمٌ حَكِيْمٌ', 'translation' => 'All-Knowing, All-Wise', 'pronunciation' => 'Aleemun Hakeem'],
        ['arabic' => 'كِتَابٌ عَزِيزٌ', 'translation' => 'A mighty Book', 'pronunciation' => 'Kitabun Azeez'],
        ['arabic' => 'نَذِيْرٌ هُدَى', 'translation' => 'A warner of guidance', 'pronunciation' => 'Natheerun Huda'],
        ['arabic' => 'رَحْمَةٌ غَفُوْرٌ', 'translation' => 'Merciful, Forgiving', 'pronunciation' => 'Rahmatun Ghafoor'],
    ];
@endphp

<div class="practice-page izhar-page">
    <div class="container py-4 py-lg-5">

        <div class="practice-hero">
            <div>
                <span class="rule-kicker">Tajweed Practice</span>
                <h1>Izhar Halqi</h1>
                <p>
                    Practise clear pronunciation of Noon Sakinah or Tanween before the six throat letters.
                </p>
            </div>

            <div class="hero-pill">
                <i class="fas fa-volume-up me-2"></i>
                Clear sound, no ghunnah
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show clean-alert">
                <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show clean-alert">
                <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="row g-4 align-items-start">
            <div class="col-lg-5">
                <div class="clean-card sticky-lg-top practice-guide-card">
                    <div class="card-label">
                        <i class="fas fa-book-open me-2"></i>
                        Reading Guide
                    </div>

                    @if($selectedAyah)
                        <h2>Selected Ayah</h2>

                        <div class="selected-ayah-box arabic-text">
                            {{ $selectedAyah }}
                        </div>

                        <div class="small-guidance">
                            <i class="fas fa-microphone-alt me-2"></i>
                            Read this exact ayah when recording.
                        </div>

                        <div class="mt-3">
                            @if($sourceSurah)
                                <a href="{{ route('recite.quran', ['surah' => $sourceSurah]) }}{{ $sourceAyah ? '#ayah-' . $sourceAyah : '' }}" class="btn btn-soft w-100">
                                    <i class="fas fa-arrow-left me-2"></i>Back to selected ayah
                                </a>
                            @else
                                <a href="{{ route('recite.quran') }}" class="btn btn-soft w-100">
                                    <i class="fas fa-book-quran me-2"></i>Open Quran page
                                </a>
                            @endif
                        </div>
                    @else
                        <h2>No ayah selected</h2>
                        <p class="muted-text">
                            You can still upload or record your recitation, but selecting an ayah first gives better context for analysis.
                        </p>

                        <a href="{{ route('recite.quran') }}" class="btn btn-primary-custom w-100">
                            <i class="fas fa-book-quran me-2"></i>Select Ayah from Quran
                        </a>
                    @endif

                    <div class="rule-summary">
                        <div>
                            <strong>Rule</strong>
                            <span>Izhar Halqi</span>
                        </div>
                        <div>
                            <strong>Letters</strong>
                            <span>6 throat letters</span>
                        </div>
                        <div>
                            <strong>Sound</strong>
                            <span>Clear, no nasal</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="clean-card">
                    <div class="section-heading">
                        <div>
                            <span class="card-label">
                                <i class="fas fa-microphone me-2"></i>
                                Practice Submission
                            </span>
                            <h2>Upload or record your recitation</h2>
                        </div>
                    </div>

                    <ul class="clean-tabs nav nav-pills mb-4" id="myTab" role="tablist">
                        <li class="nav-item flex-fill" role="presentation">
                            <button class="nav-link active w-100" id="upload-tab-izhar" data-bs-toggle="tab" data-bs-target="#upload-izhar" type="button">
                                <i class="fas fa-cloud-upload-alt me-2"></i>Upload File
                            </button>
                        </li>
                        <li class="nav-item flex-fill" role="presentation">
                            <button class="nav-link w-100" id="record-tab-izhar" data-bs-toggle="tab" data-bs-target="#record-izhar" type="button">
                                <i class="fas fa-microphone me-2"></i>Record Audio
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="upload-izhar" role="tabpanel">
                            <form id="uploadFormIzhar" method="POST" action="{{ route('tajweed.upload') }}" enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="tajweed_rule" value="izhar">
                                <input type="hidden" name="browser_transcript" id="browserTranscriptIzhar" value="">

                                @if($selectedAyah)
                                    <input type="hidden" name="selected_ayah" value="{{ $selectedAyah }}">
                                @endif

                                <label for="audioFileIzhar" class="upload-zone">
                                    <div class="upload-icon">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                    </div>

                                    <div>
                                        <h5>Choose your audio file</h5>
                                        <p>MP3, WAV, or WEBM. Maximum 10MB.</p>
                                    </div>

                                    <input type="file" name="audio" accept="audio/*" class="d-none" required id="audioFileIzhar">
                                </label>

                                <div id="fileNameIzhar" class="file-name"></div>

                                <button type="submit" class="btn btn-primary-custom w-100 mt-3" id="submitUploadIzhar">
                                    <i class="fas fa-paper-plane me-2"></i>Submit for Analysis
                                </button>
                            </form>
                        </div>

                        <div class="tab-pane fade" id="record-izhar" role="tabpanel">
                            <div class="record-panel">
                                <div class="timer-card">
                                    <span id="timerIzhar" class="timer">00:00</span>
                                    <p id="statusTextIzhar">Ready to record</p>
                                </div>

                                <div id="visualizerIzhar" class="wave-box" style="display: none;">
                                    <canvas id="waveformIzhar" width="500" height="90"></canvas>
                                </div>

                                <div class="record-actions">
                                    <button type="button" class="btn btn-record-start" id="startBtnIzhar">
                                        <i class="fas fa-microphone me-2"></i>Start
                                    </button>

                                    <button type="button" class="btn btn-record-stop" id="stopBtnIzhar" disabled>
                                        <i class="fas fa-stop me-2"></i>Stop
                                    </button>

                                    <button type="button" class="btn btn-record-pause" id="pauseBtnIzhar" disabled style="display: none;">
                                        <i class="fas fa-pause me-2"></i>Pause
                                    </button>
                                </div>

                                <div id="recordingPreviewIzhar" class="preview-box" style="display: none;">
                                    <div class="preview-title">
                                        <i class="fas fa-headphones me-2"></i>Preview your recording
                                    </div>

                                    <audio id="recordedAudioIzhar" controls class="w-100"></audio>

                                    <button type="button" id="submitRecordingIzhar" class="btn btn-primary-custom w-100 mt-3">
                                        <i class="fas fa-paper-plane me-2"></i>Submit Recording
                                    </button>
                                </div>

                                <div class="simple-note">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Focus on clear throat articulation. Izhar should not contain nasal ghunnah.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="clean-card mt-4">
                    <div class="section-heading">
                        <div>
                            <span class="card-label">
                                <i class="fas fa-graduation-cap me-2"></i>
                                Quick Learning
                            </span>
                            <h2>Izhar reference</h2>
                        </div>
                    </div>

                    <div class="accordion clean-accordion" id="izharAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingLetters">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#lettersCollapse">
                                    6 Throat Letters
                                </button>
                            </h2>
                            <div id="lettersCollapse" class="accordion-collapse collapse show" data-bs-parent="#izharAccordion">
                                <div class="accordion-body">
                                    <div class="throat-grid mb-3">
                                        <div class="throat-box upper">
                                            <strong>Upper Throat</strong>
                                            <span class="arabic-text">ء ه</span>
                                        </div>
                                        <div class="throat-box middle">
                                            <strong>Middle Throat</strong>
                                            <span class="arabic-text">ع ح</span>
                                        </div>
                                        <div class="throat-box deep">
                                            <strong>Deep Throat</strong>
                                            <span class="arabic-text">غ خ</span>
                                        </div>
                                    </div>

                                    <div class="letters-grid">
                                        @foreach($izharLetters as $letter)
                                            <div class="letter-chip {{ $letter['level'] }}">
                                                <div class="letter arabic-text">{{ $letter['letter'] }}</div>
                                                <div>
                                                    <strong>{{ $letter['name'] }}</strong>
                                                    <small>{{ $letter['position'] }}</small>
                                                    <span class="arabic-text">{{ $letter['example'] }}</span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingExamples">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#examplesCollapse">
                                    Examples
                                </button>
                            </h2>
                            <div id="examplesCollapse" class="accordion-collapse collapse" data-bs-parent="#izharAccordion">
                                <div class="accordion-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <h6 class="mini-title">Noon Sakinah</h6>
                                            <div class="example-list">
                                                @foreach($noonExamples as $example)
                                                    <div class="example-row">
                                                        <div class="arabic-text">{{ $example['arabic'] }}</div>
                                                        <small>{{ $example['pronunciation'] }} • {{ $example['translation'] }}</small>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <h6 class="mini-title">Tanween</h6>
                                            <div class="example-list">
                                                @foreach($tanweenExamples as $example)
                                                    <div class="example-row">
                                                        <div class="arabic-text">{{ $example['arabic'] }}</div>
                                                        <small>{{ $example['pronunciation'] }} • {{ $example['translation'] }}</small>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingPronounce">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#pronounceCollapse">
                                    How to Pronounce
                                </button>
                            </h2>
                            <div id="pronounceCollapse" class="accordion-collapse collapse" data-bs-parent="#izharAccordion">
                                <div class="accordion-body">
                                    <div class="steps-list">
                                        <div class="step-row">
                                            <span>1</span>
                                            <div>
                                                <strong>Pronounce clearly</strong>
                                                <p>Noon Sakinah or Tanween must be clear.</p>
                                            </div>
                                        </div>

                                        <div class="step-row">
                                            <span>2</span>
                                            <div>
                                                <strong>No ghunnah</strong>
                                                <p>Do not add nasal sound like Ikhfa or Idgham.</p>
                                            </div>
                                        </div>

                                        <div class="step-row">
                                            <span>3</span>
                                            <div>
                                                <strong>Use throat articulation</strong>
                                                <p>Focus on the correct throat level for each letter.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingAudio">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#audioCollapse">
                                    Audio Examples
                                </button>
                            </h2>
                            <div id="audioCollapse" class="accordion-collapse collapse" data-bs-parent="#izharAccordion">
                                <div class="accordion-body">
                                    <div class="audio-list">
                                        @foreach($audioExamples as $example)
                                            <div class="audio-row">
                                                <div>
                                                    <div class="arabic-text audio-arabic">{{ $example['arabic'] }}</div>
                                                    <small>{{ $example['translation'] }}</small>
                                                </div>

                                                <audio id="audio-izhar-{{ $loop->index }}" style="display: none;">
                                                    <source src="{{ route('tajweed.dataset-audio', ['rule' => 'izhar', 'filename' => $izharAudioFiles[$loop->index]]) }}" type="audio/wav">
                                                </audio>

                                                <button class="btn btn-play" onclick="playAudio('audio-izhar-{{ $loop->index }}', this)">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingCompare">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#compareCollapse">
                                    Izhar vs Ikhfa
                                </button>
                            </h2>
                            <div id="compareCollapse" class="accordion-collapse collapse" data-bs-parent="#izharAccordion">
                                <div class="accordion-body">
                                    <div class="compare-grid">
                                        <div class="compare-box active">
                                            <strong>Izhar Halqi</strong>
                                            <span>6 letters</span>
                                            <p>Clear sound, no ghunnah.</p>
                                            <div class="arabic-text">مَنْ آمَنَ</div>
                                        </div>

                                        <div class="compare-box">
                                            <strong>Ikhfa Haqiqi</strong>
                                            <span>15 letters</span>
                                            <p>Hidden sound with light ghunnah.</p>
                                            <div class="arabic-text">مَنْ تَابَ</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="bottom-nav">
            <a href="{{ route('tajweed.ikhfa-haqiqi') }}" class="btn btn-soft">
                <i class="fas fa-arrow-left me-2"></i>Ikhfa Haqiqi
            </a>

            <a href="{{ route('home') }}" class="btn btn-soft">
                <i class="fas fa-home me-2"></i>Home
            </a>

            <a href="{{ route('tajweed.history') }}" class="btn btn-primary-custom">
                View Recitations <i class="fas fa-arrow-right ms-2"></i>
            </a>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    :root {
        --primary: #0891b2;
        --primary-dark: #0e7490;
        --gold: #c29950;
        --dark: #0f172a;
        --muted: #64748b;
        --soft: #f8fafc;
        --line: #e2e8f0;
        --success: #16a34a;
        --danger: #dc2626;
        --warning: #d97706;
        --shadow: 0 20px 55px rgba(15, 23, 42, 0.10);
    }

    body {
        background:
            radial-gradient(circle at top left, rgba(8, 145, 178, 0.10), transparent 30%),
            linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%);
    }

    .practice-page {
        color: var(--dark);
    }

    .practice-hero {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        border-radius: 26px;
        color: white;
        padding: 2rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 1.5rem;
        box-shadow: var(--shadow);
        position: relative;
        overflow: hidden;
    }

    .practice-hero::after {
        content: "۞";
        position: absolute;
        right: 2rem;
        top: -1rem;
        font-size: 8rem;
        opacity: 0.08;
        font-family: serif;
    }

    .rule-kicker,
    .card-label {
        display: inline-flex;
        align-items: center;
        color: var(--gold);
        font-size: 0.8rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        margin-bottom: 0.5rem;
    }

    .practice-hero h1 {
        font-weight: 850;
        letter-spacing: -0.04em;
        margin: 0;
        font-size: clamp(2rem, 4vw, 3rem);
    }

    .practice-hero p {
        margin: 0.75rem 0 0;
        max-width: 650px;
        color: rgba(255, 255, 255, 0.84);
        line-height: 1.7;
    }

    .hero-pill {
        background: rgba(255, 255, 255, 0.15);
        border: 1px solid rgba(255, 255, 255, 0.24);
        border-radius: 999px;
        padding: 0.75rem 1rem;
        font-weight: 700;
        white-space: nowrap;
        position: relative;
        z-index: 1;
    }

    .clean-alert {
        border: 0;
        border-radius: 18px;
        box-shadow: 0 14px 32px rgba(15, 23, 42, 0.08);
    }

    .clean-card {
        background: white;
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 24px;
        padding: 1.5rem;
        box-shadow: 0 14px 40px rgba(15, 23, 42, 0.07);
    }

    .practice-guide-card {
        top: 90px;
    }

    .clean-card h2 {
        font-size: 1.35rem;
        font-weight: 850;
        letter-spacing: -0.03em;
        margin-bottom: 1rem;
    }

    .muted-text {
        color: var(--muted);
        line-height: 1.7;
    }

    .selected-ayah-box {
        background: var(--soft);
        border: 1px solid var(--line);
        border-radius: 18px;
        padding: 1.2rem;
        font-size: 1.85rem;
        line-height: 2.1;
        text-align: right;
        direction: rtl;
        margin-bottom: 1rem;
    }

    .small-guidance {
        color: var(--muted);
        font-size: 0.92rem;
        line-height: 1.6;
    }

    .rule-summary {
        display: grid;
        gap: 0.75rem;
        margin-top: 1.25rem;
        padding-top: 1.25rem;
        border-top: 1px solid var(--line);
    }

    .rule-summary div {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        color: var(--muted);
        font-size: 0.92rem;
    }

    .rule-summary strong {
        color: var(--dark);
    }

    .btn-primary-custom {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        border: 0;
        border-radius: 16px;
        padding: 0.85rem 1rem;
        font-weight: 800;
        box-shadow: 0 14px 28px rgba(8, 145, 178, 0.22);
        transition: 0.2s ease;
    }

    .btn-primary-custom:hover {
        transform: translateY(-1px);
        color: white;
        box-shadow: 0 18px 32px rgba(8, 145, 178, 0.26);
    }

    .btn-soft {
        background: #ecfeff;
        color: var(--primary-dark);
        border: 1px solid #cffafe;
        border-radius: 16px;
        padding: 0.75rem 1rem;
        font-weight: 800;
    }

    .btn-soft:hover {
        background: #cffafe;
        color: var(--primary-dark);
    }

    .section-heading {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .section-heading h2 {
        margin-bottom: 0;
    }

    .clean-tabs {
        background: var(--soft);
        border: 1px solid var(--line);
        border-radius: 18px;
        padding: 0.35rem;
        gap: 0.35rem;
    }

    .clean-tabs .nav-link {
        border-radius: 14px;
        color: var(--muted);
        font-weight: 800;
        padding: 0.85rem 1rem;
    }

    .clean-tabs .nav-link.active {
        background: white;
        color: var(--primary-dark);
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
    }

    .upload-zone {
        border: 2px dashed #cbd5e1;
        background: var(--soft);
        border-radius: 20px;
        padding: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        cursor: pointer;
        transition: 0.2s ease;
    }

    .upload-zone:hover {
        border-color: var(--primary);
        background: #ecfeff;
    }

    .upload-icon {
        width: 58px;
        height: 58px;
        border-radius: 18px;
        display: grid;
        place-items: center;
        background: #cffafe;
        color: var(--primary-dark);
        font-size: 1.45rem;
        flex-shrink: 0;
    }

    .upload-zone h5 {
        margin: 0;
        font-weight: 850;
    }

    .upload-zone p {
        margin: 0.3rem 0 0;
        color: var(--muted);
        font-size: 0.92rem;
    }

    .file-name {
        margin-top: 0.85rem;
        color: var(--success);
        font-weight: 800;
        font-size: 0.92rem;
    }

    .record-panel {
        display: grid;
        gap: 1rem;
    }

    .timer-card {
        background: var(--soft);
        border: 1px solid var(--line);
        border-radius: 22px;
        padding: 1.4rem;
        text-align: center;
    }

    .timer {
        display: block;
        font-size: 2.6rem;
        font-weight: 900;
        color: var(--dark);
        letter-spacing: -0.04em;
        line-height: 1;
    }

    .timer-card p {
        margin: 0.6rem 0 0;
        color: var(--muted);
        font-weight: 700;
    }

    .wave-box {
        background: var(--soft);
        border: 1px solid var(--line);
        border-radius: 18px;
        padding: 0.7rem;
    }

    #waveformIzhar {
        width: 100%;
        height: 90px;
        border-radius: 14px;
        background: white;
    }

    .record-actions {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 0.75rem;
    }

    .record-actions .btn {
        border-radius: 16px;
        padding: 0.85rem 1rem;
        font-weight: 850;
        border: 0;
    }

    .btn-record-start {
        background: #dcfce7;
        color: #166534;
    }

    .btn-record-stop {
        background: #fee2e2;
        color: #991b1b;
    }

    .btn-record-pause {
        background: #fef3c7;
        color: #92400e;
    }

    .preview-box {
        background: #f8fafc;
        border: 1px solid var(--line);
        border-radius: 20px;
        padding: 1rem;
    }

    .preview-title {
        font-weight: 850;
        margin-bottom: 0.75rem;
    }

    .simple-note {
        background: #ecfeff;
        color: #155e75;
        border: 1px solid #cffafe;
        border-radius: 16px;
        padding: 0.9rem 1rem;
        font-size: 0.92rem;
        font-weight: 650;
    }

    .clean-accordion .accordion-item {
        border: 1px solid var(--line);
        border-radius: 18px;
        overflow: hidden;
        margin-bottom: 0.85rem;
    }

    .clean-accordion .accordion-button {
        font-weight: 850;
        color: var(--dark);
        background: white;
        box-shadow: none;
        padding: 1rem 1.1rem;
    }

    .clean-accordion .accordion-button:not(.collapsed) {
        color: var(--primary-dark);
        background: #f8fafc;
    }

    .throat-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.75rem;
    }

    .throat-box {
        border-radius: 18px;
        padding: 1rem;
        text-align: center;
        color: white;
    }

    .throat-box strong {
        display: block;
        margin-bottom: 0.4rem;
    }

    .throat-box span {
        font-size: 1.55rem;
        font-weight: 900;
    }

    .throat-box.upper {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
    }

    .throat-box.middle {
        background: linear-gradient(135deg, #0d9488, #0f766e);
    }

    .throat-box.deep {
        background: linear-gradient(135deg, #7c3aed, #6d28d9);
    }

    .letters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(165px, 1fr));
        gap: 0.75rem;
    }

    .letter-chip {
        background: var(--soft);
        border: 1px solid var(--line);
        border-radius: 16px;
        padding: 0.8rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .letter-chip.upper {
        border-left: 4px solid #2563eb;
    }

    .letter-chip.middle {
        border-left: 4px solid #0d9488;
    }

    .letter-chip.deep {
        border-left: 4px solid #7c3aed;
    }

    .letter-chip .letter {
        width: 46px;
        height: 46px;
        border-radius: 14px;
        display: grid;
        place-items: center;
        background: white;
        color: var(--primary-dark);
        font-size: 1.5rem;
        font-weight: 900;
        flex-shrink: 0;
    }

    .letter-chip strong {
        display: block;
        font-size: 0.9rem;
    }

    .letter-chip small {
        display: block;
        color: var(--muted);
        font-size: 0.78rem;
        margin-bottom: 0.1rem;
    }

    .letter-chip span {
        display: block;
        color: var(--muted);
        font-size: 0.95rem;
        direction: rtl;
    }

    .mini-title {
        color: var(--dark);
        font-weight: 850;
        margin-bottom: 0.85rem;
    }

    .example-list {
        display: grid;
        gap: 0.75rem;
    }

    .example-row {
        background: var(--soft);
        border: 1px solid var(--line);
        border-radius: 16px;
        padding: 0.85rem;
    }

    .example-row .arabic-text {
        font-size: 1.25rem;
        margin-bottom: 0.25rem;
    }

    .example-row small {
        color: var(--muted);
    }

    .steps-list {
        display: grid;
        gap: 0.85rem;
    }

    .step-row {
        display: flex;
        gap: 0.85rem;
        background: var(--soft);
        border: 1px solid var(--line);
        border-radius: 16px;
        padding: 1rem;
    }

    .step-row > span {
        width: 34px;
        height: 34px;
        border-radius: 12px;
        background: var(--primary);
        color: white;
        display: grid;
        place-items: center;
        font-weight: 900;
        flex-shrink: 0;
    }

    .step-row p {
        color: var(--muted);
        margin: 0.25rem 0 0;
    }

    .audio-list {
        display: grid;
        gap: 0.75rem;
    }

    .audio-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        background: var(--soft);
        border: 1px solid var(--line);
        border-radius: 16px;
        padding: 0.85rem;
    }

    .audio-arabic {
        font-size: 1.25rem;
        margin-bottom: 0.2rem;
    }

    .audio-row small {
        color: var(--muted);
    }

    .btn-play {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: grid;
        place-items: center;
        background: var(--primary);
        color: white;
        border: 0;
        flex-shrink: 0;
    }

    .compare-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.85rem;
    }

    .compare-box {
        background: var(--soft);
        border: 1px solid var(--line);
        border-radius: 18px;
        padding: 1rem;
    }

    .compare-box.active {
        border-color: #67e8f9;
        background: #ecfeff;
    }

    .compare-box strong,
    .compare-box span {
        display: block;
    }

    .compare-box span {
        color: var(--muted);
        font-size: 0.85rem;
        margin: 0.2rem 0 0.5rem;
    }

    .compare-box p {
        margin: 0 0 0.6rem;
        color: var(--muted);
    }

    .compare-box .arabic-text {
        font-size: 1.25rem;
    }

    .bottom-nav {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        margin-top: 1.5rem;
        flex-wrap: wrap;
    }

    .arabic-text {
        font-family: "Amiri", "Scheherazade New", serif;
        direction: rtl;
    }

    @media (max-width: 991px) {
        .practice-guide-card {
            position: static !important;
        }

        .practice-hero {
            align-items: flex-start;
            flex-direction: column;
        }

        .hero-pill {
            white-space: normal;
        }
    }

    @media (max-width: 768px) {
        .throat-grid,
        .compare-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 576px) {
        .clean-card,
        .practice-hero {
            border-radius: 20px;
            padding: 1.1rem;
        }

        .upload-zone {
            flex-direction: column;
            align-items: flex-start;
        }

        .record-actions {
            grid-template-columns: 1fr;
        }

        .selected-ayah-box {
            font-size: 1.45rem;
        }

        .bottom-nav .btn {
            width: 100%;
        }
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
        let speechRecognition;
        let browserTranscript = '';
        let visibleBrowserTranscript = '';
        
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

        function keepArabicText(text) {
            const matches = (text || '').toString().match(/[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\s]+/g) || [];
            return matches.join(' ').replace(/\s+/g, ' ').trim();
        }

        function mergeArabicTranscript(current, next) {
            current = keepArabicText(current);
            next = keepArabicText(next);

            if (!next) return current;
            if (!current) return next;
            if (current === next || current.endsWith(` ${next}`)) return current;
            if (next.startsWith(current)) return next;

            const currentWords = current.split(' ');
            const nextWords = next.split(' ');
            const maxOverlap = Math.min(currentWords.length, nextWords.length);

            for (let overlap = maxOverlap; overlap > 0; overlap--) {
                const currentTail = currentWords.slice(-overlap).join(' ');
                const nextHead = nextWords.slice(0, overlap).join(' ');

                if (currentTail === nextHead) {
                    return keepArabicText([...currentWords, ...nextWords.slice(overlap)].join(' '));
                }
            }

            return keepArabicText(`${current} ${next}`);
        }

        function setupBrowserTranscript() {
            const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;

            if (!Recognition) {
                return null;
            }

            const recognition = new Recognition();
            recognition.lang = 'ar-SA';
            recognition.continuous = true;
            recognition.interimResults = true;
            recognition.maxAlternatives = 1;

            recognition.onresult = function(event) {
                let finalText = '';
                let interimText = '';

                for (let i = event.resultIndex; i < event.results.length; i++) {
                    const text = event.results[i][0].transcript || '';

                    if (event.results[i].isFinal) {
                        finalText += text + ' ';
                    } else {
                        interimText += text + ' ';
                    }
                }

                if (finalText.trim()) {
                    browserTranscript = mergeArabicTranscript(browserTranscript, finalText);
                    const transcriptInput = document.getElementById('browserTranscriptIzhar');
                    if (transcriptInput) transcriptInput.value = browserTranscript;
                }

                const visibleTranscript = mergeArabicTranscript(browserTranscript, interimText);
                visibleBrowserTranscript = visibleTranscript || browserTranscript;
                if (visibleTranscript) {
                    statusText.textContent = `Recording... ${visibleTranscript}`;
                }
            };

            recognition.onend = function() {
                if (isRecording && !isPaused) {
                    try {
                        recognition.start();
                    } catch (error) {
                        // Browser may already be restarting recognition.
                    }
                }
            };

            return recognition;
        }
        
        // Start recording
        startBtn?.addEventListener('click', async function() {
            try {
                // Request microphone access
                audioStream = await navigator.mediaDevices.getUserMedia({ 
                    audio: {
                        echoCancellation: false,
                        noiseSuppression: false,
                        autoGainControl: false,
                        channelCount: 1,
                        sampleRate: 16000
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
                const preferredMimeTypes = [
                    'audio/webm;codecs=opus',
                    'audio/webm',
                    'audio/mp4'
                ];
                const recorderOptions = {};
                const supportedMimeType = preferredMimeTypes.find(type => MediaRecorder.isTypeSupported(type));

                if (supportedMimeType) {
                    recorderOptions.mimeType = supportedMimeType;
                }

                mediaRecorder = new MediaRecorder(audioStream, recorderOptions);
                audioChunks = [];
                
                mediaRecorder.ondataavailable = (event) => {
                    if (event.data.size > 0) {
                        audioChunks.push(event.data);
                    }
                };
                
                mediaRecorder.onstop = () => {
                    // Create audio blob
                    const blobType = mediaRecorder.mimeType || 'audio/webm';
                    const audioBlob = new Blob(audioChunks, { type: blobType });
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
                browserTranscript = '';
                visibleBrowserTranscript = '';
                const transcriptInput = document.getElementById('browserTranscriptIzhar');
                if (transcriptInput) transcriptInput.value = '';

                speechRecognition = setupBrowserTranscript();
                if (speechRecognition) {
                    try {
                        speechRecognition.start();
                    } catch (error) {
                        speechRecognition = null;
                    }
                }
                
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
                if (speechRecognition) {
                    try {
                        browserTranscript = keepArabicText(visibleBrowserTranscript || browserTranscript);
                        const transcriptInput = document.getElementById('browserTranscriptIzhar');
                        if (transcriptInput) transcriptInput.value = browserTranscript;
                        speechRecognition.stop();
                    } catch (error) {
                        // Recognition may already be stopped.
                    }
                }
                
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

            submitRecording.disabled = true;
            submitRecording.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
            
            // Create form data
            const formData = new FormData();
            const blobType = mediaRecorder?.mimeType || 'audio/webm';
            const audioBlob = new Blob(audioChunks, { type: blobType });
            
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

                const transcriptInput = document.createElement('input');
                transcriptInput.type = 'hidden';
                transcriptInput.name = 'browser_transcript';
                transcriptInput.value = keepArabicText(visibleBrowserTranscript || browserTranscript);
                form.appendChild(transcriptInput);

                @if($selectedAyah)
                    const selectedAyahInput = document.createElement('input');
                    selectedAyahInput.type = 'hidden';
                    selectedAyahInput.name = 'selected_ayah';
                    selectedAyahInput.value = @json($selectedAyah);
                    form.appendChild(selectedAyahInput);
                @endif
                
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

            reader.onerror = function() {
                submitRecording.disabled = false;
                submitRecording.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Recording';
                alert('Could not prepare the recording. Please try recording again.');
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
