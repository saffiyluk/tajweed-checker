import os
import tempfile
from pathlib import Path

import numpy as np

SAMPLE_RATE = 16000
TARGET_RMS = float(os.environ.get("TAJWEED_TARGET_RMS", "0.10"))
NOISE_REDUCTION_AMOUNT = float(os.environ.get("TAJWEED_NOISE_REDUCTION_AMOUNT", "0.25"))
ENABLE_DENOISE = os.environ.get("TAJWEED_ENABLE_AUDIO_DENOISE", "1").lower() not in {"0", "false", "no"}
NOISE_REDUCTION_STATIONARY = os.environ.get("TAJWEED_NOISE_REDUCTION_STATIONARY", "0").lower() in {"1", "true", "yes"}
USE_NOISEREDUCE_LIBRARY = os.environ.get("TAJWEED_USE_NOISEREDUCE_LIBRARY", "0").lower() in {"1", "true", "yes"}
LIBROSA_TRIM_TOP_DB = float(os.environ.get("TAJWEED_LIBROSA_TRIM_TOP_DB", "28"))


def remove_dc_offset(y):
    if y.size == 0:
        return y.astype(np.float32)

    return (y.astype(np.float32) - float(np.mean(y))).astype(np.float32)


def trim_silence(y, sample_rate=SAMPLE_RATE, threshold=0.008, padding_ms=300):
    if y.size == 0:
        return y.astype(np.float32)

    y = y.astype(np.float32)
    abs_y = np.abs(y)
    noise_floor = float(np.percentile(abs_y, 20)) if abs_y.size else 0.0
    adaptive_threshold = max(threshold, noise_floor * 2.0)
    mask = abs_y > adaptive_threshold

    if not np.any(mask):
        return y

    indices = np.where(mask)[0]
    padding_samples = int(sample_rate * padding_ms / 1000)
    start = max(0, int(indices[0]) - padding_samples)
    end = min(y.size, int(indices[-1]) + padding_samples + 1)

    return y[start:end]


def normalize_rms(y, target_rms=TARGET_RMS, max_gain=3.0):
    if y.size == 0:
        return y.astype(np.float32)

    y = y.astype(np.float32)
    peak = float(np.max(np.abs(y)))

    if peak > 0:
        y = y / peak

    rms = float(np.sqrt(np.mean(np.square(y)))) if y.size else 0.0

    if rms > 0:
        gain = min(max_gain, target_rms / rms)
        y = np.clip(y * gain, -1.0, 1.0)

    return y.astype(np.float32)


def frame_audio(y, frame_length=512, hop_length=160):
    if y.size < frame_length:
        y = np.pad(y, (0, frame_length - y.size))

    frame_count = 1 + (y.size - frame_length) // hop_length
    frames = np.empty((frame_count, frame_length), dtype=np.float32)

    for index in range(frame_count):
        start = index * hop_length
        frames[index] = y[start:start + frame_length]

    return frames


def overlap_add(frames, original_length, hop_length=160):
    if frames.size == 0:
        return np.zeros(original_length, dtype=np.float32)

    frame_length = frames.shape[1]
    output_length = hop_length * (frames.shape[0] - 1) + frame_length
    output = np.zeros(output_length, dtype=np.float32)
    weights = np.zeros(output_length, dtype=np.float32)
    window = np.hanning(frame_length).astype(np.float32)
    window_weights = np.maximum(window ** 2, 1e-6)

    for index, frame in enumerate(frames):
        start = index * hop_length
        output[start:start + frame_length] += frame * window
        weights[start:start + frame_length] += window_weights

    output = output / np.maximum(weights, 1e-6)

    if output.size < original_length:
        output = np.pad(output, (0, original_length - output.size))

    return output[:original_length].astype(np.float32)


def spectral_gate_noise(y, sample_rate=SAMPLE_RATE, amount=NOISE_REDUCTION_AMOUNT):
    if y.size < 512:
        return y.astype(np.float32)

    y = y.astype(np.float32)
    frame_length = 512
    hop_length = max(80, int(sample_rate * 0.01))
    frames = frame_audio(y, frame_length=frame_length, hop_length=hop_length)
    window = np.hanning(frame_length).astype(np.float32)
    spectrum = np.fft.rfft(frames * window, axis=1)
    magnitude = np.abs(spectrum)
    phase = np.exp(1j * np.angle(spectrum))
    frame_rms = np.sqrt(np.mean(frames ** 2, axis=1) + 1e-8)

    quiet_limit = max(1, int(np.ceil(frame_rms.size * 0.10)))
    quiet_indices = np.argsort(frame_rms)[:quiet_limit]
    noise_profile = np.percentile(magnitude[quiet_indices], 35, axis=0)

    reduction = min(max(float(amount), 0.0), 0.95)
    floor_gain = max(0.65, 1.0 - reduction)
    cleaned_magnitude = np.maximum(magnitude - (noise_profile * reduction), magnitude * floor_gain)
    cleaned = np.fft.irfft(cleaned_magnitude * phase, n=frame_length, axis=1).astype(np.float32)

    return overlap_add(cleaned, original_length=y.size, hop_length=hop_length)


