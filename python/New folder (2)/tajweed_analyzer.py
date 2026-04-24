import sys
import json
import librosa
import numpy as np
from fastdtw import fastdtw
from scipy.spatial.distance import euclidean

# ===== INPUT =====
audio_path = sys.argv[1]
reference_path = "fixed.wav"  # change if needed

# ===== LOAD AUDIO =====
y_user, sr_user = librosa.load(audio_path, sr=22050)
y_ref, sr_ref = librosa.load(reference_path, sr=22050)

# ===== FEATURE EXTRACTION =====
mfcc_user = librosa.feature.mfcc(y=y_user, sr=sr_user, n_mfcc=13)
mfcc_ref = librosa.feature.mfcc(y=y_ref, sr=sr_ref, n_mfcc=13)

# IMPORTANT: transpose for DTW (frames)
features_user = mfcc_user.T
features_ref = mfcc_ref.T

# ===== DTW =====
distance, path = fastdtw(features_user, features_ref, dist=euclidean)

confidence = max(0, 100 - (distance / 1000))  # normalize

# ===== SIMPLE RULE LOGIC (placeholder) =====
rule_detected = "Ikhfa"

mistakes = []

if confidence < 70:
    mistakes.append({
        "type": "Weak pronunciation match",
        "confidence": float(confidence)
    })

# ===== RESULT =====
result = {
    "status": "completed",
    "confidence": float(confidence),
    "feedback": f"Recitation analyzed. Detected rule: {rule_detected}",
    "mistakes": mistakes
}

print(json.dumps(result))