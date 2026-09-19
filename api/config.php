<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

function appConfig(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $config = [
        'environment' => getenv('APP_ENV') ?: 'production',
        'session_name' => 'stranezze_session',
    ];

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
