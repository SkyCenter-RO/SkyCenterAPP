<?php

namespace App\Support;

class LicensePlate
{
    public static function normalize(?string $plate): ?string
    {
        if ($plate === null) {
            return null;
        }

        $normalized = preg_replace('/[^A-Za-z0-9]/', '', $plate) ?? '';

        return $normalized !== '' ? strtoupper($normalized) : null;
    }
}
