# Project ERD Summary

Entities and key fields (extracted from models and migrations):

- **users**
  - id (PK), name, email (unique), password, audio_path (nullable), timestamps

- **profiles**
  - id (PK), user_id (FK -> users.id)
  - Note: no migration found for `profiles` in the repo; relationship present in `Profile` model

- **audio_recitations**
  - id (PK), user_id (FK -> users.id), audio_file_path, tajweed_rule, original_filename,
    duration_seconds (nullable), firebase_url (nullable), timestamps

- **analysis_results**
  - id (PK), audio_id (FK -> audio_recitations.id), correctness (enum),
    confidence_score (decimal(5,4)), feedback_message (nullable),
    detected_errors (json, nullable), suggestions (json, nullable), processing_status, timestamps

Notes on relationships:

- `User` has many `AudioRecitation` (users.id -> audio_recitations.user_id)
- `AudioRecitation` has one `AnalysisResult` (audio_recitations.id -> analysis_results.audio_id)
- `Profile` belongs to `User` (profile.user_id -> users.id) — migration not present, cardinality unclear
- `AudioMetadata` model maps to the `analysis_results` table as an alternate model name

Files used to generate this ERD:

- `app/Models/User.php`
- `app/Models/Profile.php`
- `app/Models/AudioRecitation.php`
- `app/Models/AnalysisResult.php`
- `app/Models/AudioMetadata.php`
- `database/migrations/2014_10_12_000000_create_users_table.php`
- `database/migrations/2026_01_05_000001_add_audio_path_to_users_table.php`
- `database/migrations/2026_01_14_164723_create_audio_recitations_table.php`
- `database/migrations/2026_01_14_164733_create_analysis_results_table.php`

How to render the diagram (if you have PlantUML installed):

```bash
plantuml docs/ERD.puml
```
