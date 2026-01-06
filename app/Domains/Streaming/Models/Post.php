<?php

declare(strict_types=1);

namespace App\Domains\Streaming\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Enums\PostType;
use App\Domains\Shared\Enums\VideoProvider;
use App\Domains\Shared\Traits\HasUuid;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property PostType $type
 * @property string|null $title
 * @property string|null $description
 * @property string $source_url
 * @property VideoProvider|null $provider
 * @property string|null $provider_id
 * @property string|null $thumbnail_url
 * @property int|null $duration_seconds
 * @property bool $is_premium
 * @property bool $is_published
 * @property \Carbon\Carbon|null $published_at
 * @property array<string, mixed> $meta
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read User $user
 */
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;
    use HasUuid;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): PostFactory
    {
        return PostFactory::new();
    }

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'user_id',
        'type',
        'title',
        'description',
        'source_url',
        'provider',
        'provider_id',
        'thumbnail_url',
        'duration_seconds',
        'is_premium',
        'is_published',
        'published_at',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PostType::class,
            'provider' => VideoProvider::class,
            'is_premium' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * Get the user that owns the post.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to only published posts.
     *
     * @param Builder<Post> $query
     * @return Builder<Post>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope to filter by type.
     *
     * @param Builder<Post> $query
     * @return Builder<Post>
     */
    public function scopeOfType(Builder $query, PostType $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to filter by provider.
     *
     * @param Builder<Post> $query
     * @return Builder<Post>
     */
    public function scopeFromProvider(Builder $query, VideoProvider $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    /**
     * Get the embed URL for video posts.
     */
    public function getEmbedUrl(): ?string
    {
        if ($this->provider === null || $this->provider_id === null) {
            return null;
        }

        return $this->provider->getEmbedUrl($this->provider_id);
    }

    /**
     * Check if this is a video post.
     */
    public function isVideo(): bool
    {
        return in_array($this->type, [PostType::Video, PostType::ShortClip], true);
    }

    /**
     * Get formatted duration.
     */
    public function getFormattedDuration(): ?string
    {
        if ($this->duration_seconds === null) {
            return null;
        }

        $hours = floor($this->duration_seconds / 3600);
        $minutes = floor(($this->duration_seconds % 3600) / 60);
        $seconds = $this->duration_seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%d:%02d', $minutes, $seconds);
    }
}
