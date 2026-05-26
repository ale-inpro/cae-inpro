<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Formato de fechas unificado para la interfaz (es_ES).
 */
final class DateDisplay
{
    private const TZ = 'Europe/Madrid';

    public static function date(?string $value, string $empty = '—'): string
    {
        $dt = self::parse($value);
        return $dt ? self::format($dt, false) : $empty;
    }

    public static function dateTime(?string $value, string $empty = '—'): string
    {
        $dt = self::parse($value);
        return $dt ? self::format($dt, true) : $empty;
    }

    /** Rango legible: 30 mar 2026 → 30 jun 2026 */
    public static function range(?string $from, ?string $to, string $empty = '—'): string
    {
        $f = self::parse($from);
        $t = self::parse($to);

        if (!$f && !$t) {
            return $empty;
        }
        if ($f && !$t) {
            return 'Desde ' . self::format($f, false);
        }
        if (!$f && $t) {
            return 'Hasta ' . self::format($t, false);
        }

        return self::format($f, false) . ' → ' . self::format($t, false);
    }

    /**
     * Sustituye fechas ISO embebidas en textos de usuario (mensajes de validez, etc.).
     */
    public static function humanizeText(string $text): string
    {
        if ($text === '') {
            return $text;
        }

        $out = preg_replace_callback(
            '/\((\d{4}-\d{2}-\d{2})\)/',
            static fn (array $m): string => '(' . self::date($m[1], $m[1]) . ')',
            $text
        );
        if (!is_string($out)) {
            return $text;
        }

        $out = preg_replace_callback(
            '/(?<!\d)(\d{4}-\d{2}-\d{2})(?:[ T](\d{2}:\d{2}(?::\d{2})?))?(?!\d)/',
            static function (array $m): string {
                if (!empty($m[2])) {
                    return self::dateTime($m[1] . ' ' . substr($m[2], 0, 8), $m[0]);
                }

                return self::date($m[1], $m[1]);
            },
            $out
        );

        return is_string($out) ? $out : $text;
    }

    /** Igual que range() pero con HTML para estilos (tablas CAE). */
    public static function rangeHtml(?string $from, ?string $to, string $empty = '—'): string
    {
        $text = self::range($from, $to, '');
        if ($text === '') {
            return htmlspecialchars($empty, ENT_QUOTES, 'UTF-8');
        }
        if (!str_contains($text, '→')) {
            return '<span class="date-display">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span>';
        }

        [$left, $right] = array_map('trim', explode('→', $text, 2));

        return '<span class="date-range">'
            . '<span class="date-display">' . htmlspecialchars($left, ENT_QUOTES, 'UTF-8') . '</span>'
            . '<span class="date-range__sep" aria-hidden="true">→</span>'
            . '<span class="date-display">' . htmlspecialchars($right, ENT_QUOTES, 'UTF-8') . '</span>'
            . '</span>';
    }

    private static function parse(?string $value): ?DateTimeImmutable
    {
        $raw = trim((string) $value);
        if ($raw === '' || $raw === '-') {
            return null;
        }

        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d',
            'd/m/Y H:i',
            'd/m/Y',
        ];

        foreach ($formats as $fmt) {
            $dt = DateTimeImmutable::createFromFormat($fmt, $raw, self::tz());
            if ($dt instanceof DateTimeImmutable) {
                return $dt;
            }
        }

        $ts = strtotime($raw);
        if ($ts !== false) {
            return (new DateTimeImmutable('@' . $ts))->setTimezone(self::tz());
        }

        return null;
    }

    private static function format(DateTimeInterface $dt, bool $withTime): string
    {
        if (class_exists(\IntlDateFormatter::class)) {
            $pattern = $withTime ? "d MMM y, HH:mm" : "d MMM y";
            $fmt = new \IntlDateFormatter(
                'es_ES',
                \IntlDateFormatter::NONE,
                $withTime ? \IntlDateFormatter::SHORT : \IntlDateFormatter::NONE,
                self::TZ,
                \IntlDateFormatter::GREGORIAN,
                $pattern
            );
            $out = $fmt->format($dt);
            if (is_string($out) && $out !== '') {
                return $out;
            }
        }

        if ($withTime) {
            return $dt->format('d/m/Y H:i');
        }

        $months = [
            1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun',
            7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic',
        ];
        $m = (int) $dt->format('n');

        return $dt->format('j') . ' ' . ($months[$m] ?? $dt->format('m')) . ' ' . $dt->format('Y');
    }

    private static function tz(): \DateTimeZone
    {
        return new \DateTimeZone(self::TZ);
    }
}