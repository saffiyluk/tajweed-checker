import argparse
import json
import pickle
import sys
from collections import Counter
from pathlib import Path

import numpy as np
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import classification_report, confusion_matrix
from sklearn.model_selection import train_test_split
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import LabelEncoder, StandardScaler

from hybrid_features import extract_summary_features

BASE_DIR = Path(__file__).resolve().parent
DEFAULT_DATASET = BASE_DIR / "target_window_dataset"
DEFAULT_MODEL_PATH = BASE_DIR / "target_window_model.pkl"
DEFAULT_ENCODER_PATH = BASE_DIR / "target_window_label_encoder.pkl"
DEFAULT_METRICS_PATH = BASE_DIR / "target_window_model_metrics.json"
SUPPORTED_EXTENSIONS = (".wav", ".mp3", ".webm", ".m4a", ".ogg", ".oga", ".flac", ".aac", ".mp4")


def iter_audio_files(dataset_path):
    dataset_path = Path(dataset_path)

    if not dataset_path.exists():
        return

    for label_dir in sorted(dataset_path.iterdir()):
        if not label_dir.is_dir():
            continue

        for file_path in sorted(label_dir.iterdir()):
            if file_path.suffix.lower() in SUPPORTED_EXTENSIONS:
                yield label_dir.name, file_path


def can_stratify(labels, test_size):
    counts = Counter(labels)
    class_count = len(counts)
    test_count = int(np.ceil(len(labels) * test_size))
    train_count = len(labels) - test_count

    return min(counts.values()) >= 2 and test_count >= class_count and train_count >= class_count


def train(dataset_path, model_path, encoder_path, metrics_path, test_size):
    X = []
    y = []
    skipped = []

    for label, file_path in iter_audio_files(dataset_path):
        try:
            X.append(extract_summary_features(str(file_path)))
            y.append(label)
        except Exception as exc:
            skipped.append({"path": str(file_path), "error": str(exc)})
            print(f"Skipping {file_path}: {exc}")

    if not X:
        raise RuntimeError("No target-window audio files were found. Build the dataset first.")

    if len(set(y)) < 2:
        raise RuntimeError("At least two labels are required to train a classifier.")

    X = np.array(X, dtype=np.float32)
    label_encoder = LabelEncoder()
    y_encoded = label_encoder.fit_transform(y)

    stratify = y_encoded if can_stratify(y, test_size) else None

    X_train, X_test, y_train, y_test = train_test_split(
        X,
        y_encoded,
        test_size=test_size,
        stratify=stratify,
        random_state=42,
    )

    model = Pipeline(
        [
            ("scaler", StandardScaler()),
            (
                "classifier",
                RandomForestClassifier(
                    n_estimators=400,
                    class_weight="balanced",
                    random_state=42,
                    max_depth=None,
                    min_samples_leaf=1,
                ),
            ),
        ]
    )

    print("Loaded:", X.shape)
    print("Class counts:", Counter(y))
    print("Training target-window model...")
    model.fit(X_train, y_train)

    y_pred = model.predict(X_test)
    accuracy = float(model.score(X_test, y_test))
    labels = list(range(len(label_encoder.classes_)))
    matrix = confusion_matrix(y_test, y_pred, labels=labels)
    report_text = classification_report(
        y_test,
        y_pred,
        labels=labels,
        target_names=label_encoder.classes_,
        zero_division=0,
    )
    report_dict = classification_report(
        y_test,
        y_pred,
        labels=labels,
        target_names=label_encoder.classes_,
        output_dict=True,
        zero_division=0,
    )

    print("Accuracy:", accuracy)
    print("Confusion matrix:\n", matrix)
    print("Classification report:\n", report_text)

    model_path = Path(model_path)
    encoder_path = Path(encoder_path)
    metrics_path = Path(metrics_path)
    model_path.parent.mkdir(parents=True, exist_ok=True)
    encoder_path.parent.mkdir(parents=True, exist_ok=True)
    metrics_path.parent.mkdir(parents=True, exist_ok=True)

    with model_path.open("wb") as f:
        pickle.dump(
            {
                "model": model,
                "classes": [str(item) for item in label_encoder.classes_],
                "feature_source": "hybrid_features.extract_summary_features",
                "window_dataset": str(Path(dataset_path)),
            },
            f,
        )

    with encoder_path.open("wb") as f:
        pickle.dump(label_encoder, f)

    metrics = {
        "model_type": "target_window_feature",
        "accuracy": accuracy,
        "classes": [str(item) for item in label_encoder.classes_],
        "class_counts": dict(Counter(y)),
        "train_size": int(len(y_train)),
        "test_size": int(len(y_test)),
        "stratified_split": bool(stratify is not None),
        "confusion_matrix": matrix.tolist(),
        "classification_report": report_dict,
        "skipped_files": skipped,
        "model_path": str(model_path),
    }

    with metrics_path.open("w", encoding="utf-8") as f:
        json.dump(metrics, f, ensure_ascii=False, indent=2)

    print("Saved:", model_path)
    print("Metrics saved:", metrics_path)
    print("Label mapping:", dict(enumerate(label_encoder.classes_)))


def main():
    parser = argparse.ArgumentParser(description="Train a target-window Tajweed classifier.")
    parser.add_argument("--dataset", default=str(DEFAULT_DATASET))
    parser.add_argument("--model", default=str(DEFAULT_MODEL_PATH))
    parser.add_argument("--encoder", default=str(DEFAULT_ENCODER_PATH))
    parser.add_argument("--metrics", default=str(DEFAULT_METRICS_PATH))
    parser.add_argument("--test-size", type=float, default=0.2)
    args = parser.parse_args()

    try:
        train(args.dataset, args.model, args.encoder, args.metrics, args.test_size)
    except RuntimeError as exc:
        print(f"Error: {exc}")
        sys.exit(1)


if __name__ == "__main__":
    main()
