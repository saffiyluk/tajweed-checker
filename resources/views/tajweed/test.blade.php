@php
$result = session('tajweed_result');
@endphp

<h2>Analysis Result</h2>

<p>Status: {{ $result['status'] }}</p>
<p>Confidence: {{ $result['confidence'] }}%</p>
<p>Feedback: {{ $result['feedback'] }}</p>

<div id="waveform"></div>

<script src="https://unpkg.com/wavesurfer.js"></script>
<script>
const wavesurfer = WaveSurfer.create({
    container: '#waveform',
    waveColor: 'gray',
    progressColor: 'blue'
});

wavesurfer.load("{{ asset('storage/audio.mp3') }}");
</script>