import json
import os
import pickle
import sys
import tempfile
from pathlib import Path

import numpy as np
import soundfile as sf

from build_target_window_dataset import crop_window, load_audio, normalize_letter
from audio_cleaning import SAMPLE_RATE
from hybrid_features import extract_summary_features

BASE_DIR = Path(__file__).resolve().parent
MODEL_PATH = BASE_DIR / "target_window_model.pkl"
DEFAULT_WINDOW_SECONDS = float(os.environ.get("TAJWEED_TARGET_WINDOW_SECONDS", "2.4"))

IKHFA_FOLLOWING_LETTERS = {
    "\u062a", "\u062b", "\u062c", "\u062f", "\u0630", "\u0632", "\u0633", "\u0634",
    "\u0635", "\u0636", "\u0637", "\u0638", "\u0641", "\u0642", "\u0643",
}
IZHAR_FOLLOWING_LETTERS = {
    "\u0621", "\u0627", "\u0647", "\u0639", "\u062d", "\u063a", "\u062e",
}

# The correctness rule uses one target-local duration boundary for both rules:
# Ikhfa must reach it, while Izhar must not exceed it.  Ratio, strength, and the
# composite nasal score remain useful diagnostics but do not decide correctness.
ELONGATION_THRESHOLD_MS = float(os.environ.get("TAJWEED_ELONGATION_THRESHOLD_MS", "50"))
ELONGATION_LOCAL_WINDOW_SECONDS = float(
    os.environ.get("TAJWEED_ELONGATION_LOCAL_WINDOW_SECONDS", "0.60")
)
IZHAR_MAX_DECISIVE_SCAN_OFFSET = float(os.environ.get("TAJWEED_IZHAR_MAX_DECISIVE_SCAN_OFFSET", "0.10"))

ML_TRUST_THRESHOLD = float(os.environ.get("TAJWEED_TARGET_ML_TRUST_THRESHOLD", "0.78"))
ML_STRONG_THRESHOLD = float(os.environ.get("TAJWEED_TARGET_ML_STRONG_THRESHOLD", "0.88"))

TARGET_SILENT_RMS_THRESHOLD = float(os.environ.get("TAJWEED_TARGET_SILENT_RMS_THRESHOLD", "0.002"))
TARGET_SILENT_PEAK_THRESHOLD = float(os.environ.get("TAJWEED_TARGET_SILENT_PEAK_THRESHOLD", "0.010"))
SCAN_STEP_SECONDS = float(os.environ.get("TAJWEED_TARGET_SCAN_STEP_SECONDS", "0.45"))
MAX_SCAN_CANDIDATES = int(os.environ.get("TAJWEED_TARGET_MAX_SCAN_CANDIDATES", "18"))


def safe_target_ratio(target):
    """Return a 0.0-1.0 target position.

    The Laravel app normally sends letter_position + total_letters, while manual
    CLI tests often send position_ratio. Accept both so the script does not fail
    with KeyError: 'letter_position'.
    """

    for key in ("position_ratio", "target_ratio", "ratio", "window_ratio"):
        if key in target and target[key] is not None:
            try:
                return min(1.0, max(0.0, float(target[key])))
            except (TypeError, ValueError):
                pass

    if "letter_position" in target and "total_letters" in target:
        try:
            letter_position = float(target["letter_position"])
            total_letters = max(1.0, float(target["total_letters"]))
            return min(1.0, max(0.0, (letter_position + 0.5) / total_letters))
        except (TypeError, ValueError):
            pass

    # Last-resort fallback for ad-hoc testing: inspect the middle of the audio.
    return 0.5



def normalize_rule_name(value):
    rule = str(value or "").strip().lower()
    if rule in {"ikhfa", "ikhfa_haqiqi", "ikhfa haqiqi", "إخفاء", "اخفاء"}:
        return "ikhfa"
    if rule in {"izhar", "izhar_halqi", "izhar halqi", "إظهار", "اظهار"}:
        return "izhar"
    if rule in {"other", "unknown", "none"}:
        return rule
    return rule