def reduce_background_noise(y, sample_rate=SAMPLE_RATE, amount=NOISE_REDUCTION_AMOUNT, stationary=None):
    if y.size == 0 or not ENABLE_DENOISE:
        return y.astype(np.float32)

    if stationary is None:
        stationary = NOISE_REDUCTION_STATIONARY

    if not USE_NOISEREDUCE_LIBRARY:
        return spectral_gate_noise(y, sample_rate=sample_rate, amount=amount)

    try:
        import noisereduce as nr

        return nr.reduce_noise(
            y=y.astype(np.float32),
            sr=sample_rate,
            stationary=bool(stationary),
            prop_decrease=min(max(float(amount), 0.0), 0.95),
        ).astype(np.float32)
    except Exception:
        return spectral_gate_noise(y, sample_rate=sample_rate, amount=amount)


def clean_recitation_audio(y, sample_rate=SAMPLE_RATE):
    y = remove_dc_offset(y)
    y = trim_silence(y, sample_rate=sample_rate)
    y = reduce_background_noise(y, sample_rate=sample_rate)
    y = normalize_rms(y)

    return y.astype(np.float32)


def convert_to_wav_with_pydub(audio_path, sample_rate=SAMPLE_RATE):
    """Convert browser/container audio to a temporary mono WAV via pydub."""

    from pydub import AudioSegment

    source = Path(audio_path)
    suffix = source.suffix.lower().lstrip(".") or None
    temporary = tempfile.NamedTemporaryFile(suffix=".wav", delete=False)
    temporary.close()

    segment = AudioSegment.from_file(str(source), format=suffix)
    segment = segment.set_channels(1).set_frame_rate(sample_rate)
    segment.export(temporary.name, format="wav")

    return temporary.name


def resample_audio_linear(y, original_rate, target_rate=SAMPLE_RATE):
    """Fast dependency-light resampling fallback for short browser recordings."""

    if y.size == 0 or int(original_rate) == int(target_rate):
        return y.astype(np.float32)

    duration = y.size / float(original_rate)
    target_length = max(1, int(round(duration * target_rate)))
    original_positions = np.linspace(0.0, duration, num=y.size, endpoint=False)
    target_positions = np.linspace(0.0, duration, num=target_length, endpoint=False)

    return np.interp(target_positions, original_positions, y).astype(np.float32)


def load_audio_with_librosa(audio_path, sample_rate=SAMPLE_RATE):
    """Load browser audio safely after pydub conversion.

    The project keeps this function name because it is part of the cleaned-audio
    pipeline, but the Windows runtime used by Laragon can hang inside
    librosa.load()/librosa.resample(). For request-time reliability we read the
    converted WAV with soundfile and use a small linear resampler only if needed.
    """

    import soundfile as sf

    source = Path(audio_path)
    converted_path = None
    path_for_load = str(source)
    conversion = "direct"
    resampler = "none"

    if source.suffix.lower() in {".webm", ".ogg", ".oga", ".m4a", ".mp3", ".mp4"}:
        converted_path = convert_to_wav_with_pydub(source, sample_rate=sample_rate)
        path_for_load = converted_path
        conversion = "pydub_wav"

    try:
        wave, loaded_rate = sf.read(path_for_load, dtype="float32", always_2d=False)
    finally:
        if converted_path and os.path.exists(converted_path):
            try:
                os.unlink(converted_path)
            except OSError:
                pass

    wave = np.asarray(wave, dtype=np.float32)

    if wave.ndim > 1:
        wave = np.mean(wave, axis=1).astype(np.float32)

    original_rate = int(loaded_rate)

    if original_rate != int(sample_rate):
        wave = resample_audio_linear(wave.reshape(-1), original_rate, int(sample_rate))
        resampler = "linear"

    return wave.astype(np.float32), int(sample_rate), {
        "loader": "soundfile",
        "conversion": conversion,
        "resampler": resampler,
        "original_sample_rate": original_rate,
        "source_extension": source.suffix.lower(),
    }


