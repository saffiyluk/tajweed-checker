<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use TCPDF;
use App\Models\AnalysisResult;
use App\Models\User;

class PDFController extends Controller
{
    public function generateReport(Request $request, $userId)
    {
        // Authorization: user can only access own report.
        // If admin needs access, adjust this condition based on your role system.
        if ($request->user() && $request->user()->id !== (int) $userId) {
            abort(403);
        }

        $user = User::findOrFail($userId);

        $analyses = AnalysisResult::with('audioRecitation')
            ->whereHas('audioRecitation', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->latest()
            ->get();

        $totalAnalyses = $analyses->count();

        $correctCount = $analyses->filter(function ($analysis) {
            return strtolower((string) $analysis->correctness) === 'correct';
        })->count();

        $needsPracticeCount = $totalAnalyses - $correctCount;

        $accuracyRate = $totalAnalyses > 0
            ? round(($correctCount / $totalAnalyses) * 100)
            : 0;

        $latestAnalysisDate = $analyses->first()?->audioRecitation?->created_at
            ? $analyses->first()->audioRecitation->created_at->format('d M Y')
            : '-';

        $esc = function ($value) {
            return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        };

        $formatSuggestions = function ($suggestions) use ($esc) {
            if (empty($suggestions)) {
                return '-';
            }

            if (is_string($suggestions)) {
                $decoded = json_decode($suggestions, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $suggestions = $decoded;
                } else {
                    return $esc($suggestions);
                }
            }

            if (is_array($suggestions)) {
                return implode('<br>', array_map(function ($item) use ($esc) {
                    return '&bull; ' . $esc($item);
                }, $suggestions));
            }

            return $esc($suggestions);
        };

        $formatCorrectness = function ($correctness) use ($esc) {
            $value = strtolower((string) $correctness);

            if ($value === 'correct') {
                return '<span style="color:#15803d; font-weight:bold;">Correct</span>';
            }

            if ($value === 'incorrect') {
                return '<span style="color:#b91c1c; font-weight:bold;">Needs Practice</span>';
            }

            return $esc($correctness ?: '-');
        };

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

        $pdf->SetCreator('Tajweed Checker');
        $pdf->SetAuthor('Tajweed Checker');
        $pdf->SetTitle('Tajweed Progress Report');
        $pdf->SetSubject('User Tajweed Recitation Progress Report');

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);

        $pdf->SetMargins(12, 12, 12);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->SetFont('dejavusans', '', 10);

        $pdf->AddPage();

        $generatedDate = now()->format('d M Y, h:i A');

        $html = '
        <style>
            body {
                color: #0f172a;
                font-family: dejavusans;
            }

            .hero {
                background-color: #0f4c81;
                color: #ffffff;
                padding: 18px 20px;
                border-radius: 8px;
            }

            .hero-title {
                font-size: 24px;
                font-weight: bold;
                margin-bottom: 4px;
            }

            .hero-subtitle {
                font-size: 10px;
                color: #dbeafe;
            }

            .section-title {
                font-size: 14px;
                color: #0f4c81;
                font-weight: bold;
                margin-top: 18px;
                margin-bottom: 8px;
                border-bottom: 1px solid #dbeafe;
                padding-bottom: 5px;
            }

            .small-text {
                font-size: 9px;
                color: #64748b;
            }

            .info-table td {
                padding: 7px;
                font-size: 10px;
            }

            .info-label {
                color: #64748b;
                font-weight: bold;
            }

            .metric-table td {
                border: 1px solid #e2e8f0;
                padding: 10px;
                text-align: center;
            }

            .metric-number {
                font-size: 20px;
                font-weight: bold;
                color: #0f4c81;
            }

            .metric-label {
                font-size: 8.5px;
                color: #64748b;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .data-table {
                border-collapse: collapse;
                font-size: 8.5px;
            }

            .data-table th {
                background-color: #0f4c81;
                color: #ffffff;
                font-weight: bold;
                padding: 7px;
                border: 1px solid #0f4c81;
            }

            .data-table td {
                border: 1px solid #dbe4ef;
                padding: 7px;
                vertical-align: top;
            }

            .empty-box {
                background-color: #f8fafc;
                border: 1px solid #e2e8f0;
                padding: 18px;
                text-align: center;
                color: #64748b;
                border-radius: 8px;
            }

            .note-box {
                background-color: #f8fafc;
                border-left: 4px solid #0f4c81;
                padding: 10px;
                font-size: 9px;
                color: #475569;
            }
        </style>

        <div class="hero">
            <div class="hero-title">Tajweed Progress Report</div>
            <div class="hero-subtitle">
                AI-Based Tajweed Detection Web Application - Recitation Analysis Summary
            </div>
        </div>

        <div class="section-title">Student Information</div>

        <table class="info-table" width="100%" cellpadding="4">
            <tr>
                <td width="25%" class="info-label">Name</td>
                <td width="75%">' . $esc($user->name ?? 'Unknown') . '</td>
            </tr>
            <tr>
                <td class="info-label">Email</td>
                <td>' . $esc($user->email ?? '-') . '</td>
            </tr>
            <tr>
                <td class="info-label">Generated On</td>
                <td>' . $esc($generatedDate) . '</td>
            </tr>
            <tr>
                <td class="info-label">Latest Practice</td>
                <td>' . $esc($latestAnalysisDate) . '</td>
            </tr>
        </table>

        <div class="section-title">Progress Overview</div>

        <table class="metric-table" width="100%" cellpadding="5">
            <tr>
                <td width="25%">
                    <div class="metric-number">' . $totalAnalyses . '</div>
                    <div class="metric-label">Total Attempts</div>
                </td>
                <td width="25%">
                    <div class="metric-number">' . $correctCount . '</div>
                    <div class="metric-label">Correct</div>
                </td>
                <td width="25%">
                    <div class="metric-number">' . $needsPracticeCount . '</div>
                    <div class="metric-label">Needs Practice</div>
                </td>
                <td width="25%">
                    <div class="metric-number">' . $accuracyRate . '%</div>
                    <div class="metric-label">Accuracy Rate</div>
                </td>
            </tr>
        </table>

        <div class="section-title">Detailed Analysis Records</div>
        ';

        if ($analyses->isEmpty()) {
            $html .= '
                <div class="empty-box">
                    No progress data is available for this user yet.
                </div>
            ';
        } else {
            $html .= '
            <table class="data-table" width="100%" cellpadding="5">
                <thead>
                    <tr>
                        <th width="13%">Date</th>
                        <th width="14%">Rule</th>
                        <th width="18%">Correctness</th>
                        <th width="30%">Feedback</th>
                        <th width="25%">Suggestions</th>
                    </tr>
                </thead>
                <tbody>
            ';

            foreach ($analyses as $analysis) {
                $audio = $analysis->audioRecitation;

                $date = $audio && $audio->created_at
                    ? $audio->created_at->format('d M Y')
                    : '-';

                $rule = $audio->tajweed_rule
                    ?? $audio->rule
                    ?? $analysis->tajweed_rule
                    ?? '-';

                $feedback = $analysis->feedback_message ?? '-';
                $suggestions = $formatSuggestions($analysis->suggestions ?? null);
                $correctness = $formatCorrectness($analysis->correctness ?? '-');

                $html .= '
                    <tr>
                        <td width="13%">' . $esc($date) . '</td>
                        <td width="14%">' . $esc(ucfirst((string) $rule)) . '</td>
                        <td width="18%">' . $correctness . '</td>
                        <td width="30%">' . $esc($feedback) . '</td>
                        <td width="25%">' . $suggestions . '</td>
                    </tr>
                ';
            }

            $html .= '
                </tbody>
            </table>
            ';
        }

        $html .= '
            <br><br>
            <div class="note-box">
                <strong>Note:</strong> This report is generated automatically by Tajweed Checker.
                The analysis result is intended to support tajweed learning and should be used together
                with guidance from a qualified Quran teacher.
            </div>
        ';

        $pdf->writeHTML($html, true, false, true, false, '');

        $filename = 'tajweed_progress_report_' . $user->id . '.pdf';

        return response($pdf->Output($filename, 'S'))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }
}