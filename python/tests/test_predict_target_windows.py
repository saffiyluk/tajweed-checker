import sys
import unittest
from pathlib import Path
from unittest.mock import patch

import numpy as np


PYTHON_DIR = Path(__file__).resolve().parents[1]
if str(PYTHON_DIR) not in sys.path:
    sys.path.insert(0, str(PYTHON_DIR))

import predict_target_windows as target_windows


def izhar_quality(**overrides):
    quality = {
        "window_is_silent": False,
        "window_rms": 0.08,
        "ghunnah_duration_ms": 0.0,
        "ghunnah_frame_ratio": 0.0,
        "ghunnah_strength": 0.0,
        "nasal_excess_score": 0.0,
        "transition_smoothness": 0.30,
        "rms_stability": 0.75,
    }
    quality.update(overrides)
    return quality


def heuristic_decision(status, quality):
    return {
        "status": status,
        "reason": f"{status} test decision",
        "decision_source": "heuristic_only",
        "quality": quality,
    }


class IzharHeuristicStatusTest(unittest.TestCase):
    def test_duration_boundary_is_binary_and_inclusive_at_50_ms(self):
        cases = {
            49.0: "correct",
            50.0: "correct",
            51.0: "incorrect",
        }

        for duration, expected in cases.items():
            with self.subTest(duration=duration):
                status, _ = target_windows.heuristic_status(
                    "izhar",
                    izhar_quality(ghunnah_duration_ms=duration),
                )
                self.assertEqual(expected, status)

    def test_non_duration_cues_cannot_make_50_ms_izhar_incorrect(self):
        status, _ = target_windows.heuristic_status(
            "izhar",
            izhar_quality(
                ghunnah_duration_ms=50.0,
                ghunnah_frame_ratio=0.95,
                ghunnah_strength=0.95,
                nasal_excess_score=2.0,
                transition_smoothness=0.99,
                rms_stability=0.99,
            ),
        )

        self.assertEqual("correct", status)

    def test_low_non_duration_cues_cannot_rescue_long_izhar(self):
        status, reason = target_windows.heuristic_status(
            "izhar",
            izhar_quality(
                ghunnah_duration_ms=51.0,
                ghunnah_frame_ratio=0.0,
                ghunnah_strength=0.0,
                nasal_excess_score=0.0,
            ),
        )

        self.assertEqual("incorrect", status)
        self.assertIn("long nasal hold", reason.lower())

    def test_ml_cannot_override_izhar_duration_verdict(self):
        cases = [
            (49.0, "incorrect", "ML detected nasal Izhar.", "correct"),
            (51.0, "correct", "ML found clear Izhar.", "incorrect"),
        ]

        for duration, ml_status, ml_reason, expected in cases:
            with self.subTest(duration=duration, ml_status=ml_status):
                quality = izhar_quality(ghunnah_duration_ms=duration)
                heuristic = target_windows.heuristic_status("izhar", quality)
                status, reason, source = target_windows.resolve_status(
                    "izhar",
                    ml_status,
                    ml_reason,
                    0.99,
                    heuristic,
                    quality,
                )

                self.assertEqual(expected, status)
                self.assertEqual(heuristic[1], reason)
                self.assertEqual("elongation_duration_rule", source)

    def test_silent_target_remains_uncertain(self):
        quality = izhar_quality(window_is_silent=True, window_rms=0.0)

        status, reason, source = target_windows.resolve_status(
            "izhar",
            "correct",
            "ML found clear Izhar.",
            0.99,
            target_windows.heuristic_status("izhar", quality),
            quality,
        )

        self.assertEqual("uncertain", status)
        self.assertEqual("unusable_target_audio", source)
        self.assertIn("silent", reason.lower())

    def test_local_window_silence_defaults_to_correct_when_recording_has_speech(self):
        decision = heuristic_decision(
            "uncertain",
            izhar_quality(window_is_silent=True, window_rms=0.0),
        )

        finalized = target_windows.finalize_target_input_status(
            decision,
            recording_is_silent=False,
        )

        self.assertEqual("correct", finalized["status"])
        self.assertEqual(
            "local_window_unavailable_default_correct",
            finalized["decision_source"],
        )
        self.assertIn("audible speech", finalized["reason"])

    def test_whole_recording_silence_remains_uncertain(self):
        decision = heuristic_decision(
            "uncertain",
            izhar_quality(window_is_silent=True, window_rms=0.0),
        )

        finalized = target_windows.finalize_target_input_status(
            decision,
            recording_is_silent=True,
        )

        self.assertEqual("uncertain", finalized["status"])
        self.assertEqual("recording_silence", finalized["decision_source"])
        self.assertIn("recording is silent", finalized["reason"].lower())


