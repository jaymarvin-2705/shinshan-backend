<?php

namespace App\Support;

use App\Models\RepairRequest;

class TrinidadLocationCatalog
{
    private const PLACEHOLDER_PUROK_COUNT = 7;

    public static function barangays(): array
    {
        return RepairRequest::BARANGAYS;
    }

    public static function puroks(?string $barangay): array
    {
        if (! in_array($barangay, self::barangays(), true)) {
            return [];
        }

        return array_map(
            static fn (int $number): string => "Purok {$number}",
            range(1, self::PLACEHOLDER_PUROK_COUNT)
        );
    }
}
