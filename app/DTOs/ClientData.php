<?php

namespace App\DTOs;

class ClientData
{
    public function __construct(
        public string $fullname,
        public string $email,
        public ?string $phone,
        public ?string $address,
        public ?string $firstname = null,
        public ?string $middlename = null,
        public ?string $lastname = null,
        public ?string $birth_date = null,
        public ?string $barangay = null,
        public ?string $purok = null,
    ) {}

    public static function fromArray(
        array $data
    ): self {
        return new self(
            fullname: $data['fullname'],
            email: $data['email'],
            phone: $data['phone'] ?? null,
            address: $data['address'] ?? null,
            firstname: $data['firstname'] ?? null,
            middlename: $data['middlename'] ?? null,
            lastname: $data['lastname'] ?? null,
            birth_date: $data['birth_date'] ?? null,
            barangay: $data['barangay'] ?? null,
            purok: $data['purok'] ?? null,
        );
    }

    public function toUserArray(): array
    {
        return [
            'firstname' => $this->firstname,
            'middlename' => $this->middlename,
            'lastname' => $this->lastname,
            'fullname' => $this->fullname,
            'email' => $this->email,
            'phone' => $this->phone,
        ];
    }

    public function toProfileArray(): array
    {
        $profile = [
            'address' => $this->address,
        ];

        foreach (['birth_date', 'barangay', 'purok'] as $field) {
            if ($this->{$field} !== null) {
                $profile[$field] = $this->{$field};
            }
        }

        return $profile;
    }
}
