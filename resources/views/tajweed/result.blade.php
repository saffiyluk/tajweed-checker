@extends('layouts.app')

@section('title', 'Tajweed Analysis Result')

@section('content')
    <h1>Tajweed Analysis Result</h1>

    <p><strong>Status:</strong> {{ $result->processing_status }}</p>

    <p><strong>Duration:</strong> {{ $result->audio->duration_seconds ?? 'N/A' }} seconds</p>

    <p><strong>Confidence:</strong> {{ $result->confidence_score }}%</p>

    <p><strong>Feedback:</strong> {{ $result->feedback_message }}</p>
@endsection