def infer_expected_rule(target, fallback_rule=""):
    """Infer the real Tajweed target rule from metadata when available.

    Manual CLI tests can pass expected_rule/actual_rule/target_rule. Laravel can
    also pass next_letter or following_letter so the Python side can prevent an
    Izhar target from being validated as Ikhfa, or vice versa.
    """

    for key in ("expected_rule", "actual_rule", "target_rule", "detected_rule", "rule_from_text"):
        if key in target and target[key] is not None:
            rule = normalize_rule_name(target[key])
            if rule in {"ikhfa", "izhar", "other"}:
                return rule

    for key in ("next_letter", "following_letter", "target_letter", "letter"):
        if key in target and target[key]:
            letter = normalize_letter(str(target[key]).strip()[0])
            if letter in IKHFA_FOLLOWING_LETTERS:
                return "ikhfa"
            if letter in IZHAR_FOLLOWING_LETTERS:
                return "izhar"

    return normalize_rule_name(fallback_rule)


def wrong_rule_decision(requested_rule, expected_rule, quality):
    return {
        "label": "wrong_rule_metadata",
        "status": "incorrect",
        "reason": (
            f"This target is {expected_rule.title()} based on target metadata, "
            f"but it was validated as {requested_rule.title()}."
        ),
        "confidence": None,
        "probabilities": {},
        "decision_source": "expected_rule_mismatch",
        "heuristic_status": "incorrect",
        "heuristic_reason": "Requested rule does not match the target rule from the ayah/letter metadata.",
        "quality": quality,
        "expected_rule": expected_rule,
    }


def frame_audio(y, frame_length=512, hop_length=160):
    if y.size < frame_length:
        y = np.pad(y, (0, frame_length - y.size))

    frame_count = 1 + (y.size - frame_length) // hop_length
    frames = np.empty((frame_count, frame_length), dtype=np.float32)

    for index in range(frame_count):
        start = index * hop_length
        frames[index] = y[start:start + frame_length]

    return frames


def contiguous_segments(mask, hop_length=160, frame_length=512):
    segments = []
    start_index = None

    for index, active in enumerate(mask):
        if active and start_index is None:
            start_index = index
        elif not active and start_index is not None:
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
        end_index = len(mask) - 1
        segments.append(
            {
                "start_ms": round(start_index * hop_length / SAMPLE_RATE * 1000.0, 2),
                "end_ms": round((end_index * hop_length + frame_length) / SAMPLE_RATE * 1000.0, 2),
                "duration_ms": round(((end_index - start_index + 1) * hop_length) / SAMPLE_RATE * 1000.0, 2),
            }
        )

    return segments


def voice_activity_ratios(y, frame_length=512, hop_length=320):
    if y.size == 0:
        return []

    frames = frame_audio(y, frame_length=frame_length, hop_length=hop_length)
    frame_rms = np.sqrt(np.mean(frames ** 2, axis=1) + 1e-8)

    if frame_rms.size == 0:
        return []

    threshold = max(0.006, float(np.percentile(frame_rms, 45)) * 1.25, float(np.mean(frame_rms)) * 0.35)
    active = frame_rms > threshold
    segments = []
    start_index = None

    for index, is_active in enumerate(active):
        if is_active and start_index is None:
            start_index = index
        elif not is_active and start_index is not None:
            end_index = index - 1
            segments.append((start_index, end_index))
            start_index = None

    if start_index is not None:
        segments.append((start_index, len(active) - 1))

    ratios = []

    for start_index, end_index in segments:
        center_sample = ((start_index + end_index) / 2.0) * hop_length + (frame_length / 2.0)
        ratio = float(center_sample / max(1, y.size))

        if 0.03 <= ratio <= 0.97:
            ratios.append(round(ratio, 4))

    return ratios


