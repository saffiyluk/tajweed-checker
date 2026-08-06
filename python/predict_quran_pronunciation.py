"""Conservative Quran target-pronunciation analysis using Quran Muaalem.

CLI usage:
    python predict_quran_pronunciation.py AUDIO_PATH UTHMANI_AYAH TARGETS_JSON

The module deliberately keeps model imports and initialization inside
``analyze_recitation``.  Span mapping and decision helpers can therefore be
unit-tested without loading (or downloading) the Quran Muaalem model.
"""

from __future__ import annotations

import importlib
import json
import os
import subprocess
import sys
import tempfile
import unicodedata
from pathlib import Path
from typing import Any, Sequence

import numpy as np
import soundfile as sf


# Inference must never initiate a Hugging Face download from a web request.
os.environ["HF_HUB_OFFLINE"] = "1"
os.environ["TRANSFORMERS_OFFLINE"] = "1"

BASE_DIR = Path(__file__).resolve().parent
SAMPLE_RATE = 16_000
DEFAULT_MODEL_ID = "obadx/muaalem-model-v3_2"
DEFAULT_LOCAL_MODEL_PATH = BASE_DIR / "models" / "muaalem-model-v3_2"

# Conservative decision thresholds.  A target is only decisive when the
# global recitation still matches the selected ayah and the target itself has
# stronger evidence than the minimum accepted for the whole utterance.
MIN_MODEL_CONFIDENCE = float(os.environ.get("QURAN_MUAALEM_MIN_MODEL_CONFIDENCE", "0.72"))
HIGH_TARGET_CONFIDENCE = float(os.environ.get("QURAN_MUAALEM_HIGH_TARGET_CONFIDENCE", "0.82"))
MIN_TARGET_ALIGNMENT_COVERAGE = float(
    os.environ.get("QURAN_MUAALEM_MIN_TARGET_ALIGNMENT_COVERAGE", "0.85")
)
MAX_CONTENT_PHONEME_ERROR_RATE = float(
    os.environ.get("QURAN_MUAALEM_MAX_CONTENT_PER", "0.35")
)
MAX_CORRECT_TARGET_PHONEME_ERROR_RATE = float(
    os.environ.get("QURAN_MUAALEM_MAX_CORRECT_TARGET_PER", "0.10")
)

MIN_AUDIO_SECONDS = float(os.environ.get("QURAN_MUAALEM_MIN_AUDIO_SECONDS", "0.75"))
MAX_AUDIO_SECONDS = float(os.environ.get("QURAN_MUAALEM_MAX_AUDIO_SECONDS", "30"))
SILENT_RMS_THRESHOLD = float(os.environ.get("QURAN_MUAALEM_SILENT_RMS", "0.003"))
SILENT_PEAK_THRESHOLD = float(os.environ.get("QURAN_MUAALEM_SILENT_PEAK", "0.015"))
QUIET_RMS_THRESHOLD = float(os.environ.get("QURAN_MUAALEM_QUIET_RMS", "0.006"))
QUIET_PEAK_THRESHOLD = float(os.environ.get("QURAN_MUAALEM_QUIET_PEAK", "0.035"))
ENABLE_AUDIO_CLEANING = os.environ.get("QURAN_MUAALEM_ENABLE_AUDIO_CLEANING", "1").lower() not in {
    "0",
    "false",
    "no",
}
REFERENCE_NOISE_REDUCTION_AMOUNT = float(
    os.environ.get("QURAN_MUAALEM_NOISE_REDUCTION_AMOUNT", "0.18")
)
REFERENCE_TARGET_RMS = float(os.environ.get("QURAN_MUAALEM_TARGET_RMS", "0.08"))

# Quran text APIs do not all use the same Uthmani annotation set.  These
# characters are visual pause/verse ornaments, not spoken phonemes.  Leaving
# one of them in quran-transcript's phonetic output produces an unknown token
# in Quran Muaalem (notably U+06DA, the permissible-stop mark).
NON_SPOKEN_QURAN_ANNOTATIONS = frozenset(
    chr(codepoint)
    for codepoint in (
        *range(0x06D6, 0x06DD),
        0x06DD,  # Arabic end of ayah
        0x06DE,  # Rub el hizb
        0x06E9,  # Place of sajdah
    )
)
TANWEEN_MARKS = frozenset(chr(codepoint) for codepoint in (0x064B, 0x064C, 0x064D))
FATHATAN = chr(0x064B)
TANWEEN_CONTEXT_MARKER = chr(0x06ED)
IQLAB_CONTEXT_MARKER = chr(0x06E2)
TANWEEN_CONTEXT_MARKERS = frozenset((TANWEEN_CONTEXT_MARKER, IQLAB_CONTEXT_MARKER))
IKHFA_LETTERS = frozenset(
    chr(codepoint)
    for codepoint in (
        0x062A,
        0x062B,
        0x062C,
        0x062F,
        0x0630,
        0x0632,
        0x0633,
        0x0634,
        0x0635,
        0x0636,
        0x0637,
        0x0638,
        0x0641,
        0x0642,
        0x0643,
    )
)
IDGHAM_LETTERS = frozenset(chr(codepoint) for codepoint in (0x064A, 0x0631, 0x0645, 0x0644, 0x0648, 0x0646))
IQLAB_LETTER = chr(0x0628)
FATHATAN_CARRIER_LETTERS = frozenset((chr(0x0627), chr(0x0649)))


class ModelUnavailableError(RuntimeError):
    """Raised when no complete local Quran Muaalem artifact is available."""


def _ensure_windows_runtime_identity() -> str | None:
    """Keep ``getpass.getuser`` from importing Unix-only ``pwd`` on Windows.

    Laravel intentionally starts Python with a small, predictable environment.
    Under a web server that environment can have no login-name variable at all.
    Recent PyTorch versions ask ``getpass`` for a username while initializing
    their compiler cache; without one, Python falls back to importing ``pwd``,
    which does not exist on Windows.
    """

    if os.name != "nt":
        return None

    for variable in ("LOGNAME", "USER", "LNAME", "USERNAME"):
        value = os.environ.get(variable, "").strip()
        if value:
            return value

    profile = (os.environ.get("USERPROFILE") or os.environ.get("HOME") or "").strip()
    profile_name = profile.replace("\\", "/").rstrip("/").rsplit("/", 1)[-1]
    username = profile_name if profile_name and not profile_name.endswith(":") else "tajweed-python"
    os.environ["USERNAME"] = username

    return username


def _exception_chain(error: BaseException) -> str:
    """Preserve the useful cause hidden by optional-dependency lazy imports."""

    messages: list[str] = []
    seen: set[int] = set()
    current: BaseException | None = error

    while current is not None and id(current) not in seen:
        seen.add(id(current))
        message = str(current).strip()
        rendered = f"{type(current).__name__}: {message}" if message else type(current).__name__
        if not messages or rendered != messages[-1]:
            messages.append(rendered)
        current = current.__cause__ or current.__context__

    return " <- ".join(messages)


