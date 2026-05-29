<?php

declare(strict_types=1);

namespace App\Services\Rgpd;

final class RgpdTemplateTokens
{
    /** @return list<array{token: string, label: string}> */
    public static function definitions(): array
    {
        return [
            ['token' => '[NOMBRE]', 'label' => 'Nombre'],
            ['token' => '[APELLIDOS]', 'label' => 'Apellidos'],
            ['token' => '[DNI]', 'label' => 'DNI'],
            ['token' => '[EMAIL_VECINO]', 'label' => 'Email vecino'],
            ['token' => '[TELEFONO]', 'label' => 'Teléfono'],
            ['token' => '[VIVIENDA]', 'label' => 'Vivienda'],
            ['token' => '[DIRECCION]', 'label' => 'Dirección postal'],
            ['token' => '[COMUNIDAD]', 'label' => 'Comunidad'],
            ['token' => '[CIUDAD]', 'label' => 'Ciudad'],
            ['token' => '[CIF]', 'label' => 'CIF'],
            ['token' => '[EMAIL]', 'label' => 'Email comunidad'],
            ['token' => '[FECHA]', 'label' => 'Fecha actual'],
        ];
    }

    /** @return list<string, string> */
    public static function categories(): array
    {
        return [
            'consentimiento' => 'Consentimiento general',
            'comunicaciones' => 'Comunicaciones electrónicas',
            'videovigilancia' => 'Videovigilancia',
            'otro' => 'Otro',
        ];
    }

    /** @return array<string, string> */
    public static function previewSamples(): array
    {
        return [
            '[NOMBRE]' => 'Juan',
            '[APELLIDOS]' => 'Pérez Ruiz',
            '[DNI]' => '12345678A',
            '[EMAIL_VECINO]' => 'juan.perez@email.com',
            '[TELEFONO]' => '600 123 456',
            '[VIVIENDA]' => 'Portal B · 3º A',
            '[DIRECCION]' => 'C/ Mayor 12',
            '[COMUNIDAD]' => 'Residencial El Mirador',
            '[CIUDAD]' => 'Málaga',
            '[CIF]' => 'H12345678',
            '[EMAIL]' => 'rgpd@comunidad.es',
            '[FECHA]' => date('d/m/Y'),
        ];
    }
}