import whisper
import json

result = {
    "text": "من شر ما خلق",
    "ikhfa": 1,
    "izhar": 0
}

model = whisper.load_model("base")

result = model.transcribe("fixed.wav", language = "ar")  # use short audio here

text = result["text"]

izhar_letters = ["ء","ه","ع","ح","غ","خ"]
ikhfa_letters = ["ت","ث","ج","د","ذ","ز","س","ش","ص","ض","ط","ظ","ف","ق","ك"]

for i in range(len(text)-1):
    if text[i] == "ن":
        next_letter = text[i+1]

        # Skip space
        if next_letter == " " and i+2 < len(text):
            next_letter = text[i+2]

        if next_letter in izhar_letters:
            print(f"Izhar detected: ن + {next_letter}")

        elif next_letter in ikhfa_letters:
            print(f"Ikhfa detected: ن + {next_letter}")

print(result["text"])