def _load_muaalem_class() -> Any:
    """Load Muaalem without relying on Transformers' top-level lazy export.

    Quran Muaalem imports ``AutoFeatureExtractor`` from the Transformers package
    root.  That export is resolved lazily and can leave only a generic
    "requirements defined correctly" error when an optional import fails.  Load
    the concrete, stable auto-feature module first and publish the resolved class
    for Quran Muaalem's subsequent import.
    """

    _ensure_windows_runtime_identity()

    try:
        transformers = importlib.import_module("transformers")
        feature_module = importlib.import_module(
            "transformers.models.auto.feature_extraction_auto"
        )
        setattr(transformers, "AutoFeatureExtractor", feature_module.AutoFeatureExtractor)
        return importlib.import_module("quran_muaalem").Muaalem
    except Exception as error:
        raise RuntimeError(
            f"Quran Muaalem runtime import failed: {_exception_chain(error)}"
        ) from error


def clamp_probability(value: Any) -> float:
    try:
        return round(min(1.0, max(0.0, float(value))), 6)
    except (TypeError, ValueError):
        return 0.0


def sequence_edit_distance(reference: Sequence[Any], predicted: Sequence[Any]) -> int:
    """Return Levenshtein distance for arbitrary sequences."""

    if len(reference) < len(predicted):
        reference, predicted = predicted, reference

    previous = list(range(len(predicted) + 1))

    for ref_index, ref_item in enumerate(reference, start=1):
        current = [ref_index]
        for pred_index, pred_item in enumerate(predicted, start=1):
            current.append(
                min(
                    current[-1] + 1,
                    previous[pred_index] + 1,
                    previous[pred_index - 1] + (ref_item != pred_item),
                )
            )
        previous = current

    return previous[-1]


def phoneme_error_rate(reference_phonemes: str, predicted_phonemes: str) -> float:
    """Compute character-token PER for quran-transcript phonetic script."""

    reference = list(reference_phonemes or "")
    predicted = list(predicted_phonemes or "")

    if not reference:
        return 0.0 if not predicted else 1.0

    return round(sequence_edit_distance(reference, predicted) / len(reference), 6)


def align_reference_to_prediction(reference: str, predicted: str) -> list[int | None]:
    """Map each reference phoneme index to its aligned prediction index."""

    ref = list(reference or "")
    pred = list(predicted or "")
    rows = len(ref) + 1
    columns = len(pred) + 1
    costs = [[0] * columns for _ in range(rows)]

    for i in range(rows):
        costs[i][0] = i
    for j in range(columns):
        costs[0][j] = j

    for i in range(1, rows):
        for j in range(1, columns):
            costs[i][j] = min(
                costs[i - 1][j] + 1,
                costs[i][j - 1] + 1,
                costs[i - 1][j - 1] + (ref[i - 1] != pred[j - 1]),
            )

    mapping: list[int | None] = [None] * len(ref)
    i, j = len(ref), len(pred)

    while i > 0 or j > 0:
        diagonal_cost = None
        if i > 0 and j > 0:
            diagonal_cost = costs[i - 1][j - 1] + (ref[i - 1] != pred[j - 1])

        # Prefer a diagonal alignment on ties.  A substitution still has a
        # model probability and explain_error will retain it as explicit error.
        if diagonal_cost is not None and costs[i][j] == diagonal_cost:
            mapping[i - 1] = j - 1
            i -= 1
            j -= 1
        elif i > 0 and costs[i][j] == costs[i - 1][j] + 1:
            i -= 1
        else:
            j -= 1

    return mapping


def _mapping_position(mapping: Any) -> tuple[int, int] | None:
    value = mapping.get("pos") if isinstance(mapping, dict) else getattr(mapping, "pos", None)

    if not isinstance(value, (list, tuple)) or len(value) != 2:
        return None

    try:
        start, end = int(value[0]), int(value[1])
    except (TypeError, ValueError):
        return None

    if start < 0 or end < start:
        return None

    return start, end


def target_character_span(target: dict[str, Any], text_length: int) -> tuple[int, int] | None:
    """Convert Laravel's inclusive target bounds to a half-open span."""

    try:
        start = int(target["position"])
        inclusive_end = int(target["end_position"])
    except (KeyError, TypeError, ValueError):
        return None

    if start < 0 or inclusive_end < start or inclusive_end >= text_length:
        return None

    return start, inclusive_end + 1


def target_phoneme_span(
    target: dict[str, Any], mappings: Sequence[Any], text_length: int
) -> tuple[int, int] | None:
    """Map one Uthmani target span to its non-empty reference phoneme span."""

    character_span = target_character_span(target, text_length)
    if character_span is None:
        return None

    start, end = character_span
    positions: list[tuple[int, int]] = []

    for index in range(start, min(end, len(mappings))):
        position = _mapping_position(mappings[index])
        if position is not None and position[1] > position[0]:
            positions.append(position)

    if not positions:
        return None

    return min(item[0] for item in positions), max(item[1] for item in positions)


def spans_overlap(target_span: tuple[int, int], error_span: tuple[int, int]) -> bool:
    """Return whether two half-open spans overlap, including point insertions."""

    target_start, target_end = target_span
    error_start, error_end = error_span

    if error_start == error_end:
        return target_start <= error_start < target_end

    return max(target_start, error_start) < min(target_end, error_end)


def _error_span(error: dict[str, Any]) -> tuple[int, int] | None:
    value = error.get("uthmani_span", error.get("uthmani_pos"))
    if not isinstance(value, (list, tuple)) or len(value) != 2:
        return None

    try:
        start, end = int(value[0]), int(value[1])
    except (TypeError, ValueError):
        return None

    if start < 0 or end < start:
        return None

    return start, end


def errors_overlapping_target(
    target: dict[str, Any], errors: Sequence[dict[str, Any]], text_length: int
) -> list[dict[str, Any]]:
    character_span = target_character_span(target, text_length)
    if character_span is None:
        return []

    return [
        error
        for error in errors
        if (span := _error_span(error)) is not None and spans_overlap(character_span, span)
    ]


def is_decisive_izhar_nasalization(error: dict[str, Any]) -> bool:
    """Return whether an overlap contains extra or nasalized Izhar noon frames.

    quran-transcript currently classifies that phoneme replacement as a
    ``normal`` error, while unrelated vowel, consonant, Madd, or Qalqalah
    mistakes can also overlap Laravel's broad noon/tanween-to-throat-letter
    target span. Only an explicit extra/noon-to-hidden-noon contrast is safe to
    attribute to Izhar itself.
    """

    expected = str(error.get("expected_phonemes", ""))
    predicted = str(error.get("predicted_phonemes", ""))

    expected_clear_noons = expected.count("\u0646")
    expected_hidden_noons = expected.count("\u06ba")
    predicted_clear_noons = predicted.count("\u0646")
    predicted_hidden_noons = predicted.count("\u06ba")
    expected_noons = expected_clear_noons + expected_hidden_noons
    predicted_noons = predicted_clear_noons + predicted_hidden_noons

    # A long/nasal Izhar can be reported as a clear-to-hidden replacement,
    # duplicated clear noon, or inserted hidden-noon frames after tanween.
    nasalized_clear_noon = (
        expected_clear_noons > 0
        and predicted_hidden_noons > expected_hidden_noons
    )
    extra_noon_frames = (
        predicted_noons > expected_noons
        and (expected_noons > 0 or expected == "")
    )

    return nasalized_clear_noon or extra_noon_frames


