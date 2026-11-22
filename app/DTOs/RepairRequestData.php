<?php

namespace App\DTOs;

class RepairRequestData
{
    public function __construct(
        public int $clientId,
        public int $categoryId,
        public string $title,
        public string $description,
        public ?string $photo,
        public string $priority,
        public string $status = 'pending',
        public ?string $barangay = null,
        public ?string $purok = null,
        public ?string $landmark = null,
        public ?float $serviceLatitude = null,
        public ?float $serviceLongitude = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            clientId: $data['client_id'],
            categoryId: $data['category_id'],
            title: $data['title'],
            description: $data['description'],
            photo: $data['photo'] ?? null,
            priority: $data['priority'] ?? 'medium',
            status: $data['status'] ?? 'pending',
            barangay: $data['barangay'] ?? null,
            purok: $data['purok'] ?? null,
            landmark: $data['landmark'] ?? null,
            serviceLatitude: isset($data['service_latitude'])
                ? (float) $data['service_latitude']
                : null,
            serviceLongitude: isset($data['service_longitude'])
                ? (float) $data['service_longitude']
                : null,
        );
    }

    public function toArray(): array
    {
        return [
            'client_id' => $this->clientId,
            'category_id' => $this->categoryId,
            'title' => $this->title,
            'description' => $this->description,
            'barangay' => $this->barangay,
            'purok' => $this->purok,
            'landmark' => $this->landmark,
            'service_latitude' => $this->serviceLatitude,
            'service_longitude' => $this->serviceLongitude,
            'photo' => $this->photo,
            'priority' => $this->priority,
            'status' => $this->status,
        ];
    }
}