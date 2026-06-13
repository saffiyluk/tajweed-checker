import os
import pickle
from collections import Counter

import numpy as np
from sklearn.preprocessing import LabelEncoder

from predict import preprocess

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DATASET_PATH = os.path.join(BASE_DIR, "dataset")
SUPPORTED_EXTENSIONS = (".wav", ".mp3", ".webm", ".m4a")

X = []
y = []

for label in os.listdir(DATASET_PATH):
    folder = os.path.join(DATASET_PATH, label)

    if not os.path.isdir(folder):
        continue

    for file in os.listdir(folder):
        if not file.lower().endswith(SUPPORTED_EXTENSIONS):
            continue

        file_path = os.path.join(folder, file)
        features = preprocess(file_path)[0]
        X.append(features)
        y.append(label)

if not X:
    raise RuntimeError("No supported audio files were found in python/dataset.")

X = np.array(X)

le = LabelEncoder()
y_encoded = le.fit_transform(y)

np.save(os.path.join(BASE_DIR, "X.npy"), X)
np.save(os.path.join(BASE_DIR, "y.npy"), y_encoded)

with open(os.path.join(BASE_DIR, "label_encoder.pkl"), "wb") as f:
    pickle.dump(le, f)

print("Data ready:", X.shape)
print("Classes:", le.classes_)
print(Counter(y))
