<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AudioRecitation;
use App\Models\AnalysisResult;

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
    public function update(Request $request, $id)
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

}
