<?php

namespace App\DTOs;

class TechnicianData
{
    public function __construct(
        public string $fullname,
        public string $email,
        public ?string $phone,
        public ?string $serviceArea,
        public string $availability = 'available',
    ) {}

    public static function fromArray(
        array $data
    ): self {
        return new self(
            fullname: $data['fullname'],
            email: $data['email'],
            phone: $data['phone'] ?? null,
            serviceArea: $data['service_area'] ?? null,
            availability: $data['availability'] ?? 'available',
        );
    }

    public function toProfileArray(): array
    {
        return [
            'service_area' => $this->serviceArea,
            'availability' => $this->availability,
        ];
    }
}