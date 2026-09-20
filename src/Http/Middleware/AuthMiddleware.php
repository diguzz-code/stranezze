<?php
declare(strict_types=1);

namespace Stranezze\Http\Middleware;

use Stranezze\Http\Request;

final class AuthMiddleware
{
    /** @param callable(): never $next @param list<string> $requirements */
    public function handle(Request $request, array $requirements, callable $next): never
    {
        if (in_array('auth', $requirements, true)) {
            requireAuth();
        }
        if (in_array('csrf', $requirements, true)) {
            requireCsrf();
        }

        $next();
    }
}