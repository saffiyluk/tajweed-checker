<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use TCPDF;
use App\Models\AnalysisResult;
use App\Models\User;

class PDFController extends Controller
{
    public function generateReport($userId)
    {
        //Fetch user details
        $user = User::find($userId);

        // Fetch user analyses with related audio recitations
        $analyses = AnalysisResult::with('audioRecitation')
            ->whereHas('audioRecitation', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->get();

        // Create new TCPDF object
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

        // Set document info
        $pdf->SetCreator('Tajweed App');
        $pdf->SetAuthor('Tajweed App');
        $pdf->SetTitle('User Progress Report');
        $pdf->SetHeaderData('', 0, 'Tajweed Progress Report', '');
        $pdf->setHeaderFont(['dejavusans', '', 12]);
        $pdf->setFooterFont(['dejavusans', '', 10]);

        // Set default monospaced font
        $pdf->SetDefaultMonospacedFont('dejavusans');

        // Set margins
        $pdf->SetMargins(15, 27, 15);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);

        // Auto page breaks
        $pdf->SetAutoPageBreak(TRUE, 25);

        // Add a page
        $pdf->AddPage();

        // Arabic support
        $pdf->SetFont('dejavusans', '', 14);

        // HTML content
        $html = '<h2>User Progress Report</h2>';
        $html .= '<p><strong>User: </strong>' . ($user->name ?? 'Unknown') . '</p>';
        $html .= '<table border="1" cellpadding="5">
                    <thead>
                        <tr>
                            <th>Suggestions</th>
                            <th>Date</th>
                            <th>Feedback</th>
                            <th>Correctness</th>
                        </tr>
                    </thead>
                    <tbody>';

        $html = '<h2>User Progress Report</h2>';
        $html .= '<p><strong>User: </strong>' . ($user->name ?? 'Unknown') . '</p>';

        //Check if there are analyses
        if ($analyses->isEmpty()) {
            $html .= '<p>No progress data available for this user.</p>';
        } else {
            $html .= '<table border="1" cellpadding="5">
                <thead>
                    <tr>
                        <th>Suggestions</th>
                        <th>Date</th>
                        <th>Feedback</th>
                        <th>Correctness</th>
                    </tr>
                </thead>
                <tbody>';

            foreach ($analyses as $a) {
                $audio = $a->audioRecitation;
                $suggestions = $a->suggestions ? implode(', ', $a->suggestions) : '-';
                $html .= '<tr>
                    <td>' . $suggestions . '</td>
                    <td>' . ($audio->created_at ? $audio->created_at->format('d-m-Y') : '-') . '</td>
                    <td>' . ($a->feedback_message ?? '-') . '</td>
                    <td>' . ($a->correctness ?? '-') . '</td>
                  </tr>';
            }

            $html .= '</tbody></table>';
        }

        // Write HTML to PDF
        $pdf->writeHTML($html, true, false, true, false, '');

        // Output PDF (download)
        return response($pdf->Output('progress_report.pdf', 'S'))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="progress_report.pdf"');

        //Authorization: ensure user can only access their own report
        if ($request->user()->id !== (int) $userId) {
            abort(403);
        }
    }

}