def is_decisive_ikhfa_shortening(error: dict[str, Any]) -> bool:
    """Return whether an Ikhfa target lost its hidden-noon elongation.

    Quran Muaalem encodes the held nasal sound as repeated U+06BA phonemes.
    A short, Izhar-like reading is therefore exposed as fewer hidden-noon
    phonemes, commonly ``ںںں -> ن``.  Target alignment coverage naturally falls
    when those frames are missing, so this explicit contrast must not require a
    high target-coverage score to be useful.
    """

    expected = str(error.get("expected_phonemes", ""))
    predicted = str(error.get("predicted_phonemes", ""))
    expected_hidden_noons = expected.count("\u06ba")
    predicted_hidden_noons = predicted.count("\u06ba")

    return (
        expected_hidden_noons > 0
        and predicted_hidden_noons < expected_hidden_noons
    )


def target_elongation_error(
    rule: str, errors: Sequence[dict[str, Any]]
) -> tuple[str, dict[str, Any]] | None:
    """Find the explicit short-Ikhfa or long-Izhar phoneme contrast."""

    for error in errors:
        if rule == "ikhfa" and is_decisive_ikhfa_shortening(error):
            return "ikhfa_too_short", error

        if rule == "izhar" and is_decisive_izhar_nasalization(error):
            return "izhar_too_long", error

    return None


def target_elongation_payload(
    rule: str,
    error_match: tuple[str, dict[str, Any]] | None,
    *,
    trusted: bool,
    model_confidence: float,
) -> dict[str, Any]:
    """Build stable evidence for Laravel's final elongation policy."""

    error_code = error_match[0] if error_match else None
    error = error_match[1] if error_match else None

    if not trusted:
        return {
            "status": "uncertain",
            "trusted": False,
            "source": "quran_muaalem_phoneme_alignment",
            "error_code": error_code,
            "model_confidence": round(clamp_probability(model_confidence), 6),
            "reason": "The phoneme alignment was not reliable enough to apply the elongation rule.",
            "error": error,
        }

    if error_code == "ikhfa_too_short":
        status = "incorrect"
        reason = "Ikhfa was read too short, like clear Izhar. Hold the nasal sound longer."
    elif error_code == "izhar_too_long":
        status = "incorrect"
        reason = "Izhar was held too long with an Ikhfa-like nasal sound. Keep it short and clear."
    elif rule == "ikhfa":
        status = "correct"
        reason = "Ikhfa retained the expected nasal elongation."
    else:
        status = "correct"
        reason = "Izhar stayed short and clear without Ikhfa-like elongation."

    return {
        "status": status,
        "trusted": True,
        "source": "quran_muaalem_phoneme_alignment",
        "error_code": error_code,
        "model_confidence": round(clamp_probability(model_confidence), 6),
        "reason": reason,
        "error": error,
    }


def _rule_name(rule: Any) -> dict[str, str]:
    name = getattr(rule, "name", None)
    return {
        "ar": str(getattr(name, "ar", "")),
        "en": str(getattr(name, "en", type(rule).__name__)),
    }


def serialize_tajweed_rule(rule: Any) -> dict[str, Any]:
    return {
        "name": _rule_name(rule),
        "golden_len": getattr(rule, "golden_len", None),
        "correctness_type": getattr(rule, "correctness_type", None),
        "tag": getattr(rule, "tag", None),
    }


def serialize_reciter_error(error: Any) -> dict[str, Any]:
    """Convert quran-transcript's ReciterError into stable JSON fields."""

    def rules(field: str) -> list[dict[str, Any]]:
        value = getattr(error, field, None) or []
        return [serialize_tajweed_rule(rule) for rule in value]

    uthmani_position = getattr(error, "uthmani_pos", (0, 0))
    phoneme_position = getattr(error, "ph_pos", (0, 0))

    return {
        "uthmani_span": [int(uthmani_position[0]), int(uthmani_position[1])],
        "phoneme_span": [int(phoneme_position[0]), int(phoneme_position[1])],
        "error_type": str(getattr(error, "error_type", "unknown")),
        "speech_error_type": str(getattr(error, "speech_error_type", "unknown")),
        "expected_phonemes": str(getattr(error, "expected_ph", "")),
        # quran-transcript 0.5.2 intentionally exposes this misspelled attribute.
        "predicted_phonemes": str(getattr(error, "preditected_ph", "")),
        "expected_len": getattr(error, "expected_len", None),
        "predicted_len": getattr(error, "predicted_len", None),
        "reference_tajweed_rules": rules("ref_tajweed_rules"),
        "inserted_tajweed_rules": rules("inserted_tajweed_rules"),
        "replaced_tajweed_rules": rules("replaced_tajweed_rules"),
        "missing_tajweed_rules": rules("missing_tajweed_rules"),
    }


def _target_metadata_aligned(target: dict[str, Any]) -> bool:
    rule = str(target.get("rule", "")).strip().lower()
    expected_rule = str(target.get("expected_rule", rule)).strip().lower()
    return rule in {"ikhfa", "izhar"} and expected_rule == rule


def _target_alignment_metrics(
    phoneme_span: tuple[int, int],
    reference_phonemes: str,
    predicted_phonemes: str,
    predicted_probabilities: Sequence[float],
    reference_to_prediction: Sequence[int | None],
) -> dict[str, float]:
    start, end = phoneme_span
    reference_length = max(1, end - start)
    mapped_indices = [
        reference_to_prediction[index]
        for index in range(start, min(end, len(reference_to_prediction)))
        if reference_to_prediction[index] is not None
    ]
    mapped_indices = [int(index) for index in mapped_indices if index is not None]
    alignment_coverage = len(mapped_indices) / reference_length

    if not mapped_indices:
        return {
            "confidence": 0.0,
            "alignment_coverage": 0.0,
            "phoneme_error_rate": 1.0,
        }

    prediction_start = max(0, min(mapped_indices))
    prediction_end = min(len(predicted_phonemes), max(mapped_indices) + 1)
    reference_segment = reference_phonemes[start:end]
    predicted_segment = predicted_phonemes[prediction_start:prediction_end]
    target_per = phoneme_error_rate(reference_segment, predicted_segment)

    probabilities = [
        clamp_probability(predicted_probabilities[index])
        for index in mapped_indices
        if 0 <= index < len(predicted_probabilities)
    ]
    mean_probability = float(np.mean(probabilities)) if probabilities else 0.0
    confidence = mean_probability * min(1.0, alignment_coverage)

    return {
        "confidence": clamp_probability(confidence),
        "alignment_coverage": round(min(1.0, alignment_coverage), 6),
        "phoneme_error_rate": target_per,
    }


def _uncertain_target(
    target: dict[str, Any], index: int, reason: str, errors: Sequence[dict[str, Any]] | None = None
) -> dict[str, Any]:
    return {
        "target_index": index,
        "rule": str(target.get("rule", "unknown")).lower(),
        "status": "uncertain",
        "confidence": 0.0,
        "reason": reason,
        "errors": list(errors or []),
        "character_span": None,
        "phoneme_span": None,
        "alignment_coverage": 0.0,
        "phoneme_error_rate": None,
        "aligned_expected_target": False,
    }


def _failed_target(
    target: dict[str, Any],
    index: int,
    reason: str,
    *,
    failure_code: str,
    errors: Sequence[dict[str, Any]] | None = None,
) -> dict[str, Any]:
    """Return a machine-readable analysis failure, not a user uncertainty."""

    return {
        "target_index": index,
        "rule": str(target.get("rule", "unknown")).lower(),
        "status": "failed",
        "confidence": 0.0,
        "reason": reason,
        "failure_code": failure_code,
        "analysis_failure": True,
        "errors": list(errors or []),
        "character_span": None,
        "phoneme_span": None,
        "alignment_coverage": 0.0,
        "phoneme_error_rate": None,
        "aligned_expected_target": False,
    }