def trim_silence_librosa(y, sample_rate=SAMPLE_RATE, top_db=LIBROSA_TRIM_TOP_DB, padding_ms=250):
    """Trim leading/trailing silence with a fast librosa-style top-dB RMS check."""

    if y.size == 0:
        return y.astype(np.float32), {"trimmed": False, "start_sample": 0, "end_sample": 0}

    y = y.astype(np.float32)
    frame_length = 512
    hop_length = max(80, int(sample_rate * 0.01))
    frames = frame_audio(y, frame_length=frame_length, hop_length=hop_length)
    rms = np.sqrt(np.mean(frames**2, axis=1) + 1e-10)
    max_rms = float(np.max(rms)) if rms.size else 0.0

    if max_rms <= 1e-5:
        return y, {
            "trimmed": False,
            "start_sample": 0,
            "end_sample": int(y.size),
            "reason": "silent_or_too_quiet",
        }

    db_threshold = max_rms * (10.0 ** (-float(top_db) / 20.0))
    noise_floor = float(np.percentile(rms, 20)) if rms.size else 0.0
    threshold_candidates = [db_threshold, 1e-5]

    if noise_floor < max_rms * 0.7:
        threshold_candidates.append(noise_floor * 1.5)

    threshold = min(max(threshold_candidates), max_rms * 0.85)
    active = np.where(rms >= threshold)[0]

    if active.size == 0:
        return y, {
            "trimmed": False,
            "start_sample": 0,
            "end_sample": int(y.size),
            "reason": "no_active_frames",
            "top_db": top_db,
            "threshold_rms": round(float(threshold), 6),
            "max_rms": round(max_rms, 6),
        }

    start = int(active[0] * hop_length)
    end = int(min(y.size, active[-1] * hop_length + frame_length))

    if end <= start:
        return y, {"trimmed": False, "start_sample": 0, "end_sample": int(y.size)}

    padding = int(sample_rate * padding_ms / 1000)
    start = max(0, start - padding)
    end = min(y.size, end + padding)

    return y[start:end].astype(np.float32), {
        "trimmed": start > 0 or end < y.size,
        "start_sample": start,
        "end_sample": end,
        "top_db": top_db,
        "padding_ms": padding_ms,
        "threshold_rms": round(float(threshold), 6),
        "max_rms": round(max_rms, 6),
    }


def save_processed_wav(y, sample_rate=SAMPLE_RATE, output_path=None):
    """Save processed mono audio as PCM WAV using soundfile."""

    import soundfile as sf

    if output_path is None:
        temporary = tempfile.NamedTemporaryFile(suffix="_processed.wav", delete=False)
        temporary.close()
        output_path = temporary.name

    sf.write(output_path, np.asarray(y, dtype=np.float32), sample_rate, subtype="PCM_16")

    return output_path


def preprocess_audio_file_for_ml(
    audio_path,
    sample_rate=SAMPLE_RATE,
    target_rms=TARGET_RMS,
    noise_reduction_amount=NOISE_REDUCTION_AMOUNT,
    noise_reduction_stationary=True,
    save_wav=True,
):
    """Full preprocessing stack: pydub -> librosa -> denoise -> soundfile."""

    wave, loaded_rate, metadata = load_audio_with_librosa(audio_path, sample_rate=sample_rate)
    before_duration = wave.size / float(sample_rate) if sample_rate else 0.0
    before_rms = float(np.sqrt(np.mean(wave**2) + 1e-10)) if wave.size else 0.0

    wave = remove_dc_offset(wave)
    wave, trim_metadata = trim_silence_librosa(wave, sample_rate=sample_rate)
    wave = reduce_background_noise(
        wave,
        sample_rate=sample_rate,
        amount=noise_reduction_amount,
        stationary=noise_reduction_stationary,
    )
    wave = normalize_rms(wave, target_rms=target_rms, max_gain=4.0)

    processed_path = save_processed_wav(wave, sample_rate=sample_rate) if save_wav else None
    after_rms = float(np.sqrt(np.mean(wave**2) + 1e-10)) if wave.size else 0.0

    return {
        "wave": wave.astype(np.float32),
        "sample_rate": int(loaded_rate),
        "processed_path": processed_path,
        "metadata": {
            **metadata,
            "denoiser": "noisereduce_or_spectral_gate_fallback" if USE_NOISEREDUCE_LIBRARY else "spectral_gate",
            "writer": "soundfile",
            "before_duration_seconds": round(before_duration, 3),
            "after_duration_seconds": round(wave.size / float(sample_rate), 3) if sample_rate else 0.0,
            "before_rms": round(before_rms, 6),
            "after_rms": round(after_rms, 6),
            "target_rms": target_rms,
            "noise_reduction_amount": noise_reduction_amount,
            "noise_reduction_stationary": bool(noise_reduction_stationary),
            "trim": trim_metadata,
            "processed_path": processed_path,
        },
    }
