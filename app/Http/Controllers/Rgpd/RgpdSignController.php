<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rgpd;

use App\Core\Controller;
use App\Core\Database;
use PDO;

final class RgpdSignController extends Controller
{
    /** @param array<string, string> $params */
    public function show(array $params = []): void
    {
        $token = trim((string) ($params['token'] ?? ''));
        [$state, $data] = $this->resolveToken($token);
        $this->renderSign($state, array_merge($data, ['token' => $token]));
    }

    /** @param array<string, string> $params */
    public function submit(array $params = []): void
    {
        $token = trim((string) ($params['token'] ?? ''));
        [$state, $data] = $this->resolveToken($token);

        if ($state !== 'form') {
            $this->renderSign($state, array_merge($data, ['token' => $token]));
            return;
        }

        if (empty($_POST['accept_terms'])) {
            $data['error'] = 'Debe aceptar el documento para continuar.';
            $this->renderSign('form', array_merge($data, ['token' => $token]));
            return;
        }

        $signatureData = trim((string) ($_POST['signature_data'] ?? ''));
        if ($signatureData === '' || !str_starts_with($signatureData, 'data:image/png;base64,')) {
            $data['error'] = 'Debe dibujar su firma en el recuadro.';
            $this->renderSign('form', array_merge($data, ['token' => $token]));
            return;
        }

        $raw = base64_decode(substr($signatureData, strlen('data:image/png;base64,')), true);
        if ($raw === false || strlen($raw) < 100) {
            $data['error'] = 'Firma no válida. Inténtelo de nuevo.';
            $this->renderSign('form', array_merge($data, ['token' => $token]));
            return;
        }

        $request = $data['request'];
        $communityId = (int) ($request['community_id'] ?? 0);
        $dir = dirname(__DIR__, 4) . '/public/uploads/rgpd-signatures/' . $communityId;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $fileName = 'sig_' . (int) $request['id'] . '_' . bin2hex(random_bytes(4)) . '.png';
        $storagePath = '/uploads/rgpd-signatures/' . $communityId . '/' . $fileName;
        file_put_contents($dir . '/' . $fileName, $raw);

        $pdo = Database::connection();
        $pdo->prepare("
            UPDATE rgpd_signature_requests
            SET status = 'signed',
                signature_image_path = :path,
                signer_ip = :ip,
                signer_user_agent = :ua,
                signed_at = NOW(),
                updated_at = NOW()
            WHERE id = :id AND token = :token
        ")->execute([
            'path' => $storagePath,
            'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            'ua' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            'id' => (int) $request['id'],
            'token' => $token,
        ]);

        $this->renderSign('done', ['token' => $token, 'request' => $request]);
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function resolveToken(string $token): array
    {
        if ($token === '' || strlen($token) < 16) {
            return ['invalid', ['reason' => 'Enlace no válido.']];
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            SELECT s.*,
                TRIM(CONCAT_WS(' ', r.nombre, r.apellidos)) AS resident_name,
                r.email AS resident_email,
                t.name AS template_name, c.name AS community_name
            FROM rgpd_signature_requests s
            JOIN community_residents r ON r.id = s.resident_id
            JOIN rgpd_templates t ON t.id = s.template_id
            JOIN communities c ON c.id = s.community_id
            WHERE s.token = :token
            LIMIT 1
        ");
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['invalid', ['reason' => 'Solicitud no encontrada.']];
        }

        $status = (string) ($row['status'] ?? '');
        if ($status === 'signed') {
            return ['done', ['request' => $row]];
        }
        if ($status === 'paper' || $status === 'cancelled') {
            return ['invalid', ['reason' => 'Esta solicitud ya no admite firma electrónica.']];
        }

        $expires = $row['token_expires_at'] ?? null;
        if ($expires && strtotime((string) $expires) < time()) {
            return ['invalid', ['reason' => 'El enlace ha caducado. Solicite un nuevo envío a su administrador.']];
        }

        return ['form', ['request' => $row]];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderSign(string $state, array $data): void
    {
        $view = match ($state) {
            'done' => 'rgpd.sign.done',
            'invalid' => 'rgpd.sign.invalid',
            default => 'rgpd.sign.form',
        };

        $title = match ($state) {
            'done' => 'Firma registrada',
            'invalid' => 'Enlace no disponible',
            default => 'Firma RGPD',
        };

        $this->render($view, array_merge($data, [
            'title' => $title,
            'baseUrl' => $this->baseUrl(),
        ]), 'layouts.auth');
    }
}
