<?php
declare(strict_types=1);

namespace Stranezze\Http\Controller;

use PDO;
use Stranezze\Infrastructure\Logger;
use Stranezze\Infrastructure\RateLimiter;
use Stranezze\Infrastructure\UserRepository;
use Stranezze\Http\Request;
use Stranezze\Http\Response;

final class AuthController
{
    private readonly RateLimiter $rateLimiter;
    private readonly UserRepository $userRepository;

    public function __construct(
        private readonly PDO $pdo,
        private readonly Logger $logger,
    )
    {
        $this->rateLimiter = new RateLimiter($pdo, $logger);
        $this->userRepository = new UserRepository($pdo);
    }

    public function login(Request $request): never
    {
        $data = $request->input();
        $username = trim((string)($data['username'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $user = null;
        $retryAfter = $this->rateLimiter->retryAfter($ip, $username);
        if ($retryAfter > 0) {
            header('Retry-After: ' . $retryAfter);
            Response::json(['error' => 'Troppi tentativi. Riprova più tardi.'], 429);
        }

        $failureReason = 'password errata';
        if ($username !== '') {
            try {
                $user = $this->userRepository->findByUsername($username);
                if ($user === null) {
                    $failureReason = 'utente inesistente';
                } elseif (!$user->isActive) {
                    $failureReason = 'utente disattivato';
                }
            } catch (\Throwable $error) {
                $this->logger->error('Errore database durante la diagnosi del login.', [
                    'operation' => 'find login user',
                    'sqlstate' => $error instanceof \PDOException ? $error->errorInfo[0] ?? null : null,
                    'exception' => $error,
                ]);
            }
        }

        if ($username === '' || $password === '' || !login($username, $password)) {
            $this->rateLimiter->recordFailure($ip, $username === '' ? 'unknown' : $username);
            $this->logger->warning('Login fallito.', [
                'ip' => $ip,
                'username' => $username,
                'reason' => $failureReason,
            ]);
            Response::json(['error' => 'Credenziali non valide.'], 401);
        }

        $this->rateLimiter->recordSuccess($ip, $username);
        $userId = $user?->id ?? null;
        $this->logger->info('Login riuscito.', [
            'ip' => $ip,
            'username' => $username,
            'user_id' => $userId,
        ]);
        Response::json(['authenticated' => true, 'csrf_token' => csrfToken()]);
    }

    public function logout(Request $request): never
    {
        $userId = $_SESSION['user_id'] ?? null;
        logout();
        $this->logger->info('Logout eseguito.', ['user_id' => $userId]);
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