class IkhfaHeuristicStatusTest(unittest.TestCase):
    def test_duration_boundary_is_binary_and_inclusive_at_50_ms(self):
        cases = {
            49.0: "incorrect",
            50.0: "correct",
            51.0: "correct",
        }

        for duration, expected in cases.items():
            with self.subTest(duration=duration):
                status, _ = target_windows.heuristic_status(
                    "ikhfa",
                    izhar_quality(ghunnah_duration_ms=duration),
                )
                self.assertEqual(expected, status)

    def test_strong_non_duration_cues_cannot_rescue_short_ikhfa(self):
        status, reason = target_windows.heuristic_status(
            "ikhfa",
            izhar_quality(
                ghunnah_duration_ms=49.0,
                ghunnah_frame_ratio=0.95,
                ghunnah_strength=0.95,
                nasal_excess_score=2.0,
                transition_smoothness=0.99,
                rms_stability=0.99,
            ),
        )

        self.assertEqual("incorrect", status)
        self.assertIn("too short", reason.lower())

    def test_low_non_duration_cues_cannot_invalidate_long_enough_ikhfa(self):
        status, _ = target_windows.heuristic_status(
            "ikhfa",
            izhar_quality(
                ghunnah_duration_ms=50.0,
                ghunnah_frame_ratio=0.0,
                ghunnah_strength=0.0,
                nasal_excess_score=0.0,
            ),
        )

        self.assertEqual("correct", status)

    def test_ml_cannot_override_ikhfa_duration_verdict(self):
        cases = [
            (49.0, "correct", "ML found correct Ikhfa.", "incorrect"),
            (50.0, "incorrect", "ML found weak Ikhfa.", "correct"),
        ]

        for duration, ml_status, ml_reason, expected in cases:
            with self.subTest(duration=duration, ml_status=ml_status):
                quality = izhar_quality(ghunnah_duration_ms=duration)
                heuristic = target_windows.heuristic_status("ikhfa", quality)
                status, reason, source = target_windows.resolve_status(
                    "ikhfa",
                    ml_status,
                    ml_reason,
                    0.99,
                    heuristic,
                    quality,
                )

                self.assertEqual(expected, status)
                self.assertEqual(heuristic[1], reason)
                self.assertEqual("elongation_duration_rule", source)


