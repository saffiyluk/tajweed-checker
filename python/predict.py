import os
import sys
import json

os.environ.setdefault("TF_CPP_MIN_LOG_LEVEL", "3")

import librosa
import numpy as np
from tensorflow.keras.models import load_model

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
MODEL_PATH = os.path.join(BASE_DIR, "tajweed_model.keras")
CLASSES = ["ikhfa", "izhar"]


def preprocess(file_path):
    y, sr = librosa.load(file_path, sr=16000)
    y, _ = librosa.effects.trim(y, top_db=25)

    target_length = 16000 * 2
    y = np.pad(y, (0, max(0, target_length - len(y))))[:target_length]

    mel = librosa.feature.melspectrogram(y=y, sr=16000, n_mels=128)
    mel = librosa.power_to_db(mel, ref=np.max)
    mel = (mel - mel.min()) / (mel.max() - mel.min() + 1e-8)

    mel = mel[..., np.newaxis]
    mel = np.expand_dims(mel, axis=0)
    return mel


def main():
    if len(sys.argv) < 2:
        raise ValueError("Audio file path is required.")

    file_path = sys.argv[1]
    model = load_model(MODEL_PATH)
    sample = preprocess(file_path)
    pred = model.predict(sample, verbose=0)[0]

    index = int(np.argmax(pred))
    confidence = float(pred[index])

    result = {
        "prediction": CLASSES[index],
        "confidence": confidence,
        "ikhfa_confidence": float(pred[0]),
        "izhar_confidence": float(pred[1]),
        "status": "confident" if confidence >= 0.70 else "uncertain",
        "model_path": MODEL_PATH,
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
