# TajweedCheck Dependency and Tech Stack Inventory

This README lists the main technologies, libraries, tools, and external services used by this Laravel application. It was compiled from `composer.json`, `package.json`, `python/requirements.txt`, Laravel config files, routes, views, and service/controller usage in the codebase.

## Application Overview

TajweedCheck is a Laravel web app for Quran recitation, memorization practice, Tajweed audio upload, AI-assisted transcription, rule analysis, feedback, PDF reporting, and admin monitoring.

Core application areas:

- User authentication and profile management
- Quran reading and memorization pages
- Tajweed checking for Ikhfa and Izhar recitations
- Audio upload, playback, download, re-analysis, and correction review
- Python-based audio preprocessing, ML prediction, transcription, and model training
- Optional Firebase Storage and Firestore integration
- Optional Gemini feedback and Arabic diacritization
- Admin dashboard, analytics, logs, user management, and recitation management

## Backend Tech Stack

- PHP `^8.1`
- Laravel Framework `^10.10`
- Laravel Blade templates
- Laravel Eloquent ORM
- Laravel routing, middleware, validation, storage, logging, queues, sessions, cache, and mail configuration
- MySQL by default, from `.env.example`
- Laravel public/local storage with optional Firebase Storage
- Firestore synchronization where enabled
- Google Gemini API integration for AI feedback and diacritization
- External Quran APIs for Quran page data and Quran ayah matching/cache building

## PHP Runtime Dependencies

Defined in `composer.json` under `require`:

| Package | Version | Purpose |
| --- | --- | --- |
| `php` | `^8.1` | PHP runtime |
| `laravel/framework` | `^10.10` | Main Laravel application framework |
| `laravel/sanctum` | `^3.3` | API token and first-party SPA authentication support |
| `laravel/tinker` | `^2.8` | Interactive Laravel shell |
| `laravel/ui` | `^4.6` | Laravel auth/UI scaffolding |
| `guzzlehttp/guzzle` | `^7.2` | HTTP client dependency used by Laravel HTTP integrations |
| `google/cloud-firestore` | `^1.55` | Google Cloud Firestore client |
| `kreait/firebase-php` | `7.24` | Firebase Admin SDK for PHP, used for Firestore and Storage |
| `tecnickcom/tcpdf` | `^6.10` | PDF report generation |

## PHP Development Dependencies

Defined in `composer.json` under `require-dev`:

| Package | Version | Purpose |
| --- | --- | --- |
| `fakerphp/faker` | `^1.9.1` | Test and seeding fake data |
| `laravel/pint` | `^1.0` | PHP code style formatter |
| `laravel/sail` | `^1.18` | Laravel Docker development environment tooling |
| `mockery/mockery` | `^1.4.4` | Mocking framework for tests |
| `nunomaduro/collision` | `^7.0` | Better CLI exception output |
| `phpunit/phpunit` | `^10.1` | PHP test runner |
| `spatie/laravel-ignition` | `^2.0` | Laravel error page/debug tooling |

## Frontend Tech Stack

- Vite `^5.0.0`
- Laravel Vite Plugin `^1.0.0`
- Vue 3 `^3.2.37`
- Bootstrap 5
- Sass/SCSS
- Axios
- Popper.js
- Blade templates with page-level JavaScript
- Font Awesome 6.4.0 from CDN
- Chart.js from CDN in the admin layout
- Google Fonts: Amiri and Scheherazade New

## Node Dependencies

Defined in `package.json`.

### Runtime Dependencies

| Package | Version | Purpose |
| --- | --- | --- |
| `firebase` | `^12.7.0` | Firebase JavaScript SDK |

### Development Dependencies

| Package | Version | Purpose |
| --- | --- | --- |
| `@vitejs/plugin-vue` | `^4.5.0` | Vue support in Vite |
| `laravel-vite-plugin` | `^1.0.0` | Laravel and Vite asset integration |
| `vite` | `^5.0.0` | Frontend build tool and dev server |
| `vue` | `^3.2.37` | Frontend component framework |
| `bootstrap` | `^5.2.3` | UI framework imported through Sass/JS |
| `@popperjs/core` | `^2.11.6` | Bootstrap dropdowns, popovers, and tooltips |
| `axios` | `^1.6.4` | Browser HTTP client |
| `sass` | `^1.56.1` | SCSS compiler |

## Frontend Build Scripts

Defined in `package.json`:

```bash
npm run dev
npm run build
```

