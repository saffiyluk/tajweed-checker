<?php

namespace App\Http\Controllers;

use App\Models\AudioRecitation;
use Illuminate\Http\Request;

class TajweedAnalysisService extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(AudioRecitation $audioRecitation)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AudioRecitation $audioRecitation)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AudioRecitation $audioRecitation)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AudioRecitation $audioRecitation)
    {
        //
    }

    public function audio()
    {
        return $this->belongsTo(AudioRecitation::class, 'audio_id');
    }
}
