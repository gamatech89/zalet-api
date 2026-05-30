<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HubController extends Controller
{
    /**
     * List available country hubs with stats.
     * GET /api/v1/hubs
     */
    public function index(Request $request): JsonResponse
    {
        // Aggregate profiles by current_country to find active hubs
        $hubs = Profile::query()
            ->whereNotNull('current_country')
            ->select('current_country', DB::raw('count(*) as member_count'))
            ->groupBy('current_country')
            ->orderByDesc('member_count')
            ->get()
            ->map(function ($hub) {
                // Get creator count for this country
                $creatorCount = Profile::where('current_country', $hub->current_country)
                    ->whereHas('user', function ($q) {
                        $q->whereIn('role', ['creator', 'admin']);
                    })
                    ->count();

                // Get live streams count
                $liveCount = DB::table('live_streams')
                    ->join('users', 'live_streams.user_id', '=', 'users.id')
                    ->join('profiles', 'users.id', '=', 'profiles.user_id')
                    ->where('profiles.current_country', $hub->current_country)
                    ->where('is_live', true) 
                    ->count();

                return [
                    'country_code' => $hub->current_country, // e.g., 'AT'
                    'name' => $this->getCountryName($hub->current_country),
                    'slug' => strtolower($hub->current_country),
                    'stats' => [
                        'members' => $hub->member_count,
                        'creators' => $creatorCount,
                        'live' => $liveCount,
                    ],
                    // We'll map these to static assets on frontend for now
                    'image_url' => null, 
                ];
            });

        return response()->json(['data' => $hubs]);
    }

    /**
     * Get details for a specific hub (country).
     * GET /api/v1/hubs/{country}
     */
    public function show(string $country): JsonResponse
    {
        $countryCode = strtoupper($country);
        
        // Get all cities in this country that have members
        $cities = Profile::where('current_country', $countryCode)
            ->whereNotNull('current_city')
            ->select('current_city', DB::raw('count(*) as count'))
            ->groupBy('current_city')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn($c) => [
                'name' => $c->current_city,
                'count' => $c->count,
                'slug' => strtolower($c->current_city)
            ]);

        // Hub Stats
        $memberCount = Profile::where('current_country', $countryCode)->count();
        $creatorCount = Profile::where('current_country', $countryCode)
            ->whereHas('user', fn($q) => $q->whereIn('role', ['creator', 'admin']))
            ->count();
        $liveCount = DB::table('live_streams')
            ->join('users', 'live_streams.user_id', '=', 'users.id')
            ->join('profiles', 'users.id', '=', 'profiles.user_id')
            ->where('profiles.current_country', $countryCode)
            ->where('live_streams.is_live', true)
            ->count();

        return response()->json([
            'data' => [
                'country_code' => $countryCode,
                'name' => $this->getCountryName($countryCode),
                'cities' => $cities,
                'stats' => [
                    'members' => $memberCount,
                    'creators' => $creatorCount,
                    'live' => $liveCount,
                ]
            ]
        ]);
    }

    /**
     * Get users in a hub, optionally filtered by city.
     * GET /api/v1/hubs/{country}/users
     */
    public function users(Request $request, string $country): JsonResponse
    {
        $countryCode = strtoupper($country);
        $city = $request->input('city');

        $users = User::query()
            ->with(['profile'])
            ->whereHas('profile', function ($q) use ($countryCode, $city) {
                $q->where('current_country', $countryCode);
                
                if ($city && $city !== 'all') {
                    $q->where(DB::raw('LOWER(current_city)'), strtolower($city));
                }
            })
            // Prioritize creators and those with filled profiles
            // Order by CASE when role='creator' then 1 else 0 END
            ->orderByRaw("CASE WHEN role = 'creator' THEN 1 ELSE 0 END DESC")
            ->orderByDesc('created_at')
            ->paginate(20);

        $currentUserId = $request->user()?->id;

        return response()->json([
            'data' => $users->map(function ($user) use ($currentUserId) {
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'avatar_url' => $user->profile->avatar_url,
                    'is_creator' => $user->isCreator(), // Method call is fine on model
                    'is_live' => $user->liveStreams()->where('is_live', true)->exists(),
                    'journey' => [
                        'hometown' => [
                            'city' => $user->profile->hometown_city,
                            'country' => $user->profile->hometown_country,
                        ],
                        'current' => [
                            'city' => $user->profile->current_city,
                            'country' => $user->profile->current_country,
                        ]
                    ],
                    'is_following' => $currentUserId ? $user->isFollowedBy($currentUserId) : false,
                ];
            }),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'total' => $users->total(),
            ]
        ]);
    }

    private function getCountryName(string $code): string 
    {
        // Simple map for core markets
        return match($code) {
            'AT' => 'Austria',
            'DE' => 'Germany',
            'CH' => 'Switzerland',
            'RS' => 'Serbia',
            'BA' => 'Bosnia & Herzegovina',
            'HR' => 'Croatia',
            'US' => 'USA',
            'AU' => 'Australia',
            'SE' => 'Sweden',
            default => $code
        };
    }
}
