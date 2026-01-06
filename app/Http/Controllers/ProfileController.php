<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\User;

class ProfileController extends Controller
{
    public function index(){
        $user = User::all();
        //dd($student);
        return view('profile.show', compact('user'));
    }

    public function show(User $user)
    {
        return view('profile.show', compact('user'));
    }

    public function edit(User $user)
    {
        return view('profile.edit',compact('user'));
    }

    public function update(Request $request, $id)
    {
        // Ensure user can only update their own profile
        if (Auth::id() != $id) {
            abort(403);
        }

        $user = User::findOrFail($id);

        // Validate input
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|min:8|confirmed',
            'audio' => 'nullable|file|mimes:mp3,wav,ogg,m4a|max:51200',
        ]);

        // Update fields
        $user->name = $request->name;
        $user->email = $request->email;

        // Update password ONLY if provided
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        // Handle audio upload
        if ($request->hasFile('audio')) {
            // store on the public disk under 'audios'
            $path = $request->file('audio')->store('audios', 'public');

            // Optionally delete previous audio file
            if (!empty($user->audio_path) && Storage::disk('public')->exists($user->audio_path)) {
                Storage::disk('public')->delete($user->audio_path);
            }

            $user->audio_path = $path;
        }

        $user->save();

        return redirect()
            ->route('profile.show', $user->id)
            ->with('success', 'Profile updated successfully.');
    }

    /**
     * Update only the user's audio from dashboard.
     */
    public function updateAudio(Request $request, $id)
    {
        if (Auth::id() != $id) {
            abort(403);
        }

        $user = User::findOrFail($id);

        $request->validate([
            'audio' => 'required|file|mimes:mp3,wav,ogg,m4a|max:51200',
        ]);

        if ($request->hasFile('audio')) {
            $path = $request->file('audio')->store('audios', 'public');

            if (!empty($user->audio_path) && Storage::disk('public')->exists($user->audio_path)) {
                Storage::disk('public')->delete($user->audio_path);
            }

            $user->audio_path = $path;
            $user->save();
        }

        return redirect()->route('home')->with('success', 'Audio uploaded successfully.');
    }

    public function destroy($id)
    {
        // Ensure user deletes only their own account
        if (Auth::id() != $id) {
            abort(403);
        }

        $user = User::findOrFail($id);

        // Logout user first
        Auth::logout();

        // Delete user
        $user->delete();

        // Invalidate session
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/')
            ->with('success', 'Your account has been deleted.');
    }
}