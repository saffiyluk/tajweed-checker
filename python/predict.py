import os
import sys
import json
import hashlib
import subprocess
import tempfile
import random
import pickle

os.environ.setdefault("TF_CPP_MIN_LOG_LEVEL", "3")
os.environ.setdefault("TF_ENABLE_ONEDNN_OPTS", "0")


def install_deterministic_urandom_fallback():
    counter = 0

    def deterministic_urandom(size):
        nonlocal counter
        chunks = []

        while sum(len(chunk) for chunk in chunks) < size:
            counter += 1
            chunks.append(hashlib.sha256(f"tajweed-{counter}".encode("ascii")).digest())

        return b"".join(chunks)[:size]

    os.urandom = deterministic_urandom
    random._urandom = deterministic_urandom


install_deterministic_urandom_fallback()

try:
    import numpy as np
    import soundfile as sf
    from tensorflow.keras.models import load_model
    from audio_cleaning import SAMPLE_RATE, clean_recitation_audio
except ModuleNotFoundError as e:
    print(
        json.dumps(
            {
                "error": f"Missing Python dependency: {e.name}. Install dependencies with: python -m pip install -r python/requirements.txt",
                "status": "failed",
            }
        )
    )
    sys.exit(1)
except Exception as e:
    print(
        json.dumps(
            {
                "error": f"Failed to load Python prediction dependencies: {e}",
                "status": "failed",
            }
        )
    )
    sys.exit(1)

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
MODEL_PATH = os.path.join(BASE_DIR, "tajweed_model.keras")
FEATURE_MODEL_PATH = os.path.join(BASE_DIR, "feature_model.pkl")
LABEL_ENCODER_PATH = os.path.join(BASE_DIR, "label_encoder.pkl")
CLASSES = ["ikhfa", "izhar"]
LOW_CONFIDENCE_THRESHOLD = 0.55
AMBIGUOUS_MARGIN_THRESHOLD = 0.10
CNN_WEIGHT = float(os.environ.get("TAJWEED_CNN_WEIGHT", "0.6"))
OTHER_GATE_THRESHOLD = float(os.environ.get("TAJWEED_OTHER_GATE_THRESHOLD", "0.65"))
CNN_STRONG_THRESHOLD = float(os.environ.get("TAJWEED_CNN_STRONG_THRESHOLD", "0.85"))


def load_classes(output_size):
    if os.path.exists(LABEL_ENCODER_PATH):
        try:
            with open(LABEL_ENCODER_PATH, "rb") as f:
                encoder = pickle.load(f)
            classes = [str(item) for item in encoder.classes_]

            if len(classes) == output_size:
                return classes
        except Exception:
            pass

    if output_size == len(CLASSES):
        return CLASSES

    return [f"class_{index}" for index in range(output_size)]


def hz_to_mel(hz):
    return 2595.0 * np.log10(1.0 + hz / 700.0)


def mel_to_hz(mel):
    return 700.0 * (10.0 ** (mel / 2595.0) - 1.0)


