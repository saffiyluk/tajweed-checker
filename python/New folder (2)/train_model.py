import os
import numpy as np
import librosa
from sklearn.model_selection import train_test_split
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import classification_report
from sklearn.metrics import confusion_matrix

DATASET_PATH = "dataset"

X = []
y = []

def extract_features(file_path):
    y_audio, sr = librosa.load(file_path, sr=22050)
    mfcc = librosa.feature.mfcc(y=y_audio, sr=sr, n_mfcc=13)
    return np.mean(mfcc.T, axis=0)

# Load dataset
for label in ["izhar", "ikhfa"]:
    folder = os.path.join(DATASET_PATH, label)
    for file in os.listdir(folder):
        file_path = os.path.join(folder, file)
        
        try:
            features = extract_features(file_path)
            X.append(features)
            y.append(label)
        except:
            print(f"Error processing {file_path}")

X = np.array(X)
y = np.array(y)

# Split data
X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42, stratify=y)

# Train model
model = RandomForestClassifier(n_estimators=100)
model.fit(X_train, y_train)

# Test model
y_pred = model.predict(X_test)

print("\n=== RESULT ===")
print(classification_report(y_test, y_pred))
print(y_test)
print(y_pred)
print(confusion_matrix(y_test, y_pred))