def evaluate_targets(
    targets: Sequence[dict[str, Any]],
    mappings: Sequence[Any],
    errors: Sequence[dict[str, Any]],
    uthmani_text: str,
    reference_phonemes: str,
    predicted_phonemes: str,
    predicted_probabilities: Sequence[float],
    model_confidence: float,
    global_per: float,
    *,
    audio_usable: bool = True,
    audio_status: str | None = None,
) -> list[dict[str, Any]]:
    """Apply conservative binary decisions after the input gates pass.

    ``uncertain`` is an input-validation state for actual silence or content
    that differs from the selected ayah.  Malformed target metadata and other
    technical input failures are reported as ``failed``.  Once those gates pass,
    the target-aligned hidden-noon phoneme length supplies the binary elongation
    decision: short Ikhfa and long/nasal Izhar fail; otherwise the target passes.
    """

    reference_to_prediction = align_reference_to_prediction(
        reference_phonemes, predicted_phonemes
    )
    content_mismatch = global_per > MAX_CONTENT_PHONEME_ERROR_RATE
    decisions: list[dict[str, Any]] = []

    for index, target_value in enumerate(targets):
        target = target_value if isinstance(target_value, dict) else {}
        character_span = target_character_span(target, len(uthmani_text))
        phoneme_span = target_phoneme_span(target, mappings, len(uthmani_text))
        overlapping_errors = errors_overlapping_target(target, errors, len(uthmani_text))
        rule = str(target.get("rule", "unknown")).lower()
        aligned_expected_target = _target_metadata_aligned(target) and phoneme_span is not None

        if not audio_usable and audio_status == "silent":
            decision = _uncertain_target(
                target, index, "The recording is silent, so pronunciation cannot be judged."
            )
        elif not audio_usable:
            normalized_audio_status = str(audio_status or "unusable_audio")
            decision = _failed_target(
                target,
                index,
                "The recording could not be analyzed because its audio input is unusable.",
                failure_code=normalized_audio_status,
            )
        elif character_span is None or phoneme_span is None or not aligned_expected_target:
            decision = _failed_target(
                target,
                index,
                "The supplied Tajweed target could not be mapped to the selected Uthmani ayah.",
                failure_code="target_alignment_failed",
                errors=overlapping_errors,
            )
        else:
            metrics = _target_alignment_metrics(
                phoneme_span,
                reference_phonemes,
                predicted_phonemes,
                predicted_probabilities,
                reference_to_prediction,
            )
            target_confidence = metrics["confidence"]
            alignment_is_strong = (
                model_confidence >= MIN_MODEL_CONFIDENCE
                and target_confidence >= HIGH_TARGET_CONFIDENCE
                and metrics["alignment_coverage"] >= MIN_TARGET_ALIGNMENT_COVERAGE
            )
            elongation_error = target_elongation_error(rule, overlapping_errors)
            elongation_is_trusted = (
                not content_mismatch and model_confidence >= MIN_MODEL_CONFIDENCE
            )
            elongation = target_elongation_payload(
                rule,
                elongation_error,
                trusted=elongation_is_trusted,
                model_confidence=model_confidence,
            )

            if content_mismatch:
                status = "uncertain"
                reason = (
                    "The recited content differs too much from the selected ayah; "
                    "content mismatch is not treated as a Tajweed pronunciation error."
                )
            else:
                decisive_rule_error = elongation_error is not None
                strong_rule_error = (
                    model_confidence >= MIN_MODEL_CONFIDENCE
                    and decisive_rule_error
                )

                if strong_rule_error:
                    status = "incorrect"
                    reason = elongation["reason"]
                elif decisive_rule_error:
                    status = "correct"
                    reason = (
                        "A possible target elongation contrast was detected, "
                        "but the alignment was not strong enough to establish a "
                        "rule-specific error."
                    )
                elif rule == "izhar" and overlapping_errors:
                    status = "correct"
                    reason = (
                        "The overlapping pronunciation difference is not the explicit "
                        "clear-noon to hidden-noon Izhar error."
                    )
                elif rule == "ikhfa" and overlapping_errors:
                    status = "correct"
                    reason = (
                        "The overlapping pronunciation difference does not show a short, "
                        "Izhar-like Ikhfa target."
                    )
                elif not alignment_is_strong:
                    status = "correct"
                    reason = (
                        "The recitation matches the selected ayah and no strong, "
                        "rule-specific target error was established."
                    )
                elif metrics["phoneme_error_rate"] <= MAX_CORRECT_TARGET_PHONEME_ERROR_RATE:
                    status = "correct"
                    reason = (
                        "The expected target phonemes aligned with high confidence and no "
                        "overlapping pronunciation error was found."
                    )
                else:
                    status = "correct"
                    reason = (
                        "No explicit, rule-specific target error was found in the "
                        "matching recitation."
                    )

            decision = {
                "target_index": index,
                "rule": rule,
                "status": status,
                "confidence": target_confidence,
                "reason": reason,
                "errors": overlapping_errors,
                "character_span": list(character_span),
                "phoneme_span": list(phoneme_span),
                "alignment_coverage": metrics["alignment_coverage"],
                "phoneme_error_rate": metrics["phoneme_error_rate"],
                "aligned_expected_target": aligned_expected_target,
                "elongation": elongation,
            }

        decisions.append(decision)

    return decisions


def uncertain_targets(
    targets: Sequence[dict[str, Any]], uthmani_text: str, reason: str
) -> list[dict[str, Any]]:
    decisions = []
    for index, target_value in enumerate(targets):
        target = target_value if isinstance(target_value, dict) else {}
        decision = _uncertain_target(target, index, reason)
        span = target_character_span(target, len(uthmani_text))
        decision["character_span"] = list(span) if span else None
        decisions.append(decision)
    return decisions


def _frame_audio(y: np.ndarray, frame_length: int = 512, hop_length: int = 320) -> np.ndarray:
    if y.size < frame_length:
        y = np.pad(y, (0, frame_length - y.size))
    frame_count = 1 + (y.size - frame_length) // hop_length
    return np.stack(
        [y[index * hop_length : index * hop_length + frame_length] for index in range(frame_count)]
    )


def estimate_audio_quality(y: np.ndarray) -> dict[str, Any]:
    """Small input gate; Quran/model alignment remains the decisive evidence."""

    audio = np.asarray(y, dtype=np.float32).reshape(-1)
    duration_seconds = audio.size / float(SAMPLE_RATE)
    rms = float(np.sqrt(np.mean(audio**2) + 1e-10)) if audio.size else 0.0
    peak = float(np.max(np.abs(audio))) if audio.size else 0.0

    if audio.size:
        frame_rms = np.sqrt(np.mean(_frame_audio(audio) ** 2, axis=1) + 1e-10)
        lower = float(np.percentile(frame_rms, 20))
        upper = float(np.percentile(frame_rms, 90))
        activity_threshold = max(0.006, lower + ((upper - lower) * 0.25))
        active_ratio = float(np.mean(frame_rms > activity_threshold))
    else:
        activity_threshold = 0.006
        active_ratio = 0.0

    is_too_short = duration_seconds < MIN_AUDIO_SECONDS
    is_too_long = duration_seconds > MAX_AUDIO_SECONDS
    is_silent = rms < SILENT_RMS_THRESHOLD or peak < SILENT_PEAK_THRESHOLD
    is_too_quiet = not is_silent and (
        rms < QUIET_RMS_THRESHOLD or peak < QUIET_PEAK_THRESHOLD
    )
    usable = not (is_too_short or is_too_long or is_silent or is_too_quiet)

    # Silence is the user's explicit "uncertain" input state even when the
    # silent recording is also very short.  Other capture problems keep their
    # own status so the application can report an analysis failure instead.
    if is_silent:
        status = "silent"
    elif is_too_short:
        status = "too_short"
    elif is_too_long:
        status = "too_long"
    elif is_too_quiet:
        status = "too_quiet"
    else:
        status = "usable"

    return {
        "status": status,
        "usable": usable,
        "duration_seconds": round(duration_seconds, 3),
        "rms": round(rms, 6),
        "peak_amplitude": round(peak, 6),
        "active_frame_ratio": round(active_ratio, 6),
        "activity_threshold": round(activity_threshold, 6),
    }


