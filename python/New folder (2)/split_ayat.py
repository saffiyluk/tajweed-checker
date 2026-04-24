import librosa
import json
import sys
import os
import soundfile as sf

audio_path = sys.argv[1]

# load audio
y, sr = librosa.load(audio_path, sr=16000)

# split based on silence
intervals = librosa.effects.split(y, top_db=25)

os.makedirs("segments", exist_ok=True)

segments_info = []

for idx, (start, end) in enumerate(intervals):
    segment = y[start:end]

    out_path = f"segments/seg_{idx}.wav"
    sf.write(out_path, segment, sr)

    segments_info.append({
        "file": out_path,
        "start_sample": int(start),
        "end_sample": int(end),
        "start_time": float(start / sr),
        "end_time": float(end / sr)
    })

    print(f"Saved {out_path}")

print(json.dumps(segments_info, indent=2))