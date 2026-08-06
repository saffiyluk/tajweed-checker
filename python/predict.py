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
LOW_CONFIDENCE_THRESHOLD = float(os.environ.get("TAJWEED_LOW_CONFIDENCE_THRESHOLD", "0.60"))
AMBIGUOUS_MARGIN_THRESHOLD = float(os.environ.get("TAJWEED_AMBIGUOUS_MARGIN_THRESHOLD", "0.15"))
CNN_WEIGHT = float(os.environ.get("TAJWEED_CNN_WEIGHT", "0.6"))
OTHER_GATE_THRESHOLD = float(os.environ.get("TAJWEED_OTHER_GATE_THRESHOLD", "0.70"))
CNN_STRONG_THRESHOLD = float(os.environ.get("TAJWEED_CNN_STRONG_THRESHOLD", "0.88"))
IKHFA_CNN_ONLY_THRESHOLD = float(os.environ.get("TAJWEED_IKHFA_CNN_ONLY_THRESHOLD", "0.78"))
RULE_CNN_ONLY_THRESHOLD = float(os.environ.get("TAJWEED_RULE_CNN_ONLY_THRESHOLD", "0.72"))
CNN_PRIORITY_MARGIN_THRESHOLD = float(os.environ.get("TAJWEED_CNN_PRIORITY_MARGIN_THRESHOLD", "0.18"))

# Audio-input quality gate. These thresholds are intentionally conservative:
# they catch silence/near-silence before the ML model turns empty audio into a
# false Ikhfa/Izhar prediction.
SILENT_RMS_THRESHOLD = float(os.environ.get("TAJWEED_SILENT_RMS_THRESHOLD", "0.003"))
SILENT_PEAK_THRESHOLD = float(os.environ.get("TAJWEED_SILENT_PEAK_THRESHOLD", "0.015"))
SILENT_ACTIVE_RATIO_THRESHOLD = float(os.environ.get("TAJWEED_SILENT_ACTIVE_RATIO_THRESHOLD", "0.025"))
QUIET_RMS_THRESHOLD = float(os.environ.get("TAJWEED_QUIET_RMS_THRESHOLD", "0.006"))
QUIET_PEAK_THRESHOLD = float(os.environ.get("TAJWEED_QUIET_PEAK_THRESHOLD", "0.035"))
QUIET_ACTIVE_RATIO_THRESHOLD = float(os.environ.get("TAJWEED_QUIET_ACTIVE_RATIO_THRESHOLD", "0.055"))
MIN_AUDIO_DURATION_SECONDS = float(os.environ.get("TAJWEED_MIN_AUDIO_DURATION_SECONDS", "0.75"))


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


# ===== REPORT SCREENSHOT START: Section 4.3.5 - Audio Pre-processing =====
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
# ===== REPORT SCREENSHOT END: Section 4.3.5 - Audio Pre-processing =====


def frame_audio(y, frame_length, hop_length):
    if y.size < frame_length:
        y = np.pad(y, (0, frame_length - y.size))

    frame_count = 1 + (y.size - frame_length) // hop_length
    frames = np.empty((frame_count, frame_length), dtype=np.float32)

    for index in range(frame_count):
        start = index * hop_length
        frames[index] = y[start:start + frame_length]

    return frames


