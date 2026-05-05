<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Database;

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (!empty($_SESSION['user']['id'])) {
            header('Location: ' . $this->baseUrl() . $this->homePathForRole((string) ($_SESSION['user']['role'] ?? '')));
            exit;
        }

        $this->render('auth.login', [
            'title' => 'Login',
            'baseUrl' => $this->baseUrl(),
        ], 'layouts.auth');
    }

    public function login(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $this->flash('Email y contraseña son obligatorios.', 'danger', 'Error');
            header('Location: ' . $this->baseUrl() . '/login');
            exit;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT id, email, full_name, password_hash, role::text AS role, is_active
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || empty($user['password_hash']) || !password_verify($password, (string) $user['password_hash'])) {
            $this->flash('Credenciales incorrectas.', 'danger', 'Error');
            header('Location: ' . $this->baseUrl() . '/login');
            exit;
        }

        if (empty($user['is_active'])) {
            $this->flash('Usuario desactivado.', 'warning', 'Aviso');
            header('Location: ' . $this->baseUrl() . '/login');
            exit;
        }

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'full_name' => (string) ($user['full_name'] ?? ''),
            'role' => (string) $user['role'],
        ];

        $this->flash('Sesión iniciada correctamente.', 'success', 'Bienvenido');
        header('Location: ' . $this->baseUrl() . $this->homePathForRole((string) $user['role']));
        exit;
    }

    public function logout(): void
    {
        $_SESSION['user'] = [];
        unset($_SESSION['user']);
        $this->flash('Has cerrado sesión.', 'info', 'Sesión');
        header('Location: ' . $this->baseUrl() . '/login');
        exit;
    }

    private function homePathForRole(string $role): string
    {
        return $role === 'admin' ? '/admin/dashboard' : '/gestor/dashboard';
    }
}