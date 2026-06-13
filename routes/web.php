<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Kreait\Firebase\Factory;
use App\Http\Controllers\TajweedController;
use App\Http\Controllers\PDFController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\QuranController;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('auth/login');
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/profile/{user}', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/{user}/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile/{id}/audio', [ProfileController::class, 'updateAudio'])->name('profile.audio');
    Route::put('/profile/{id}', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile/{id}', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/** Firebase Testing */
Route::get('/firebase-test', function () {
    $factory = (new \Kreait\Firebase\Factory)
        ->withServiceAccount(base_path(config('firebase.credentials')))
        ->withDefaultStorageBucket(config('firebase.storage_bucket'))
        ->createFirestore(['transport' => 'rest']);

    $bucket = $factory->createStorage()->getBucket();
    $usersCollection = $factory->createFirestore()->collection('users');

    return 'Firebase connected successfully!';
});

// Tajweed endpoints (requires auth)
Route::middleware('auth')->group(function () {
    // Main Tajweed routes (make sure these match your navbar links)
    Route::get('/tajweed/ikhfa-haqiqi', [TajweedController::class, 'ikhfaHaqiqi'])->name('tajweed.ikhfa-haqiqi');
    Route::get('/tajweed/izhar-halqi', [TajweedController::class, 'izharHalqi'])->name('tajweed.izhar-halqi');

    // Upload and result routes
    Route::post('/tajweed/upload', [TajweedController::class, 'upload'])->name('tajweed.upload');
    Route::get('/tajweed/result/{audioRecitation}', [TajweedController::class, 'result'])->name('tajweed.result');
    Route::get('/tajweed/history', [TajweedController::class, 'history'])->name('tajweed.history');
    Route::get('/tajweed/analysis-status/{audioRecitation}', [TajweedController::class, 'getAnalysisStatus'])->name('tajweed.analysis-status');
    Route::post('/tajweed/reanalyze/{audioRecitation}', [TajweedController::class, 'reanalyze'])->name('tajweed.reanalyze');
    Route::post('/tajweed/correction/{audioRecitation}', [TajweedController::class, 'storeCorrection'])->name('tajweed.correction.store');
});

//Test Firebase Storage connection
Route::get('/test-firebase', function () {
    echo "<pre>";
    echo "FIREBASE_STORAGE_BUCKET: " . config('firebase.storage_bucket') . "\n";
    echo "FIREBASE_CREDENTIALS path: " . base_path(config('firebase.credentials')) . "\n";
    echo "File exists: " . (file_exists(base_path(config('firebase.credentials'))) ? 'YES' : 'NO') . "\n";
    echo "</pre>";

    try {
        $factory = (new \Kreait\Firebase\Factory())
            ->withServiceAccount(base_path(config('firebase.credentials')))
            ->withDefaultStorageBucket(config('firebase.storage_bucket'));

        $storage = $factory->createStorage();
        $bucket = $storage->getBucket();

        return response()->json([
            'status' => 'success',
            'bucket' => $bucket->name(),
            'message' => 'Firebase Storage connected successfully'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
});

Route::get('/debug/firebase', [TajweedController::class, 'debugFirebase']);

Route::delete('/tajweed/delete/{audioRecitation}', [TajweedController::class, 'destroy'])->name('tajweed.delete');
Route::get('/tajweed/download/{audioRecitation}', [TajweedController::class, 'download'])->name('tajweed.download');

Route::get('/tajweed/audio-url/{audioRecitation}', [TajweedController::class, 'getAudioUrl'])->name('tajweed.audio-url');

Route::get('/tajweed/play/{audioRecitation}', [TajweedController::class, 'playAudio'])->name('tajweed.play-audio');

Route::get('/pdf/report/{userId}', [PDFController::class, 'generateReport'])->name('report.generate');

// Admin Routes
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');

    // User Management
    Route::get('/users', [AdminController::class, 'index'])->name('users.index');
    Route::get('/users/create', [AdminController::class, 'createUser'])->name('users.create');
    Route::post('/users', [AdminController::class, 'storeUser'])->name('users.store');
    Route::get('/users/{user}', [AdminController::class, 'showUser'])->name('users.show');
    Route::get('/users/{user}/edit', [AdminController::class, 'editUser'])->name('users.edit');
    Route::put('/users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
    Route::delete('/users/{user}', [AdminController::class, 'destroyUser'])->name('users.destroy');

    // Recitations Management
    Route::get('/recitations', [AdminController::class, 'recitations'])->name('recitations.index');
    Route::get('/recitations/{audioRecitation}', [AdminController::class, 'showRecitation'])->name('recitations.show');
    Route::delete('/recitations/{audioRecitation}', [AdminController::class, 'destroyRecitation'])->name('recitations.destroy');
    Route::get('/corrections', [AdminController::class, 'corrections'])->name('corrections.index');
    Route::patch('/corrections/{analysisResult}', [AdminController::class, 'updateCorrection'])->name('corrections.update');

    // Dataset and Model Management
    Route::get('/datasets', [AdminController::class, 'datasets'])->name('datasets.index');
    Route::post('/datasets', [AdminController::class, 'uploadDataset'])->name('datasets.upload');
    Route::post('/model/retrain', [AdminController::class, 'retrainModel'])->name('model.retrain');
    Route::get('/evaluation', [AdminController::class, 'evaluation'])->name('evaluation');

    // System Monitoring
    Route::get('/monitoring', [AdminController::class, 'monitoring'])->name('monitoring');

    // Analytics
    Route::get('/analytics', [AdminController::class, 'analytics'])->name('analytics');

    // System Logs
    Route::get('/logs', [AdminController::class, 'logs'])->name('logs');
});

// Quran routes
Route::get('/quran', [QuranController::class, 'surahList'])->name('quran.list');
Route::get('/quran/{id}', [QuranController::class, 'surah'])->name('quran.surah');

// Recite Quran page (optional surah, default = 1)
Route::get('/recite-quran/{surah?}', [QuranController::class, 'showSurah'])
    ->where('surah', '[0-9]+')
    ->name('recite.quran');
