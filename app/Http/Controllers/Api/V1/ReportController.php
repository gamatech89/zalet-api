<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    const VALID_REASONS = [
        'spam',
        'harassment',
        'fake_profile',
        'inappropriate_content',
        'threats',
        'other',
    ];

    /**
     * Submit a report.
     * POST /api/v1/reports
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reported_user_id' => ['required', 'uuid', 'exists:users,id'],
            'reason'           => ['required', 'string', 'in:' . implode(',', self::VALID_REASONS)],
            'description'      => ['required', 'string', 'min:10', 'max:1000'],
            'evidence'         => ['nullable', 'image', 'max:5120'],
        ]);

        if ($validated['reported_user_id'] === $request->user()->id) {
            return response()->json(['message' => 'Ne možeš prijaviti sam sebe.'], 422);
        }

        $evidenceUrl = null;
        if ($request->hasFile('evidence')) {
            $path = $request->file('evidence')->store('reports/evidence', 's3');
            $evidenceUrl = Storage::disk('s3')->url($path);
        }

        $alreadyReported = Report::where('reporter_id', $request->user()->id)
            ->where('reported_user_id', $validated['reported_user_id'])
            ->where('status', 'pending')
            ->exists();

        if ($alreadyReported) {
            return response()->json(['message' => 'Već si prijavio ovog korisnika. Čeka se pregled.'], 409);
        }

        Report::create([
            'reporter_id'      => $request->user()->id,
            'reported_user_id' => $validated['reported_user_id'],
            'reason'           => $validated['reason'],
            'description'      => $validated['description'],
            'evidence_url'     => $evidenceUrl,
        ]);

        return response()->json(['message' => 'Prijava je poslata. Hvala na povratnoj informaciji.'], 201);
    }

    /**
     * Admin: list reports.
     * GET /api/v1/admin/reports
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $status = $request->query('status', 'pending');

        $reports = Report::when($status !== 'all', fn($q) => $q->where('status', $status))
            ->with([
                'reporter:id,username,name',
                'reporter.profile:user_id,avatar_url',
                'reportedUser:id,username,name',
                'reportedUser.profile:user_id,avatar_url',
            ])
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $reports->map(fn($r) => $this->formatReport($r)),
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page'    => $reports->lastPage(),
                'total'        => $reports->total(),
            ],
        ]);
    }

    /**
     * Admin: update report status.
     * PATCH /api/v1/admin/reports/{report}
     */
    public function adminUpdate(Request $request, Report $report): JsonResponse
    {
        $validated = $request->validate([
            'status'     => ['required', 'in:reviewed,dismissed'],
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        $report->update($validated);

        return response()->json([
            'message' => 'Status prijave ažuriran.',
            'data'    => $this->formatReport($report->fresh(['reporter:id,username,name', 'reportedUser:id,username,name'])),
        ]);
    }

    private function formatReport(Report $report): array
    {
        return [
            'id'             => $report->id,
            'reason'         => $report->reason,
            'description'    => $report->description,
            'evidence_url'   => $report->evidence_url,
            'status'         => $report->status,
            'admin_note'     => $report->admin_note,
            'created_at'     => $report->created_at->toIso8601String(),
            'reporter'       => [
                'id'         => $report->reporter->id,
                'username'   => $report->reporter->username,
                'name'       => $report->reporter->name,
                'avatar_url' => $report->reporter->profile?->avatar_url,
            ],
            'reported_user'  => [
                'id'         => $report->reportedUser->id,
                'username'   => $report->reportedUser->username,
                'name'       => $report->reportedUser->name,
                'avatar_url' => $report->reportedUser->profile?->avatar_url,
            ],
        ];
    }
}