The Vite entry points are defined in `vite.config.js`:

- `resources/sass/app.scss`
- `resources/js/app.js`

## Python and Machine Learning Stack

Python is used for audio preprocessing, model inference, model training, feature extraction, transcription, and target-window analysis.

Configured through `.env.example` and `config/tajweed.php`:

- `PYTHON_BINARY`
- `WHISPER_MODEL` (blank uses bundled `python/models/tarteel-ai-whisper-base-ar-quran` when available; this Quran-trained model is preferred over generic Whisper)
- `HAFALAN_WHISPER_MODEL`
- `WHISPER_TRANSCRIPTION_TIMEOUT`
- `TAJWEED_PREDICTION_TIMEOUT`
- `TAJWEED_ENABLE_QURAN_PRONUNCIATION_MODEL`
- `QURAN_MUAALEM_MODEL` (optional absolute model directory; blank uses `python/models/muaalem-model-v3_2`)
- `QURAN_MUAALEM_TIMEOUT`
- `QURAN_MUAALEM_ENABLE_AUDIO_CLEANING`, `QURAN_MUAALEM_NOISE_REDUCTION_AMOUNT`, `QURAN_MUAALEM_TARGET_RMS` (cleaned audio candidates for reference alignment)
- `TAJWEED_ENABLE_HYBRID_RULE_AUDIO_FALLBACK`, `TAJWEED_HYBRID_RULE_AUDIO_MIN_CONFIDENCE` (hybrid Quran-text rule detection plus audio-rule fallback when direct reference alignment is unavailable)
- `TAJWEED_ELONGATION_THRESHOLD_MS`, `TAJWEED_ELONGATION_LOCAL_WINDOW_SECONDS` (target-window acoustic diagnostics and the boundary reserved for a timing-aligned fallback)
- `TAJWEED_USE_NOISEREDUCE_LIBRARY`, `TAJWEED_LIBROSA_TRIM_TOP_DB` (optional `noisereduce` backend switch and librosa silence trimming sensitivity)
- Tajweed analysis feature flags and thresholds

## Python Dependencies

Defined in `python/requirements.txt`:

| Package | Version | Purpose |
| --- | --- | --- |
| `librosa` | `0.11.0` | Audio analysis and feature extraction |
| `tensorflow` | `2.21.0` | Neural network model training/inference |
| `numpy` | Unpinned | Numerical processing |
| `noisereduce` | `2.0.1` | Optional audio noise reduction backend |
| `scikit-learn` | Unpinned | Random forest models, petripelines, mics, preprocessing |
| `soundfile` | Unpinned | Audio file reading/writing |
| `pydub` | `0.25.1` | Browser/container audio conversion such as WebM to WAV |
| `openai-whisper` | Unpinned | Whisper transcription |
| `ffmpeg-python` | Unpinned | Python bindings for FFmpeg workflows |
| `transformers` | Unpinned | Hugging Face transformer model support, including the local Tarteel Quran Whisper transcription model |
| `quran-muaalem` | `0.1.0` | Reference-aware Quran phoneme and pronunciation inference |
| `quran-transcript` | `0.5.2` | Uthmani-to-phonetic reference generation and localized Tajweed error explanation |

Additional Python imports found in scripts but not currently listed in `python/requirements.txt`:

| Package | Used In | Note |
| --- | --- | --- |
| `matplotlib` | `python/generate_spectrogram_examples.py` | Needed for spectrogram example generation |
| `scipy` | `python/generate_spectrogram_examples.py` | Needed for signal/spectrogram processing |

## Python Scripts and Model Artifacts

Main Python scripts in `python/`:

- `audio_cleaning.py`
- `build_target_window_dataset.py`
- `generate_spectrogram_examples.py`
- `hybrid_features.py`
- `predict.py`
- `predict_quran_pronunciation.py`
- `predict_target_windows.py`
- `prepare_data.py`
- `train_cnn.py`
- `train_feature_model.py`
- `train_target_window_model.py`
- `transcribe.py`

### Reference-aware pronunciation model

The broad CNN/random-forest pipeline recognizes `Ikhfa`, `Izhar`, or `Other`; it is not used as proof that pronunciation is correct. `predict_quran_pronunciation.py` compares the recorded phonemes with the selected Uthmani ayah. After the content and model-confidence gates pass, an explicit target phoneme contrast is decisive even when the missing or extra nasal frames lower target coverage—the error itself must not disqualify its evidence.

