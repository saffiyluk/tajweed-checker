<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules;
use App\Models\User;
use App\Models\AudioRecitation;
use App\Models\AnalysisResult;
use Symfony\Component\Process\Process;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('admin');
    }

    /**
     * Admin Dashboard
     */
    public function dashboard()
    {
        $stats = [
            'totalUsers' => User::count(),
            'activeUsers' => User::where('created_at', '>=', now()->subDays(30))->count(),
            'totalRecitations' => AudioRecitation::count(),
            'todayRecitations' => AudioRecitation::whereDate('created_at', today())->count(),
            'completedAnalyses' => AnalysisResult::where('processing_status', 'completed')->count(),
            'pendingAnalyses' => AnalysisResult::where('processing_status', 'pending')->orWhere('processing_status', 'processing')->count(),
            'pendingCorrections' => AnalysisResult::whereNotNull('correction_submitted_at')
                ->where('correction_review_status', 'pending')
                ->count(),
        ];

        // Recent activities
        $recentRecitations = AudioRecitation::with('user')
            ->latest()
            ->limit(10)
            ->get();

        $recentUsers = User::latest()
            ->limit(10)
            ->get();

        // Chart data for last 7 days
        $recitationsByDay = AudioRecitation::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count')
        )
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return view('admin.dashboard', compact('stats', 'recentRecitations', 'recentUsers', 'recitationsByDay'));
    }

    //Display all users
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role') && in_array($request->role, ['admin', 'user'])) {
            $query->where('role', $request->role);
        }

        $users = $query->latest()->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    //Show single user
    public function showUser($id)
    {
        $user = User::findOrFail($id);
        return view('admin.users.show', compact('user'));
    }

    public function createUser()
    {
        return view('admin.users.create');
    }

    // Store new user     
    public function storeUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'is_admin' => 'boolean',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->has('is_admin') ? 'admin' : 'user',
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    public function editUser($id)
    {
        $user = User::findOrFail($id);
        return view('admin.users.edit', compact('user'));
    }

    /**
     * Update user
     */
    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => 'required|in:user,admin',
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
        ]);

        return redirect()->route('admin.users.show', $user->id)->with('success', 'User updated successfully.');
    }

    //Delete user
    public function destroyUser($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User deleted successfully.');
    }

    //Display all recitations
    public function recitations(Request $request)
    {
        $query = AudioRecitation::with(['user', 'analysisResult']);

        // Search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('original_filename', 'like', "%{$search}%")
                    ->orWhere('tajweed_rule', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by rule
        if ($request->has('rule') && in_array($request->rule, ['ikhfa', 'izhar'])) {
            $query->where('tajweed_rule', $request->rule);
        }

        // Filter by status
        if ($request->has('status')) {
            if ($request->status === 'completed') {
                $query->whereHas('analysisResult', function ($q) {
                    $q->where('processing_status', 'completed');
                });
            } elseif ($request->status === 'pending') {
                $query->whereHas('analysisResult', function ($q) {
                    $q->where('processing_status', 'pending')
                        ->orWhere('processing_status', 'processing');
                });
            }
        }

        $recitations = $query->latest()->paginate(20);

        return view('admin.recitations.index', compact('recitations'));
    }

    public function showRecitation(AudioRecitation $audioRecitation)
    {
        $audioRecitation->load([
            'user',
            'analysisResult.correctionSubmitter',
            'analysisResult.correctionReviewer',
        ]);

        return view('admin.recitations.show', compact('audioRecitation'));
    }

    public function corrections(Request $request)
    {
        $query = AnalysisResult::with([
            'audioRecitation.user',
            'correctionSubmitter',
            'correctionReviewer',
        ])->whereNotNull('correction_submitted_at');

        if ($request->filled('status') && in_array($request->status, ['pending', 'reviewed', 'used', 'rejected'], true)) {
            $query->where('correction_review_status', $request->status);
        }

        if ($request->filled('rule') && in_array($request->rule, ['ikhfa', 'izhar', 'other'], true)) {
            $query->where(function ($ruleQuery) use ($request) {
                $ruleQuery->where('corrected_rule', $request->rule)
                    ->orWhereHas('audioRecitation', function ($audioQuery) use ($request) {
                        $audioQuery->where('tajweed_rule', $request->rule);
                    });
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('corrected_transcription', 'like', "%{$search}%")
                    ->orWhere('correction_note', 'like', "%{$search}%")
                    ->orWhereHas('audioRecitation', function ($audioQuery) use ($search) {
                        $audioQuery->where('original_filename', 'like', "%{$search}%")
                            ->orWhereHas('user', function ($userQuery) use ($search) {
                                $userQuery->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            });
                    });
            });
        }

        $statusCounts = AnalysisResult::whereNotNull('correction_submitted_at')
            ->select('correction_review_status', DB::raw('COUNT(*) as total'))
            ->groupBy('correction_review_status')
            ->pluck('total', 'correction_review_status');

        $corrections = $query->latest('correction_submitted_at')->paginate(15)->withQueryString();

        return view('admin.corrections.index', compact('corrections', 'statusCounts'));
    }

    public function updateCorrection(Request $request, AnalysisResult $analysisResult)
    {
        $validated = $request->validate([
            'correction_review_status' => 'required|in:pending,reviewed,used,rejected',
            'correction_admin_note' => 'nullable|string|max:2000',
        ]);

        $analysisResult->update([
            'correction_review_status' => $validated['correction_review_status'],
            'correction_admin_note' => $validated['correction_admin_note'] ?? null,
            'correction_reviewed_by' => auth()->id(),
            'correction_reviewed_at' => now(),
        ]);

        Log::info('Admin updated Tajweed correction review', [
            'admin_id' => auth()->id(),
            'analysis_result_id' => $analysisResult->id,
            'status' => $validated['correction_review_status'],
        ]);

        return redirect()
            ->route('admin.corrections.index', $request->only(['status', 'rule', 'search']))
            ->with('success', 'Correction review updated.');
    }

    public function datasets()
    {
        $datasetPath = base_path('python/dataset');
        $classes = $this->datasetClassSummary($datasetPath);

        $modelInfo = [
            'keras_exists' => File::exists(base_path('python/tajweed_model.keras')),
            'h5_exists' => File::exists(base_path('python/tajweed_model.h5')),
            'updated_at' => File::exists(base_path('python/tajweed_model.keras'))
                ? date('Y-m-d H:i', File::lastModified(base_path('python/tajweed_model.keras')))
                : null,
        ];

        return view('admin.datasets.index', compact('classes', 'modelInfo'));
    }

    public function evaluation()
    {
        $datasetPath = base_path('python/dataset');
        $classes = $this->datasetClassSummary($datasetPath);
        $datasetCounts = collect($classes)->mapWithKeys(fn ($data, $label) => [$label => $data['count']])->all();

        $featureMetrics = $this->loadModelMetrics(base_path('python/feature_model_metrics.json'));
        $cnnMetrics = $this->loadModelMetrics(base_path('python/cnn_model_metrics.json'));

        $summaryCards = [
            'dataset_size' => array_sum($datasetCounts),
            'class_count' => count(array_filter($datasetCounts, fn ($count) => $count > 0)),
            'feature_accuracy' => Arr::get($featureMetrics, 'accuracy'),
            'cnn_accuracy' => Arr::get($cnnMetrics, 'accuracy'),
        ];

        return view('admin.evaluation.index', compact(
            'classes',
            'datasetCounts',
            'featureMetrics',
            'cnnMetrics',
            'summaryCards'
        ));
    }

    public function uploadDataset(Request $request)
    {
        $request->validate([
            'tajweed_rule' => 'required|in:ikhfa,izhar,other',
            'dataset_files' => 'required|array|min:1',
            'dataset_files.*' => 'file|mimetypes:audio/mpeg,audio/wav,audio/webm,audio/mp4,audio/x-wav|max:51200',
        ]);

        $targetDirectory = base_path('python/dataset/' . $request->tajweed_rule);
        File::ensureDirectoryExists($targetDirectory);

        $uploaded = 0;
        foreach ($request->file('dataset_files', []) as $file) {
            $extension = strtolower($file->getClientOriginalExtension() ?: 'wav');
            $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', $name);
            $filename = now()->format('Ymd_His') . '_' . uniqid() . '_' . $safeName . '.' . $extension;

            $file->move($targetDirectory, $filename);
            $uploaded++;
        }

        Log::info('Admin uploaded dataset files', [
            'admin_id' => auth()->id(),
            'rule' => $request->tajweed_rule,
            'count' => $uploaded,
        ]);

        return redirect()->route('admin.datasets.index')
            ->with('success', "{$uploaded} dataset file(s) uploaded to {$request->tajweed_rule}.");
    }

    public function retrainModel()
    {
        $pythonBinary = config('tajweed.python_binary', 'python');
        $pythonPath = base_path('python');
        $prepareScript = $pythonPath . DIRECTORY_SEPARATOR . 'prepare_data.py';
        $trainScript = $pythonPath . DIRECTORY_SEPARATOR . 'train_cnn.py';
        $featureTrainScript = $pythonPath . DIRECTORY_SEPARATOR . 'train_feature_model.py';

        if (!File::exists($prepareScript) || !File::exists($trainScript) || !File::exists($featureTrainScript)) {
            return redirect()->route('admin.datasets.index')
                ->with('error', 'Training scripts are missing from the python folder.');
        }

        $process = new Process([$pythonBinary, $prepareScript], $pythonPath);
        $process->setTimeout(300);
        $process->setEnv($this->pythonProcessEnvironment());
        $process->run();

        if (!$process->isSuccessful()) {
            Log::error('Dataset preparation failed', ['output' => $process->getErrorOutput() ?: $process->getOutput()]);

            return redirect()->route('admin.datasets.index')
                ->with('error', 'Dataset preparation failed. Check storage/logs/laravel.log for details.');
        }

        $featureTrainProcess = new Process([$pythonBinary, $featureTrainScript], $pythonPath);
        $featureTrainProcess->setTimeout(1200);
        $featureTrainProcess->setEnv($this->pythonProcessEnvironment());
        $featureTrainProcess->run();

        if (!$featureTrainProcess->isSuccessful()) {
            Log::error('Feature model retraining failed', ['output' => $featureTrainProcess->getErrorOutput() ?: $featureTrainProcess->getOutput()]);

            return redirect()->route('admin.datasets.index')
                ->with('error', 'Feature model retraining failed. Check storage/logs/laravel.log for details.');
        }

        $trainProcess = new Process([$pythonBinary, $trainScript], $pythonPath);
        $trainProcess->setTimeout(1200);
        $trainProcess->setEnv($this->pythonProcessEnvironment());
        $trainProcess->run();

        if (!$trainProcess->isSuccessful()) {
            Log::error('Model retraining failed', ['output' => $trainProcess->getErrorOutput() ?: $trainProcess->getOutput()]);

            return redirect()->route('admin.datasets.index')
                ->with('error', 'Model retraining failed. Check storage/logs/laravel.log for details.');
        }

        Log::info('Admin retrained Tajweed model', [
            'admin_id' => auth()->id(),
            'feature_output' => $featureTrainProcess->getOutput(),
            'output' => $trainProcess->getOutput(),
        ]);

        return redirect()->route('admin.datasets.index')
            ->with('success', 'Feature and CNN models retrained successfully.');
    }

    private function pythonProcessEnvironment(): array
    {
        $systemRoot = getenv('SystemRoot') ?: getenv('SYSTEMROOT') ?: 'C:\\Windows';
        $path = getenv('PATH') ?: getenv('Path') ?: '';
        $temp = getenv('TEMP') ?: sys_get_temp_dir();
        $tmp = getenv('TMP') ?: $temp;
        $pythonHome = storage_path('app/python-home');
        $appData = $pythonHome . DIRECTORY_SEPARATOR . 'AppData';
        $localAppData = $appData . DIRECTORY_SEPARATOR . 'Local';
        $roamingAppData = $appData . DIRECTORY_SEPARATOR . 'Roaming';

        foreach ([$pythonHome, $localAppData, $roamingAppData] as $directory) {
            if (!is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }
        }

        $windowsPythonHome = str_replace('/', '\\', $pythonHome);
        $drive = preg_match('/^[A-Za-z]:/', $windowsPythonHome) ? substr($windowsPythonHome, 0, 2) : 'C:';
        $homePath = preg_match('/^[A-Za-z]:(.*)$/', $windowsPythonHome, $matches) ? $matches[1] : $windowsPythonHome;

        return [
            'PATH' => $path,
            'Path' => $path,
            'SystemRoot' => $systemRoot,
            'SYSTEMROOT' => $systemRoot,
            'WINDIR' => getenv('WINDIR') ?: $systemRoot,
            'HOME' => $pythonHome,
            'USERPROFILE' => $pythonHome,
            'HOMEDRIVE' => $drive,
            'HOMEPATH' => $homePath,
            'APPDATA' => $roamingAppData,
            'LOCALAPPDATA' => $localAppData,
            'KERAS_HOME' => $pythonHome . DIRECTORY_SEPARATOR . '.keras',
            'TEMP' => $temp,
            'TMP' => $tmp,
            'PYTHONHASHSEED' => '0',
            'PYTHONIOENCODING' => 'utf-8',
            'TF_CPP_MIN_LOG_LEVEL' => '3',
            'TF_ENABLE_ONEDNN_OPTS' => '0',
        ];
    }

    //Delete recitation
    public function destroyRecitation(AudioRecitation $audioRecitation)
    {
        // Delete associated files
        if ($audioRecitation->audio_file_path) {
            // Delete local file if exists
            $localPath = $audioRecitation->audio_file_path;
            if (strpos($localPath, 'public/') === 0) {
                $localPath = substr($localPath, 7);
            }
            if (Storage::disk('public')->exists($localPath)) {
                Storage::disk('public')->delete($localPath);
            }
        }

        // Delete analysis result
        if ($audioRecitation->analysisResult) {
            $audioRecitation->analysisResult->delete();
        }

        $audioRecitation->delete();

        return redirect()->route('admin.recitations.index')
            ->with('success', 'Recitation deleted successfully.');
    }

    //System monitoring
    public function monitoring()
    {
        // Server info
        $serverInfo = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'N/A',
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'max_memory' => ini_get('memory_limit'),
        ];

        // Database info
        $databaseInfo = [
            'connection' => config('database.default'),
            'name' => config('database.connections.' . config('database.default') . '.database'),
            'size' => $this->getDatabaseSize(),
            'tables' => $this->getTableCounts(),
        ];

        // Storage info
        $storageInfo = [
            'total' => disk_total_space('/'),
            'free' => disk_free_space('/'),
            'used' => disk_total_space('/') - disk_free_space('/'),
            'usage_percentage' => round(((disk_total_space('/') - disk_free_space('/')) / disk_total_space('/')) * 100, 2),
        ];

        // Recent logs
        $logFile = storage_path('logs/laravel.log');
        $recentLogs = [];
        if (file_exists($logFile)) {
            $logs = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $recentLogs = array_slice($logs, -50); // Last 50 lines
        }

        return view('admin.monitoring.index', compact('serverInfo', 'databaseInfo', 'storageInfo', 'recentLogs'));
    }

    //Analytics
    public function analytics()
    {
        // User growth (last 30 days)
        $userGrowth = User::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count')
        )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Recitations by rule
        $recitationsByRule = AudioRecitation::select(
            'tajweed_rule',
            DB::raw('COUNT(*) as count')
        )
            ->groupBy('tajweed_rule')
            ->get();

        // Analysis results
        $analysisResults = AnalysisResult::select(
            'correctness',
            DB::raw('COUNT(*) as count')
        )
            ->whereNotNull('correctness')
            ->groupBy('correctness')
            ->get();

        // Active users (last 7 days)
        $activeUsers = User::where('created_at', '>=', now()->subDays(7))
            ->count();

        // Top users by recitations
        $topUsers = User::withCount('audioRecitations')
            ->orderBy('audio_recitations_count', 'desc')
            ->limit(10)
            ->get();

        return view('admin.analytics.index', compact(
            'userGrowth',
            'recitationsByRule',
            'analysisResults',
            'activeUsers',
            'topUsers'
        ));
    }

    //System logs
    public function logs()
    {
        $logFile = storage_path('logs/laravel.log');
        $logs = [];

        if (file_exists($logFile)) {
            $file = file_get_contents($logFile);
            $pattern = '/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\].*?\n/s';
            preg_match_all($pattern, $file, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $logs[] = [
                    'timestamp' => $match[1],
                    'message' => trim($match[0]),
                ];
            }

            // Reverse to show newest first
            $logs = array_reverse($logs);
        }

        return view('admin.logs.index', compact('logs'));
    }

    //Get database size
    private function getDatabaseSize()
    {
        try {
            $databaseName = config('database.connections.mysql.database');
            $size = DB::select("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb 
                               FROM information_schema.tables 
                               WHERE table_schema = ?", [$databaseName]);
            return $size[0]->size_mb . ' MB';
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    //Helper to get table counts
    private function getTableCounts()
    {
        $tables = [
            'users' => User::count(),
            'audio_recitations' => AudioRecitation::count(),
            'analysis_results' => AnalysisResult::count(),
        ];

        return $tables;
    }

    private function datasetClassSummary(string $datasetPath): array
    {
        $classes = [];

        foreach (['ikhfa', 'izhar', 'other'] as $class) {
            $path = $datasetPath . DIRECTORY_SEPARATOR . $class;
            $files = File::isDirectory($path)
                ? collect(File::files($path))->filter(fn ($file) => in_array(strtolower($file->getExtension()), ['wav', 'mp3', 'webm', 'm4a', 'ogg', 'oga', 'flac', 'aac', 'mp4']))->values()
                : collect();

            $classes[$class] = [
                'path' => $path,
                'count' => $files->count(),
                'latest' => $files->sortByDesc(fn ($file) => $file->getMTime())->take(5),
            ];
        }

        return $classes;
    }

    private function loadModelMetrics(string $path): ?array
    {
        if (!File::exists($path)) {
            return null;
        }

        $decoded = json_decode(File::get($path), true);

        if (!is_array($decoded)) {
            return null;
        }

        $decoded['updated_at'] = date('Y-m-d H:i', File::lastModified($path));

        return $decoded;
    }

}
