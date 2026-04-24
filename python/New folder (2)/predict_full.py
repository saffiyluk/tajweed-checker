import pickle
from collections import Counter

import librosa
import numpy as np
import tensorflow as tf

from audio_features import SAMPLE_RATE, extract_mfcc_features, to_model_input

model = tf.keras.models.load_model("tajweed_model.keras")

with open("label_encoder.pkl", "rb") as f:
    le = pickle.load(f)

WINDOW_SIZE = 0.8
STEP_SIZE = 0.4
CONFIDENCE_THRESHOLD = 0.7


def predict_full(audio_path):
    y, sr = librosa.load(audio_path, sr=SAMPLE_RATE)

    window_samples = int(WINDOW_SIZE * sr)
    step_samples = int(STEP_SIZE * sr)
    results = []

    for start in range(0, len(y) - window_samples + 1, step_samples):
        segment = y[start:start + window_samples]
        features = extract_mfcc_features(y_audio=segment, sr=sr)
        features = np.expand_dims(to_model_input(features), axis=0)

        pred = model.predict(features, verbose=0)[0]
        label_index = int(np.argmax(pred))
        label = le.inverse_transform([label_index])[0]
        confidence = float(np.max(pred))

        if confidence < CONFIDENCE_THRESHOLD:
            label = "uncertain"

        results.append(
            {
                "time": start / sr,
                "label": label,
                "confidence": confidence,
                "probabilities": {
                    class_name: float(prob)
                    for class_name, prob in zip(le.classes_, pred)
                },
            }
        )

    return results


def summarize_predictions(results):
    label_counts = Counter(item["label"] for item in results)
    confident = [item for item in results if item["label"] != "uncertain"]

    if confident:
        best_label = Counter(item["label"] for item in confident).most_common(1)[0][0]
        avg_confidence = float(np.mean([item["confidence"] for item in confident]))
    else:
        best_label = "uncertain"
        avg_confidence = 0.0

    return {
        "summary_label": best_label,
        "avg_confidence": avg_confidence,
        "counts": dict(label_counts),
    }
