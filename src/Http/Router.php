<?php
declare(strict_types=1);

namespace Stranezze\Http;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Stranezze\Http\Middleware\AuthMiddleware;
use Stranezze\Http\Middleware\SecurityHeadersMiddleware;
use Stranezze\Infrastructure\Logger;
use Throwable;

final class Router
{
    /** @param array<string, object> $controllers */
    public function __construct(
        private readonly Request $request,
        private readonly array $controllers,
        private readonly AuthMiddleware $authMiddleware,
        private readonly Logger $logger,
        private readonly SecurityHeadersMiddleware $securityHeadersMiddleware = new SecurityHeadersMiddleware(),
    ) {
    }

    public function dispatch(): never
    {
        $this->securityHeadersMiddleware->apply();
        $startedAt = hrtime(true);
        register_shutdown_function(function () use ($startedAt): void {
            $durationMs = (hrtime(true) - $startedAt) / 1_000_000;
            if ($durationMs > 500) {
                $this->logger->warning('Richiesta lenta.', [
                    'uri' => (string)($_SERVER['REQUEST_URI'] ?? '/'),
                    'method' => $this->request->method(),
                    'duration_ms' => round($durationMs, 2),
                ]);
            }
        });
        try {
            $dispatcher = \FastRoute\simpleDispatcher(function (RouteCollector $routes): void {
                foreach (Routes::definitions() as $name => $definition) {
                    foreach ($definition['methods'] as $method) {
                        $routes->addRoute($method, $definition['path'], $name);
                    }
                }
            });
            $result = $dispatcher->dispatch($this->request->method(), $this->request->path());
            if ($result[0] === Dispatcher::NOT_FOUND) {
                $this->logger->warning('Endpoint non trovato.', [
                    'uri' => (string)($_SERVER['REQUEST_URI'] ?? '/'),
                    'method' => $this->request->method(),
                ]);
                Response::json(['error' => 'Pagina non trovata.'], 404);
            }
            if ($result[0] === Dispatcher::METHOD_NOT_ALLOWED) {
                $this->logger->warning('Metodo HTTP non consentito.', [
                    'uri' => (string)($_SERVER['REQUEST_URI'] ?? '/'),
                    'method' => $this->request->method(),
                ]);
                Response::json(['error' => 'Metodo non consentito.'], 405);
            }

            $route = (string)$result[1];
            [$controllerClass, $method, $requirements] = Routes::resolve($route, $this->request);
            $controller = $this->controllers[$controllerClass] ?? null;
            if ($controller === null) {
                throw new \RuntimeException('Controller non configurato: ' . $controllerClass);
            }
            $invoke = fn(): never => $controller->{$method}($this->request);
            $this->authMiddleware->handle($this->request, $requirements, $invoke);
        } catch (Throwable $error) {
            $this->logger->error('Errore interno HTTP.', [
                'uri' => (string)($_SERVER['REQUEST_URI'] ?? '/'),
                'method' => $this->request->method(),
                'exception' => $error,
            ]);
            Response::json(['error' => 'Errore interno.'], 500);
        }
    }
}