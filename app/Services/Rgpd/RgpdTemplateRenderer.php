<?php

declare(strict_types=1);

namespace App\Services\Rgpd;

final class RgpdTemplateRenderer
{
    /**
     * @param array<string, mixed> $community
     * @param array<string, mixed>|null $resident
     */
    public static function render(string $bodyHtml, array $community, ?array $resident = null): string
    {
        $communityEmail = (string) ($community['contact_email'] ?? $community['email_rgpd'] ?? '');

        $map = [
            '[COMUNIDAD]' => (string) ($community['name'] ?? ''),
            '[CIUDAD]' => (string) ($community['city'] ?? ''),
            '[CIF]' => (string) ($community['cif'] ?? ''),
            '[DIRECCION_COMUNIDAD]' => (string) ($community['address'] ?? ''),
            '[EMAIL]' => $communityEmail,
            '[FECHA]' => date('d/m/Y'),
            '{{comunidad}}' => (string) ($community['name'] ?? ''),
            '{{email}}' => $communityEmail,
        ];

        if ($resident !== null) {
            $vivienda = (string) ($resident['unit_label'] ?? '');
            if ($vivienda === '' && !empty($resident['propiedades'])) {
                $props = is_string($resident['propiedades'])
                    ? json_decode($resident['propiedades'], true)
                    : $resident['propiedades'];
                $vivienda = is_array($props) ? (string) ($props['vivienda'] ?? '') : '';
            }

            $map['[NOMBRE]'] = trim((string) ($resident['nombre'] ?? ''));
            $map['[APELLIDOS]'] = trim((string) ($resident['apellidos'] ?? ''));
            $map['[DNI]'] = (string) ($resident['dni'] ?? '');
            $map['[EMAIL_VECINO]'] = (string) ($resident['email'] ?? '');
            $map['[TELEFONO]'] = (string) ($resident['telefono'] ?? '');
            $map['[VIVIENDA]'] = $vivienda;
            $map['[DIRECCION]'] = (string) ($resident['direccion_postal'] ?? $vivienda);
        }

        return str_replace(array_keys($map), array_values($map), $bodyHtml);
    }

    /** Vista previa en el editor (datos ficticios). */
    public static function preview(string $bodyHtml): string
    {
        $fakeCommunity = [
            'name' => 'Residencial El Mirador',
            'city' => 'Málaga',
            'cif' => 'H12345678',
            'address' => 'Av. del Mar 45',
            'contact_email' => 'rgpd@comunidad.es',
        ];
        $fakeResident = [
            'nombre' => 'Juan',
            'apellidos' => 'Pérez Ruiz',
            'dni' => '12345678A',
            'email' => 'juan.perez@email.com',
            'telefono' => '600 123 456',
            'unit_label' => 'Portal B · 3º A',
            'direccion_postal' => 'C/ Mayor 12',
        ];

        $html = self::render($bodyHtml, $fakeCommunity, $fakeResident);
        foreach (RgpdTemplateTokens::previewSamples() as $token => $sample) {
            $html = str_replace($token, $sample, $html);
        }

        return $html;
    }
}