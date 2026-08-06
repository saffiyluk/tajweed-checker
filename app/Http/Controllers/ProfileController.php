<?php

namespace App\Http\Controllers;

use App\Helpers\Firebase;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    protected $firestore = null;

    protected $storage = null;

    public function __construct()
    {
        $this->middleware('auth');
        $this->initializeOptionalFirebase();
    }

    // Show user profile
    public function show(User $user)
    {
        $this->authorize('manageProfile', $user);
        $userData = $this->loadProfileData($user);

        return view('profile.show', [
            'user' => $user,
            'userData' => $userData,
        ]);
    }

    // Show edit form
    public function edit(User $user)
    {
        $this->authorize('manageProfile', $user);
        $userData = $this->loadProfileData($user);

        return view('profile.edit', compact('user', 'userData'));
    }

    // Update profile info (name/email/password)
    public function update(Request $request, User $user)
    {
        $this->authorize('manageProfile', $user);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'password' => 'nullable|min:8|confirmed',
        ]);

        // Update MySQL Auth data
        $user->name = $request->name;
        $user->email = $request->email;
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }
        $user->save();

        $this->syncProfileToFirestore($user, [
            'name' => $request->name,
            'email' => $request->email,
        ]);

        return redirect()->route('profile.show', $user->id)->with('success', 'Profile updated successfully.');
    }

    // Upload/update audio
    public function updateAudio(Request $request, User $user)
    {
        $this->authorize('manageProfile', $user);

        $request->validate([
            'audio' => 'required|file|mimes:mp3,wav,ogg,m4a|max:51200',
        ]);

        $file = $request->file('audio');
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'audio');
        $filename = Str::uuid().'.'.$extension;
        $storagePath = null;

        if ($this->storage) {
            $firebasePath = "profile-audios/{$user->id}/{$filename}";
            $stream = fopen($file->getRealPath(), 'rb');

            try {
                $this->storage->upload($stream, [
                    'name' => $firebasePath,
                    'metadata' => [
                        'contentType' => $file->getMimeType() ?: 'application/octet-stream',
                        'userId' => (string) $user->id,
                    ],
                ]);
                $storagePath = $firebasePath;
            } catch (\Throwable $exception) {
                Log::warning('Firebase profile audio upload failed; using local storage', [
                    'user_id' => $user->id,
                    'message' => $exception->getMessage(),
                ]);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }

        if (! $storagePath) {
            $storagePath = Storage::disk('public')->putFileAs(
                "profile-audios/{$user->id}",
                $file,
                $filename
            );
        }

        $previousPath = (string) $user->audio_path;
        $user->forceFill(['audio_path' => $storagePath])->save();

        if ($previousPath !== '' && $previousPath !== $storagePath) {
            $this->deleteStoredAudio($user, $previousPath);
        }

        $this->syncProfileToFirestore($user, [
            'audio_path' => $storagePath,
        ]);

        return redirect()->route('profile.show', $user->id)->with('success', 'Audio uploaded successfully.');
    }

    // Delete profile
    public function destroy(Request $request, User $user)
    {
        $this->authorize('manageProfile', $user);
        $this->deleteStoredAudio($user, (string) $user->audio_path);
        $this->deleteFirebaseProfile($user);

        // Delete MySQL Auth account
        Auth::logout();
        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Your account has been deleted.');
    }

    private function initializeOptionalFirebase(): void
    {
        $credentials = trim((string) config('firebase.credentials'));
        $bucket = trim((string) config('firebase.storage_bucket'));

        if ($credentials === '' || $bucket === '') {
            return;
        }

        $credentialsPath = preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/]{1,2})/', $credentials)
            ? $credentials
            : base_path($credentials);

        if (! is_file($credentialsPath)) {
            Log::info('Firebase profile integration disabled because credentials are unavailable.');

            return;
        }

        try {
            $this->firestore = Firebase::firestore();
            $this->storage = Firebase::storage();
        } catch (\Throwable $exception) {
            Log::warning('Firebase profile integration unavailable; using MySQL/local storage', [
                'message' => $exception->getMessage(),
            ]);
            $this->firestore = null;
            $this->storage = null;
        }
    }

    private function loadProfileData(User $user): array
    {
        $mysqlData = [
            'name' => $user->name,
            'email' => $user->email,
        ];

        if ($user->audio_path) {
            $mysqlData['audio_path'] = $user->audio_path;
        }

        if (! $this->firestore) {
            return $mysqlData;
        }

        try {
            $document = $this->firestore
                ->collection('users')
                ->document((string) $user->id)
                ->snapshot();

            $firebaseData = $document->exists() ? (array) $document->data() : [];

            return array_merge($firebaseData, $mysqlData);
        } catch (\Throwable $exception) {
            Log::warning('Unable to read Firebase profile; using MySQL profile', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);

            return $mysqlData;
        }
    }

    private function syncProfileToFirestore(User $user, array $data): void
    {
        if (! $this->firestore) {
            return;
        }

        try {
            $this->firestore
                ->collection('users')
                ->document((string) $user->id)
                ->set($data, ['merge' => true]);
        } catch (\Throwable $exception) {
            Log::warning('Unable to synchronize profile to Firebase', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function deleteStoredAudio(User $user, string $path): void
    {
        if ($path === '') {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        if (! $this->storage || ! str_starts_with($path, "profile-audios/{$user->id}/")) {
            return;
        }

        try {
            $object = $this->storage->object($path);

            if ($object->exists()) {
                $object->delete();
            }
        } catch (\Throwable $exception) {
            Log::warning('Unable to delete Firebase profile audio', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function deleteFirebaseProfile(User $user): void
    {
        if ($this->firestore) {
            try {
                $this->firestore
                    ->collection('users')
                    ->document((string) $user->id)
                    ->delete();
            } catch (\Throwable $exception) {
                Log::warning('Unable to delete Firebase profile document', [
                    'user_id' => $user->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if (! $this->storage) {
            return;
        }

        try {
            foreach (["audios/{$user->id}/", "profile-audios/{$user->id}/"] as $prefix) {
                foreach ($this->storage->objects(['prefix' => $prefix]) as $object) {
                    $object->delete();
                }
            }
        } catch (\Throwable $exception) {
            Log::warning('Unable to delete all Firebase profile audio', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
