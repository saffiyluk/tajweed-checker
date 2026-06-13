import csv
import os
import shutil
from collections import Counter

import soundfile as sf

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
PROJECT_DATASET_DIR = os.path.join(BASE_DIR, "dataset")
KAGGLE_BASE_DIR = os.path.join(
    os.path.expanduser("~"),
    ".cache",
    "kagglehub",
    "datasets",
    "alawdisoft",
    "tajweed-dataset",
    "versions",
    "5",
)
KAGGLE_METADATA_PATH = os.path.join(KAGGLE_BASE_DIR, "metadataset.csv")
KAGGLE_AUDIO_DIR = os.path.join(KAGGLE_BASE_DIR, "dataset", "content", "dataset")
LABEL_MAP = {
    "Ikhfa": "ikhfa",
    "Izhar": "izhar",
    "Idgham": "other",
    "Iqlab": "other",
}
MAX_DURATION_SECONDS = 10.0


def ensure_directories():
    for project_label in LABEL_MAP.values():
        os.makedirs(os.path.join(PROJECT_DATASET_DIR, project_label), exist_ok=True)


def audio_duration_seconds(file_path):
    info = sf.info(file_path)
    return info.frames / float(info.samplerate)


def import_dataset():
    if not os.path.exists(KAGGLE_METADATA_PATH):
        raise FileNotFoundError(f"Kaggle metadata not found: {KAGGLE_METADATA_PATH}")

    if not os.path.isdir(KAGGLE_AUDIO_DIR):
        raise FileNotFoundError(f"Kaggle audio directory not found: {KAGGLE_AUDIO_DIR}")

    ensure_directories()

    copied = Counter()
    skipped_long = Counter()
    skipped_existing = Counter()

    with open(KAGGLE_METADATA_PATH, newline="", encoding="utf-8-sig") as handle:
        reader = csv.DictReader(handle)

        for row in reader:
            source_label = row["label_name"]
            target_label = LABEL_MAP.get(source_label)

            if not target_label:
                continue

            source_path = os.path.join(KAGGLE_AUDIO_DIR, row["new_file"])
            if not os.path.exists(source_path):
                continue

            duration = audio_duration_seconds(source_path)
            if duration > MAX_DURATION_SECONDS:
                skipped_long[target_label] += 1
                continue

            destination_name = f"kg_{row['new_file']}"
            destination_path = os.path.join(PROJECT_DATASET_DIR, target_label, destination_name)

            if os.path.exists(destination_path):
                skipped_existing[target_label] += 1
                continue

            shutil.copy2(source_path, destination_path)
            copied[target_label] += 1

    print("Imported files:", dict(copied))
    print("Skipped long clips:", dict(skipped_long))
    print("Skipped existing files:", dict(skipped_existing))


if __name__ == "__main__":
    import_dataset()
