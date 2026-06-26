<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Global search across Moments, Scena, and Users.
     *
     * GET /api/v1/search?q=belgrade&type=all
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
            'type' => 'sometimes|string|in:all,moments,scena,users',
        ]);

        $query = strtolower($request->input('q'));
        $term = '%' . $query . '%';
        $type = $request->input('type', 'all');

        $isPgsql = config('database.default') === 'pgsql'
            || config('database.connections.' . config('database.default') . '.driver') === 'pgsql';

        $results = [];

        $authUser = $request->user('sanctum');
        $blockedIds = $authUser
            ? Block::where('blocker_id', $authUser->id)->pluck('blocked_id')
                ->merge(Block::where('blocked_id', $authUser->id)->pluck('blocker_id'))
                ->unique()
            : collect();

        // Search Moments (short-form)
        if (in_array($type, ['all', 'moments'])) {
            $momentsQuery = Media::moments()->with('user:id,username')
                ->when($blockedIds->isNotEmpty(), fn ($q) => $q->whereNotIn('user_id', $blockedIds));

            if ($isPgsql) {
                $momentsQuery->where(function ($q) use ($term) {
                    $q->whereRaw('unaccent(LOWER(title)) LIKE unaccent(?)', [$term])
                      ->orWhereRaw('unaccent(LOWER(description)) LIKE unaccent(?)', [$term]);
                });
            } else {
                $momentsQuery->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(title) LIKE ?', [$term])
                      ->orWhereRaw('LOWER(description) LIKE ?', [$term]);
                });
            }

            $moments = $momentsQuery->latest()->limit(10)->get();

            $results['moments'] = [
                'count' => $moments->count(),
                'data' => $moments->map(fn ($m) => [
                    'id' => $m->id,
                    'title' => $m->title,
                    'description' => $m->description,
                    'thumbnail_url' => $m->thumbnail_url,
                    'is_ppv' => $m->is_ppv,
                    'price_coins' => $m->price_coins,
                    'user' => $m->user,
                    'created_at' => $m->created_at,
                    'type' => 'moment',
                ]),
            ];
        }

        // Search Scena (long-form)
        if (in_array($type, ['all', 'scena'])) {
            $scenaQuery = Media::longForm()->with('user:id,username')
                ->when($blockedIds->isNotEmpty(), fn ($q) => $q->whereNotIn('user_id', $blockedIds));

            if ($isPgsql) {
                $scenaQuery->where(function ($q) use ($term) {
                    $q->whereRaw('unaccent(LOWER(title)) LIKE unaccent(?)', [$term])
                      ->orWhereRaw('unaccent(LOWER(description)) LIKE unaccent(?)', [$term]);
                });
            } else {
                $scenaQuery->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(title) LIKE ?', [$term])
                      ->orWhereRaw('LOWER(description) LIKE ?', [$term]);
                });
            }

            $scena = $scenaQuery->latest()->limit(10)->get();

            $results['scena'] = [
                'count' => $scena->count(),
                'data' => $scena->map(fn ($s) => [
                    'id' => $s->id,
                    'title' => $s->title,
                    'description' => $s->description,
                    'thumbnail_url' => $s->thumbnail_url,
                    'is_ppv' => $s->is_ppv,
                    'price_coins' => $s->price_coins,
                    'user' => $s->user,
                    'created_at' => $s->created_at,
                    'type' => 'scena',
                ]),
            ];
        }

        // Search Users
        if (in_array($type, ['all', 'users'])) {
            $usersQuery = User::query()->with('profile')
                ->when($blockedIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $blockedIds));

            if ($isPgsql) {
                $usersQuery->where(function ($q) use ($term) {
                    $q->whereRaw('unaccent(LOWER(username)) LIKE unaccent(?)', [$term])
                      ->orWhereHas('profile', function ($pq) use ($term) {
                          $pq->whereRaw('unaccent(LOWER(bio)) LIKE unaccent(?)', [$term]);
                      });
                });
            } else {
                $usersQuery->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(username) LIKE ?', [$term])
                      ->orWhereHas('profile', function ($pq) use ($term) {
                          $pq->whereRaw('LOWER(bio) LIKE ?', [$term]);
                      });
                });
            }

            $users = $usersQuery->limit(10)->get();

            $results['users'] = [
                'count' => $users->count(),
                'data' => $users->map(fn ($u) => [
                    'id' => $u->id,
                    'username' => $u->username,
                    'name' => $u->username,
                    'avatar_url' => $u->profile?->avatar_url,
                    'bio' => $u->profile?->bio,
                    'type' => 'user',
                ]),
            ];
        }

        return response()->json([
            'query' => $request->input('q'),
            'results' => $results,
        ]);
    }
}