def estimate_audio_activity(y, frame_length=512, hop_length=160):
    """Return raw activity features before normalization/denoising.

    This gate is used to reject silent or near-silent recordings. Without it,
    CNN/feature models may still output a tajweed label for empty audio.
    """

    y = np.asarray(y, dtype=np.float32)

    if y.size == 0:
        return {
            "raw_duration_ms": 0.0,
            "raw_rms": 0.0,
            "raw_peak_amplitude": 0.0,
            "raw_active_frame_ratio": 0.0,
            "raw_voiced_frame_ratio": 0.0,
            "audio_activity_status": "silent",
            "audio_activity_reason": "Audio contains no samples.",
            "is_silent": True,
            "is_too_quiet": True,
            "is_too_short": True,
            "minimum_duration_ms": round(MIN_AUDIO_DURATION_SECONDS * 1000.0, 2),
        }

    frames = frame_audio(y, frame_length, hop_length)
    frame_rms = np.sqrt(np.mean(frames ** 2, axis=1) + 1e-10)
    raw_rms = float(np.sqrt(np.mean(y ** 2) + 1e-10))
    peak = float(np.max(np.abs(y))) if y.size else 0.0
    noise_floor = float(np.percentile(frame_rms, 20)) if frame_rms.size else 0.0
    upper_activity_level = float(np.percentile(frame_rms, 90)) if frame_rms.size else noise_floor
    activity_dynamic_range = max(0.0, upper_activity_level - noise_floor)

    # A multiple of the 20th-percentile RMS is not a valid activity threshold
    # when a clip contains speech almost continuously. In that case the old
    # noise_floor * 3 rule could be greater than every frame and report 0%
    # activity for a loud, valid recitation. Interpolating inside the observed
    # frame-RMS range remains adaptive while keeping an absolute quiet floor.
    active_threshold = max(0.006, noise_floor + (activity_dynamic_range * 0.35))
    voiced_threshold = max(0.004, noise_floor + (activity_dynamic_range * 0.10))
    active_ratio = float(np.mean(frame_rms > active_threshold)) if frame_rms.size else 0.0
    voiced_ratio = float(np.mean(frame_rms > voiced_threshold)) if frame_rms.size else 0.0
    duration_seconds = y.size / float(SAMPLE_RATE)

    has_silent_level = raw_rms < SILENT_RMS_THRESHOLD or peak < SILENT_PEAK_THRESHOLD
    has_quiet_level = raw_rms < QUIET_RMS_THRESHOLD or peak < QUIET_PEAK_THRESHOLD
    is_silent = has_silent_level and active_ratio < SILENT_ACTIVE_RATIO_THRESHOLD
    is_too_quiet = is_silent or (has_quiet_level and active_ratio < QUIET_ACTIVE_RATIO_THRESHOLD)
    is_too_short = duration_seconds < MIN_AUDIO_DURATION_SECONDS

    if is_too_short:
        status = "too_short"
        reason = (
            f"Audio is too short for reliable tajweed validation; record at least "
            f"{MIN_AUDIO_DURATION_SECONDS:.2f} seconds."
        )
    elif is_silent:
        status = "silent"
        reason = "Audio is silent or has too little usable voice signal."
    elif is_too_quiet:
        status = "too_quiet"
        reason = "Audio is too quiet or unclear for reliable tajweed validation."
    else:
        status = "usable"
        reason = "Audio has enough signal for analysis."

    return {
        "raw_duration_ms": round(float(y.size / SAMPLE_RATE * 1000.0), 2),
        "raw_rms": round(raw_rms, 6),
        "raw_peak_amplitude": round(peak, 6),
        "raw_active_frame_ratio": round(active_ratio, 4),
        "raw_voiced_frame_ratio": round(voiced_ratio, 4),
        "audio_activity_status": status,
        "audio_activity_reason": reason,
        "is_silent": bool(is_silent),
        "is_too_quiet": bool(is_too_quiet),
        "is_too_short": bool(is_too_short),
        "minimum_duration_ms": round(MIN_AUDIO_DURATION_SECONDS * 1000.0, 2),
        "audio_activity_thresholds": {
            "active_rms": round(float(active_threshold), 6),
            "voiced_rms": round(float(voiced_threshold), 6),
            "noise_floor_rms": round(float(noise_floor), 6),
            "upper_activity_rms": round(float(upper_activity_level), 6),
            "silent_rms": SILENT_RMS_THRESHOLD,
            "silent_peak": SILENT_PEAK_THRESHOLD,
            "silent_active_ratio": SILENT_ACTIVE_RATIO_THRESHOLD,
            "quiet_rms": QUIET_RMS_THRESHOLD,
            "quiet_peak": QUIET_PEAK_THRESHOLD,
            "quiet_active_ratio": QUIET_ACTIVE_RATIO_THRESHOLD,
        },
    }


