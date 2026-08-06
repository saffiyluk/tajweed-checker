import sys
import os
import tempfile
import unittest
from pathlib import Path
from types import SimpleNamespace
from unittest.mock import patch

import numpy as np
import soundfile as sf


PYTHON_DIR = Path(__file__).resolve().parents[1]
if str(PYTHON_DIR) not in sys.path:
    sys.path.insert(0, str(PYTHON_DIR))

import predict_quran_pronunciation as pronunciation
from audio_cleaning import preprocess_audio_file_for_ml


class QuranPronunciationDecisionTest(unittest.TestCase):
    def setUp(self):
        self.text = "abcdef"
        self.reference = "abcdef"
        self.predicted = "abcdef"
        self.mappings = [{"pos": [index, index + 1]} for index in range(len(self.text))]
        self.target = {
            "rule": "izhar",
            "expected_rule": "izhar",
            "position": 1,
            "end_position": 3,
        }
        self.probabilities = [0.95] * len(self.predicted)

    def evaluate(self, *, errors=None, confidence=0.95, per=0.0, target=None):
        return pronunciation.evaluate_targets(
            [target or self.target],
            self.mappings,
            errors or [],
            self.text,
            self.reference,
            self.predicted,
            self.probabilities,
            confidence,
            per,
        )[0]

    def test_high_confidence_aligned_target_without_errors_is_correct(self):
        result = self.evaluate()

        self.assertEqual("correct", result["status"])
        self.assertTrue(result["aligned_expected_target"])
        self.assertEqual([1, 4], result["character_span"])
        self.assertGreaterEqual(result["confidence"], 0.82)

    def test_hidden_noon_error_at_inclusive_end_of_izhar_target_is_incorrect(self):
        error = {
            "uthmani_span": [3, 4],
            "phoneme_span": [3, 4],
            # quran-transcript 0.5.2 classifies this explicit Izhar contrast as
            # normal even though it replaces clear noon with hidden/nasal noon.
            "error_type": "normal",
            "speech_error_type": "replace",
            "expected_phonemes": "\u0646",
            "predicted_phonemes": "\u06ba\u06ba\u06ba",
        }
        result = self.evaluate(errors=[error])

        self.assertEqual("incorrect", result["status"])
        self.assertEqual([error], result["errors"])
        self.assertEqual("izhar_too_long", result["elongation"]["error_code"])
        self.assertTrue(result["elongation"]["trusted"])

    def test_explicit_long_izhar_remains_decisive_when_target_coverage_is_low(self):
        self.probabilities = [0.50] * len(self.predicted)
        error = {
            "uthmani_span": [2, 3],
            "error_type": "normal",
            "speech_error_type": "replace",
            "expected_phonemes": "\u0646",
            "predicted_phonemes": "\u06ba\u06ba\u06ba",
        }

        result = self.evaluate(errors=[error], confidence=0.95)

        self.assertLess(result["confidence"], 0.82)
        self.assertEqual("incorrect", result["status"])
        self.assertEqual("izhar_too_long", result["elongation"]["error_code"])

    def test_hidden_noon_insertion_at_tanween_is_long_izhar(self):
        error = {
            "uthmani_span": [2, 2],
            "error_type": "normal",
            "speech_error_type": "insert",
            "expected_phonemes": "",
            "predicted_phonemes": "\u06ba",
        }

        result = self.evaluate(errors=[error])

        self.assertEqual("incorrect", result["status"])
        self.assertEqual("izhar_too_long", result["elongation"]["error_code"])

    def test_duplicated_clear_noon_is_long_izhar(self):
        error = {
            "uthmani_span": [2, 3],
            "error_type": "normal",
            "speech_error_type": "replace",
            "expected_phonemes": "\u0646",
            "predicted_phonemes": "\u0646\u0646",
        }

        result = self.evaluate(errors=[error])

        self.assertEqual("incorrect", result["status"])
        self.assertEqual("izhar_too_long", result["elongation"]["error_code"])

    def test_clear_noon_replacing_hidden_noon_is_short_ikhfa(self):
        self.probabilities = [0.50] * len(self.predicted)
        target = dict(self.target, rule="ikhfa", expected_rule="ikhfa")
        error = {
            "uthmani_span": [2, 3],
            "error_type": "normal",
            "speech_error_type": "replace",
            "expected_phonemes": "\u06ba\u06ba\u06ba",
            "predicted_phonemes": "\u0646",
        }

        result = self.evaluate(errors=[error], confidence=0.95, target=target)

        self.assertLess(result["confidence"], 0.82)
        self.assertEqual("incorrect", result["status"])
        self.assertEqual("ikhfa_too_short", result["elongation"]["error_code"])
        self.assertIn("too short", result["reason"])

    def test_matching_target_gets_a_trusted_correct_elongation_verdict(self):
        result = self.evaluate()

        self.assertEqual("correct", result["elongation"]["status"])
        self.assertTrue(result["elongation"]["trusted"])
        self.assertIsNone(result["elongation"]["error_code"])

    def test_unrelated_normal_error_overlapping_izhar_defaults_to_correct(self):
        error = {
            "uthmani_span": [2, 3],
            "error_type": "normal",
            "speech_error_type": "replace",
            "expected_phonemes": "c",
            "predicted_phonemes": "x",
        }

        result = self.evaluate(errors=[error])

        self.assertEqual("correct", result["status"])
        self.assertEqual([error], result["errors"])
        self.assertIn("not the explicit", result["reason"])

    def test_unrelated_tajweed_error_overlapping_izhar_defaults_to_correct(self):
        error = {
            "uthmani_span": [2, 3],
            "error_type": "tajweed",
            "speech_error_type": "replace",
            "expected_phonemes": "aaaa",
            "predicted_phonemes": "aa",
            "reference_tajweed_rules": [
                {"name": {"en": "Normal Madd"}, "tag": "alif"}
            ],
        }

        result = self.evaluate(errors=[error])

        self.assertEqual("correct", result["status"])
        self.assertEqual([error], result["errors"])

    def test_high_global_per_is_content_mismatch_not_incorrect(self):
        error = {
            "uthmani_span": [2, 3],
            "error_type": "normal",
            "speech_error_type": "replace",
            "expected_phonemes": "c",
            "predicted_phonemes": "x",
        }
        result = self.evaluate(errors=[error], per=0.60)

        self.assertEqual("uncertain", result["status"])
        self.assertIn("content mismatch", result["reason"])

    def test_low_model_confidence_without_rule_error_defaults_to_correct(self):
        result = self.evaluate(confidence=0.60)

        self.assertEqual("correct", result["status"])
        self.assertIn("no strong", result["reason"])

    def test_low_model_confidence_does_not_make_izhar_error_decisive(self):
        error = {
            "uthmani_span": [2, 3],
            "error_type": "normal",
            "speech_error_type": "replace",
            "expected_phonemes": "\u0646",
            "predicted_phonemes": "\u06ba",
        }

        result = self.evaluate(errors=[error], confidence=0.60)

        self.assertEqual("correct", result["status"])
        self.assertIn("not strong enough", result["reason"])

    def test_low_target_alignment_without_rule_error_defaults_to_correct(self):
        self.probabilities = [0.70] * len(self.predicted)

        result = self.evaluate()

        self.assertEqual("correct", result["status"])
        self.assertIn("no strong", result["reason"])

    def test_silent_audio_remains_uncertain(self):
        result = pronunciation.evaluate_targets(
            [self.target],
            self.mappings,
            [],
            self.text,
            self.reference,
            self.predicted,
            self.probabilities,
            0.95,
            0.0,
            audio_usable=False,
            audio_status="silent",
        )[0]

        self.assertEqual("uncertain", result["status"])
        self.assertIn("silent", result["reason"])

    def test_non_silent_unusable_audio_is_analysis_failure(self):
        result = pronunciation.evaluate_targets(
            [self.target],
            self.mappings,
            [],
            self.text,
            self.reference,
            self.predicted,
            self.probabilities,
            0.95,
            0.0,
            audio_usable=False,
            audio_status="too_short",
        )[0]

        self.assertEqual("failed", result["status"])
        self.assertEqual("too_short", result["failure_code"])

    def test_target_outside_selected_text_is_analysis_failure(self):
        invalid = dict(self.target, position=20, end_position=21)
        result = self.evaluate(target=invalid)

        self.assertEqual("failed", result["status"])
        self.assertTrue(result["analysis_failure"])
        self.assertEqual("target_alignment_failed", result["failure_code"])
        self.assertFalse(result["aligned_expected_target"])

    def test_point_insertion_inside_target_overlaps(self):
        self.assertTrue(pronunciation.spans_overlap((1, 4), (2, 2)))
        self.assertFalse(pronunciation.spans_overlap((1, 4), (4, 4)))