def clean_audio_for_reference_model(y: np.ndarray) -> np.ndarray:
    """Lightly clean browser audio before Quran Muaalem alignment.

    The cleanup is intentionally conservative.  Tajweed cues are small, so this
    improves level/noise/silence issues without trying to reshape pronunciation.
    """

    audio = np.asarray(y, dtype=np.float32).reshape(-1)

    if audio.size == 0:
        return audio

    try:
        from audio_cleaning import (
            normalize_rms,
            reduce_background_noise,
            remove_dc_offset,
            trim_silence,
        )

        cleaned = remove_dc_offset(audio)
        cleaned = trim_silence(cleaned, sample_rate=SAMPLE_RATE, threshold=0.006, padding_ms=250)
        cleaned = reduce_background_noise(
            cleaned,
            sample_rate=SAMPLE_RATE,
            amount=REFERENCE_NOISE_REDUCTION_AMOUNT,
            stationary=True,
        )
        cleaned = normalize_rms(cleaned, target_rms=REFERENCE_TARGET_RMS, max_gain=4.0)

        return cleaned.astype(np.float32)
    except Exception:
        audio = audio - float(np.mean(audio))
        peak = float(np.max(np.abs(audio))) if audio.size else 0.0
        if peak > 0:
            audio = audio / peak
        rms = float(np.sqrt(np.mean(audio**2))) if audio.size else 0.0
        if rms > 0:
            audio = np.clip(audio * min(4.0, REFERENCE_TARGET_RMS / rms), -1.0, 1.0)

        return audio.astype(np.float32)


def _quality_rank(quality: dict[str, Any]) -> tuple[int, float, float]:
    status_rank = {
        "usable": 4,
        "too_quiet": 3,
        "too_long": 2,
        "too_short": 1,
        "silent": 0,
    }.get(str(quality.get("status", "")), 0)

    return (
        status_rank,
        float(quality.get("active_frame_ratio") or 0.0),
        float(quality.get("rms") or 0.0),
    )


def prepare_audio_candidates(wave: np.ndarray, audio_path: str | Path | None = None) -> list[dict[str, Any]]:
    """Return raw and cleaned audio candidates for reference alignment."""

    raw = np.asarray(wave, dtype=np.float32).reshape(-1)
    candidates: list[dict[str, Any]] = [
        {
            "variant": "raw",
            "wave": raw,
            "quality": estimate_audio_quality(raw),
        }
    ]

    if ENABLE_AUDIO_CLEANING:
        full_stack_added = False

        if audio_path is not None:
            try:
                from audio_cleaning import preprocess_audio_file_for_ml

                processed = preprocess_audio_file_for_ml(
                    audio_path,
                    sample_rate=SAMPLE_RATE,
                    target_rms=REFERENCE_TARGET_RMS,
                    noise_reduction_amount=REFERENCE_NOISE_REDUCTION_AMOUNT,
                    save_wav=True,
                )
                processed_wave = np.asarray(processed["wave"], dtype=np.float32).reshape(-1)

                if processed_wave.size:
                    candidates.append(
                        {
                            "variant": "librosa_denoised_wav",
                            "wave": processed_wave,
                            "quality": estimate_audio_quality(processed_wave),
                            "preprocessing": processed.get("metadata", {}),
                            "temporary_path": processed.get("processed_path"),
                        }
                    )
                    full_stack_added = True
            except Exception as error:
                candidates[0]["preprocessing_error"] = str(error)

        if not full_stack_added:
            cleaned = clean_audio_for_reference_model(raw)
            if cleaned.size:
                candidates.append(
                    {
                        "variant": "cleaned",
                        "wave": cleaned,
                        "quality": estimate_audio_quality(cleaned),
                    }
                )

    # Put the best quality candidate first, while keeping raw as a fallback.
    return sorted(candidates, key=lambda candidate: _quality_rank(candidate["quality"]), reverse=True)


def cleanup_audio_candidates(candidates: Sequence[dict[str, Any]]) -> None:
    for candidate in candidates:
        temporary_path = candidate.get("temporary_path")

        if isinstance(temporary_path, str) and temporary_path:
            try:
                os.unlink(temporary_path)
            except OSError:
                pass


def _resample_audio(y: np.ndarray, original_rate: int) -> np.ndarray:
    if y.size == 0 or original_rate == SAMPLE_RATE:
        return y.astype(np.float32)

    target_length = max(1, int(round(y.size * SAMPLE_RATE / float(original_rate))))
    source_positions = np.linspace(0, y.size - 1, num=y.size, dtype=np.float64)
    target_positions = np.linspace(0, y.size - 1, num=target_length, dtype=np.float64)
    return np.interp(target_positions, source_positions, y).astype(np.float32)


def load_audio(audio_path: str | Path) -> np.ndarray:
    """Load arbitrary browser audio as 16 kHz mono without librosa."""

    path = Path(audio_path)
    if not path.is_file():
        raise FileNotFoundError(f"Audio file not found: {path}")

    temporary_wav: str | None = None
    try:
        try:
            wave, sample_rate = sf.read(str(path), always_2d=False)
        except Exception:
            temporary = tempfile.NamedTemporaryFile(suffix=".wav", delete=False)
            temporary.close()
            temporary_wav = temporary.name
            process = subprocess.run(
                [
                    "ffmpeg",
                    "-y",
                    "-loglevel",
                    "error",
                    "-i",
                    str(path),
                    "-ac",
                    "1",
                    "-ar",
                    str(SAMPLE_RATE),
                    temporary_wav,
                ],
                stdout=subprocess.DEVNULL,
                stderr=subprocess.PIPE,
                check=False,
            )
            if process.returncode != 0:
                detail = process.stderr.decode("utf-8", errors="replace").strip()
                raise RuntimeError(f"ffmpeg could not decode the audio: {detail}")
            wave, sample_rate = sf.read(temporary_wav, always_2d=False)
    finally:
        if temporary_wav:
            try:
                os.unlink(temporary_wav)
            except OSError:
                pass

    audio = np.asarray(wave, dtype=np.float32)
    if audio.ndim > 1:
        audio = np.mean(audio, axis=1)
    return _resample_audio(audio.reshape(-1), int(sample_rate))


def _has_complete_weights(model_path: Path) -> bool:
    direct_weights = [model_path / "model.safetensors", model_path / "pytorch_model.bin"]
    if any(path.is_file() and path.stat().st_size > 1024 for path in direct_weights):
        return True

    for index_name in ("model.safetensors.index.json", "pytorch_model.bin.index.json"):
        index_path = model_path / index_name
        if not index_path.is_file():
            continue
        try:
            payload = json.loads(index_path.read_text(encoding="utf-8"))
            shards = set((payload.get("weight_map") or {}).values())
        except (OSError, ValueError, TypeError):
            return False
        return bool(shards) and all(
            (model_path / shard).is_file() and (model_path / shard).stat().st_size > 1024
            for shard in shards
        )

    return False


