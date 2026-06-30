import numpy as np

SAMPLE_RATE = 16000
TARGET_RMS = 0.12


def remove_dc_offset(y):
    if y.size == 0:
        return y.astype(np.float32)

    return (y.astype(np.float32) - float(np.mean(y))).astype(np.float32)


def trim_silence(y, sample_rate=SAMPLE_RATE, threshold=0.02, padding_ms=180):
    if y.size == 0:
        return y.astype(np.float32)

    y = y.astype(np.float32)
    abs_y = np.abs(y)
    noise_floor = float(np.percentile(abs_y, 20)) if abs_y.size else 0.0
    adaptive_threshold = max(threshold, noise_floor * 3.0)
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


def clean_recitation_audio(y, sample_rate=SAMPLE_RATE):
    y = remove_dc_offset(y)
    y = trim_silence(y, sample_rate=sample_rate)
    y = normalize_rms(y)

    return y.astype(np.float32)