class QuranPronunciationPureHelpersTest(unittest.TestCase):
    def test_short_silent_audio_is_classified_as_silence(self):
        quality = pronunciation.estimate_audio_quality(np.zeros(100, dtype=np.float32))

        self.assertEqual("silent", quality["status"])
        self.assertFalse(quality["usable"])

    def test_model_unavailable_payload_is_failure_not_uncertainty(self):
        target = {
            "rule": "izhar",
            "expected_rule": "izhar",
            "position": 1,
            "end_position": 3,
        }
        payload = pronunciation._model_unavailable_payload(
            model_id="missing-model",
            reason="Local model is unavailable.",
            uthmani_text="abcdef",
            reference=SimpleNamespace(phonemes="abcdef"),
            targets=[target],
            quality={"status": "usable", "usable": True},
        )

        self.assertEqual("failed", payload["status"])
        self.assertTrue(payload["analysis_failure"])
        self.assertEqual("unavailable", payload["model_status"])
        self.assertEqual("failed", payload["targets"][0]["status"])
        self.assertEqual("model_unavailable", payload["targets"][0]["failure_code"])

    def test_silent_input_payload_is_uncertain(self):
        target = {
            "rule": "izhar",
            "expected_rule": "izhar",
            "position": 1,
            "end_position": 3,
        }
        payload = pronunciation._model_unavailable_payload(
            model_id="not-run",
            reason="Audio quality gate returned silent; pronunciation was not scored.",
            uthmani_text="abcdef",
            reference=SimpleNamespace(phonemes="abcdef"),
            targets=[target],
            quality={"status": "silent", "usable": False},
            model_status="not_run_unusable_audio",
            payload_status="uncertain",
            target_status="uncertain",
            failure_code="silent",
        )

        self.assertEqual("uncertain", payload["status"])
        self.assertFalse(payload["analysis_failure"])
        self.assertEqual("uncertain", payload["targets"][0]["status"])
        self.assertIsNone(payload["failure_code"])

    def test_low_confidence_matching_payload_is_success(self):
        target = {
            "rule": "izhar",
            "expected_rule": "izhar",
            "position": 1,
            "end_position": 3,
        }
        reference = SimpleNamespace(
            phonemes="abcdef",
            mappings=[{"pos": [index, index + 1]} for index in range(6)],
        )
        output = SimpleNamespace(
            phonemes=SimpleNamespace(text="abcdef", probs=[0.60] * 6)
        )
        candidate = {
            "variant": "raw",
            "quality": {"status": "usable", "usable": True},
        }

        payload = pronunciation._payload_from_model_output(
            output=output,
            candidate=candidate,
            candidates=[candidate],
            text="abcdef",
            reference=reference,
            targets=[target],
            model_id="test-model",
            model_path="test-model-path",
            device="cpu",
            reference_normalization={},
            explain_error=lambda **_kwargs: [],
        )

        self.assertEqual("success", payload["status"])
        self.assertFalse(payload["analysis_failure"])
        self.assertEqual("correct", payload["targets"][0]["status"])
        self.assertLess(payload["model_confidence"], pronunciation.MIN_MODEL_CONFIDENCE)

    def test_model_import_failure_still_cleans_preprocessed_audio_candidates(self):
        candidate = {
            "wave": np.zeros(1600, dtype=np.float32),
            "quality": {"usable": True, "status": "usable"},
        }
        reference = SimpleNamespace(phonemes="abcdef", mappings=[])

        with (
            patch.object(
                pronunciation,
                "resolve_canonical_reference_text",
                return_value=("abcdef", {}),
            ),
            patch.object(pronunciation, "_reference_phonetization", return_value=reference),
            patch.object(pronunciation, "load_audio", return_value=np.zeros(1600, dtype=np.float32)),
            patch.object(pronunciation, "prepare_audio_candidates", return_value=[candidate]),
            patch.object(pronunciation, "resolve_local_model", return_value=("model", "model-id")),
            patch.object(pronunciation, "_load_muaalem_class", side_effect=RuntimeError("import failed")),
            patch.object(pronunciation, "cleanup_audio_candidates") as cleanup,
        ):
            with self.assertRaisesRegex(RuntimeError, "import failed"):
                pronunciation.analyze_recitation(
                    "audio.wav",
                    "abcdef",
                    [{"rule": "izhar", "expected_rule": "izhar", "position": 1, "end_position": 3}],
                )

        cleanup.assert_called_once_with([candidate])

    def test_windows_runtime_identity_uses_sandbox_profile_when_login_name_is_missing(self):
        with (
            patch.object(pronunciation.os, "name", "nt"),
            patch.dict(
                pronunciation.os.environ,
                {
                    "USERPROFILE": r"C:\laragon\www\laravel\storage\app\python-home",
                    "HOME": r"C:\laragon\www\laravel\storage\app\python-home",
                },
                clear=True,
            ),
        ):
            username = pronunciation._ensure_windows_runtime_identity()

            self.assertEqual("python-home", username)
            self.assertEqual("python-home", pronunciation.os.environ["USERNAME"])

    def test_windows_runtime_identity_preserves_existing_login_name(self):
        with (
            patch.object(pronunciation.os, "name", "nt"),
            patch.dict(
                pronunciation.os.environ,
                {"USER": "existing-service-user"},
                clear=True,
            ),
        ):
            username = pronunciation._ensure_windows_runtime_identity()

            self.assertEqual("existing-service-user", username)
            self.assertNotIn("USERNAME", pronunciation.os.environ)

    def test_muaalem_loader_resolves_concrete_feature_extractor_before_package(self):
        transformers = SimpleNamespace()
        feature_extractor = object()
        muaalem = object()
        imported = []

        def import_module(name):
            imported.append(name)
            if name == "transformers":
                return transformers
            if name == "transformers.models.auto.feature_extraction_auto":
                return SimpleNamespace(AutoFeatureExtractor=feature_extractor)
            if name == "quran_muaalem":
                self.assertIs(feature_extractor, transformers.AutoFeatureExtractor)
                return SimpleNamespace(Muaalem=muaalem)
            raise AssertionError(f"Unexpected import: {name}")

        with patch.object(pronunciation.importlib, "import_module", side_effect=import_module):
            loaded = pronunciation._load_muaalem_class()

        self.assertIs(muaalem, loaded)
        self.assertEqual(
            [
                "transformers",
                "transformers.models.auto.feature_extraction_auto",
                "quran_muaalem",
            ],
            imported,
        )

    def test_muaalem_loader_preserves_nested_import_cause(self):
        root_error = OSError("runtime DLL was unavailable")
        lazy_error = ModuleNotFoundError("Could not import AutoFeatureExtractor")
        lazy_error.__cause__ = root_error

        with patch.object(
            pronunciation.importlib,
            "import_module",
            side_effect=lazy_error,
        ):
            with self.assertRaisesRegex(
                RuntimeError,
                "Could not import AutoFeatureExtractor.*runtime DLL was unavailable",
            ):
                pronunciation._load_muaalem_class()

    def test_phoneme_error_rate_uses_reference_length(self):
        self.assertEqual(0.25, pronunciation.phoneme_error_rate("abcd", "abxd"))

    def test_alignment_maps_substitution_diagonally(self):
        self.assertEqual([0, 1, 2], pronunciation.align_reference_to_prediction("abc", "axc"))

    def test_reference_canonicalization_removes_pause_mark_and_marks_contextual_tanween(self):
        text = (
            "\u062d\u064e\u0642\u0651\u064b\u0627 \u06da "
            "\u0644\u0651\u064e\u0647\u064f\u0645\u0652"
        )

        canonical, metadata = pronunciation.canonicalize_uthmani_reference_text(text)

        self.assertNotIn("\u06da", canonical)
        self.assertIn("\u064b\u06ed\u0627", canonical)
        self.assertEqual("U+06DA", metadata["removed_annotations"][0]["codepoint"])
        self.assertEqual("idgham", metadata["inserted_context_markers"][0]["context"])

    def test_simplified_tanween_before_ikhfa_gets_ghunnah_reference_and_source_offsets(self):
        text = (
            "\u0648\u064e\u0631\u0650\u0632\u0652\u0642\u064c "
            "\u0643\u064e\u0631\u0650\u064a\u0645\u064c"
        )
        canonical, _ = pronunciation.canonicalize_uthmani_reference_text(text)
        reference = pronunciation._reference_phonetization(text, canonical)
        tanween_index = text.index("\u064c")
        kaf_index = text.index("\u0643")
        target = {
            "rule": "ikhfa",
            "expected_rule": "ikhfa",
            "position": text.index("\u0642"),
            "end_position": kaf_index,
        }
        span = pronunciation.target_phoneme_span(target, reference.mappings, len(text))

        self.assertEqual(len(text), len(reference.mappings))
        self.assertIn("\u064c\u06ed", canonical)
        self.assertIsNotNone(span)
        self.assertIn("\u06ba\u06ba\u06ba", reference.phonemes[span[0] : span[1]])
        self.assertLess(tanween_index, kaf_index)

    def test_izhar_tanween_does_not_receive_context_marker(self):
        text = "\u062f\u064e\u0631\u064e\u062c\u064e\u0627\u062a\u064c \u0639\u0650\u0646\u062f\u064e"

        canonical, metadata = pronunciation.canonicalize_uthmani_reference_text(text)

        self.assertNotIn("\u06ed", canonical)
        self.assertEqual([], metadata["inserted_context_markers"])

    def test_matching_coordinates_use_bundled_canonical_quran_reference(self):
        simplified = (
            "\u0623\u064f\u0648\u06df\u0644\u064e\u0640\u0670\u0653\u0626\u0650\u0643\u064e "
            "\u0647\u064f\u0645\u064f \u0671\u0644\u0652\u0645\u064f\u0624\u0652\u0645\u0650\u0646\u064f\u0648\u0646\u064e "
            "\u062d\u064e\u0642\u0651\u064b\u0627 \u06da \u0644\u0651\u064e\u0647\u064f\u0645\u0652 "
            "\u062f\u064e\u0631\u064e\u062c\u064e\u0640\u0670\u062a\u064c \u0639\u0650\u0646\u062f\u064e "
            "\u0631\u064e\u0628\u0651\u0650\u0647\u0650\u0645\u0652 \u0648\u064e\u0645\u064e\u063a\u0652\u0641\u0650\u0631\u064e\u0629\u064c "
            "\u0648\u064e\u0631\u0650\u0632\u0652\u0642\u064c \u0643\u064e\u0631\u0650\u064a\u0645\u064c"
        )

        canonical, metadata = pronunciation.resolve_canonical_reference_text(simplified, 8, 4)

        self.assertEqual("bundled_quran_transcript_ayah", metadata["strategy"])
        self.assertGreaterEqual(metadata["bundled_reference_similarity"], 0.94)
        self.assertNotIn("\u06da", canonical)
        self.assertIn("\u06ed", canonical)

    def test_mismatched_coordinates_are_rejected(self):
        text = "\u062f\u064e\u0631\u064e\u062c\u064e\u0627\u062a\u064c \u0639\u0650\u0646\u062f\u064e"

        _, metadata = pronunciation.resolve_canonical_reference_text(text, 1, 1)

        self.assertEqual("contextual_canonicalization", metadata["strategy"])
        self.assertTrue(metadata["bundled_reference_rejected"])

    def test_quiet_audio_gets_cleaned_reference_candidate(self):
        duration_seconds = 1.5
        samples = int(pronunciation.SAMPLE_RATE * duration_seconds)
        time = np.arange(samples, dtype=np.float32) / pronunciation.SAMPLE_RATE
        quiet_wave = (0.001 * np.sin(2 * np.pi * 220 * time)).astype(np.float32)

        candidates = pronunciation.prepare_audio_candidates(quiet_wave)
        qualities = {candidate["variant"]: candidate["quality"] for candidate in candidates}

        self.assertIn("raw", qualities)
        self.assertIn("cleaned", qualities)
        self.assertFalse(qualities["raw"]["usable"])
        self.assertTrue(qualities["cleaned"]["usable"])

    def test_audio_payload_selection_prefers_successful_alignment(self):
        raw = {
            "status": "uncertain",
            "model_confidence": 0.99,
            "global_per": 0.5,
            "audio_quality": {"variant": "raw"},
            "targets": [],
        }
        cleaned = {
            "status": "success",
            "model_confidence": 0.93,
            "global_per": 0.05,
            "audio_quality": {"variant": "cleaned"},
            "targets": [
                {
                    "status": "correct",
                    "aligned_expected_target": True,
                }
            ],
        }

        self.assertIs(cleaned, pronunciation.choose_best_model_payload([raw, cleaned]))

    def test_full_audio_preprocessing_stack_saves_processed_wav(self):
        samples = int(pronunciation.SAMPLE_RATE * 1.2)
        time = np.arange(samples, dtype=np.float32) / pronunciation.SAMPLE_RATE
        wave = (0.02 * np.sin(2 * np.pi * 220 * time)).astype(np.float32)
        source = tempfile.NamedTemporaryFile(suffix=".wav", delete=False)
        source.close()

        try:
            sf.write(source.name, wave, pronunciation.SAMPLE_RATE, subtype="PCM_16")
            processed = preprocess_audio_file_for_ml(
                source.name,
                sample_rate=pronunciation.SAMPLE_RATE,
                target_rms=0.08,
                noise_reduction_amount=0.05,
                save_wav=True,
            )

            self.assertEqual("soundfile", processed["metadata"]["loader"])
            self.assertEqual("soundfile", processed["metadata"]["writer"])
            self.assertTrue(os.path.isfile(processed["processed_path"]))
            self.assertGreater(processed["wave"].size, 0)
        finally:
            if os.path.exists(source.name):
                os.unlink(source.name)
            processed_path = locals().get("processed", {}).get("processed_path")
            if processed_path and os.path.exists(processed_path):
                os.unlink(processed_path)


if __name__ == "__main__":
    unittest.main()
