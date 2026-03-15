<?php

namespace App\Http\Controllers;

use App\Services\ReportExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function __construct(
        private ReportExportService $reportService,
    ) {}

    /**
     * GET /api/reports/skills — export skill inventory as CSV.
     */
    public function skills(Request $request): StreamedResponse
    {
        $projectId = $request->query('project_id') ? (int) $request->query('project_id') : null;
        $data = $this->reportService->exportSkillInventory($projectId);

        return $this->streamCsv('skill-inventory.csv', $data);
    }

    /**
     * GET /api/reports/usage — export usage report as CSV.
     */
    public function usage(Request $request): StreamedResponse
    {
        $data = $this->reportService->exportUsageReport(
            $request->query('from'),
            $request->query('to'),
            $request->query('organization_id') ? (int) $request->query('organization_id') : null,
        );

        return $this->streamCsv('usage-report.csv', $data);
    }

    /**
     * GET /api/reports/audit — export audit log as CSV.
     */
    public function audit(Request $request): StreamedResponse
    {
        $data = $this->reportService->exportAuditLog(
            $request->query('from'),
            $request->query('to'),
        );

        return $this->streamCsv('audit-log.csv', $data);
    }

    /**
     * Stream an array of associative arrays as CSV download.
     */
    private function streamCsv(string $filename, array $data): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-store, no-cache',
            'Pragma' => 'no-cache',
        ];

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            if (! empty($data)) {
                fputcsv($handle, array_keys($data[0]));

                foreach ($data as $row) {
                    fputcsv($handle, array_values($row));
                }
            }

            fclose($handle);
        }, $filename, $headers);
    }
}