def mel_filterbank(sample_rate, n_fft, n_mels):
    min_mel = hz_to_mel(0)
    max_mel = hz_to_mel(sample_rate / 2)
    mel_points = np.linspace(min_mel, max_mel, n_mels + 2)
    hz_points = mel_to_hz(mel_points)
    bins = np.floor((n_fft + 1) * hz_points / sample_rate).astype(int)

    filters = np.zeros((n_mels, n_fft // 2 + 1), dtype=np.float32)

    for i in range(1, n_mels + 1):
        left, center, right = bins[i - 1], bins[i], bins[i + 1]

        if center > left:
            filters[i - 1, left:center] = (np.arange(left, center) - left) / (center - left)

        if right > center:
            filters[i - 1, center:right] = (right - np.arange(center, right)) / (right - center)

    return filters


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
    source_positions = np.linspace(0, y.size - 1, num=y.size, dtype=np.float32)
    target_positions = np.linspace(0, y.size - 1, num=target_length, dtype=np.float32)

    return np.interp(target_positions, source_positions, y).astype(np.float32)


def preprocess(file_path):
    y = load_audio(file_path, sample_rate=SAMPLE_RATE)
    y = clean_recitation_audio(y, sample_rate=SAMPLE_RATE)

    target_length = SAMPLE_RATE * 2
    y = np.pad(y, (0, max(0, target_length - len(y))))[:target_length]

    n_fft = 2048
    hop_length = 512
    frames = frame_audio(y, n_fft, hop_length)
    window = np.hanning(n_fft).astype(np.float32)
    spectrum = np.fft.rfft(frames * window, n=n_fft, axis=1).T

    power = np.abs(spectrum) ** 2
    mel = np.dot(mel_filterbank(SAMPLE_RATE, n_fft, 128), power)
    mel = 10.0 * np.log10(np.maximum(mel, 1e-10))
    mel = mel - np.max(mel)
    mel = (mel - mel.min()) / (mel.max() - mel.min() + 1e-8)

    mel = mel[..., np.newaxis]
    mel = np.expand_dims(mel, axis=0)
    return mel


def frame_audio(y, frame_length, hop_length):
    if y.size < frame_length:
        y = np.pad(y, (0, frame_length - y.size))

    frame_count = 1 + (y.size - frame_length) // hop_length
    frames = np.empty((frame_count, frame_length), dtype=np.float32)

    for index in range(frame_count):
        start = index * hop_length
        frames[index] = y[start:start + frame_length]

    return frames


def estimate_ghunnah_features(file_path):
    y = load_audio(file_path, sample_rate=SAMPLE_RATE)
    y = clean_recitation_audio(y, sample_rate=SAMPLE_RATE)

    frame_length = 512
    hop_length = 160
    frames = frame_audio(y, frame_length, hop_length)
    window = np.hanning(frame_length).astype(np.float32)
    spectrum = np.abs(np.fft.rfft(frames * window, axis=1)) ** 2
    frequencies = np.fft.rfftfreq(frame_length, d=1.0 / SAMPLE_RATE)

    total_power = np.sum(spectrum, axis=1) + 1e-8
    nasal_band = np.sum(spectrum[:, (frequencies >= 220) & (frequencies < 950)], axis=1) / total_power
    fricative_band = np.sum(spectrum[:, (frequencies >= 2500) & (frequencies < 7500)], axis=1) / total_power
    frame_rms = np.sqrt(np.mean(frames ** 2, axis=1) + 1e-8)
    zero_crossing_rate = np.mean(np.diff(np.signbit(frames), axis=1), axis=1)
    voiced_threshold = max(0.015, float(np.percentile(frame_rms, 60)) * 0.65)

    nasal_frames = (
        (frame_rms > voiced_threshold)
        & (nasal_band > 0.28)
        & (fricative_band < 0.42)
        & (zero_crossing_rate < 0.16)
    )

    longest_run = 0
    current_run = 0

    for is_nasal_frame in nasal_frames:
        current_run = current_run + 1 if is_nasal_frame else 0
        longest_run = max(longest_run, current_run)

    segments = []
    start_index = None

    for index, is_nasal_frame in enumerate(nasal_frames):
        if is_nasal_frame and start_index is None:
            start_index = index
        elif not is_nasal_frame and start_index is not None:
            end_index = index - 1
            segments.append(
                {
                    "start_ms": round(start_index * hop_length / SAMPLE_RATE * 1000.0, 2),
                    "end_ms": round((end_index * hop_length + frame_length) / SAMPLE_RATE * 1000.0, 2),
                    "duration_ms": round(((end_index - start_index + 1) * hop_length) / SAMPLE_RATE * 1000.0, 2),
                }
            )
            start_index = None

    if start_index is not None:
        end_index = len(nasal_frames) - 1
        segments.append(
            {
                "start_ms": round(start_index * hop_length / SAMPLE_RATE * 1000.0, 2),
                "end_ms": round((end_index * hop_length + frame_length) / SAMPLE_RATE * 1000.0, 2),
                "duration_ms": round(((end_index - start_index + 1) * hop_length) / SAMPLE_RATE * 1000.0, 2),
            }
        )

    ghunnah_duration_ms = longest_run * hop_length / SAMPLE_RATE * 1000.0
    ghunnah_frame_ratio = float(np.mean(nasal_frames)) if nasal_frames.size else 0.0
    ghunnah_strength = float(np.mean(nasal_band[nasal_frames])) if np.any(nasal_frames) else 0.0

    return {
        "duration_ms": round(float(y.size / SAMPLE_RATE * 1000.0), 2),
        "ghunnah_duration_ms": round(float(ghunnah_duration_ms), 2),
        "ghunnah_frame_ratio": round(ghunnah_frame_ratio, 4),
        "ghunnah_strength": round(ghunnah_strength, 4),
        "ghunnah_segments": segments,
    }


def probability_map(classes, probabilities):
    return dict(zip(classes, [float(value) for value in probabilities]))


def aligned_probabilities(probabilities, classes):
    return np.array([float(probabilities.get(label, 0.0)) for label in classes], dtype=np.float32)


def normalize_probabilities(probabilities):
    total = float(np.sum(probabilities))

    if total <= 0:
        return np.full_like(probabilities, 1.0 / len(probabilities), dtype=np.float32)

    return (probabilities / total).astype(np.float32)


def classify_probabilities(probabilities, classes):
    probabilities = normalize_probabilities(np.array(probabilities, dtype=np.float32))
    index = int(np.argmax(probabilities))
    confidence = float(probabilities[index])
    sorted_probabilities = sorted([float(value) for value in probabilities], reverse=True)
    runner_up = sorted_probabilities[1] if len(sorted_probabilities) > 1 else 0.0
    margin = confidence - runner_up
    raw_prediction = classes[index]
    prediction = raw_prediction
    status = "confident"

    if raw_prediction == "other":
        status = "unrelated"
    elif "other" not in classes and confidence < LOW_CONFIDENCE_THRESHOLD and margin < AMBIGUOUS_MARGIN_THRESHOLD:
        prediction = "other"
        status = "unrelated"
    elif confidence < 0.70:
        status = "uncertain"

    return {
        "prediction": prediction,
        "raw_prediction": raw_prediction,
        "confidence": confidence,
        "margin": margin,
        "probabilities": probability_map(classes, probabilities),
        "ikhfa_confidence": float(probabilities[classes.index("ikhfa")]) if "ikhfa" in classes else None,
        "izhar_confidence": float(probabilities[classes.index("izhar")]) if "izhar" in classes else None,
        "other_confidence": float(probabilities[classes.index("other")]) if "other" in classes else None,
        "status": status,
    }


def predict_cnn(file_path):
    model = load_model(MODEL_PATH)
    sample = preprocess(file_path)
    pred = model.predict(sample, verbose=0)[0]
    classes = load_classes(len(pred))
    result = classify_probabilities(pred, classes)
    result["model_path"] = MODEL_PATH
    return result


def predict_feature_model(file_path):
    if not os.path.exists(FEATURE_MODEL_PATH):
        return None

    from hybrid_features import extract_summary_features

    with open(FEATURE_MODEL_PATH, "rb") as f:
        payload = pickle.load(f)

    model = payload["model"]
    classes = [str(item) for item in payload["classes"]]
    features = extract_summary_features(file_path).reshape(1, -1)

    if hasattr(model, "predict_proba"):
        pred = model.predict_proba(features)[0]
    else:
        predicted = int(model.predict(features)[0])
        pred = np.zeros(len(classes), dtype=np.float32)
        pred[predicted] = 1.0

    result = classify_probabilities(pred, classes)
    result["model_path"] = FEATURE_MODEL_PATH
    return result


def combine_predictions(cnn_result, feature_result):
    if not feature_result:
        result = dict(cnn_result)
        result["method"] = "cnn_only"
        return result

    cnn_has_other = "other" in cnn_result["probabilities"]
    feature_says_other = feature_result["raw_prediction"] == "other"

    if (
        feature_says_other
        and not cnn_has_other
        and feature_result["confidence"] >= OTHER_GATE_THRESHOLD
        and cnn_result["confidence"] < CNN_STRONG_THRESHOLD
    ):
        result = dict(feature_result)
        result["method"] = "feature_other_gate"
        result["weights"] = {
            "cnn": 0.0,
            "feature_model": 1.0,
        }
        return result

    classes = sorted(set(cnn_result["probabilities"].keys()) | set(feature_result["probabilities"].keys()))
    cnn_probs = aligned_probabilities(cnn_result["probabilities"], classes)
    feature_probs = aligned_probabilities(feature_result["probabilities"], classes)

    cnn_weight = min(max(CNN_WEIGHT, 0.0), 1.0)
    feature_weight = 1.0 - cnn_weight
    combined = normalize_probabilities((cnn_probs * cnn_weight) + (feature_probs * feature_weight))

    result = classify_probabilities(combined, classes)
    result["method"] = "weighted_ensemble"
    result["weights"] = {
        "cnn": cnn_weight,
        "feature_model": feature_weight,
    }
    result["model_path"] = MODEL_PATH
    return result


def main():
    if len(sys.argv) < 2:
        raise ValueError("Audio file path is required.")

    file_path = sys.argv[1]
    cnn_result = predict_cnn(file_path)
    feature_result = predict_feature_model(file_path)
    ensemble_result = combine_predictions(cnn_result, feature_result)

    result = {
        **ensemble_result,
        "quality": estimate_ghunnah_features(file_path),
        "cnn": cnn_result,
        "feature_model": feature_result,
    }

    print(json.dumps(result))


if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        print(
            json.dumps(
                {
                    "error": str(e),
                    "status": "failed",
                }
            )
        )
