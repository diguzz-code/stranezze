<?php
declare(strict_types=1);

namespace Stranezze\Http\Controller;

use PDO;
use Stranezze\Http\Request;
use Stranezze\Http\Response;

final class AuthController
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function login(Request $request): never
    {
        $data = $request->input();
        $username = trim((string)($data['username'] ?? ''));
        $password = (string)($data['password'] ?? '');
        if ($username === '' || $password === '' || !login($username, $password)) {
            Response::json(['error' => 'Credenziali non valide.'], 401);
        }
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