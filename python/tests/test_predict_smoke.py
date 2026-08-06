import os
import pickle
import sys
import tempfile
import unittest
from pathlib import Path

import numpy as np
import soundfile as sf


PYTHON_DIR = Path(__file__).resolve().parents[1]
if str(PYTHON_DIR) not in sys.path:
    sys.path.insert(0, str(PYTHON_DIR))

import predict
from hybrid_features import extract_summary_features


EXPECTED_CLASSES = ["ikhfa", "izhar", "other"]


def speech_like_signal(seconds=2.0):
    sample_count = int(round(predict.SAMPLE_RATE * seconds))
    time = np.arange(sample_count, dtype=np.float32) / predict.SAMPLE_RATE
    envelope = 0.45 + (0.55 * ((np.sin(2.0 * np.pi * 2.4 * time) + 1.0) / 2.0))
    carrier = np.sin(2.0 * np.pi * 185.0 * time) + (0.3 * np.sin(2.0 * np.pi * 370.0 * time))
    return (0.12 * envelope * carrier).astype(np.float32)


class AudioActivitySmokeTest(unittest.TestCase):
    def test_speech_like_audio_has_nonzero_activity(self):
        quality = predict.estimate_audio_activity(speech_like_signal())

        self.assertEqual("usable", quality["audio_activity_status"])
        self.assertGreater(quality["raw_active_frame_ratio"], 0.05)
        self.assertFalse(predict.audio_is_unusable(quality))

    def test_silence_is_rejected(self):
        silence = np.zeros(predict.SAMPLE_RATE * 2, dtype=np.float32)
        quality = predict.estimate_audio_activity(silence)

        self.assertEqual("silent", quality["audio_activity_status"])
        self.assertTrue(quality["is_silent"])
        self.assertTrue(predict.audio_is_unusable(quality))

    def test_too_short_audio_is_rejected_even_when_loud(self):
        quality = predict.estimate_audio_activity(speech_like_signal(seconds=0.25))

        self.assertEqual("too_short", quality["audio_activity_status"])
        self.assertTrue(quality["is_too_short"])
        self.assertTrue(predict.audio_is_unusable(quality))


class EnsembleDecisionSmokeTest(unittest.TestCase):
    def test_strong_feature_other_gate_runs_before_moderate_cnn_priority(self):
        cnn_result = {
            "prediction": "ikhfa",
            "raw_prediction": "ikhfa",
            "confidence": 0.80,
            "margin": 0.65,
            "probabilities": {"ikhfa": 0.80, "izhar": 0.05, "other": 0.15},
            "status": "confident",
        }
        feature_result = {
            "prediction": "other",
            "raw_prediction": "other",
            "confidence": 0.82,
            "margin": 0.70,
            "probabilities": {"ikhfa": 0.08, "izhar": 0.10, "other": 0.82},
            "status": "unrelated",
        }

        result = predict.combine_predictions(cnn_result, feature_result)

        self.assertEqual("other", result["prediction"])
        self.assertEqual("feature_other_gate", result["method"])

    def test_genuinely_strong_cnn_can_pass_the_other_gate(self):
        cnn_result = {
            "prediction": "ikhfa",
            "raw_prediction": "ikhfa",
            "confidence": 0.92,
            "margin": 0.84,
            "probabilities": {"ikhfa": 0.92, "izhar": 0.04, "other": 0.04},
            "status": "confident",
        }
        feature_result = {
            "prediction": "other",
            "raw_prediction": "other",
            "confidence": 0.82,
            "margin": 0.70,
            "probabilities": {"ikhfa": 0.08, "izhar": 0.10, "other": 0.82},
            "status": "unrelated",
        }

        result = predict.combine_predictions(cnn_result, feature_result)

        self.assertEqual("ikhfa", result["prediction"])
        self.assertEqual("cnn_rule_priority", result["method"])


class ModelArtifactSmokeTest(unittest.TestCase):
    def test_saved_models_match_class_and_feature_contracts(self):
        from tensorflow.keras.models import load_model

        with open(predict.LABEL_ENCODER_PATH, "rb") as handle:
            label_encoder = pickle.load(handle)

        classes = [str(item) for item in label_encoder.classes_]
        self.assertEqual(EXPECTED_CLASSES, classes)

        cnn_model = load_model(predict.MODEL_PATH, compile=False)
        self.assertEqual(len(classes), int(cnn_model.output_shape[-1]))

        with open(predict.FEATURE_MODEL_PATH, "rb") as handle:
            feature_payload = pickle.load(handle)

        self.assertEqual(classes, [str(item) for item in feature_payload["classes"]])

        temp_path = None
        try:
            with tempfile.NamedTemporaryFile(suffix=".wav", delete=False) as temp:
                temp_path = temp.name
            sf.write(temp_path, speech_like_signal(), predict.SAMPLE_RATE, subtype="PCM_16")
            features = extract_summary_features(temp_path)
        finally:
            if temp_path and os.path.exists(temp_path):
                os.unlink(temp_path)

        classifier = feature_payload["model"].named_steps["classifier"]
        self.assertEqual(int(classifier.n_features_in_), int(features.shape[0]))


if __name__ == "__main__":
    unittest.main()
