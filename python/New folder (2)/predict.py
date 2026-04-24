import argparse
import pickle

import numpy as np
from tensorflow.keras.models import load_model

from audio_features import extract_mfcc_features, to_model_input
from predict_full import predict_full, summarize_predictions

model = load_model("tajweed_model.keras")

with open("label_encoder.pkl", "rb") as f:
    le = pickle.load(f)


def predict_clip(file_path):
    mfcc = extract_mfcc_features(file_path=file_path)
    features = np.expand_dims(to_model_input(mfcc), axis=0)

    pred = model.predict(features, verbose=0)[0]
    label_index = int(np.argmax(pred))

    return {
        "label": le.inverse_transform([label_index])[0],
        "confidence": float(np.max(pred)),
        "probabilities": {
            class_name: float(prob)
            for class_name, prob in zip(le.classes_, pred)
        },
    }


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("audio_path", help="Path to the audio file to classify.")
    parser.add_argument(
        "--mode",
        choices=["clip", "full"],
        default="full",
        help="Use clip for one-shot classification or full for sliding-window analysis.",
    )
    args = parser.parse_args()

    if args.mode == "clip":
        result = predict_clip(args.audio_path)
        print("Label:", result["label"])
        print("Confidence:", round(result["confidence"], 4))
        print("Probabilities:", result["probabilities"])
        return

    results = predict_full(args.audio_path)
    summary = summarize_predictions(results)

    print("Summary label:", summary["summary_label"])
    print("Average confidence:", round(summary["avg_confidence"], 4))
    print("Window counts:", summary["counts"])
    print()

    for item in results:
        print(
            f"{item['time']:.2f}s -> {item['label']} "
            f"({item['confidence']:.2f}) {item['probabilities']}"
        )


if __name__ == "__main__":
    main()
