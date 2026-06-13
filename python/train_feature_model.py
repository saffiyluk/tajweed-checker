import os
import pickle
import json
from collections import Counter

import numpy as np
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import classification_report, confusion_matrix
from sklearn.model_selection import train_test_split
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import LabelEncoder, StandardScaler

from hybrid_features import extract_summary_features

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DATASET_PATH = os.path.join(BASE_DIR, "dataset")
MODEL_PATH = os.path.join(BASE_DIR, "feature_model.pkl")
LABEL_ENCODER_PATH = os.path.join(BASE_DIR, "label_encoder.pkl")
METRICS_PATH = os.path.join(BASE_DIR, "feature_model_metrics.json")
SUPPORTED_EXTENSIONS = (".wav", ".mp3", ".webm", ".m4a", ".ogg", ".oga", ".flac", ".aac", ".mp4")


def iter_audio_files():
    for label in sorted(os.listdir(DATASET_PATH)):
        folder = os.path.join(DATASET_PATH, label)

        if not os.path.isdir(folder):
            continue

        for file_name in sorted(os.listdir(folder)):
            if file_name.lower().endswith(SUPPORTED_EXTENSIONS):
                yield label, os.path.join(folder, file_name)


def main():
    X = []
    y = []

    for label, file_path in iter_audio_files():
        try:
            X.append(extract_summary_features(file_path))
            y.append(label)
        except Exception as exc:
            print(f"Skipping {file_path}: {exc}")

    if not X:
        raise RuntimeError("No supported audio files were found in python/dataset.")

    X = np.array(X, dtype=np.float32)

    label_encoder = LabelEncoder()
    y_encoded = label_encoder.fit_transform(y)

    print("Loaded:", X.shape)
    print("Class counts:", Counter(y))

    X_train, X_test, y_train, y_test = train_test_split(
        X,
        y_encoded,
        test_size=0.2,
        stratify=y_encoded,
        random_state=42,
    )

    model = Pipeline(
        [
            ("scaler", StandardScaler()),
            (
                "classifier",
                RandomForestClassifier(
                    n_estimators=300,
                    class_weight="balanced",
                    random_state=42,
                    max_depth=None,
                    min_samples_leaf=2,
                ),
            ),
        ]
    )

    print("Training feature model...")
    model.fit(X_train, y_train)

    y_pred = model.predict(X_test)
    accuracy = float(model.score(X_test, y_test))
    matrix = confusion_matrix(y_test, y_pred)
    report_text = classification_report(y_test, y_pred, target_names=label_encoder.classes_)
    report_dict = classification_report(y_test, y_pred, target_names=label_encoder.classes_, output_dict=True)

    print("Accuracy:", accuracy)
    print("Confusion matrix:\n", matrix)
    print("Classification report:\n", report_text)

    with open(MODEL_PATH, "wb") as f:
        pickle.dump(
            {
                "model": model,
                "classes": [str(item) for item in label_encoder.classes_],
            },
            f,
        )

    with open(LABEL_ENCODER_PATH, "wb") as f:
        pickle.dump(label_encoder, f)

    metrics = {
        "model_type": "feature",
        "accuracy": accuracy,
        "classes": [str(item) for item in label_encoder.classes_],
        "class_counts": dict(Counter(y)),
        "train_size": int(len(y_train)),
        "test_size": int(len(y_test)),
        "confusion_matrix": matrix.tolist(),
        "classification_report": report_dict,
        "model_path": MODEL_PATH,
    }

    with open(METRICS_PATH, "w", encoding="utf-8") as f:
        json.dump(metrics, f, ensure_ascii=False, indent=2)

    print("Saved:", MODEL_PATH)
    print("Metrics saved:", METRICS_PATH)
    print("Label mapping:", dict(enumerate(label_encoder.classes_)))


if __name__ == "__main__":
    main()