The combined checker posts the selected surah/ayah coordinates when available, allowing the model to use `quran-transcript`'s bundled canonical Uthmani reference after verifying that it matches the displayed ayah. For older/direct submissions without coordinates, the wrapper removes non-spoken Quran pause ornaments and restores contextual tanween markers before phonetization while preserving the original character offsets. This avoids tokenizer failures and false Ikhfa errors caused by differences between Quran text providers. Fathatan's silent carrier alif is skipped during target detection, and the result page derives `Ikhfa & Izhar` from every evaluated target instead of the legacy single-rule storage field.

For a usable matching recitation, final Ikhfa/Izhar correctness follows the target-aligned Quran phonemes. Ikhfa is incorrect when its expected elongated hidden noon (`ں…`) is shortened to a clear noon (`ن`); Izhar is incorrect when an extra clear or hidden/nasal noon is substituted or inserted at its target. Otherwise the target passes this elongation rule. The target-window acoustic analyzer still records the default 50 ms diagnostic boundary, but a proportional text-position crop is not allowed to decide correctness because recitation timing is nonlinear. A duration fallback becomes decisive only when its target timing is explicitly verified. Whole-recording ghunnah never satisfies this target rule.

Install the Python packages and download the local model once:

```bash
python -m pip install -r python/requirements.txt
python -c "from huggingface_hub import snapshot_download; snapshot_download(repo_id='obadx/muaalem-model-v3_2', local_dir='python/models/muaalem-model-v3_2')"
```

The model weights are approximately 2.42 GB and are intentionally ignored by Git. Web inference is offline-only: an absent or incomplete model returns `Uncertain` instead of downloading during a request. On CPU, keep `QURAN_MUAALEM_TIMEOUT=120` or higher; a CUDA-capable deployment can set `QURAN_MUAALEM_DEVICE=cuda`.

Model and data artifacts present in `python/`:

- `tajweed_model.h5`
- `tajweed_model.keras`
- `feature_model.pkl`
- `label_encoder.pkl`
- `X.npy`
- `y.npy`
- `cnn_model_metrics.json`
- `feature_model_metrics.json`
- Local dataset files under `python/Dataset/`
- Local Whisper/Quran model files under `python/models/`

System-level tool expected by the Python audio pipeline:

- FFmpeg, required by Whisper/audio conversion flows

## External Services and APIs

Configured or used by the application:

| Service | Where Configured/Used | Purpose |
| --- | --- | --- |
| Firebase Storage | `config/firebase.php`, `TajweedController`, `ProfileController` | Audio/profile media storage |
| Cloud Firestore | `config/firebase.php`, Firebase helper/services | User/profile and optional analysis data synchronization |
| Google Gemini API | `config/services.php`, `GeminiFeedbackService` | AI feedback and Arabic diacritization |
| Quran API Pages | `QuranController` | Quran surah and ayah display data |
| Al Quran Cloud API | `QuranTranscriptionMatcher` | Quran ayah corpus cache for transcription matching |
| Mailpit SMTP | `.env.example` | Local development mail target |
| AWS SES/S3 config slots | `config/services.php`, `.env.example` | Laravel standard mail/storage configuration placeholders |
| Pusher config slots | `.env.example`, broadcasting config | Laravel broadcasting placeholders |

## Database and Storage

Default `.env.example` settings:

- Database: MySQL
- Host: `127.0.0.1`
- Port: `3306`
- Database name: `laravel`
- Cache driver: `file`
- Session driver: `file`
- Queue connection: `sync`
- Filesystem disk: `local`
- Optional Redis and Memcached configuration
- Optional Firebase credentials and bucket configuration

## Testing and Quality Tools

- PHPUnit `^10.1`, configured in `phpunit.xml`
- Laravel Pint `^1.0`
- Mockery `^1.4.4`
- Faker `^1.9.1`
- Collision `^7.0`
- Spatie Laravel Ignition `^2.0`

## Local Development Tooling

- Composer for PHP dependency management
- npm for Node dependency management
- Vite for frontend development/builds
- Artisan CLI for Laravel commands, migrations, cache, queues, and custom commands
- Laravel Sail is available as a development dependency
- Laragon appears to be the local Windows development environment based on the workspace path

## Key Config Files

- `composer.json`
- `package.json`
- `vite.config.js`
- `python/requirements.txt`
- `.env.example`
- `phpunit.xml`
- `config/tajweed.php`
- `config/firebase.php`
- `config/services.php`
- `config/sanctum.php`
