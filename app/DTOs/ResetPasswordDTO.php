<?php

namespace App\DTOs;

final class ResetPasswordDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $otp,
        public readonly string $password,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self($data['email'], $data['otp'], $data['password']);
    }
}