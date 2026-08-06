import json
import os
import re
import sys
import traceback
import tempfile
import subprocess
import warnings
from contextlib import redirect_stderr
from io import StringIO

os.environ.setdefault("PYTHONIOENCODING", "utf-8")

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")

if hasattr(sys.stderr, "reconfigure"):
    sys.stderr.reconfigure(encoding="utf-8")

DEFAULT_OPENAI_WHISPER_MODEL = "small"
TARGET_SAMPLE_RATE = 16000
ARABIC_TEXT_RE = re.compile(r"[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\s]+")
ARABIC_LETTER_RE = re.compile(r"[\u0621-\u064A]")


def fail_missing_dependency(package_name):
    print(
        json.dumps(
            {
                "status": "failed",
                "error": f"Missing Python dependency: {package_name}. Install dependencies with: python -m pip install -r python/requirements.txt",
            },
            ensure_ascii=False,
        )
    )
    sys.exit(1)


def resample_audio(y, original_rate, target_rate):
    if y.size == 0 or original_rate == target_rate:
        return y.astype("float32")

    duration = y.size / float(original_rate)
    target_length = max(1, int(round(duration * target_rate)))
    source_positions = np.linspace(0, y.size - 1, num=y.size, dtype="float32")
    target_positions = np.linspace(0, y.size - 1, num=target_length, dtype="float32")

    return np.interp(target_positions, source_positions, y).astype("float32")


def trim_silence(y, threshold=0.02, padding_samples=1600):
    if y.size == 0:
        return y

    mask = np.abs(y) > threshold

    if not np.any(mask):
        return y

    indices = np.where(mask)[0]
    start = max(0, int(indices[0]) - padding_samples)
    end = min(y.size, int(indices[-1]) + padding_samples + 1)
    return y[start:end]


def normalize_audio(y):
    if y.size == 0:
        return y.astype("float32")

    y = y.astype("float32")
    peak = float(np.max(np.abs(y)))

    if peak > 0:
        y = y / peak

    rms = float(np.sqrt(np.mean(np.square(y)))) if y.size else 0.0
    target_rms = 0.12

    if rms > 0:
        gain = min(3.0, target_rms / rms)
        y = np.clip(y * gain, -1.0, 1.0)

    return y.astype("float32")


def load_audio_with_fallback(audio_path, sample_rate=TARGET_SAMPLE_RATE):
    temp_wav = None

    try:
        y, sr = sf.read(audio_path, always_2d=False)
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
                audio_path,
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

    y = y.astype("float32")

    if sr != sample_rate:
        y = resample_audio(y, sr, sample_rate)
        sr = sample_rate

    return y, sr


# ===== REPORT SCREENSHOT START: Section 4.3.9A - Transcription Audio Preparation =====
def prepare_audio_for_transcription(audio_path):
    y, sr = load_audio_with_fallback(audio_path, sample_rate=TARGET_SAMPLE_RATE)

    try:
        from audio_cleaning import clean_recitation_audio

        y = clean_recitation_audio(y, sample_rate=sr)
    except Exception:
        y = trim_silence(y)
        y = normalize_audio(y)

    temp = tempfile.NamedTemporaryFile(suffix=".wav", delete=False)
    temp.close()
    sf.write(temp.name, y, sr, subtype="PCM_16")
    return temp.name
# ===== REPORT SCREENSHOT END: Section 4.3.9A - Transcription Audio Preparation =====


# ===== REPORT SCREENSHOT START: Section 4.3.9B - Whisper Speech-to-Text =====
def transcribe_with_openai_whisper(audio_path, model_name, arabic_prompt):
    try:
        import whisper
    except ModuleNotFoundError as exc:
        fail_missing_dependency(exc.name)

    stderr_buffer = StringIO()
    with warnings.catch_warnings():
        warnings.simplefilter("ignore")
        with redirect_stderr(stderr_buffer):
            model = whisper.load_model(model_name)
            result = model.transcribe(
                audio_path,
                language="ar",
                task="transcribe",
                verbose=False,
                fp16=False,
                temperature=0,
                initial_prompt=arabic_prompt,
                condition_on_previous_text=False,
                beam_size=5,
                best_of=5,
                no_speech_threshold=0.9,
            )

    return result.get("text", "").strip()
# ===== REPORT SCREENSHOT END: Section 4.3.9B - Whisper Speech-to-Text =====


