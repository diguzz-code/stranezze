<?php
declare(strict_types=1);

namespace Stranezze\Http;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Stranezze\Http\Middleware\AuthMiddleware;
use Stranezze\Http\Middleware\SecurityHeadersMiddleware;
use Throwable;

final class Router
{
    /** @param array<string, object> $controllers */
    public function __construct(
        private readonly Request $request,
        private readonly array $controllers,
        private readonly AuthMiddleware $authMiddleware,
        private readonly SecurityHeadersMiddleware $securityHeadersMiddleware = new SecurityHeadersMiddleware(),
    ) {
    }

    public function dispatch(): never
    {
        $this->securityHeadersMiddleware->apply();
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
                Response::json(['error' => 'Pagina non trovata.'], 404);
            }
            if ($result[0] === Dispatcher::METHOD_NOT_ALLOWED) {
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
            error_log($error->getMessage());
            Response::json(['error' => 'Errore interno.'], 500);
        }
    }
}