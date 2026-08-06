import argparse
import json
import os
import re
import shutil
import subprocess
import tempfile
from pathlib import Path

import numpy as np
import soundfile as sf

from audio_cleaning import SAMPLE_RATE, clean_recitation_audio

BASE_DIR = Path(__file__).resolve().parent
PROJECT_DIR = BASE_DIR.parent
DEFAULT_MANIFEST = PROJECT_DIR / "storage" / "app" / "target-window" / "manifest.json"
DEFAULT_OUTPUT = BASE_DIR / "target_window_dataset"
SUPPORTED_LABELS = {
    "ikhfa_correct",
    "ikhfa_weak_ghunnah",
    "izhar_correct",
    "izhar_with_ghunnah",
    "other",
}


def load_audio(path, sample_rate=SAMPLE_RATE):
    temp_wav = None

    try:
        y, sr = sf.read(path, always_2d=False)
    except Exception:
        temp = tempfile.NamedTemporaryFile(suffix=".wav", delete=False)
        temp.close()
        temp_wav = temp.name
        subprocess.run(
            [
                "ffmpeg",
                "-y",
                "-loglevel",
                "error",
                "-i",
                str(path),
                "-ac",
                "1",
                "-ar",
                str(sample_rate),
                temp_wav,
            ],
            check=True,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.PIPE,
        )
        y, sr = sf.read(temp_wav, always_2d=False)
    finally:
        if temp_wav and os.path.exists(temp_wav):
            try:
                os.unlink(temp_wav)
            except OSError:
                pass

    if getattr(y, "ndim", 1) > 1:
        y = np.mean(y, axis=1)

    y = y.astype(np.float32)

    if sr != sample_rate:
        duration = y.size / float(sr)
        target_length = max(1, int(round(duration * sample_rate)))
        y = np.interp(
            np.linspace(0, y.size - 1, target_length, dtype=np.float32),
            np.linspace(0, y.size - 1, y.size, dtype=np.float32),
            y,
        ).astype(np.float32)

    return clean_recitation_audio(y, sample_rate=sample_rate)


def normalize_letter(letter):
    return {
        "\u0623": "\u0621",
        "\u0625": "\u0621",
        "\u0622": "\u0627",
        "\u0671": "\u0627",
        "\u0624": "\u0621",
        "\u0626": "\u0621",
    }.get(letter, letter)


def is_arabic_letter(char):
    return bool(re.match(r"[\u0621-\u064A\u0671]", char))


def is_arabic_mark(char):
    return bool(re.match(r"[\u064B-\u065F\u0670\u0640\u06D6-\u06ED]", char))


def next_arabic_letter(chars, start):
    for index in range(start, len(chars)):
        if is_arabic_letter(chars[index]):
            return index
    return None


def count_letters_before(chars, position):
    return sum(1 for char in chars[:position] if is_arabic_letter(char))


def detect_targets(text):
    chars = list(text or "")
    total_letters = sum(1 for char in chars if is_arabic_letter(char))
    ikhfa_letters = {
        "\u062a",
        "\u062b",
        "\u062c",
        "\u062f",
        "\u0630",
        "\u0632",
        "\u0633",
        "\u0634",
        "\u0635",
        "\u0636",
        "\u0637",
        "\u0638",
        "\u0641",
        "\u0642",
        "\u0643",
    }
    izhar_letters = {
        "\u0621",
        "\u0627",
        "\u0647",
        "\u0639",
        "\u062d",
        "\u063a",
        "\u062e",
    }
    targets = []

    for index, char in enumerate(chars):
        is_tanween = char in {"\u064B", "\u064C", "\u064D"}
        is_noon = normalize_letter(char) == "\u0646"
        mark_end = index
        has_sukun = False

        if is_noon:
            scan = index + 1
            while scan < len(chars) and is_arabic_mark(chars[scan]):
                mark_end = scan
                has_sukun = has_sukun or chars[scan] in {"\u0652", "\u06E1"}
                scan += 1

        if not is_tanween and not (is_noon and has_sukun):
            continue

        next_index = next_arabic_letter(chars, mark_end + 1)

        if next_index is None:
            continue

        next_letter = normalize_letter(chars[next_index])
        rule = None

        if next_letter in ikhfa_letters:
            rule = "ikhfa"
        elif next_letter in izhar_letters:
            rule = "izhar"

        if rule:
            targets.append(
                {
                    "rule": rule,
                    "position": index,
                    "letter_position": count_letters_before(chars, index),
                    "total_letters": max(1, total_letters),
                    "next_letter": next_letter,
                }
            )

    return targets


def crop_window(y, center_ratio, seconds):
    window_length = int(round(seconds * SAMPLE_RATE))
    center = int(round(center_ratio * y.size))
    start = center - (window_length // 2)
    end = start + window_length
    output = np.zeros(window_length, dtype=np.float32)
    source_start = max(0, start)
    source_end = min(y.size, end)
    output_start = source_start - start

    if source_end > source_start:
        output[output_start : output_start + (source_end - source_start)] = y[source_start:source_end]

    return output


def target_ratio(target):
    return min(1.0, max(0.0, (target["letter_position"] + 0.5) / max(1, target["total_letters"])))


def build_dataset(manifest_path, output_dir, window_seconds):
    manifest = json.loads(Path(manifest_path).read_text(encoding="utf-8"))
    output_dir = Path(output_dir)

    if output_dir.exists():
        shutil.rmtree(output_dir)

    output_dir.mkdir(parents=True, exist_ok=True)
    exported = []

    for entry in manifest:
        label = entry.get("target_label")

        if label not in SUPPORTED_LABELS:
            continue

        audio_path = PROJECT_DIR / entry["audio_path"]

        if not audio_path.exists():
            print(f"Skipping missing audio: {audio_path}")
            continue

        y = load_audio(audio_path)
        transcript = entry.get("transcript") or ""
        targets = detect_targets(transcript)

        if label == "other" or not targets:
            targets = [{"rule": "other", "letter_position": 0, "total_letters": 1}]

        for target_index, target in enumerate(targets):
            if label.startswith("ikhfa") and target["rule"] != "ikhfa":
                continue

            if label.startswith("izhar") and target["rule"] != "izhar":
                continue

            ratio = 0.5 if target["rule"] == "other" else target_ratio(target)
            window = crop_window(y, ratio, window_seconds)
            label_dir = output_dir / label
            label_dir.mkdir(parents=True, exist_ok=True)
            file_name = f"audio_{entry['audio_id']}_target_{target_index}.wav"
            file_path = label_dir / file_name
            sf.write(file_path, window, SAMPLE_RATE, subtype="PCM_16")
            exported.append(
                {
                    **entry,
                    "target_index": target_index,
                    "target_rule": target["rule"],
                    "target_ratio": ratio,
                    "window_path": str(file_path.relative_to(PROJECT_DIR)).replace("\\", "/"),
                }
            )

    (output_dir / "manifest.json").write_text(
        json.dumps(exported, ensure_ascii=False, indent=2),
        encoding="utf-8",
    )

    print(f"Exported {len(exported)} target windows to {output_dir}")


def main():
    parser = argparse.ArgumentParser(description="Build target-window Tajweed training dataset.")
    parser.add_argument("--manifest", default=str(DEFAULT_MANIFEST))
    parser.add_argument("--output", default=str(DEFAULT_OUTPUT))
    parser.add_argument("--window-seconds", type=float, default=2.4)
    args = parser.parse_args()
    build_dataset(args.manifest, args.output, args.window_seconds)


if __name__ == "__main__":
    main()
