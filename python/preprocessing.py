# ========================
# 1. IMPORTS
# ========================

import os
import numpy as np
import librosa

# ========================
# 2. SETTINGS
# ========================

DATASET_PATH = "dataset"
CLASSES = ["ikhfa", "izhar"]

SR = 16000
DURATION = 2
N_MELS = 128

def extract_melspectrogram(file_path, sr=SR, duration=DURATION, n_mels=N_MELS, augment=None):
    y, _ = librosa.load(file_path, sr=sr)

    if augment == "noise":
        noise = np.random.normal(0, 0.005, y.shape)
        y = y + noise

    elif augment == "shift":
        shift = int(0.1 * sr)
        y = np.roll(y, shift)

    target_length = sr * duration

    if len(y) < target_length:
        y = np.pad(y, (0, target_length - len(y)))
    else:
        y = y[:target_length]

    mel_spec = librosa.feature.melspectrogram(y=y, sr=sr, n_mels=n_mels)
    mel_spec_db = librosa.power_to_db(mel_spec, ref=np.max)

    mel_spec_db = (mel_spec_db - mel_spec_db.min()) / (
        mel_spec_db.max() - mel_spec_db.min() + 1e-8
    )

    return mel_spec_db

# ========================
# 3. LOAD DATA
# ========================

X = []
y = []

label_map = {"ikhfa": 0, "izhar": 1}

for class_name in CLASSES:
    folder_path = os.path.join(DATASET_PATH, class_name)
    
    for file_name in os.listdir(folder_path):
        if file_name.endswith(".wav"):
            file_path = os.path.join(folder_path, file_name)
            mel = extract_melspectrogram(file_path)
            X.append(mel)
            y.append(label_map[class_name])

            # Augmentation 1: add noise
            mel_noise = extract_melspectrogram(file_path, augment="noise")
            X.append(mel_noise)
            y.append(label_map[class_name])

            # Augmentation 2: time shift
            mel_shift = extract_melspectrogram(file_path, augment="shift")
            X.append(mel_shift)
            y.append(label_map[class_name])

X = np.array(X)
y = np.array(y)

# Add channel dimension for CNN
X = X[..., np.newaxis]

print("X shape:", X.shape)
print("y shape:", y.shape)

# ========================
# 4. MODELING
# ========================

from tensorflow.keras.models import Sequential
from tensorflow.keras.layers import Input, Conv2D, MaxPooling2D, Dense, Dropout, GlobalAveragePooling2D
from tensorflow.keras.regularizers import l2
from tensorflow.keras.optimizers import Adam

input_shape = X.shape[1:]

model = Sequential([
    Input(shape=input_shape),

    Conv2D(8, (3, 3), activation='relu', padding='same',
           kernel_regularizer=l2(0.001)),
    MaxPooling2D((2, 2)),
    Dropout(0.25),

    Conv2D(16, (3, 3), activation='relu', padding='same',
           kernel_regularizer=l2(0.001)),
    MaxPooling2D((2, 2)),
    Dropout(0.30),

    Conv2D(32, (3, 3), activation='relu', padding='same',
           kernel_regularizer=l2(0.001)),
    MaxPooling2D((2, 2)),
    Dropout(0.35),

    GlobalAveragePooling2D(),

    Dense(16, activation='relu', kernel_regularizer=l2(0.001)),
    Dropout(0.5),

    Dense(2, activation='softmax')
])

model.compile(
    optimizer=Adam(learning_rate=0.0003),
    loss='sparse_categorical_crossentropy',
    metrics=['accuracy']
)

model.summary()

# ========================
# 5. SPLIT DATA
# ========================

from sklearn.model_selection import train_test_split
from sklearn.utils.class_weight import compute_class_weight
import numpy as np

X_train, X_temp, y_train, y_temp = train_test_split(
    X, y,
    test_size=0.30,
    random_state=42,
    stratify=y
)

X_val, X_test, y_val, y_test = train_test_split(
    X_temp, y_temp,
    test_size=0.50,
    random_state=42,
    stratify=y_temp
)

classes = np.unique(y_train)
weights = compute_class_weight(
    class_weight='balanced',
    classes=classes,
    y=y_train
)

class_weights = dict(zip(classes, weights))

print("Train:", X_train.shape)
print("Validation:", X_val.shape)
print("Test:", X_test.shape)
print("Class weights:", class_weights)

# ========================
# 6. TRAINING
# ========================

from tensorflow.keras.callbacks import EarlyStopping, ReduceLROnPlateau

early_stop = EarlyStopping(
    monitor='val_loss',
    patience=10,
    min_delta=0.001,
    restore_best_weights=True
)

lr_reduce = ReduceLROnPlateau(
    monitor='val_loss',
    factor=0.5,
    patience=4,
    min_lr=0.00001,
    verbose=1
)

# ========================
# 7. TESTING
# ========================

history = model.fit(
    X_train,
    y_train,
    validation_data=(X_val, y_val),
    epochs=80,
    batch_size=8,
    class_weight=class_weights,
    callbacks=[early_stop, lr_reduce]
)

test_loss, test_acc = model.evaluate(X_test, y_test)

print("Test accuracy:", test_acc)

# ========================
# 8. EVALUATION
# ========================

from sklearn.metrics import classification_report, confusion_matrix

y_pred = model.predict(X_test)
y_pred_classes = np.argmax(y_pred, axis=1)

print(confusion_matrix(y_test, y_pred_classes))
print(classification_report(
    y_test,
    y_pred_classes,
    target_names=CLASSES
))

# ========================
# 9. SAVE MODEL
# ========================

from tensorflow.keras.models import load_model

model.save("tajweed_model.keras")
print("Model saved as tajweed_model.keras")

model = load_model("tajweed_model.keras")
print(model.summary())