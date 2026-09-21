<?php
declare(strict_types=1);

namespace Stranezze\Http\Middleware;

final class SecurityHeadersMiddleware
{
    public function apply(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");

        if ($this->isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    private function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        $trustedProxies = array_filter(array_map('trim', explode(',', (string)getenv('TRUSTED_PROXIES'))));
        $remoteAddress = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        if ($remoteAddress === '' || !in_array($remoteAddress, $trustedProxies, true)) {
            return false;
        }

        return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }
}