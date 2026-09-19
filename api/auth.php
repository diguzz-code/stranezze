<?php
declare(strict_types=1);

use Stranezze\Infrastructure\UserRepository;

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

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

function login(string $username, string $password): bool
{
    $repository = new UserRepository(database());
    $user = $repository->findByUsername($username);
    $dummyPasswordHash = '$2y$12$Tu5VP57Mqeyq9aW8gwjZ4OzMNzwNiirDdva8VqyRSuCoI68PZ4MUa';
    $passwordHash = $user?->passwordHash ?? $dummyPasswordHash;

    if (!password_verify($password, $passwordHash) || $user === null || !$user->isActive) {
        return false;
    }

    startSecureSession();
    session_regenerate_id(true);
    $_SESSION['authenticated'] = true;
    $_SESSION['user_id'] = $user->id;
    $_SESSION['username'] = $user->username;
    $_SESSION['role'] = $user->role;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    try {
        $repository->updateLastLogin($user->id);
    } catch (Throwable $error) {
        error_log('Impossibile aggiornare last_login_at: ' . $error->getMessage());
    }

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
