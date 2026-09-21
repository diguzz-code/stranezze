<?php
declare(strict_types=1);

namespace Stranezze\Http\Controller;

use PDO;
use Stranezze\Infrastructure\RateLimiter;
use Stranezze\Http\Request;
use Stranezze\Http\Response;

final class AuthController
{
    private readonly RateLimiter $rateLimiter;

    public function __construct(private readonly PDO $pdo)
    {
        $this->rateLimiter = new RateLimiter($pdo);
    }

    public function login(Request $request): never
    {
        $data = $request->input();
        $username = trim((string)($data['username'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $retryAfter = $this->rateLimiter->retryAfter($ip, $username);
        if ($retryAfter > 0) {
            header('Retry-After: ' . $retryAfter);
            Response::json(['error' => 'Troppi tentativi. Riprova più tardi.'], 429);
        }

        if ($username === '' || $password === '' || !login($username, $password)) {
            $this->rateLimiter->recordFailure($ip, $username === '' ? 'unknown' : $username);
            Response::json(['error' => 'Credenziali non valide.'], 401);
        }

        $this->rateLimiter->recordSuccess($ip, $username);
        Response::json(['authenticated' => true, 'csrf_token' => csrfToken()]);
    }

    public function logout(Request $request): never
    {
        logout();
        Response::json(['authenticated' => false]);
    }

    public function session(Request $request): never
    {
        startSecureSession();
        Response::json([
            'authenticated' => !empty($_SESSION['authenticated']),
            'csrf_token' => !empty($_SESSION['authenticated']) ? csrfToken() : null,
        ]);
    }
}