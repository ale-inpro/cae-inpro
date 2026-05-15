<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Services\AeatCotejoInternetService;

final class AeatCotejoTestController extends Controller
{
    /** @param array<string, string> $params */
    public function probe(array $params = []): void
    {
        $this->requireAuth();
        $this->requireAdmin();

        $cfg = $this->appConfig();
        if (empty($cfg['aeat_cotejo_debug_enabled'])) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Prueba AEAT desactivada (aeat_cotejo_debug_enabled).';
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => 'Usa POST JSON: {"csv":"16CHARSXXXXXXXX", "eni": false}',
                'hint' => 'Ejemplo del manual AEAT (entorno de pruebas): 8SAXDBA76DD26B9J',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $rawIn = file_get_contents('php://input') ?: '';
        $json = json_decode($rawIn, true);
        if (!is_array($json)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'JSON inválido'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $csv = (string) ($json['csv'] ?? '');
        $eni = !empty($json['eni']);

        $svc = new AeatCotejoInternetService();
        $result = $svc->cotejar($csv, $eni, [
            'endpoint' => (string) ($cfg['aeat_cotejo_endpoint'] ?? ''),
            'client_cert_path' => (string) ($cfg['aeat_cotejo_client_cert_path'] ?? ''),
            'client_cert_password' => (string) ($cfg['aeat_cotejo_client_cert_password'] ?? ''),
            'ca_bundle' => (string) ($cfg['aeat_cotejo_ca_bundle'] ?? ''),
            'use_mock' => !empty($cfg['aeat_cotejo_use_mock']),
            'mock_scenario' => (string) ($cfg['aeat_cotejo_mock_scenario'] ?? 'success'),
            'mock_sha1_for_file' => '',
        ]);

        // No devolver el XML completo ni PDF gigante en JSON por defecto
        if (isset($result['raw_response'])) {
            unset($result['raw_response']);
        }
        if (isset($result['binario_base64']) && strlen((string) $result['binario_base64']) > 200) {
            $result['binario_base64'] = '[omitido, ' . strlen((string) $result['binario_base64']) . ' chars]';
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function requireAdmin(): void
    {
        if (($_SESSION['user']['role'] ?? '') !== 'admin') {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Acceso denegado';
            exit;
        }
    }
}