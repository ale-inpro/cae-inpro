<?php

declare(strict_types=1);

namespace App\Services\Supply;

final class SupplyContractStatus
{
    public static function resolveFromDates(string $startDate, ?string $endDate): string
    {
        $today = date('Y-m-d');
        if ($endDate !== null && $endDate !== '' && $endDate < $today) {
            return 'expired';
        }
        if ($endDate !== null && $endDate !== '' && $endDate <= date('Y-m-d', strtotime('+60 days'))) {
            return 'pending_renewal';
        }
        return 'active';
    }

    /** @return array{0:string,1:string} label, badge class */
    public static function badge(string $status): array
    {
        return match ($status) {
            'active' => ['Activo', 'text-bg-success'],
            'pending_renewal' => ['Próxima renovación', 'text-bg-warning'],
            'expired' => ['Vencido', 'text-bg-danger'],
            'cancelled' => ['Baja', 'text-bg-secondary'],
            default => ['Borrador', 'text-bg-secondary'],
        };
    }
}