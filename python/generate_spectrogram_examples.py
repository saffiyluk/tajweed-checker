import argparse
from pathlib import Path

import matplotlib
import numpy as np
import soundfile as sf
from scipy import signal


matplotlib.use("Agg")
import matplotlib.pyplot as plt

BASE_DIR = Path(__file__).resolve().parent
DEFAULT_OUTPUT_DIR = BASE_DIR.parent / "public" / "spectrograms"
SAMPLE_RATE = 16000


def first_audio_file(directory):
    for suffix in ("*.wav", "*.mp3", "*.webm", "*.m4a"):
        files = sorted(Path(directory).glob(suffix))
        if files:
            return files[0]

    raise FileNotFoundError(f"No audio file found in {directory}")


def load_audio(path):
    y, sr = sf.read(path, dtype="float32", always_2d=False)

    if y.ndim > 1:
        y = np.mean(y, axis=1)

    if sr != SAMPLE_RATE:
        gcd = int(np.gcd(sr, SAMPLE_RATE))
        y = signal.resample_poly(y, SAMPLE_RATE // gcd, sr // gcd).astype(np.float32)
        sr = SAMPLE_RATE

    if y.size == 0:
        raise ValueError(f"Audio file is empty: {path}")

    return y, sr


def power_to_db(power):
    power = np.maximum(power, 1e-12)
    reference = max(float(np.max(power)), 1e-12)
    return 10.0 * np.log10(power / reference)


def audio_metrics(y, sr):
    hop_length = 160
    n_fft = 512
    _, _, stft = signal.stft(
        y,
        fs=sr,
        window="hann",
        nperseg=n_fft,
        noverlap=n_fft - hop_length,
        boundary=None,
    )
    spectrum = np.abs(stft) ** 2
    frequencies = np.fft.rfftfreq(n_fft, d=1.0 / sr)
    total_power = np.sum(spectrum, axis=0) + 1e-8

    nasal_ratio = np.sum(spectrum[(frequencies >= 220) & (frequencies < 950), :], axis=0) / total_power
    fricative_ratio = np.sum(spectrum[(frequencies >= 2500) & (frequencies < 7500), :], axis=0) / total_power
    rms = np.sqrt(np.mean(np.lib.stride_tricks.sliding_window_view(
        np.pad(y, (0, max(0, n_fft - y.size))),
        n_fft,
    )[::hop_length] ** 2, axis=1) + 1e-8)

    return {
        "duration_s": round(float(y.size / sr), 2),
        "mean_nasal_ratio": round(float(np.mean(nasal_ratio)), 4),
        "max_nasal_ratio": round(float(np.max(nasal_ratio)), 4),
        "mean_fricative_ratio": round(float(np.mean(fricative_ratio)), 4),
        "rms_mean": round(float(np.mean(rms)), 5),
    }


def plot_pair(ikhfa_path, izhar_path, output_path):
    items = [("Ikhfa", ikhfa_path), ("Izhar", izhar_path)]
    fig, axes = plt.subplots(2, 2, figsize=(15, 8), constrained_layout=True)

    for row, (label, path) in enumerate(items):
        y, sr = load_audio(path)
        duration = y.size / sr
        times = np.linspace(0, duration, num=y.size)
        metrics = audio_metrics(y, sr)

        axes[row, 0].plot(times, y, linewidth=0.7)
        axes[row, 0].set_title(f"{label} waveform - {path.name}")
        axes[row, 0].set_xlabel("Time (s)")
        axes[row, 0].set_ylabel("Amplitude")
        axes[row, 0].grid(alpha=0.25)

        frequencies, _, stft = signal.stft(
            y,
            fs=sr,
            window="hann",
            nperseg=1024,
            noverlap=1024 - 160,
            boundary=None,
        )
        mel = np.abs(stft) ** 2
        mel = mel[frequencies <= 8000, :]
        mel_db = power_to_db(mel)
        img = axes[row, 1].imshow(
            mel_db,
            origin="lower",
            aspect="auto",
            extent=[0, duration, 0, 8000],
            cmap="magma",
        )
        axes[row, 1].set_xlabel("Time (s)")
        axes[row, 1].set_ylabel("Frequency (mel-scaled bins)")
        axes[row, 1].set_title(
            f"{label} mel spectrogram | nasal mean={metrics['mean_nasal_ratio']} max={metrics['max_nasal_ratio']}"
        )
        fig.colorbar(img, ax=axes[row, 1], format="%+2.0f dB")

    fig.suptitle("Ikhfa vs Izhar: waveform and mel spectrogram", fontsize=16)
    fig.savefig(output_path, dpi=160)
    plt.close(fig)


def plot_low_frequency_zoom(ikhfa_path, izhar_path, output_path):
    items = [("Ikhfa", ikhfa_path), ("Izhar", izhar_path)]
    fig, axes = plt.subplots(2, 1, figsize=(15, 8), constrained_layout=True)

    for row, (label, path) in enumerate(items):
        y, sr = load_audio(path)
        frequencies, _, stft = signal.stft(
            y,
            fs=sr,
            window="hann",
            nperseg=1024,
            noverlap=1024 - 160,
            boundary=None,
        )
        db = power_to_db(np.abs(stft) ** 2)
        duration = y.size / sr
        img = axes[row].imshow(
            db,
            origin="lower",
            aspect="auto",
            extent=[0, duration, 0, sr / 2],
            cmap="magma",
        )
        axes[row].set_xlabel("Time (s)")
        axes[row].set_ylabel("Frequency (Hz)")
        axes[row].set_ylim(0, 2500)
        axes[row].axhspan(220, 950, color="cyan", alpha=0.14, label="Approx. nasal band 220-950 Hz")
        axes[row].set_title(f"{label} low-frequency zoom - {path.name}")
        axes[row].legend(loc="upper right")
        fig.colorbar(img, ax=axes[row], format="%+2.0f dB")

    fig.suptitle("Low-frequency / nasal-band comparison", fontsize=16)
    fig.savefig(output_path, dpi=160)
    plt.close(fig)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--ikhfa", default=str(first_audio_file(BASE_DIR / "Dataset" / "ikhfa")))
    parser.add_argument("--izhar", default=str(first_audio_file(BASE_DIR / "Dataset" / "izhar")))
    parser.add_argument("--out-dir", default=str(DEFAULT_OUTPUT_DIR))
    args = parser.parse_args()

    output_dir = Path(args.out_dir)
    output_dir.mkdir(parents=True, exist_ok=True)

    ikhfa_path = Path(args.ikhfa)
    izhar_path = Path(args.izhar)
    comparison_path = output_dir / "ikhfa_vs_izhar_spectrogram.png"
    zoom_path = output_dir / "ikhfa_vs_izhar_low_frequency_zoom.png"

    plot_pair(ikhfa_path, izhar_path, comparison_path)
    plot_low_frequency_zoom(ikhfa_path, izhar_path, zoom_path)

    print(f"Ikhfa sample: {ikhfa_path}")
    print(f"Izhar sample: {izhar_path}")
    print(f"Saved: {comparison_path}")
    print(f"Saved: {zoom_path}")


if __name__ == "__main__":
    main()