def model_directory_complete(model_path: str | Path) -> bool:
    path = Path(model_path)
    required = ("config.json", "vocab.json", "preprocessor_config.json")
    return path.is_dir() and all((path / name).is_file() for name in required) and _has_complete_weights(path)


def resolve_local_model(requested_model: str | None = None) -> tuple[str, str]:
    """Resolve only complete local artifacts; never fetch from the network."""

    configured = (requested_model or os.environ.get("QURAN_MUAALEM_MODEL") or "").strip()
    model_id = DEFAULT_MODEL_ID

    if configured:
        configured_path = Path(configured).expanduser()
        if configured_path.exists():
            if model_directory_complete(configured_path):
                return str(configured_path.resolve()), model_id
            raise ModelUnavailableError(
                f"Configured Quran Muaalem model directory is incomplete: {configured_path}"
            )
        model_id = configured

    if model_directory_complete(DEFAULT_LOCAL_MODEL_PATH):
        return str(DEFAULT_LOCAL_MODEL_PATH.resolve()), model_id

    try:
        from huggingface_hub import snapshot_download

        cached_path = Path(snapshot_download(repo_id=model_id, local_files_only=True))
    except Exception as error:
        raise ModelUnavailableError(
            "No complete local Quran Muaalem model is available. "
            "Install the model artifact outside the web request and set QURAN_MUAALEM_MODEL."
        ) from error

    if not model_directory_complete(cached_path):
        raise ModelUnavailableError(f"Cached Quran Muaalem artifact is incomplete: {cached_path}")

    return str(cached_path.resolve()), model_id


def _probability_list(values: Any) -> list[float]:
    if hasattr(values, "detach"):
        values = values.detach().cpu().tolist()
    elif hasattr(values, "tolist"):
        values = values.tolist()
    return [clamp_probability(value) for value in (values or [])]


def _threshold_payload() -> dict[str, float]:
    return {
        "minimum_model_confidence": MIN_MODEL_CONFIDENCE,
        "high_target_confidence": HIGH_TARGET_CONFIDENCE,
        "minimum_target_alignment_coverage": MIN_TARGET_ALIGNMENT_COVERAGE,
        "maximum_content_phoneme_error_rate": MAX_CONTENT_PHONEME_ERROR_RATE,
        "maximum_correct_target_phoneme_error_rate": MAX_CORRECT_TARGET_PHONEME_ERROR_RATE,
    }


def _is_arabic_letter(character: str) -> bool:
    codepoint = ord(character)
    return 0x0621 <= codepoint <= 0x064A or codepoint == 0x0671


def _normalize_context_letter(character: str) -> str:
    return {
        chr(0x0623): chr(0x0621),
        chr(0x0625): chr(0x0621),
        chr(0x0622): chr(0x0621),
        chr(0x0624): chr(0x0621),
        chr(0x0626): chr(0x0621),
        chr(0x0671): chr(0x0627),
    }.get(character, character)


def _tanween_following_context(text: str, tanween_index: int) -> tuple[str | None, str | None]:
    """Return the next spoken letter and any existing Quran tanween marker."""

    tanween = text[tanween_index]
    crossed_word_boundary = False
    existing_marker: str | None = None
    index = tanween_index + 1

    while index < len(text):
        character = text[index]

        if character in TANWEEN_CONTEXT_MARKERS:
            existing_marker = character
            index += 1
            continue

        if character.isspace():
            crossed_word_boundary = True
            index += 1
            continue

        if character in NON_SPOKEN_QURAN_ANNOTATIONS:
            index += 1
            continue

        if unicodedata.category(character).startswith("M") or ord(character) == 0x0640:
            index += 1
            continue

        # The alif after fathatan in forms such as haqqan is an orthographic
        # carrier.  It is not the following Tajweed letter.
        if (
            tanween == FATHATAN
            and not crossed_word_boundary
            and character in FATHATAN_CARRIER_LETTERS
        ):
            index += 1
            continue

        if _is_arabic_letter(character):
            return _normalize_context_letter(character), existing_marker

        index += 1

    return None, existing_marker


def canonicalize_uthmani_reference_text(uthmani_text: str) -> tuple[str, dict[str, Any]]:
    """Create a Quran-Muaalem-safe reference while retaining source offset metadata.

    Simplified Uthmani sources commonly omit U+06ED, which quran-transcript
    requires to distinguish contextual tanween (Ikhfa/Idgham) from Izhar.
    Add that non-spoken control marker from the actual following letter, and
    remove visual pause annotations that are not model phonemes.
    """

    source = str(uthmani_text or "")
    without_annotations = "".join(
        character for character in source if character not in NON_SPOKEN_QURAN_ANNOTATIONS
    )
    removed_annotations = [
        {
            "source_index": index,
            "character": character,
            "codepoint": f"U+{ord(character):04X}",
        }
        for index, character in enumerate(source)
        if character in NON_SPOKEN_QURAN_ANNOTATIONS
    ]

    canonical_characters: list[str] = []
    inserted_markers: list[dict[str, Any]] = []

    for index, character in enumerate(without_annotations):
        canonical_characters.append(character)

        if character not in TANWEEN_MARKS:
            continue

        next_letter, existing_marker = _tanween_following_context(without_annotations, index)
        if existing_marker is not None or next_letter is None:
            continue

        marker: str | None = None
        context = ""
        if next_letter in IKHFA_LETTERS:
            marker = TANWEEN_CONTEXT_MARKER
            context = "ikhfa"
        elif next_letter in IDGHAM_LETTERS:
            marker = TANWEEN_CONTEXT_MARKER
            context = "idgham"
        elif next_letter == IQLAB_LETTER:
            marker = IQLAB_CONTEXT_MARKER
            context = "iqlab"

        if marker is None:
            continue

        canonical_characters.append(marker)
        inserted_markers.append(
            {
                "after_normalized_index": index,
                "marker": marker,
                "codepoint": f"U+{ord(marker):04X}",
                "context": context,
                "next_letter": next_letter,
            }
        )

    canonical_text = "".join(canonical_characters)
    return canonical_text, {
        "strategy": "contextual_canonicalization",
        "source_length": len(source),
        "canonical_length": len(canonical_text),
        "removed_annotations": removed_annotations,
        "inserted_context_markers": inserted_markers,
    }


def _normalized_quran_letters(text: str) -> str:
    return "".join(
        _normalize_context_letter(character)
        for character in str(text or "")
        if _is_arabic_letter(character)
    )


