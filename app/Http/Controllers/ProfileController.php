<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Helpers\Firebase;

class ProfileController extends Controller
{
    protected $firestore;
    protected $storage;

    public function __construct()
    {
        $this->middleware('auth');
        $this->firestore = Firebase::firestore();
        $this->storage = Firebase::storage();
    }

    // Show user profile
    public function show($id)
    {
        $user = Auth::user(); // Or find by $id if you allow admin viewing

        // Fetch user document from Firestore
        $firestoreDoc = Firebase::firestore()->collection('users')->document($user->id)->snapshot();

        // Check if document exists
        if ($firestoreDoc->exists()) {
            $userData = $firestoreDoc->data(); // This is an array with fields from Firestore
        } else {
            $userData = []; // fallback if no Firestore doc
        }

        return view('profile.show', [
            'user' => $user,
            'userData' => $userData,
        ]);
    }

    // Show edit form
    public function edit()
    {
        $userId = Auth::id();
        $userDoc = $this->firestore->collection('users')->document($userId)->snapshot();
        $userData = $userDoc->exists() ? $userDoc->data() : [];

        return view('profile.edit', compact('userData'));
    }

    // Update profile info (name/email/password)
    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|min:8|confirmed',
        ]);

        // Update MySQL Auth data
        $user->name = $request->name;
        $user->email = $request->email;
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }
        $user->save();

        // Update Firebase profile (optional extra fields)
        $userId = $user->id;
        $this->firestore->collection('users')->document($userId)->set([
            'name' => $request->name,
            'email' => $request->email,
        ], ['merge' => true]);

        return redirect()->route('profile.show')->with('success', 'Profile updated successfully.');
    }

    // Upload/update audio
    public function updateAudio(Request $request)
    {
        $request->validate([
            'audio' => 'required|file|mimes:mp3,wav,ogg,m4a|max:51200',
        ]);

        $userId = Auth::id();
        $file = $request->file('audio');

        // Upload to Firebase Storage
        $storagePath = "audios/{$userId}/" . $file->getClientOriginalName();
        $stream = fopen($file->getRealPath(), 'r');
        $this->storage->upload($stream, [
            'name' => $storagePath,
            'predefinedAcl' => 'publicRead' // optional: make file public
        ]);
        fclose($stream);

        // Save audio path in Firestore
        $this->firestore->collection('users')->document($userId)->set([
            'audio_path' => $storagePath
        ], ['merge' => true]);

        return redirect()->route('profile.show')->with('success', 'Audio uploaded successfully.');
    }

    // Delete profile
    public function destroy()
    {
        $user = Auth::user();
        $userId = $user->id;

        // Delete Firebase data
        $this->firestore->collection('users')->document($userId)->delete();

        // Optionally delete audio from Storage
        $files = $this->storage->objects(["prefix" => "audios/{$userId}/"]);
        foreach ($files as $file) {
            $file->delete();
        }

        // Delete MySQL Auth account
        Auth::logout();
        $user->delete();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/')->with('success', 'Your account has been deleted.');
    }
       
}
