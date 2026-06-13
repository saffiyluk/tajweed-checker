import pickle
import os
import json

import numpy as np
import tensorflow as tf
from sklearn.metrics import classification_report, confusion_matrix
from sklearn.model_selection import train_test_split
from sklearn.utils.class_weight import compute_class_weight
from tensorflow.keras.callbacks import EarlyStopping, ReduceLROnPlateau
from tensorflow.keras.layers import BatchNormalization, Conv2D, Dense, Dropout, Flatten, MaxPooling2D
from tensorflow.keras.models import Sequential

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
METRICS_PATH = os.path.join(BASE_DIR, "cnn_model_metrics.json")

X = np.load(os.path.join(BASE_DIR, "X.npy"))
y = np.load(os.path.join(BASE_DIR, "y.npy"))

print("Loaded:", X.shape)
print("Class counts:", np.unique(y, return_counts=True))

tf.keras.utils.set_random_seed(42)

X_train, X_test, y_train, y_test = train_test_split(
    X,
    y,
    test_size=0.2,
    stratify=y,
    random_state=42,
)

num_classes = len(np.unique(y))
class_weights = compute_class_weight(
    class_weight="balanced",
    classes=np.unique(y_train),
    y=y_train,
)
class_weight_map = dict(zip(np.unique(y_train), class_weights))

model = Sequential(
    [
        tf.keras.Input(shape=X.shape[1:]),
        Conv2D(32, (3, 3), activation="relu"),
        MaxPooling2D(2, 2),
        BatchNormalization(),
        Conv2D(64, (3, 3), activation="relu"),
        MaxPooling2D(2, 2),
        BatchNormalization(),
        Flatten(),
        Dense(128, activation="relu"),
        Dropout(0.4),
        Dense(num_classes, activation="softmax"),
    ]
)

model.compile(
    optimizer="adam",
    loss="sparse_categorical_crossentropy",
    metrics=["accuracy"],
)

callbacks = [
    EarlyStopping(monitor="val_loss", patience=6, restore_best_weights=True),
    ReduceLROnPlateau(monitor="val_loss", factor=0.5, patience=3, min_lr=1e-5),
]

print("Training...")
history = model.fit(
    X_train,
    y_train,
    epochs=40,
    batch_size=16,
    validation_data=(X_test, y_test),
    class_weight=class_weight_map,
    callbacks=callbacks,
    verbose=1,
)

loss, acc = model.evaluate(X_test, y_test, verbose=0)
print("Accuracy:", acc)

y_pred = np.argmax(model.predict(X_test, verbose=0), axis=1)
matrix = confusion_matrix(y_test, y_pred)
with open(os.path.join(BASE_DIR, "label_encoder.pkl"), "rb") as f:
    le = pickle.load(f)

class_names = [str(item) for item in le.classes_]
report_text = classification_report(y_test, y_pred, target_names=class_names)
report_dict = classification_report(y_test, y_pred, target_names=class_names, output_dict=True)
best_val_accuracy = float(max(history.history["val_accuracy"]))

print("Confusion matrix:\n", matrix)
print("Classification report:\n", report_text)
print("Best val_accuracy:", best_val_accuracy)

model.save(os.path.join(BASE_DIR, "tajweed_model.keras"))
model.save(os.path.join(BASE_DIR, "tajweed_model.h5"))

metrics = {
    "model_type": "cnn",
    "accuracy": float(acc),
    "best_val_accuracy": best_val_accuracy,
    "classes": class_names,
    "class_counts": {class_name: int(count) for class_name, count in zip(class_names, np.unique(y, return_counts=True)[1])},
    "train_size": int(len(y_train)),
    "test_size": int(len(y_test)),
    "confusion_matrix": matrix.tolist(),
    "classification_report": report_dict,
    "model_path": os.path.join(BASE_DIR, "tajweed_model.keras"),
}

with open(METRICS_PATH, "w", encoding="utf-8") as f:
    json.dump(metrics, f, ensure_ascii=False, indent=2)

print("Metrics saved:", METRICS_PATH)
print("Label mapping:", dict(enumerate(le.classes_)))