def resolve_canonical_reference_text(
    uthmani_text: str,
    source_surah: int | None = None,
    source_ayah: int | None = None,
) -> tuple[str, dict[str, Any]]:
    """Prefer quran-transcript's bundled canonical ayah when coordinates agree."""

    fallback_text, fallback_metadata = canonicalize_uthmani_reference_text(uthmani_text)
    if source_surah is None or source_ayah is None:
        return fallback_text, fallback_metadata

    try:
        surah = int(source_surah)
        ayah = int(source_ayah)
        if not 1 <= surah <= 114 or ayah < 1:
            raise ValueError("Quran coordinates are outside their valid range.")

        from quran_transcript import Aya

        bundled_text = str(Aya(surah, ayah).get().uthmani or "").strip()
        source_letters = _normalized_quran_letters(uthmani_text)
        bundled_letters = _normalized_quran_letters(bundled_text)
        maximum_length = max(1, len(source_letters), len(bundled_letters))
        similarity = 1.0 - (
            sequence_edit_distance(source_letters, bundled_letters) / maximum_length
        )

        # Never trust request coordinates blindly.  A wrong/stale ayah number
        # must fall back to the supplied text rather than score another verse.
        if not bundled_text or similarity < 0.94:
            return fallback_text, {
                **fallback_metadata,
                "requested_surah": surah,
                "requested_ayah": ayah,
                "bundled_reference_similarity": round(similarity, 6),
                "bundled_reference_rejected": True,
            }

        canonical_text, metadata = canonicalize_uthmani_reference_text(bundled_text)
        return canonical_text, {
            **metadata,
            "strategy": "bundled_quran_transcript_ayah",
            "requested_surah": surah,
            "requested_ayah": ayah,
            "bundled_reference_similarity": round(similarity, 6),
            "display_text_length": len(uthmani_text),
        }
    except Exception as error:
        return fallback_text, {
            **fallback_metadata,
            "requested_surah": source_surah,
            "requested_ayah": source_ayah,
            "bundled_reference_rejected": True,
            "bundled_reference_error": str(error),
        }


def _reference_phonetization(
    uthmani_text: str, canonical_text: str | None = None
) -> Any:
    from quran_transcript import MoshafAttributes, QuranPhoneticScriptOutput, quran_phonetizer
    from quran_transcript.phonetics.conv_base_operation import get_mappings, merge_mappings

    moshaf = MoshafAttributes(
        rewaya="hafs",
        madd_monfasel_len=4,
        madd_mottasel_len=4,
        madd_mottasel_waqf=4,
        madd_aared_len=4,
    )
    normalized_text = canonical_text
    if normalized_text is None:
        normalized_text, _ = canonicalize_uthmani_reference_text(uthmani_text)

    canonical_reference = quran_phonetizer(normalized_text, moshaf, remove_spaces=True)
    if normalized_text == uthmani_text:
        return canonical_reference

    # quran_phonetizer maps canonical characters to phonemes. Compose that map
    # with the source-to-canonical edit map so Laravel's original target offsets
    # remain valid even after deleting ornaments or inserting context markers.
    source_to_canonical = get_mappings(uthmani_text, normalized_text)
    source_to_phonemes = merge_mappings(source_to_canonical, canonical_reference.mappings)

    return QuranPhoneticScriptOutput(
        phonemes=canonical_reference.phonemes,
        sifat=canonical_reference.sifat,
        mappings=source_to_phonemes,
    )


def _model_unavailable_payload(
    *,
    model_id: str,
    reason: str,
    uthmani_text: str,
    reference: Any,
    targets: Sequence[dict[str, Any]],
    quality: dict[str, Any],
    reference_normalization: dict[str, Any] | None = None,
    model_status: str = "unavailable",
    payload_status: str = "failed",
    target_status: str = "failed",
    failure_code: str = "model_unavailable",
) -> dict[str, Any]:
    if target_status == "uncertain":
        target_decisions = uncertain_targets(targets, uthmani_text, reason)
    else:
        target_decisions = [
            _failed_target(
                target_value if isinstance(target_value, dict) else {},
                index,
                reason,
                failure_code=failure_code,
            )
            for index, target_value in enumerate(targets)
        ]

    return {
        "status": payload_status,
        "analysis_failure": payload_status == "failed",
        "model_status": model_status,
        "model_id": model_id,
        "model_path": None,
        "reason": reason,
        "reference_phonemes": reference.phonemes,
        "predicted_phonemes": "",
        "global_per": None,
        "phoneme_error_rate": None,
        "model_confidence": 0.0,
        "audio_quality": quality,
        "reference_normalization": reference_normalization or {},
        "errors": [],
        "failure_code": failure_code if payload_status == "failed" else None,
        "targets": target_decisions,
        "thresholds": _threshold_payload(),
    }


def _candidate_public_metadata(candidate: dict[str, Any]) -> dict[str, Any]:
    metadata = candidate.get("preprocessing") if isinstance(candidate, dict) else {}

    if not isinstance(metadata, dict):
        return {}

    return {key: value for key, value in metadata.items() if key != "processed_path"}


def _candidate_summary(candidates: Sequence[dict[str, Any]], selected_variant: str | None) -> dict[str, Any]:
    return {
        "enabled": ENABLE_AUDIO_CLEANING,
        "selected_variant": selected_variant,
        "noise_reduction_amount": REFERENCE_NOISE_REDUCTION_AMOUNT,
        "target_rms": REFERENCE_TARGET_RMS,
        "candidates": [
            {
                "variant": str(candidate.get("variant", "unknown")),
                "quality": candidate.get("quality", {}),
                "preprocessing": _candidate_public_metadata(candidate),
            }
            for candidate in candidates
        ],
    }


def _payload_from_model_output(
    *,
    output: Any,
    candidate: dict[str, Any],
    candidates: Sequence[dict[str, Any]],
    text: str,
    reference: Any,
    targets: Sequence[dict[str, Any]],
    model_id: str,
    model_path: str,
    device: str,
    reference_normalization: dict[str, Any],
    explain_error: Any,
) -> dict[str, Any]:
    predicted_phonemes = str(output.phonemes.text or "")
    probabilities = _probability_list(output.phonemes.probs)
    model_confidence = clamp_probability(float(np.mean(probabilities)) if probabilities else 0.0)
    global_per = phoneme_error_rate(reference.phonemes, predicted_phonemes)

    explanation_error: str | None = None
    try:
        reciter_errors = explain_error(
            uthmani_text=text,
            ref_ph_text=reference.phonemes,
            predicted_ph_text=predicted_phonemes,
            mappings=reference.mappings,
        )
        serialized_errors = [serialize_reciter_error(error) for error in reciter_errors]
    except Exception as error:
        explanation_error = str(error)
        serialized_errors = []

    if explanation_error is not None:
        target_decisions = [
            _failed_target(
                target_value if isinstance(target_value, dict) else {},
                index,
                "The phoneme error explainer failed, so no target correctness decision was made.",
                failure_code="error_explainer_failed",
            )
            for index, target_value in enumerate(targets)
        ]
    else:
        target_decisions = evaluate_targets(
            targets,
            reference.mappings,
            serialized_errors,
            text,
            reference.phonemes,
            predicted_phonemes,
            probabilities,
            model_confidence,
            global_per,
        )

    content_mismatch = global_per > MAX_CONTENT_PHONEME_ERROR_RATE
    low_confidence = model_confidence < MIN_MODEL_CONFIDENCE
    status = "failed" if explanation_error else ("uncertain" if content_mismatch else "success")

    if explanation_error:
        reason = "The model ran, but structured phoneme error explanation failed."
    elif content_mismatch:
        reason = (
            "The global phoneme error rate indicates content mismatch; this is not "
            "reported as an incorrect Tajweed pronunciation."
        )
    elif low_confidence:
        reason = (
            "The model confidence is low, but the recitation passed the content gate; "
            "only strong rule-specific errors are rejected."
        )
    else:
        reason = "Quran phonemes were aligned and evaluated at the supplied Tajweed targets."

    selected_variant = str(candidate.get("variant", "unknown"))
    quality = {
        **(candidate.get("quality", {}) if isinstance(candidate.get("quality"), dict) else {}),
        "variant": selected_variant,
    }

    return {
        "status": status,
        "analysis_failure": status == "failed",
        "failure_code": "error_explainer_failed" if explanation_error else None,
        "model_status": "loaded",
        "model_id": model_id,
        "model_path": model_path,
        "device": device,
        "reason": reason,
        "reference_phonemes": reference.phonemes,
        "predicted_phonemes": predicted_phonemes,
        "global_per": global_per,
        "phoneme_error_rate": global_per,
        "model_confidence": model_confidence,
        "low_model_confidence": low_confidence,
        "content_mismatch": content_mismatch,
        "audio_quality": quality,
        "audio_preprocessing": _candidate_summary(candidates, selected_variant),
        "reference_normalization": reference_normalization,
        "errors": serialized_errors,
        "error_explanation_error": explanation_error,
        "targets": target_decisions,
        "thresholds": _threshold_payload(),
    }


