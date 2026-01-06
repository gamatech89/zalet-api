<?php

declare(strict_types=1);

namespace App\Domains\Identity\DTOs;

final readonly class UpdateProfileDTO
{
    public function __construct(
        public ?string $username = null,
        public ?string $displayName = null,
        public ?string $bio = null,
        public ?string $avatarUrl = null,
        public ?int $originLocationId = null,
        public ?int $currentLocationId = null,
        public ?bool $isPrivate = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            username: $data['username'] ?? null,
            displayName: $data['display_name'] ?? null,
            bio: $data['bio'] ?? null,
            avatarUrl: $data['avatar_url'] ?? null,
            originLocationId: isset($data['origin_location_id']) ? (int) $data['origin_location_id'] : null,
            currentLocationId: isset($data['current_location_id']) ? (int) $data['current_location_id'] : null,
            isPrivate: isset($data['is_private']) ? (bool) $data['is_private'] : null,
        );
    }
}
