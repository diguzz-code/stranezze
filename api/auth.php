<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function csrfToken(): string
{
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function requireAuth(): void
{
    startSecureSession();
    if (empty($_SESSION['authenticated'])) {
        respond(['error' => 'Autenticazione richiesta.'], 401);
    }
}

function requireCsrf(): void
{
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrfToken(), $provided)) {
        respond(['error' => 'Token di sicurezza non valido.'], 419);
    }
}

function login(string $password): bool
{
    $config = appConfig();
    if (!password_verify($password, $config['password_hash'])) {
        return false;
    }

    startSecureSession();
    session_regenerate_id(true);
    $_SESSION['authenticated'] = true;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return true;
}

function logout(): void
{
    startSecureSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], '', (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
}