class IzharCandidateWindowTest(unittest.TestCase):
    def setUp(self):
        self.audio = np.zeros(target_windows.SAMPLE_RATE * 3, dtype=np.float32)

    def test_usable_expected_window_is_not_overridden_by_another_word(self):
        expected = heuristic_decision(
            "correct",
            izhar_quality(ghunnah_duration_ms=49.0, nasal_excess_score=0.28),
        )
        neighbouring_word = heuristic_decision(
            "incorrect",
            izhar_quality(
                window_rms=0.20,
                ghunnah_duration_ms=180.0,
                ghunnah_frame_ratio=0.30,
                ghunnah_strength=0.30,
                nasal_excess_score=0.90,
            ),
        )

        with (
            patch.object(target_windows, "target_candidate_ratios", return_value=[0.5, 0.58]),
            patch.object(target_windows, "crop_window", return_value=np.zeros(32, dtype=np.float32)) as crop,
            patch.object(target_windows, "predict_single_target", side_effect=[expected, neighbouring_word]) as predict,
        ):
            decision, selected_ratio, checked = target_windows.predict_from_candidate_windows(
                None,
                [],
                self.audio,
                0.5,
                2.4,
                "izhar",
            )

        self.assertEqual("correct", decision["status"])
        self.assertEqual(0.5, selected_ratio)
        self.assertEqual(1, len(checked))
        self.assertEqual(1, predict.call_count)
        self.assertEqual(target_windows.ELONGATION_LOCAL_WINDOW_SECONDS, crop.call_args.args[2])

    def test_short_expected_ikhfa_is_not_replaced_by_neighbouring_nasal_activity(self):
        expected = heuristic_decision(
            "incorrect",
            izhar_quality(ghunnah_duration_ms=49.0),
        )
        neighbouring_nasal = heuristic_decision(
            "correct",
            izhar_quality(
                window_rms=0.20,
                ghunnah_duration_ms=180.0,
                ghunnah_frame_ratio=0.30,
                ghunnah_strength=0.30,
                nasal_excess_score=0.90,
            ),
        )

        with (
            patch.object(target_windows, "target_candidate_ratios", return_value=[0.5, 0.58]),
            patch.object(target_windows, "crop_window", return_value=np.zeros(32, dtype=np.float32)) as crop,
            patch.object(target_windows, "predict_single_target", side_effect=[expected, neighbouring_nasal]) as predict,
        ):
            decision, selected_ratio, checked = target_windows.predict_from_candidate_windows(
                None,
                [],
                self.audio,
                0.5,
                2.4,
                "ikhfa",
            )

        self.assertEqual("incorrect", decision["status"])
        self.assertEqual(0.5, selected_ratio)
        self.assertEqual(1, len(checked))
        self.assertEqual(1, predict.call_count)
        self.assertEqual(target_windows.ELONGATION_LOCAL_WINDOW_SECONDS, crop.call_args.args[2])

    def test_scan_recovers_nearby_active_window_when_expected_window_is_silent(self):
        silent = heuristic_decision("uncertain", izhar_quality(window_is_silent=True, window_rms=0.0))
        nearby = heuristic_decision(
            "incorrect",
            izhar_quality(
                window_rms=0.16,
                ghunnah_duration_ms=120.0,
                ghunnah_frame_ratio=0.18,
                ghunnah_strength=0.25,
                nasal_excess_score=0.60,
            ),
        )

        with (
            patch.object(target_windows, "target_candidate_ratios", return_value=[0.5, 0.58]),
            patch.object(target_windows, "crop_window", return_value=np.zeros(32, dtype=np.float32)),
            patch.object(target_windows, "predict_single_target", side_effect=[silent, nearby]),
        ):
            decision, selected_ratio, _ = target_windows.predict_from_candidate_windows(
                None,
                [],
                self.audio,
                0.5,
                2.4,
                "izhar",
            )

        self.assertEqual("incorrect", decision["status"])
        self.assertEqual(0.58, selected_ratio)

    def test_distant_scanned_nasal_event_defaults_to_correct(self):
        silent = heuristic_decision("uncertain", izhar_quality(window_is_silent=True, window_rms=0.0))
        distant = heuristic_decision(
            "incorrect",
            izhar_quality(
                window_rms=0.20,
                ghunnah_duration_ms=180.0,
                ghunnah_frame_ratio=0.30,
                ghunnah_strength=0.30,
                nasal_excess_score=0.90,
            ),
        )

        with (
            patch.object(target_windows, "target_candidate_ratios", return_value=[0.5, 0.7]),
            patch.object(target_windows, "crop_window", return_value=np.zeros(32, dtype=np.float32)),
            patch.object(target_windows, "predict_single_target", side_effect=[silent, distant]),
        ):
            decision, selected_ratio, _ = target_windows.predict_from_candidate_windows(
                None,
                [],
                self.audio,
                0.5,
                2.4,
                "izhar",
            )

        self.assertEqual("correct", decision["status"])
        self.assertEqual("distant_heuristic_candidate", decision["decision_source"])
        self.assertEqual(0.7, selected_ratio)

    def test_loaded_model_keeps_configured_window_size(self):
        decision = heuristic_decision("correct", izhar_quality())

        with (
            patch.object(target_windows, "target_candidate_ratios", return_value=[0.5]),
            patch.object(target_windows, "crop_window", return_value=np.zeros(32, dtype=np.float32)) as crop,
            patch.object(target_windows, "predict_single_target", return_value=decision),
        ):
            target_windows.predict_from_candidate_windows(
                object(),
                ["izhar_correct"],
                self.audio,
                0.5,
                2.4,
                "izhar",
            )

        self.assertEqual(2.4, crop.call_args.args[2])

    def test_predict_targets_emits_expected_narrow_elongation_quality_separately(self):
        model = object()
        audio = np.ones(target_windows.SAMPLE_RATE * 3, dtype=np.float32) * 0.05
        broad_window = np.ones(64, dtype=np.float32)
        local_window = np.ones(32, dtype=np.float32)
        elongation_quality = izhar_quality(ghunnah_duration_ms=49.0)
        model_quality = izhar_quality(ghunnah_duration_ms=180.0)
        decision = {
            "label": "ikhfa_correct",
            "status": "incorrect",
            "reason": "Ikhfa duration is short.",
            "confidence": 0.99,
            "probabilities": {"ikhfa_correct": 0.99},
            "decision_source": "elongation_duration_rule",
            "quality": model_quality,
            "elongation_quality": elongation_quality,
        }
        target = {
            "rule": "ikhfa",
            "expected_rule": "ikhfa",
            "position_ratio": 0.5,
        }

        with (
            patch.object(target_windows, "load_target_model", return_value=(model, ["ikhfa_correct"])),
            patch.object(target_windows, "load_audio", return_value=audio),
            patch.object(target_windows, "crop_window", side_effect=[broad_window, local_window]) as crop,
            patch.object(target_windows, "estimate_window_ghunnah_features", return_value=elongation_quality),
            patch.object(target_windows, "predict_single_target", return_value=decision) as predict,
        ):
            payload = target_windows.predict_targets("test.wav", [target], window_seconds=2.4)

        prediction = payload["targets"][0]
        diagnostic_quality = {
            **elongation_quality,
            "target_alignment_verified": False,
            "target_alignment_method": "linear_text_ratio",
        }
        self.assertEqual(diagnostic_quality, prediction["elongation_quality"])
        self.assertEqual(model_quality, prediction["quality"])
        self.assertEqual(2.4, crop.call_args_list[0].args[2])
        self.assertEqual(
            target_windows.ELONGATION_LOCAL_WINDOW_SECONDS,
            crop.call_args_list[1].args[2],
        )
        self.assertEqual(
            diagnostic_quality,
            predict.call_args.kwargs["elongation_quality"],
        )


if __name__ == "__main__":
    unittest.main()
