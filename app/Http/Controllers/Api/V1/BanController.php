<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BannedIdentifier;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BanController extends Controller
{
    /**
     * GET /api/v1/admin/bans
     * List all bans, filterable by type, paginated (20/page).
     */
    public function index(Request $request): JsonResponse
    {
        $query = BannedIdentifier::with('bannedByUser:id,username')
            ->orderBy('created_at', 'desc');

        if ($request->filled('type') && in_array($request->type, ['email', 'ip', 'email_domain'])) {
            $query->where('type', $request->type);
        }

        return response()->json($query->paginate(20));
    }

    /**
     * POST /api/v1/admin/bans
     * Create a new ban entry.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type'   => ['required', Rule::in(['email', 'ip', 'email_domain'])],
            'value'  => ['required', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $typeLabel = match ($validated['type']) {
            'email'        => 'email',
            'ip'           => 'IP',
            'email_domain' => 'email domena',
        };

        try {
            $ban = BannedIdentifier::create([
                'type'      => $validated['type'],
                'value'     => strtolower(trim($validated['value'])),
                'reason'    => $validated['reason'] ?? null,
                'banned_by' => auth()->id(),
            ]);

            $ban->load('bannedByUser:id,username');

            return response()->json($ban, 201);
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'message' => "Ovaj {$typeLabel} je već banovan.",
            ], 409);
        }
    }

    /**
     * DELETE /api/v1/admin/bans/{ban}
     * Remove a ban entry.
     */
    public function destroy(BannedIdentifier $ban): JsonResponse
    {
        $ban->delete();

        return response()->json(['message' => 'Ban uklonjen.']);
    }

    /**
     * POST /api/v1/admin/users/{user}/ban
     * Ban email + IP of the given user, delete their tokens, then delete the user.
     */
    public function banUser(Request $request, User $user): JsonResponse
    {
        $bannedEmail = null;
        $bannedIp    = null;
        $adminId     = auth()->id();

        // Ban email
        try {
            BannedIdentifier::create([
                'type'      => 'email',
                'value'     => strtolower(trim($user->email)),
                'reason'    => 'Banovan uz brisanje naloga.',
                'banned_by' => $adminId,
            ]);
            $bannedEmail = $user->email;
        } catch (UniqueConstraintViolationException) {
            // Already banned — that's fine, proceed.
            $bannedEmail = $user->email . ' (već banovan)';
        }

        // Ban last IP if available
        if ($user->last_ip) {
            try {
                BannedIdentifier::create([
                    'type'      => 'ip',
                    'value'     => $user->last_ip,
                    'reason'    => 'Banovan uz brisanje naloga.',
                    'banned_by' => $adminId,
                ]);
                $bannedIp = $user->last_ip;
            } catch (UniqueConstraintViolationException) {
                $bannedIp = $user->last_ip . ' (već banovan)';
            }
        }

        // Revoke all tokens
        $user->tokens()->delete();

        // Delete the user
        $user->delete();

        return response()->json([
            'message'      => 'Korisnik je banovan i obrisan.',
            'banned_email' => $bannedEmail,
            'banned_ip'    => $bannedIp,
        ]);
    }
}
