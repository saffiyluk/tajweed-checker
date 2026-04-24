import os
import pickle
from collections import Counter

import numpy as np
from sklearn.preprocessing import LabelEncoder

from audio_features import extract_mfcc_features, to_model_input

DATASET_PATH = "dataset"

X = []
y = []

for label in os.listdir(DATASET_PATH):
    folder = os.path.join(DATASET_PATH, label)

    if not os.path.isdir(folder):
        continue

    for file in os.listdir(folder):
        if not file.endswith((".wav", ".mp3")):
            continue

        file_path = os.path.join(folder, file)
        features = extract_mfcc_features(file_path=file_path)
        X.append(features)
        y.append(label)

X = np.array(X)
X = to_model_input(X)

le = LabelEncoder()
y_encoded = le.fit_transform(y)

np.save("X.npy", X)
np.save("y.npy", y_encoded)

with open("label_encoder.pkl", "wb") as f:
    pickle.dump(le, f)

print("Data ready:", X.shape)
print("Classes:", le.classes_)
print(Counter(y))
