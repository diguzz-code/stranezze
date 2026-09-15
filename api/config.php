<?php
declare(strict_types=1);

function appConfig(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $config = [
        'password_hash' => getenv('STRANEZZE_PASSWORD_HASH') ?: '',
        'environment' => getenv('APP_ENV') ?: 'production',
        'session_name' => 'stranezze_session',
    ];

    if ($config['password_hash'] === '') {
        throw new RuntimeException('Configurazione mancante: imposta STRANEZZE_PASSWORD_HASH.');
    }

    return $config;
}

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name(appConfig()['session_name']);
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $isHttps,
        'samesite' => 'Strict',
        'path' => '/',
    ]);
    session_start();
}
