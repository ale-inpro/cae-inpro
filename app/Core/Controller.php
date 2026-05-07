<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    /**
     * Render a view inside a layout.
     *
     * @param array<string, mixed> $data
     */
    protected function render(string $view, array $data = [], string $layout = 'layouts.app'): void
    {
        $viewFile = $this->viewPath($view);
        $layoutFile = $this->viewPath($layout);

        if (!is_file($viewFile)) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo "View not found: {$view}";
            return;
        }

        if (!is_file($layoutFile)) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo "Layout not found: {$layout}";
            return;
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();

        require $layoutFile;
    }

    /**
     * Render a partial file.
     *
     * @param array<string, mixed> $data
     */
    protected function partial(string $partial, array $data = []): void
    {
        $partialFile = $this->viewPath($partial);

        if (!is_file($partialFile)) {
            echo "<!-- Partial not found: {$partial} -->";
            return;
        }

        extract($data, EXTR_SKIP);
        require $partialFile;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function respond(string $message, array $data = []): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo $message;

        if ($data !== []) {
            echo PHP_EOL . PHP_EOL . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
    }

    private function viewPath(string $view): string
    {
        return __DIR__ . '/../../resources/views/' . str_replace('.', '/', $view) . '.php';
    }

    protected function flash(string $message, string $type = 'info', string $title = 'Aviso'): void
    {
        $_SESSION['flash'] = [
            'message' => $message,
            'type' => $type,
            'title' => $title,
        ];
    }

    /** @return array<string, mixed> */
    protected function appConfig(): array
    {
        static $cfg = null;
        if ($cfg === null) {
            $cfg = require dirname(__DIR__, 2) . '/config/app.php';
        }
        return $cfg;
    }

    protected function baseUrl(): string
    {
        return rtrim((string) ($this->appConfig()['url'] ?? ''), '/');
    }

    protected function currentArea(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return str_contains($uri, '/admin/') ? 'admin' : 'gestor';
    }

    protected function areaBaseUrl(): string
    {
        return $this->baseUrl() . '/' . $this->currentArea();
    }

    protected function requireAuth(): void
    {
        if (empty($_SESSION['user']['id'])) {
            header('Location: ' . $this->baseUrl() . '/login');
            exit;
        }
    }

    /**
     * Rutas bajo /gestor/* y /admin/*: cada rol solo usa su prefijo.
     * Admin que entre en /gestor/* → mismo path bajo /admin/*.
     */
    protected function assertAreaAccess(): void
    {
        $this->requireAuth();

        $role = (string) ($_SESSION['user']['role'] ?? '');
        $base = $this->baseUrl();
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/';

        $rel = $path;
        if ($base !== '' && str_starts_with($path, $base)) {
            $rel = substr($path, strlen($base)) ?: '/';
        }
        if ($rel === '') {
            $rel = '/';
        }

        if (!str_starts_with($rel, '/gestor') && !str_starts_with($rel, '/admin')) {
            return;
        }

        if ($role === 'admin' && str_starts_with($rel, '/gestor')) {
            $suffix = substr($rel, strlen('/gestor'));
            if ($suffix === '' || $suffix === '/') {
                $suffix = '/dashboard';
            }
            header('Location: ' . $base . '/admin' . $suffix);
            exit;
        }

        if ($role === 'gestor' && str_starts_with($rel, '/admin')) {
            $this->flash('No tienes permiso para el panel de administración.', 'warning', 'Acceso');
            header('Location: ' . $base . '/gestor/dashboard');
            exit;
        }
    }

        /**
     * Convierte un valor booleano de PostgreSQL a PHP bool.
     * PDO/pgsql devuelve TRUE como 't' o '1' y FALSE como 'f' o ''.
     */
    protected function boolFromPg(mixed $val): bool
    {
        if (is_bool($val)) {
            return $val;
        }
        return in_array($val, ['t', '1', 'true', 'yes', 'on'], true);
    }

    /**
     * Inserta una notificación en la tabla para un usuario.
     * @param array<string, mixed>|null $payload
     */
    protected function createNotification(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?array $payload = null
    ): void {
        $pdo = \App\Core\Database::connection();
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, payload_json, is_read, created_at)
            VALUES (:uid, :type, :title, :message, CAST(:payload AS jsonb), FALSE, NOW())
        ");
        $stmt->execute([
            'uid'     => $userId,
            'type'    => $type,
            'title'   => $title,
            'message' => $message,
            'payload' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }
}