<?php

declare(strict_types=1);

namespace App\Domains\Identity\DTOs;

final readonly class RegisterUserDTO
{
    public function __construct(
        public string $email,
        public string $password,
        public string $username,
        public ?string $displayName = null,
        public ?string $bio = null,
        public ?int $originLocationId = null,
        public ?int $currentLocationId = null,
        public bool $isPrivate = false,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            email: $data['email'],
            password: $data['password'],
            username: $data['username'],
            displayName: $data['display_name'] ?? null,
            bio: $data['bio'] ?? null,
            originLocationId: isset($data['origin_location_id']) ? (int) $data['origin_location_id'] : null,
            currentLocationId: isset($data['current_location_id']) ? (int) $data['current_location_id'] : null,
            isPrivate: (bool) ($data['is_private'] ?? false),
        );
    }
}
