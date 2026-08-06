# Target-Window Tajweed Training

This pipeline trains a separate model on short audio windows around detected Ikhfa/Izhar targets.

## Labels

An admin/Tajweed expert must assign one of these explicit pronunciation labels:

- `ikhfa_correct`
- `ikhfa_weak_ghunnah`
- `izhar_correct`
- `izhar_with_ghunnah`
- `other`

User feedback about whether the rule prediction was correct is not a pronunciation
correctness label. In the admin correction screen, choose the expert label and
set the review status to `Used for Dataset`. The exporter ignores pending,
reviewed, rejected, or unlabeled rows.

## Build

Run these from the Laravel project root:

```powershell
php artisan tajweed:export-target-windows
python python\build_target_window_dataset.py
python python\train_target_window_model.py
```

The first command writes `storage/app/target-window/manifest.json` and copied audio files.

The second command rebuilds `python/target_window_dataset`. It deletes the previous generated dataset folder before recreating it.

The third command writes:

- `python/target_window_model.pkl`
- `python/target_window_label_encoder.pkl`
- `python/target_window_model_metrics.json`

Once `python/target_window_model.pkl` exists, Laravel will call `python/predict_target_windows.py` during Tajweed analysis and use the target-window label for each detected Ikhfa/Izhar target. If the model file does not exist yet, the app falls back to the older ghunnah heuristic and marks each target with `target_window_model_status: unavailable`.

## Data Notes

This will only become reliable if a qualified reviewer listens to each target and
assigns the expert label. Notes such as `ikhfa ghunnah pendek` or
`izhar ada dengung` are useful context, but they are not parsed into labels.

For each class, aim for many recordings from different speakers and microphones. Do not trust the accuracy number until every label has enough examples and the confusion matrix looks sensible.
