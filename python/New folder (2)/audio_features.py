import numpy as np
import librosa

SAMPLE_RATE = 16000
N_MFCC = 13
MAX_PAD = 130


def extract_mfcc_features(file_path=None, y_audio=None, sr=SAMPLE_RATE):
    if y_audio is None:
        if file_path is None:
            raise ValueError("Either file_path or y_audio must be provided.")
        y_audio, sr = librosa.load(file_path, sr=SAMPLE_RATE)
    elif sr != SAMPLE_RATE:
        y_audio = librosa.resample(y_audio, orig_sr=sr, target_sr=SAMPLE_RATE)
        sr = SAMPLE_RATE

    y_audio = librosa.util.normalize(y_audio)
    mfcc = librosa.feature.mfcc(y=y_audio, sr=sr, n_mfcc=N_MFCC)

    if mfcc.shape[1] < MAX_PAD:
        pad_width = MAX_PAD - mfcc.shape[1]
        mfcc = np.pad(mfcc, ((0, 0), (0, pad_width)), mode="constant")
    else:
        mfcc = mfcc[:, :MAX_PAD]

    return mfcc.astype(np.float32)


def to_model_input(mfcc):
    return mfcc[..., np.newaxis]