def estimate_window_ghunnah_features(window):
    """Estimate nasal/ghunnah evidence inside one target window.

    This does not try to replace a trained model. It is a safety layer so the
    app does not blindly mark Ikhfa/Izhar correctness from a noisy ML label.
    """

    y = np.asarray(window, dtype=np.float32)
    raw_rms = float(np.sqrt(np.mean(y ** 2) + 1e-10)) if y.size else 0.0
    peak = float(np.max(np.abs(y))) if y.size else 0.0
    window_is_silent = y.size == 0 or raw_rms < TARGET_SILENT_RMS_THRESHOLD or peak < TARGET_SILENT_PEAK_THRESHOLD

    if y.size == 0:
        return {
            "duration_ms": 0.0,
            "window_rms": 0.0,
            "window_peak_amplitude": 0.0,
            "window_is_silent": True,
            "ghunnah_duration_ms": 0.0,
            "ghunnah_frame_ratio": 0.0,
            "ghunnah_strength": 0.0,
            "fricative_strength": 0.0,
            "nasal_excess_score": 0.0,
            "ghunnah_segments": [],
        }

    frame_length = 512
    hop_length = 160
    frames = frame_audio(y, frame_length=frame_length, hop_length=hop_length)
    window_fn = np.hanning(frame_length).astype(np.float32)
    spectrum = np.abs(np.fft.rfft(frames * window_fn, axis=1)) ** 2
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

    voiced_threshold = max(0.0035, float(np.percentile(frame_rms, 50)) * 0.35)
    voiced_frames = frame_rms > voiced_threshold

    # Nasal frames have strong low/mid energy but not too much high-frequency friction.
    nasal_score = nasal_band - (fricative_band * 0.35) - (zero_crossing_rate * 0.25)

    if np.any(voiced_frames):
        score_threshold = max(0.09, float(np.percentile(nasal_score[voiced_frames], 52)))
    else:
        score_threshold = 0.09

    nasal_candidates = (
        voiced_frames
        & (nasal_band > 0.16)
        & (nasal_score >= score_threshold)
        & (fricative_band < 0.65)
        & (zero_crossing_rate < 0.32)
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

    ghunnah_duration_ms = longest_run * hop_length / SAMPLE_RATE * 1000.0
    ghunnah_frame_ratio = float(np.mean(nasal_frames)) if nasal_frames.size else 0.0
    ghunnah_strength = float(np.mean(nasal_band[nasal_frames])) if np.any(nasal_frames) else 0.0
    fricative_strength = float(np.mean(fricative_band[voiced_frames])) if np.any(voiced_frames) else 0.0
    voiced_rms = frame_rms[voiced_frames]
    rms_stability = 0.0

    if voiced_rms.size:
        rms_stability = 1.0 - min(1.0, float(np.std(voiced_rms) / (np.mean(voiced_rms) + 1e-8)))

    formant_transition_score = 0.0

    if formant_band.size > 1:
        formant_transition_score = float(np.mean(np.abs(np.diff(formant_band))))

    transition_smoothness = max(0.0, min(1.0, (1.0 - min(1.0, spectral_flux * 4.0)) * 0.65 + rms_stability * 0.35))

    # One compact score for excess nasalization: useful for Izhar checks.
    nasal_excess_score = (
        (ghunnah_duration_ms / 1000.0)
        + (ghunnah_frame_ratio * 1.5)
        + max(0.0, ghunnah_strength - 0.16)
        + max(0.0, transition_smoothness - 0.45) * 0.20
    )

    return {
        "duration_ms": round(float(y.size / SAMPLE_RATE * 1000.0), 2),
        "window_rms": round(raw_rms, 6),
        "window_peak_amplitude": round(peak, 6),
        "window_is_silent": bool(window_is_silent),
        "ghunnah_duration_ms": round(float(ghunnah_duration_ms), 2),
        "ghunnah_frame_ratio": round(ghunnah_frame_ratio, 4),
        "ghunnah_strength": round(ghunnah_strength, 4),
        "fricative_strength": round(fricative_strength, 4),
        "spectral_flux": round(float(spectral_flux), 5),
        "rms_stability": round(float(rms_stability), 4),
        "transition_smoothness": round(float(transition_smoothness), 4),
        "formant_transition_score": round(float(formant_transition_score), 5),
        "nasal_excess_score": round(float(nasal_excess_score), 4),
        "ghunnah_segments": contiguous_segments(nasal_frames, hop_length=hop_length, frame_length=frame_length),
        "ghunnah_thresholds": {
            "voiced_rms": round(float(voiced_threshold), 5),
            "nasal_score": round(float(score_threshold), 5),
        },
    }


def signal_is_silent(audio):
    """Use the same silence gate for a crop or the complete recording."""

    y = np.asarray(audio, dtype=np.float32)
    if y.size == 0:
        return True

    rms = float(np.sqrt(np.mean(y ** 2) + 1e-10))
    peak = float(np.max(np.abs(y)))
    return rms < TARGET_SILENT_RMS_THRESHOLD or peak < TARGET_SILENT_PEAK_THRESHOLD


def finalize_target_input_status(decision, recording_is_silent):
    """Prevent alignment-window silence from masquerading as recording silence."""

    if decision.get("status") != "uncertain":
        return decision

    finalized = dict(decision)
    if recording_is_silent:
        finalized["reason"] = (
            "The recording is silent and contains no audible recitation, so "
            "Tajweed correctness cannot be judged."
        )
        finalized["decision_source"] = "recording_silence"
        return finalized

    # The full recording contains speech.  A quiet target crop therefore means
    # the approximate timing missed the boundary, not that the user submitted
    # silence.  With no strong localized error, use the conservative pass policy.
    finalized["status"] = "correct"
    finalized["reason"] = (
        "The recording contains audible speech, and no strong rule-specific "
        "error was established at the estimated target position."
    )
    finalized["decision_source"] = "local_window_unavailable_default_correct"
    return finalized


def heuristic_status(rule, quality):
    if quality.get("window_is_silent"):
        return "uncertain", "The target window is silent and contains no audible speech, so Tajweed correctness cannot be judged."

    duration = float(quality.get("ghunnah_duration_ms", 0.0))

    if rule == "ikhfa":
        if duration < ELONGATION_THRESHOLD_MS:
            return (
                "incorrect",
                f"Ikhfa ghunnah was too short ({duration:.0f} ms); it must be at least "
                f"{ELONGATION_THRESHOLD_MS:.0f} ms.",
            )

        return (
            "correct",
            f"Ikhfa ghunnah duration reached the required {ELONGATION_THRESHOLD_MS:.0f} ms boundary.",
        )

    if rule == "izhar":
        if duration > ELONGATION_THRESHOLD_MS:
            return (
                "incorrect",
                f"Izhar contained a long nasal hold ({duration:.0f} ms); it must not exceed "
                f"{ELONGATION_THRESHOLD_MS:.0f} ms.",
            )

        return (
            "correct",
            f"Izhar nasal duration stayed within the {ELONGATION_THRESHOLD_MS:.0f} ms boundary.",
        )

    return "unknown", "No Ikhfa/Izhar rule was available for this target."


def classify_label(rule, label):
    if rule == "ikhfa":
        if label == "ikhfa_correct":
            return "correct", "Target-window ML detected a correct Ikhfa pattern."

        if label == "ikhfa_weak_ghunnah":
            return "incorrect", "Target-window ML detected weak or short ghunnah for this Ikhfa target."

    if rule == "izhar":
        if label == "izhar_correct":
            return "correct", "Target-window ML detected a clear Izhar pattern."

        if label == "izhar_with_ghunnah":
            return "incorrect", "Target-window ML detected nasal sound near this Izhar target."

    if label == "other":
        return "unknown", "Target-window ML could not match this target to a trained Ikhfa/Izhar pattern."

    return "unknown", f"Target-window ML predicted {label}, which does not match this target rule."


def resolve_status(rule, ml_status, ml_reason, ml_confidence, heuristic, quality):
    heuristic_result, heuristic_reason = heuristic

    # "Uncertain" is reserved for unusable input.  A model disagreement or low
    # probability is not the same thing as silence and must not leak into the
    # user-facing input-validation state.
    if quality.get("window_is_silent") or heuristic_result == "uncertain":
        return "uncertain", heuristic_reason, "unusable_target_audio"

    if rule not in {"ikhfa", "izhar"}:
        return heuristic_result, heuristic_reason, "unsupported_target_rule"

    # The target-local elongation duration is authoritative for both supported
    # rules.  ML labels and the remaining acoustic features are retained for
    # diagnostics, but they cannot reverse this binary duration verdict.
    return heuristic_result, heuristic_reason, "elongation_duration_rule"


def load_target_model():
    if not MODEL_PATH.exists():
        return None

    with MODEL_PATH.open("rb") as f:
        payload = pickle.load(f)

    return payload["model"], [str(item) for item in payload["classes"]]


def predict_single_target(model, classes, window, rule, elongation_quality=None):
    quality = estimate_window_ghunnah_features(window)
    if elongation_quality is None:
        elongation_quality = quality
    heuristic = heuristic_status(rule, elongation_quality)

    if model is None:
        status, reason, source = resolve_status(
            rule,
            None,
            None,
            0.0,
            heuristic,
            elongation_quality,
        )
        return {
            "label": "heuristic_only",
            "status": status,
            "reason": reason,
            "confidence": None,
            "probabilities": {},
            "decision_source": source,
            "heuristic_status": heuristic[0],
            "heuristic_reason": heuristic[1],
            "quality": quality,
            "elongation_quality": elongation_quality,
        }

    temp = tempfile.NamedTemporaryFile(suffix=".wav", delete=False)
    temp.close()

    try:
        sf.write(temp.name, window, SAMPLE_RATE, subtype="PCM_16")
        features = extract_summary_features(temp.name).reshape(1, -1)

        if hasattr(model, "predict_proba"):
            probabilities = model.predict_proba(features)[0]
            predicted_index = int(np.argmax(probabilities))
        else:
            predicted_index = int(model.predict(features)[0])
            probabilities = np.zeros(len(classes), dtype=np.float32)
            probabilities[predicted_index] = 1.0

        label = classes[predicted_index]
        confidence = float(probabilities[predicted_index])
        ml_status, ml_reason = classify_label(rule, label)
        status, reason, source = resolve_status(
            rule,
            ml_status,
            ml_reason,
            confidence,
            heuristic,
            elongation_quality,
        )

        return {
            "label": label,
            "status": status,
            "reason": reason,
            "confidence": confidence,
            "probabilities": dict(zip(classes, [float(value) for value in probabilities])),
            "decision_source": source,
            "ml_status": ml_status,
            "ml_reason": ml_reason,
            "heuristic_status": heuristic[0],
            "heuristic_reason": heuristic[1],
            "quality": quality,
            "elongation_quality": elongation_quality,
        }
    finally:
        try:
            os.unlink(temp.name)
        except OSError:
            pass


def nasal_evidence_score(decision):
    quality = decision.get("quality") or {}

    return (
        (float(quality.get("ghunnah_duration_ms", 0.0)) / 1000.0)
        + (float(quality.get("ghunnah_frame_ratio", 0.0)) * 1.5)
        + max(0.0, float(quality.get("ghunnah_strength", 0.0)) - 0.12)
        + max(0.0, float(quality.get("nasal_excess_score", 0.0)))
    )


def compact_quality(quality):
    return {
        "ghunnah_duration_ms": quality.get("ghunnah_duration_ms", 0.0),
        "ghunnah_frame_ratio": quality.get("ghunnah_frame_ratio", 0.0),
        "ghunnah_strength": quality.get("ghunnah_strength", 0.0),
        "nasal_excess_score": quality.get("nasal_excess_score", 0.0),
        "transition_smoothness": quality.get("transition_smoothness", 0.0),
        "rms_stability": quality.get("rms_stability", 0.0),
        "window_rms": quality.get("window_rms", 0.0),
    }


def target_candidate_ratios(y, ratio):
    duration_seconds = y.size / float(SAMPLE_RATE)
    step_ratio = min(0.18, max(0.04, SCAN_STEP_SECONDS / max(duration_seconds, 0.1)))
    offsets = [0.0, -0.08, 0.08, -0.16, 0.16, -0.24, 0.24]
    candidates = []

    for offset in offsets:
        candidate = round(min(1.0, max(0.0, ratio + offset)), 4)

        if candidate not in candidates:
            candidates.append(candidate)

    for candidate_ratio in voice_activity_ratios(y):
        candidate = round(min(1.0, max(0.0, candidate_ratio)), 4)

        if candidate not in candidates:
            candidates.append(candidate)

    if duration_seconds > 0:
        scan_start = max(0.05, ratio - 0.32)
        scan_end = min(0.95, ratio + 0.32)
        current = scan_start

        while current <= scan_end:
            candidate = round(current, 4)

            if candidate not in candidates:
                candidates.append(candidate)

            current += step_ratio

    candidates = sorted(candidates, key=lambda candidate: (abs(candidate - ratio), candidate))

    if len(candidates) > MAX_SCAN_CANDIDATES:
        candidates = candidates[:MAX_SCAN_CANDIDATES]

    return candidates


def target_alignment_score(decision, candidate_ratio, expected_ratio, rule):
    distance = abs(candidate_ratio - expected_ratio)
    nasal_score = nasal_evidence_score(decision)
    quality = decision.get("quality") or {}
    rms = float(quality.get("window_rms", 0.0))
    active_bonus = min(0.25, rms * 2.0)
    distance_penalty = distance * (0.75 if rule == "ikhfa" else 1.15)

    if rule == "ikhfa":
        return nasal_score + active_bonus - distance_penalty

    if rule == "izhar":
        return active_bonus - (distance_penalty * 0.75)

    return active_bonus - distance_penalty


def predict_from_candidate_windows(model, classes, y, ratio, window_seconds, rule):
    best_decision = None
    best_ratio = ratio
    best_score = None
    checked = []

    for candidate_ratio in target_candidate_ratios(y, ratio):
        analysis_window_seconds = window_seconds

        # Without a trained target-window model, correctness comes from the same
        # narrow elongation crop for both rules. A broad 2.4-second crop can pick
        # up a legitimate nasal consonant in a neighbouring word.
        if model is None and rule in {"ikhfa", "izhar"}:
            analysis_window_seconds = min(window_seconds, ELONGATION_LOCAL_WINDOW_SECONDS)

        window = crop_window(y, candidate_ratio, analysis_window_seconds)
        decision = predict_single_target(model, classes, window, rule)
        score = target_alignment_score(decision, candidate_ratio, ratio, rule)
        checked.append(
            {
                "ratio": candidate_ratio,
                "status": decision.get("status"),
                "score": round(float(score), 4),
                "nasal_evidence_score": round(float(nasal_evidence_score(decision)), 4),
                "quality": compact_quality(decision.get("quality") or {}),
            }
        )

        # The expected target crop is authoritative whenever it contains speech.
        # In particular, a nearby nasal event must not turn a short expected Ikhfa
        # into Correct or a clear expected Izhar into Incorrect.
        is_expected_candidate = abs(candidate_ratio - ratio) <= 0.0001
        quality = decision.get("quality") or {}

        if model is None and rule in {"ikhfa", "izhar"} and is_expected_candidate and not quality.get("window_is_silent"):
            return decision, candidate_ratio, checked

        if best_decision is None or score > best_score:
            best_decision = decision
            best_ratio = candidate_ratio
            best_score = score

    if (
        model is None
        and rule == "izhar"
        and best_decision is not None
        and best_decision.get("status") == "incorrect"
        and abs(best_ratio - ratio) > IZHAR_MAX_DECISIVE_SCAN_OFFSET
    ):
        best_decision = dict(best_decision)
        best_decision["status"] = "correct"
        best_decision["reason"] = (
            "Strong nasal evidence was found away from the expected Izhar target, "
            "so it is not treated as an error at the selected target."
        )
        best_decision["decision_source"] = "distant_heuristic_candidate"

    return best_decision, best_ratio, checked


def predict_targets(audio_path, targets, window_seconds=DEFAULT_WINDOW_SECONDS):
    loaded = load_target_model()

    if loaded is None:
        model = None
        classes = []
        model_status = "unavailable_using_heuristic"
        model_error = "target_window_model.pkl not found. Using acoustic ghunnah heuristic for target correctness."
    else:
        model, classes = loaded
        model_status = "loaded"
        model_error = None

    y = load_audio(audio_path)
    recording_is_silent = signal_is_silent(y)
    predictions = []

    # ===== REPORT SCREENSHOT START: Section 4.3.10C - Target-Window Verification =====
    for index, target in enumerate(targets):
        rule = normalize_rule_name(target.get("rule", ""))
        expected_rule = infer_expected_rule(target, fallback_rule=rule)
        ratio = safe_target_ratio(target)
        window = crop_window(y, ratio, window_seconds)
        elongation_window = crop_window(y, ratio, ELONGATION_LOCAL_WINDOW_SECONDS)
        elongation_quality = {
            **estimate_window_ghunnah_features(elongation_window),
            # This crop is based on proportional text position, not forced
            # phoneme alignment. Keep it diagnostic until genuine target timing
            # is available; Quran Muaalem supplies the decisive aligned verdict.
            "target_alignment_verified": False,
            "target_alignment_method": "linear_text_ratio",
        }
        checked_windows = None

        if rule in {"ikhfa", "izhar"} and expected_rule in {"ikhfa", "izhar"} and rule != expected_rule:
            quality = estimate_window_ghunnah_features(window)
            decision = wrong_rule_decision(rule, expected_rule, quality)
        elif rule in {"ikhfa", "izhar"} and model is None:
            decision, ratio, checked_windows = predict_from_candidate_windows(model, classes, y, ratio, window_seconds, rule)
        else:
            decision = predict_single_target(
                model,
                classes,
                window,
                rule,
                elongation_quality=elongation_quality,
            )

        decision = finalize_target_input_status(decision, recording_is_silent)

        payload = {
            "target_index": index,
            **decision,
            "target_ratio": ratio,
            "rule": rule,
            "expected_rule": expected_rule,
            # This always describes the narrow crop at the expected target
            # position, even when a fallback scan inspected other windows.
            "elongation_quality": elongation_quality,
        }

        if checked_windows is not None:
            payload["checked_windows"] = checked_windows
            payload["decision_source"] = f"{payload.get('decision_source', 'unknown')}+nearby_window_scan"

        predictions.append(payload)
    # ===== REPORT SCREENSHOT END: Section 4.3.10C - Target-Window Verification =====

    return {
        "status": "success",
        "model_status": model_status,
        "error": model_error,
        "model_path": str(MODEL_PATH),
        "window_seconds": window_seconds,
        "recording_is_silent": recording_is_silent,
        "targets": predictions,
    }


def main():
    if len(sys.argv) < 3:
        raise ValueError("Usage: predict_target_windows.py <audio_path> <targets_json>")

    audio_path = sys.argv[1]
    targets = json.loads(sys.argv[2])
    print(json.dumps(predict_targets(audio_path, targets), ensure_ascii=False))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:
        print(json.dumps({"status": "failed", "error": str(exc)}, ensure_ascii=False))
        sys.exit(1)