def _payload_selection_score(payload: dict[str, Any]) -> tuple[int, int, float, float, int]:
    decisive_targets = sum(
        1
        for target in payload.get("targets", [])
        if isinstance(target, dict)
        and target.get("status") in {"correct", "incorrect"}
        and bool(target.get("aligned_expected_target", False))
    )
    success_rank = 1 if payload.get("status") == "success" else 0
    confidence = float(payload.get("model_confidence") or 0.0)
    global_per = float(payload.get("global_per") if payload.get("global_per") is not None else 1.0)
    variant_rank = 1 if (payload.get("audio_quality") or {}).get("variant") == "raw" else 0

    return (success_rank, decisive_targets, -global_per, confidence, variant_rank)


def choose_best_model_payload(payloads: Sequence[dict[str, Any]]) -> dict[str, Any]:
    if not payloads:
        raise RuntimeError("No Quran Muaalem audio candidate payloads were produced.")

    return max(payloads, key=_payload_selection_score)


def analyze_recitation(
    audio_path: str,
    uthmani_text: str,
    targets: Sequence[dict[str, Any]],
    *,
    requested_model: str | None = None,
    source_surah: int | None = None,
    source_ayah: int | None = None,
) -> dict[str, Any]:
    text = str(uthmani_text or "").strip()
    if not text:
        raise ValueError("Selected Uthmani ayah text is required.")
    if not isinstance(targets, (list, tuple)):
        raise ValueError("targets_json must decode to a JSON array.")

    canonical_text, reference_normalization = resolve_canonical_reference_text(
        text,
        source_surah,
        source_ayah,
    )
    reference = _reference_phonetization(text, canonical_text)
    wave = load_audio(audio_path)
    candidates = prepare_audio_candidates(wave, audio_path=audio_path)
    best_quality_candidate = candidates[0]
    quality = best_quality_candidate["quality"]
    model_id = DEFAULT_MODEL_ID

    if not targets:
        payload = _model_unavailable_payload(
            model_id=model_id,
            reason="No Laravel Tajweed targets were supplied for the selected ayah.",
            uthmani_text=text,
            reference=reference,
            targets=targets,
            quality=quality,
            reference_normalization=reference_normalization,
            model_status="not_run_no_targets",
            failure_code="missing_targets",
        )
        payload["audio_preprocessing"] = _candidate_summary(candidates, None)
        cleanup_audio_candidates(candidates)
        return payload

    usable_candidates = [
        candidate for candidate in candidates if bool(candidate.get("quality", {}).get("usable"))
    ]

    if not usable_candidates:
        is_silent = quality.get("status") == "silent"
        payload = _model_unavailable_payload(
            model_id=model_id,
            reason=f"Audio quality gate returned {quality['status']}; pronunciation was not scored.",
            uthmani_text=text,
            reference=reference,
            targets=targets,
            quality=quality,
            reference_normalization=reference_normalization,
            model_status="not_run_unusable_audio",
            payload_status="uncertain" if is_silent else "failed",
            target_status="uncertain" if is_silent else "failed",
            failure_code=str(quality.get("status") or "unusable_audio"),
        )
        payload["audio_preprocessing"] = _candidate_summary(candidates, None)
        cleanup_audio_candidates(candidates)
        return payload

    try:
        model_path, model_id = resolve_local_model(requested_model)
    except ModelUnavailableError as error:
        payload = _model_unavailable_payload(
            model_id=model_id,
            reason=str(error),
            uthmani_text=text,
            reference=reference,
            targets=targets,
            quality=quality,
            reference_normalization=reference_normalization,
        )
        payload["audio_preprocessing"] = _candidate_summary(candidates, None)
        cleanup_audio_candidates(candidates)
        return payload

    try:
        import torch
        from quran_transcript import explain_error

        Muaalem = _load_muaalem_class()
        requested_device = os.environ.get("QURAN_MUAALEM_DEVICE", "").strip().lower()
        device = requested_device or ("cuda" if torch.cuda.is_available() else "cpu")
        if device == "cuda" and not torch.cuda.is_available():
            device = "cpu"
        dtype = torch.bfloat16 if device == "cuda" else torch.float32

        model = Muaalem(model_name_or_path=model_path, device=device, dtype=dtype)
        outputs = model(
            [candidate["wave"] for candidate in usable_candidates],
            [reference for _ in usable_candidates],
            sampling_rate=SAMPLE_RATE,
        )
        if not outputs:
            raise RuntimeError("Quran Muaalem returned no prediction output.")

        payloads = [
            _payload_from_model_output(
                output=output,
                candidate=candidate,
                candidates=candidates,
                text=text,
                reference=reference,
                targets=targets,
                model_id=model_id,
                model_path=model_path,
                device=device,
                reference_normalization=reference_normalization,
                explain_error=explain_error,
            )
            for candidate, output in zip(usable_candidates, outputs)
        ]

        return choose_best_model_payload(payloads)
    finally:
        cleanup_audio_candidates(candidates)


def main(argv: Sequence[str] | None = None) -> int:
    arguments = list(argv if argv is not None else sys.argv[1:])
    if len(arguments) not in (3, 5):
        print(
            json.dumps(
                {
                    "status": "failed",
                    "model_status": "not_run",
                    "error": (
                        "Usage: predict_quran_pronunciation.py "
                        "<audio_path> <selected_uthmani_ayah_text> <targets_json> "
                        "[<surah_number> <ayah_number>]"
                    ),
                }
            )
        )
        return 2

    audio_path, uthmani_text, targets_json = arguments[:3]
    try:
        targets = json.loads(targets_json)
        source_surah = int(arguments[3]) if len(arguments) == 5 else None
        source_ayah = int(arguments[4]) if len(arguments) == 5 else None
        payload = analyze_recitation(
            audio_path,
            uthmani_text,
            targets,
            source_surah=source_surah,
            source_ayah=source_ayah,
        )
        print(json.dumps(payload, ensure_ascii=False))
        return 0
    except Exception as error:
        print(
            json.dumps(
                {
                    "status": "failed",
                    "model_status": "failed",
                    "model_id": os.environ.get("QURAN_MUAALEM_MODEL", DEFAULT_MODEL_ID),
                    "error": str(error),
                },
                ensure_ascii=False,
            )
        )
        return 1


if __name__ == "__main__":
    if hasattr(sys.stdout, "reconfigure"):
        sys.stdout.reconfigure(encoding="utf-8")
    raise SystemExit(main())
