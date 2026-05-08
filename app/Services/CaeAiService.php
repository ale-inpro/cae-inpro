<?php

declare(strict_types=1);

namespace App\Services;

final class CaeAiService
{
    /** @param array<string,mixed> $technician @param array<int,array<string,mixed>> $sources */
    public static function buildDraft(array $technician, array $sources, string $extraNotes = ''): array
    {
        $cfg = require dirname(__DIR__, 2) . '/config/ai.php';
        $apiKey = (string) ($cfg['openai_api_key'] ?? '');
        $model  = (string) ($cfg['model'] ?? 'gpt-4o-mini');

        $sourceText = [];
        foreach ($sources as $s) {
            $name = (string) ($s['original_filename'] ?? 'documento');
            $txt  = trim((string) ($s['extracted_text'] ?? ''));
            if ($txt === '') {
                $txt = '[Sin texto extraído; posible escaneo o formato no soportado]';
            }
            $sourceText[] = "Documento: {$name}\n{$txt}";
        }

        // Fechas fijas del CAE vigente (o valores por defecto si no hay CAE)
        $validFrom  = ($technician['valid_from']  ?? '') !== '' ? (string) $technician['valid_from']  : date('Y-m-d');
        $validHasta = ($technician['valid_until'] ?? '') !== '' ? (string) $technician['valid_until'] : date('Y-m-d', strtotime('+3 months'));

        $prompt = <<<PROMPT
        Eres técnico de prevención y CAE. Los documentos obligatorios (Póliza RC y Recibo RC) ya han sido verificados por el sistema. Tu tarea es analizar la CALIDAD y VALIDEZ de los documentos proporcionados y generar un JSON en español con este formato exacto:
        
        {
          "conclusion_estado": "approved|in_review|rejected",
          "resumen": "texto breve profesional",
          "observaciones": ["...","..."],
          "faltantes": [],
          "campos": {
            "tecnico_nombre": "{$technician['full_name']}",
            "tecnico_email": "{$technician['email']}",
            "profesion": "{$technician['professions']}",
            "valido_desde": "{$validFrom}",
            "valido_hasta": "{$validHasta}"
          }
        }
        
        CRITERIOS DE ESTADO:
        1. "approved"  → Todos los documentos presentes son legibles, coherentes y vigentes. Sin observaciones críticas.
        2. "in_review" → Los documentos existen pero hay dudas de vigencia, legibilidad parcial o datos inconsistentes.
        3. "rejected"  → Documentos claramente caducados, ilegibles en su totalidad o con datos contradictorios graves.
        
        REGLAS:
        - No uses "pending_docs" — ese estado lo gestiona el sistema automáticamente.
        - No inventes datos que no aparezcan en los documentos.
        - Las fechas valido_desde ({$validFrom}) y valido_hasta ({$validHasta}) están fijas; devuélvelas exactamente.
        - El nombre, email y profesión del técnico ya están dados; devuélvelos exactamente.
        - "faltantes" déjalo siempre como array vacío [].
        - Responde SOLO JSON válido, sin texto adicional ni bloques markdown.
        
        Datos del técnico:
        - Nombre: {$technician['full_name']}
        - Email: {$technician['email']}
        - Profesión: {$technician['professions']}
        Notas admin: {$extraNotes}
        
        Documentos analizados:
        """
        PROMPT;

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'Especialista en CAE y cumplimiento documental.'],
                ['role' => 'user', 'content' => $prompt . "\n" . implode("\n\n----\n\n", $sourceText)],
            ],
            'temperature' => 0.2,
        ];

        if ($apiKey === '') {
            return self::fallbackDraft($technician, 'API key IA no configurada.');
        }

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 45,
        ]);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if (!is_string($raw) || $raw === '' || $err !== '') {
            return self::fallbackDraft($technician, $err !== '' ? $err : 'Sin respuesta IA');
        }

        $resp = json_decode($raw, true);
        $content = (string) ($resp['choices'][0]['message']['content'] ?? '');

        // GPT often wraps the JSON in markdown code fences (```json ... ```)
        // Extract the first JSON object found regardless of surrounding text
        if (preg_match('/\{.*\}/s', $content, $m)) {
            $content = $m[0];
        }

        $json = json_decode($content, true);
        if (!is_array($json)) {
            error_log('[CaeAiService] Respuesta no parseable. Content: ' . substr($content, 0, 500));
            return self::fallbackDraft($technician, 'Respuesta IA no parseable.');
        }

        return $json;
    }

    /** @param array<string,mixed> $technician */
    private static function fallbackDraft(array $technician, string $reason): array
    {
        $today = date('Y-m-d');
        $plus3 = date('Y-m-d', strtotime('+3 months'));

        return [
            'conclusion_estado' => 'in_review',
            'resumen' => 'Borrador generado en modo de contingencia. Revisar documentación manualmente.',
            'observaciones' => ['Validación manual recomendada.'],
            'faltantes' => ['Revisión documental por administrador.'],
            'campos' => [
                'tecnico_nombre' => (string) ($technician['full_name'] ?? ''),
                'tecnico_email' => (string) ($technician['email'] ?? ''),
                'profesion' => (string) ($technician['professions'] ?? ''),
                'valido_desde' => $today,
                'valido_hasta' => $plus3,
            ],
            '_fallback_reason' => $reason,
        ];
    }
}