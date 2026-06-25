<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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

        if (in_array($type, ['all', 'moments'])) {
            $moments = $this->applyTitleDescSearch(Media::moments()->with('user:id,username'), $term, $isPgsql)
                ->latest()->limit(10)->get();

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

        if (in_array($type, ['all', 'scena'])) {
            $scena = $this->applyTitleDescSearch(Media::longForm()->with('user:id,username'), $term, $isPgsql)
                ->latest()->limit(10)->get();

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

        if (in_array($type, ['all', 'users'])) {
            $users = User::query()->with('profile')
                ->where(function ($q) use ($term, $isPgsql) {
                    $q->whereRaw($this->likeRaw('username', $isPgsql), [$term])
                      ->orWhereHas('profile', fn ($pq) => $pq->whereRaw($this->likeRaw('bio', $isPgsql), [$term]));
                })
                ->limit(10)->get();

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

    private function likeRaw(string $column, bool $isPgsql): string
    {
        return $isPgsql
            ? "unaccent(LOWER({$column})) LIKE unaccent(?)"
            : "LOWER({$column}) LIKE ?";
    }

    private function applyTitleDescSearch(Builder $query, string $term, bool $isPgsql): Builder
    {
        return $query->where(fn ($q) => $q
            ->whereRaw($this->likeRaw('title', $isPgsql), [$term])
            ->orWhereRaw($this->likeRaw('description', $isPgsql), [$term])
        );
    }
}