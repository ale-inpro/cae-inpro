<?php
declare(strict_types=1);
namespace App\Services\Rgpd;

final class RgpdTemplateRenderer
{
    /** @param array<string, mixed> $community */
    public static function render(string $bodyHtml, array $community): string
    {
        $map = [
            '[COMUNIDAD]' => (string) ($community['name'] ?? ''),
            '[EMAIL]' => (string) ($community['contact_email'] ?? $community['email_rgpd'] ?? ''),
            '{{comunidad}}' => (string) ($community['name'] ?? ''),
            '{{email}}' => (string) ($community['contact_email'] ?? ''),
        ];
        return str_replace(array_keys($map), array_values($map), $bodyHtml);
    }
}