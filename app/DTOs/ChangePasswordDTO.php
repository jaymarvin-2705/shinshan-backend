<?php

namespace App\DTOs;

final class ChangePasswordDTO
{
    public function __construct(
        public readonly string $currentPassword,
        public readonly string $newPassword,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self($data['current_password'], $data['password']);
    }
}