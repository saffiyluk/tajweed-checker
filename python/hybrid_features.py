import os
import subprocess
import tempfile

import numpy as np
import soundfile as sf

from audio_cleaning import SAMPLE_RATE, clean_recitation_audio

TARGET_SECONDS = 2
TARGET_LENGTH = SAMPLE_RATE * TARGET_SECONDS


def load_audio(file_path, sample_rate=SAMPLE_RATE):
    temp_wav = None

    try:
        y, sr = sf.read(file_path, always_2d=False)
    except Exception:
        temp = tempfile.NamedTemporaryFile(suffix=".wav", delete=False)
        temp.close()
        temp_wav = temp.name

        subprocess.run(
            [
                "ffmpeg",
                "-y",
                "-loglevel",
                "error",
                "-i",
                file_path,
                "-ac",
                "1",
                "-ar",
                str(sample_rate),
                temp_wav,
            ],
            check=True,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.PIPE,
        )

        y, sr = sf.read(temp_wav, always_2d=False)
    finally:
        if temp_wav and os.path.exists(temp_wav):
            try:
                os.unlink(temp_wav)
            except OSError:
                pass

    if y.ndim > 1:
        y = np.mean(y, axis=1)

    y = y.astype(np.float32)

    if sr != sample_rate:
        y = resample_audio(y, sr, sample_rate)

    return y


def resample_audio(y, original_rate, target_rate):
    if y.size == 0 or original_rate == target_rate:
        return y.astype(np.float32)

    duration = y.size / float(original_rate)
    target_length = max(1, int(round(duration * target_rate)))
    target_positions = np.linspace(0, y.size - 1, num=target_length, dtype=np.float32)
    source_positions = np.linspace(0, y.size - 1, num=y.size, dtype=np.float32)

    return np.interp(target_positions, source_positions, y).astype(np.float32)


def fixed_length_audio(file_path):
    y = clean_recitation_audio(load_audio(file_path), sample_rate=SAMPLE_RATE)
    return np.pad(y, (0, max(0, TARGET_LENGTH - len(y))))[:TARGET_LENGTH]


def frame_audio(y, frame_length=512, hop_length=256):
    if y.size < frame_length:
        y = np.pad(y, (0, frame_length - y.size))

    frame_count = 1 + (y.size - frame_length) // hop_length
    frames = np.empty((frame_count, frame_length), dtype=np.float32)

    for index in range(frame_count):
        start = index * hop_length
        frames[index] = y[start:start + frame_length]

    return frames


def summarize(values):
    values = np.asarray(values, dtype=np.float32)

    return np.array(
        [
            np.mean(values),
            np.std(values),
            np.min(values),
            np.max(values),
            np.percentile(values, 25),
            np.percentile(values, 50),
            np.percentile(values, 75),
        ],
        dtype=np.float32,
    )


def band_energy_features(power, frequencies):
    bands = [
        (0, 300),
        (300, 600),
        (600, 1000),
        (1000, 1600),
        (1600, 2400),
        (2400, 3400),
        (3400, 5000),
        (5000, 8000),
    ]

    total_power = np.sum(power, axis=1) + 1e-8
    features = []

    for low, high in bands:
        mask = (frequencies >= low) & (frequencies < high)
        band_power = np.sum(power[:, mask], axis=1) / total_power
        features.extend(summarize(band_power))

    return np.array(features, dtype=np.float32)


def extract_summary_features(file_path):
    y = fixed_length_audio(file_path)
    frames = frame_audio(y)
    window = np.hanning(frames.shape[1]).astype(np.float32)
    spectrum = np.fft.rfft(frames * window, axis=1)
    power = np.abs(spectrum) ** 2
    frequencies = np.fft.rfftfreq(frames.shape[1], d=1.0 / SAMPLE_RATE)

    frame_energy = np.sqrt(np.mean(frames ** 2, axis=1) + 1e-8)
    zero_crossing_rate = np.mean(np.diff(np.signbit(frames), axis=1), axis=1)
    power_sum = np.sum(power, axis=1) + 1e-8
    centroid = np.sum(power * frequencies, axis=1) / power_sum
    bandwidth = np.sqrt(np.sum(power * ((frequencies - centroid[:, None]) ** 2), axis=1) / power_sum)
    peak_frequency = frequencies[np.argmax(power, axis=1)]

    features = np.concatenate(
        [
            summarize(frame_energy),
            summarize(zero_crossing_rate),
            summarize(centroid),
            summarize(bandwidth),
            summarize(peak_frequency),
            band_energy_features(power, frequencies),
        ]
    )

    return np.nan_to_num(features.astype(np.float32))