def transcribe_with_transformers(audio_path, model_name):
    try:
        import torch
        from transformers import WhisperForConditionalGeneration, WhisperProcessor, logging as transformers_logging
    except ModuleNotFoundError as exc:
        missing_name = exc.name or "transformers runtime dependency"
        raise RuntimeError(
            f"Missing Python dependency: {missing_name}. Install dependencies with: python -m pip install -r python/requirements.txt"
        ) from exc

    transformers_logging.set_verbosity_error()
    speech, rate = load_audio_with_fallback(audio_path, sample_rate=TARGET_SAMPLE_RATE)
    processor = WhisperProcessor.from_pretrained(model_name)
    model = WhisperForConditionalGeneration.from_pretrained(
        model_name,
        torch_dtype=torch.float32,
        low_cpu_mem_usage=True,
    )

    if torch.cuda.is_available():
        model = model.to("cuda")

    model.generation_config.forced_decoder_ids = processor.get_decoder_prompt_ids(
        language="ar",
        task="transcribe",
    )

    inputs = processor(
        speech,
        sampling_rate=rate,
        return_tensors="pt",
    )

    generate_kwargs = {
        "max_length": 448,
        "num_beams": 5,
        "repetition_penalty": 1.15,
        "no_repeat_ngram_size": 4,
    }

    if hasattr(inputs, "attention_mask"):
        generate_kwargs["attention_mask"] = inputs.attention_mask.to(model.device)

    with torch.no_grad():
        generated_ids = model.generate(
            inputs.input_features.to(model.device),
            **generate_kwargs,
        )

    return processor.batch_decode(generated_ids, skip_special_tokens=True)[0].strip()


def keep_arabic_text(text):
    text = str(text or "")
    Arabic_chunks = ARABIC_TEXT_RE.findall(text)
    text = " ".join(chunk.strip() for chunk in Arabic_chunks if chunk.strip())
    text = re.sub(r"\s+", " ", text).strip()

    if not ARABIC_LETTER_RE.search(text):
        return ""

    return text


def is_repetitive_hallucination(text):
    normalized = re.sub(r"\s+", " ", str(text or "")).strip()

    if not normalized:
        return False

    prompt_leaks = [
        "\u0644\u0627 \u062a\u062a\u0631\u062c\u0645",
        "\u0644\u0627 \u062a\u0643\u062a\u0628",
        "\u0627\u0643\u062a\u0628 \u0627\u0644\u0643\u0644\u0645\u0627\u062a",
    ]

    if any(leak in normalized for leak in prompt_leaks):
        return True

    words = normalized.split()

    if len(words) < 8:
        return False

    for ngram_size in (2, 3, 4):
        ngrams = [
            " ".join(words[index:index + ngram_size])
            for index in range(0, len(words) - ngram_size + 1)
        ]

        if not ngrams:
            continue

        most_common = max(ngrams.count(ngram) for ngram in set(ngrams))

        if most_common >= 3 and most_common / len(ngrams) >= 0.35:
            return True

    unique_word_ratio = len(set(words)) / len(words)
    return len(words) >= 12 and unique_word_ratio < 0.35


def is_huggingface_model(model_name):
    return "/" in model_name or "\\" in model_name or os.path.isdir(model_name) or model_name.startswith("hf:")


def main():
    global np, sf

    if len(sys.argv) < 2:
        raise ValueError("Audio file path is required.")

    try:
        import numpy as np
        import soundfile as sf
    except ModuleNotFoundError as exc:
        fail_missing_dependency(exc.name)

    audio_path = sys.argv[1]
    model_name = os.environ.get("WHISPER_MODEL", DEFAULT_OPENAI_WHISPER_MODEL).strip()
    arabic_prompt = ""

    if model_name.startswith("hf:"):
        model_name = model_name[3:]

    fallback_model = os.environ.get("WHISPER_FALLBACK_MODEL", DEFAULT_OPENAI_WHISPER_MODEL).strip() or DEFAULT_OPENAI_WHISPER_MODEL
    prepared_audio_path = prepare_audio_for_transcription(audio_path)

    try:
        if is_huggingface_model(model_name):
            try:
                text = transcribe_with_transformers(prepared_audio_path, model_name)
                backend = "transformers"
                actual_model = model_name
                warning_message = None
            except Exception as primary_error:
                text = transcribe_with_openai_whisper(prepared_audio_path, fallback_model, arabic_prompt)
                backend = "openai-whisper-fallback"
                actual_model = fallback_model
                warning_message = f"Transformers transcription failed for {model_name}: {primary_error}"
        else:
            text = transcribe_with_openai_whisper(prepared_audio_path, model_name, arabic_prompt)
            backend = "openai-whisper"
            actual_model = model_name
            warning_message = None
    finally:
        if prepared_audio_path and os.path.exists(prepared_audio_path):
            try:
                os.unlink(prepared_audio_path)
            except OSError:
                pass

    raw_text = text
    text = keep_arabic_text(text)

    if is_repetitive_hallucination(text):
        text = ""

    print(
        json.dumps(
            {
                "status": "success" if text else "empty",
                "text": text,
                "raw_text": raw_text,
                "backend": backend,
                "model": actual_model,
                "requested_model": model_name,
                "warning": warning_message,
            },
            ensure_ascii=False,
        )
    )


if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        print(
            json.dumps(
                {
                    "status": "failed",
                    "error": str(e),
                    "traceback": traceback.format_exc(),
                },
                ensure_ascii=False,
            )
        )