def audio_is_unusable(quality):
    return bool(
        quality.get("is_silent")
        or quality.get("is_too_quiet")
        or quality.get("is_too_short")
    )


def estimate_ghunnah_features(file_path):
    raw_y = load_audio(file_path, sample_rate=SAMPLE_RATE)
    activity = estimate_audio_activity(raw_y)
    y = clean_recitation_audio(raw_y, sample_rate=SAMPLE_RATE)

    frame_length = 512
    hop_length = 160
    frames = frame_audio(y, frame_length, hop_length)
    window = np.hanning(frame_length).astype(np.float32)
    spectrum = np.abs(np.fft.rfft(frames * window, axis=1)) ** 2
    frequencies = np.fft.rfftfreq(frame_length, d=1.0 / SAMPLE_RATE)

    total_power = np.sum(spectrum, axis=1) + 1e-8
    normalized_spectrum = spectrum / total_power[:, None]
    spectral_flux = 0.0

    if normalized_spectrum.shape[0] > 1:
        spectral_flux = float(np.mean(np.sqrt(np.sum(np.diff(normalized_spectrum, axis=0) ** 2, axis=1))))

    nasal_band = np.sum(spectrum[:, (frequencies >= 220) & (frequencies < 950)], axis=1) / total_power
    formant_band = np.sum(spectrum[:, (frequencies >= 950) & (frequencies < 2400)], axis=1) / total_power
    fricative_band = np.sum(spectrum[:, (frequencies >= 2500) & (frequencies < 7500)], axis=1) / total_power
    frame_rms = np.sqrt(np.mean(frames ** 2, axis=1) + 1e-8)
    zero_crossing_rate = np.mean(np.diff(np.signbit(frames), axis=1), axis=1)
    voiced_threshold = max(0.0045, float(np.percentile(frame_rms, 50)) * 0.38)
    voiced_frames = frame_rms > voiced_threshold
    nasal_score = nasal_band - (fricative_band * 0.35) - (zero_crossing_rate * 0.25)

    if np.any(voiced_frames):
        score_threshold = max(0.10, float(np.percentile(nasal_score[voiced_frames], 55)))
    else:
        score_threshold = 0.10

    nasal_candidates = (
        voiced_frames
        & (nasal_band > 0.18)
        & (nasal_score >= score_threshold)
        & (fricative_band < 0.62)
        & (zero_crossing_rate < 0.30)
    )

    if nasal_candidates.size >= 5:
        smoothed = np.convolve(nasal_candidates.astype(np.int16), np.ones(5, dtype=np.int16), mode="same")
        nasal_frames = smoothed >= 2
    else:
        nasal_frames = nasal_candidates

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
    voiced_rms = frame_rms[voiced_frames]
    rms_stability = 0.0

    if voiced_rms.size:
        rms_stability = 1.0 - min(1.0, float(np.std(voiced_rms) / (np.mean(voiced_rms) + 1e-8)))

    formant_transition_score = 0.0

    if formant_band.size > 1:
        formant_transition_score = float(np.mean(np.abs(np.diff(formant_band))))

    transition_smoothness = max(0.0, min(1.0, (1.0 - min(1.0, spectral_flux * 4.0)) * 0.65 + rms_stability * 0.35))

    return {
        **activity,
        "duration_ms": round(float(y.size / SAMPLE_RATE * 1000.0), 2),
        "ghunnah_duration_ms": round(float(ghunnah_duration_ms), 2),
        "ghunnah_frame_ratio": round(ghunnah_frame_ratio, 4),
        "ghunnah_strength": round(ghunnah_strength, 4),
        "spectral_flux": round(float(spectral_flux), 5),
        "rms_stability": round(float(rms_stability), 4),
        "transition_smoothness": round(float(transition_smoothness), 4),
        "formant_transition_score": round(float(formant_transition_score), 5),
        "ghunnah_segments": segments,
        "ghunnah_thresholds": {
            "voiced_rms": round(float(voiced_threshold), 5),
            "nasal_score": round(float(score_threshold), 5),
        },
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


# ===== REPORT SCREENSHOT START: Section 4.3.8 - Confidence Score Interpretation =====
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
# ===== REPORT SCREENSHOT END: Section 4.3.8 - Confidence Score Interpretation =====


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

    # Run the independent summary-feature rejection gate before allowing a CNN
    # rule-priority shortcut. This branch used to require the CNN to have no
    # `other` class, which made it unreachable for the current three-class CNN.
    # A strong Random Forest `other` result now rejects a merely moderate CNN
    # rule result; a genuinely strong CNN result can still proceed.
    feature_says_other = feature_result["raw_prediction"] == "other"
    cnn_says_rule = cnn_result["raw_prediction"] in {"ikhfa", "izhar"}

    if (
        feature_says_other
        and cnn_says_rule
        and feature_result["confidence"] >= OTHER_GATE_THRESHOLD
        and cnn_result["confidence"] < CNN_STRONG_THRESHOLD
    ):
        result = dict(feature_result)
        result["method"] = "feature_other_gate"
        result["weights"] = {
            "cnn": 0.0,
            "feature_model": 1.0,
        }
        result["reason"] = (
            "Summary-feature model strongly rejected the recording as Other while "
            "the CNN rule result was below the strong-confidence threshold."
        )
        return result

    if (
        cnn_result["raw_prediction"] in {"ikhfa", "izhar"}
        and cnn_result["confidence"] >= RULE_CNN_ONLY_THRESHOLD
        and cnn_result.get("margin", 0.0) >= CNN_PRIORITY_MARGIN_THRESHOLD
    ):
        result = dict(cnn_result)
        result["method"] = "cnn_rule_priority"
        result["weights"] = {
            "cnn": 1.0,
            "feature_model": 0.0,
        }
        result["reason"] = "CNN detected the selected Tajweed rule clearly; summary feature model is ignored for rule decision stability."
        return result

    if (
        cnn_result["raw_prediction"] == "ikhfa"
        and cnn_result["confidence"] >= IKHFA_CNN_ONLY_THRESHOLD
        and cnn_result.get("margin", 0.0) >= CNN_PRIORITY_MARGIN_THRESHOLD
    ):
        result = dict(cnn_result)
        result["method"] = "cnn_ikhfa_priority"
        result["weights"] = {
            "cnn": 1.0,
            "feature_model": 0.0,
        }
        result["reason"] = "CNN detected Ikhfa clearly; summary feature model is ignored for this local nasal event."
        return result

    # ===== REPORT SCREENSHOT START: Section 4.3.7 - Hybrid Rule-Pattern Classification =====
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
    # ===== REPORT SCREENSHOT END: Section 4.3.7 - Hybrid Rule-Pattern Classification =====


def main():
    if len(sys.argv) < 2:
        raise ValueError("Audio file path is required.")

    file_path = sys.argv[1]
    quality = estimate_ghunnah_features(file_path)

    if audio_is_unusable(quality):
        result = {
            "prediction": "other",
            "raw_prediction": "other",
            "confidence": 0.0,
            "margin": 0.0,
            "probabilities": {},
            "ikhfa_confidence": None,
            "izhar_confidence": None,
            "other_confidence": 1.0,
            "status": "unrelated",
            "method": "audio_quality_gate",
            "reason": quality.get("audio_activity_reason", "Audio is not usable for Tajweed validation."),
            "quality": quality,
            "cnn": None,
            "feature_model": None,
        }
        print(json.dumps(result))
        return

    cnn_result = predict_cnn(file_path)
    feature_result = predict_feature_model(file_path)
    ensemble_result = combine_predictions(cnn_result, feature_result)

    result = {
        **ensemble_result,
        "quality": quality,
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
