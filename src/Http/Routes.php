<?php
declare(strict_types=1);

namespace Stranezze\Http;

final class Routes
{
    /** @return list<string> */
    public static function basePaths(): array
    {
        return ['/api', '/api/v1'];
    }

    /** @return array<string, array<string, mixed>> */
    public static function definitions(): array
    {
        return [
            'api' => [
                'path' => '',
                'methods' => ['GET', 'POST', 'PUT', 'DELETE'],
                'actions' => [
                    'GET:session' => ['AuthController', 'session'],
                    'GET:stats' => ['StatsController', 'index'],
                    'GET:export' => ['ExportController', 'download'],
                    'GET:' => ['ObservationsController', 'index'],
                    'POST:login' => ['AuthController', 'login'],
                    'POST:logout' => ['AuthController', 'logout'],
                    'POST:' => ['ObservationsController', 'create'],
                    'PUT:' => ['ObservationsController', 'update'],
                    'DELETE:' => ['ObservationsController', 'delete'],
                ],
                'middleware' => [
                    'GET:session' => [],
                    'GET:stats' => ['auth'],
                    'GET:export' => ['auth'],
                    'GET:' => ['auth'],
                    'POST:login' => [],
                    'POST:logout' => ['auth', 'csrf'],
                    'POST:' => ['auth', 'csrf'],
                    'PUT:' => ['auth', 'csrf'],
                    'DELETE:' => ['auth', 'csrf'],
                ],
            ],
        ];
    }

    /** @return array{0: string, 1: string, 2: list<string>} */
    public static function resolve(string $route, Request $request): array
    {
        $definition = self::definitions()[$route] ?? null;
        if ($definition === null) {
            Response::json(['error' => 'Pagina non trovata.'], 404);
        }
        $action = (string)$request->query('action', '');
        $key = $request->method() . ':' . $action;
        $fallbackKey = $request->method() . ':';
        $handler = $definition['actions'][$key] ?? $definition['actions'][$fallbackKey] ?? null;
        if ($handler === null) {
            Response::json(['error' => 'Metodo non consentito.'], 405);
        }

        return [$handler[0], $handler[1], $definition['middleware'][$key] ?? $definition['middleware'][$fallbackKey] ?? []];
    }
}