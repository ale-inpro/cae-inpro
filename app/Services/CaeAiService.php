<?php

declare(strict_types=1);

namespace App\Services;

final class CaeAiService
{
    // ─────────────────────────────────────────────────────────────
    // PASO 1: PHP decide el estado — lógica determinista, sin IA
    // Mismos documentos → mismo resultado siempre
    // ─────────────────────────────────────────────────────────────
    
    /** @param array<int, array<string,mixed>> $sources */
    public static function determineStatus(array $sources): array
    {
        // CaeAiController ya exige CaeReadinessService antes de llamar aquí.
        // Solo alineamos el estado del PDF con los cuatro tipos canónicos (sin heurísticas de texto).
        if ($sources === []) {
            return [
                'status'   => 'pending_docs',
                'missing'  => CaeReadinessService::REQUIRED_SUPPORTING_DOC_NAMES,
                'reason'   => 'No hay documentos complementarios cargados.',
                'has_text' => false,
            ];
        }

        $present = [];
        foreach ($sources as $s) {
            $n = trim((string) ($s['document_type_name'] ?? ''));
            if ($n !== '') {
                $present[$n] = true;
            }
        }

        $missing = [];
        foreach (CaeReadinessService::REQUIRED_SUPPORTING_DOC_NAMES as $req) {
            if (!isset($present[$req])) {
                $missing[] = $req;
            }
        }

        if ($missing !== []) {
            return [
                'status'   => 'pending_docs',
                'missing'  => $missing,
                'reason'   => 'Faltan tipos de documento obligatorios: ' . implode(', ', $missing) . '.',
                'has_text' => false,
            ];
        }

        return [
            'status'   => 'approved',
            'missing'  => [],
            'reason'   => 'Documentación conforme a las reglas del sistema (vigencia y AEAT en Hacienda ya validadas al generar).',
            'has_text' => true,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // PASO 2: IA solo redacta el texto narrativo del PDF
    // El estado ya viene decidido por PHP — la IA no puede cambiarlo
    // ─────────────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $tech
     * @param array<int, array<string,mixed>> $sources
     * @param array<string,mixed> $statusResult  Resultado de determineStatus()
     */
    public static function generateNarrative(
        array $tech,
        array $sources,
        array $statusResult,
        string $extraNotes = ''
    ): array {
        $cfg    = require dirname(__DIR__, 2) . '/config/ai.php';
        $apiKey = (string) ($cfg['openai_api_key'] ?? '');
        $model  = (string) ($cfg['model'] ?? 'gpt-4o-mini');

        $status      = (string) ($statusResult['status'] ?? 'in_review');
        $reason      = (string) ($statusResult['reason'] ?? '');
        $missing     = (array)  ($statusResult['missing'] ?? []);

        $validFrom  = ($tech['valid_from']  ?? '') !== '' ? (string) $tech['valid_from']  : date('Y-m-d');
        $validUntil = ($tech['valid_until'] ?? '') !== '' ? (string) $tech['valid_until'] : date('Y-m-d', strtotime('+3 months'));

        // Draft base con campos ya fijados por PHP
        $baseDraft = [
            'conclusion_estado' => $status,
            'faltantes'         => $missing,
            'campos'            => [
                'tecnico_nombre' => (string) ($tech['full_name']   ?? ''),
                'tecnico_email'  => (string) ($tech['email']       ?? ''),
                'profesion'      => (string) ($tech['professions'] ?? ''),
                'valido_desde'   => $validFrom,
                'valido_hasta'   => $validUntil,
            ],
        ];

        if ($apiKey === '') {
            return array_merge($baseDraft, self::fallbackNarrative($status, $reason, $missing));
        }

        // Construir resumen de documentos para el prompt
        $docLines = [];
        foreach ($sources as $s) {
            $name = (string) ($s['original_filename'] ?? 'documento');
            $txt  = trim((string) ($s['extracted_text'] ?? ''));

            if ($txt === '' || str_contains($txt, '[Sin texto extraído')) {
                $txt = '[Documento sin texto extraíble — posiblemente escaneado o imagen]';
            } elseif (mb_strlen($txt) > 2000) {
                // Truncar para no exceder tokens
                $txt = mb_substr($txt, 0, 2000) . '... [truncado]';
            }

            $docLines[] = "• {$name}:\n{$txt}";
        }

        $statusLabel = match($status) {
            'approved'     => 'APROBADO',
            'in_review'    => 'EN REVISIÓN',
            'pending_docs' => 'PENDIENTE DE DOCUMENTOS',
            'rejected'     => 'RECHAZADO',
            default        => strtoupper($status),
        };

        $missingLine = $missing !== []
            ? 'Documentos faltantes detectados: ' . implode(', ', $missing) . '.'
            : '';

        $notesLine = $extraNotes !== '' ? "Notas del administrador: {$extraNotes}" : '';

        $prompt = "Eres un técnico de prevención de riesgos laborales redactando el texto de un certificado CAE en español.

ESTADO DETERMINADO POR EL SISTEMA (no puedes cambiarlo): {$statusLabel}
MOTIVO DEL ESTADO: {$reason}
{$missingLine}
{$notesLine}

Datos del técnico:
- Nombre: {$tech['full_name']}
- Email: {$tech['email']}
- Profesión: {$tech['professions']}

Documentos proporcionados:
" . implode("\n\n", $docLines) . "

Redacta el texto narrativo profesional para el certificado CAE con el estado {$statusLabel}.
- resumen: 2-3 frases profesionales y objetivas que describan la situación documental.
- observaciones: 2-4 puntos concretos sobre los documentos revisados, adecuados al estado {$statusLabel}.
Tono: formal, técnico, en tercera persona.";

        // Structured output — OpenAI garantiza el schema exacto (gpt-4o / gpt-4o-mini)
        $payload = [
            'model'       => $model,
            'temperature' => 0,
            'messages'    => [
                [
                    'role'    => 'system',
                    'content' => 'Especialista en certificados CAE y documentación de prevención de riesgos laborales. Respondes ÚNICAMENTE con JSON válido según el schema indicado.',
                ],
                ['role' => 'user', 'content' => $prompt],
            ],
            'response_format' => [
                'type'        => 'json_schema',
                'json_schema' => [
                    'name'   => 'cae_narrative',
                    'strict' => true,
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'resumen'       => ['type' => 'string'],
                            'observaciones' => [
                                'type'  => 'array',
                                'items' => ['type' => 'string'],
                            ],
                        ],
                        'required'             => ['resumen', 'observaciones'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => 45,
        ]);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if (!is_string($raw) || $raw === '' || $err !== '') {
            error_log('[CaeAiService] curl error: ' . $err);
            return array_merge($baseDraft, self::fallbackNarrative($status, $reason, $missing));
        }

        $resp    = json_decode($raw, true);
        $content = (string) ($resp['choices'][0]['message']['content'] ?? '');

        // Por si acaso el modelo no soporta json_schema y devuelve markdown
        if (preg_match('/\{.*\}/s', $content, $m)) {
            $content = $m[0];
        }

        $narrative = json_decode($content, true);
        if (!is_array($narrative) || !isset($narrative['resumen'])) {
            error_log('[CaeAiService] narrative parse error. Content: ' . substr($content, 0, 300));
            return array_merge($baseDraft, self::fallbackNarrative($status, $reason, $missing));
        }

        return array_merge($baseDraft, [
            'resumen'       => (string) ($narrative['resumen']       ?? $reason),
            'observaciones' => (array)  ($narrative['observaciones'] ?? []),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Fallback: texto de plantilla si la IA no está disponible
    // ─────────────────────────────────────────────────────────────

    /** @param string[] $missing */
    private static function fallbackNarrative(string $status, string $reason, array $missing): array
    {
        $obs = match($status) {
            'approved'     => [
                'Toda la documentación requerida ha sido verificada correctamente.',
                'La documentación de Responsabilidad Civil y Prevención de Riesgos está presente y es legible.',
                'El certificado CAE puede ser emitido sin observaciones.',
            ],
            'in_review'    => [
                'La documentación requiere revisión manual por parte del administrador.',
                'Algunos documentos no han podido verificarse automáticamente.',
                'Se recomienda contactar con el técnico para confirmar la vigencia de los documentos.',
            ],
            'pending_docs' => array_merge(
                ['Faltan los siguientes documentos obligatorios para completar el CAE:'],
                array_map(static fn($d) => "— {$d}", $missing),
                ['El técnico debe aportar la documentación indicada antes de proceder.']
            ),
            default        => ['Estado pendiente de revisión administrativa.'],
        };

        return [
            'resumen'       => $reason,
            'observaciones' => $obs,
        ];
    }
}