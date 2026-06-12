<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EventType;
use App\Models\User;
use App\Services\AchievementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AchievementTestController extends Controller
{
    public function __construct(private AchievementService $achievementService) {}

    public function trigger(Request $request): JsonResponse
    {
        $request->validate([
            'email'      => 'required|email|exists:users,email',
            'event_type' => 'required|string|in:' . implode(',', array_column(EventType::cases(), 'value')),
            'value'      => 'nullable|numeric',
        ]);

        $user      = User::where('email', $request->email)->firstOrFail();
        $eventType = EventType::from($request->event_type);

        $this->achievementService->record($user, $eventType, $request->value ? (float) $request->value : null);

        $earned = $user->earnedAchievements()->get()->map(fn($a) => [
            'name'      => $a->name,
            'earned_at' => $a->pivot->earned_at,
        ]);

        return response()->json([
            'user'               => $user->email,
            'event_recorded'     => $eventType->value,
            'earned_achievements' => $earned,
        ]);
    }
}