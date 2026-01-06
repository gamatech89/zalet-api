<?php

declare(strict_types=1);

namespace App\Domains\Identity\DTOs;

final readonly class LoginUserDTO
{
    public function __construct(
        public string $email,
        public string $password,
        public string $deviceName = 'web',
        public bool $singleSession = false,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            email: $data['email'],
            password: $data['password'],
            deviceName: $data['device_name'] ?? 'web',
            singleSession: (bool) ($data['single_session'] ?? false),
        );
    }
}
