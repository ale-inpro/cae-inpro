<?php

declare(strict_types=1);

use App\Support\DateDisplay;

function app_date(?string $value, string $empty = '—'): string
{
    return DateDisplay::date($value, $empty);
}

function app_datetime(?string $value, string $empty = '—'): string
{
    return DateDisplay::dateTime($value, $empty);
}

function app_date_range(?string $from, ?string $to, string $empty = '—'): string
{
    return DateDisplay::range($from, $to, $empty);
}

function app_date_range_html(?string $from, ?string $to, string $empty = '—'): string
{
    return DateDisplay::rangeHtml($from, $to, $empty);
}


/**
 * Nombre visible de un vecino (array de community_residents).
 * @param array<string, mixed> $resident
 */
function app_resident_name(array $resident): string
{
    $nombre = trim((string) ($resident['nombre'] ?? ''));
    $apellidos = trim((string) ($resident['apellidos'] ?? ''));
    $combined = trim($nombre . ' ' . $apellidos);

    if ($combined !== '') {
        return $combined;
    }

    return trim((string) ($resident['full_name'] ?? ''));
}