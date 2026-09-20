<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

use Stranezze\Http\Controller\AuthController;
use Stranezze\Http\Controller\ExportController;
use Stranezze\Http\Controller\ObservationsController;
use Stranezze\Http\Controller\StatsController;
use Stranezze\Http\Middleware\AuthMiddleware;
use Stranezze\Http\Request;
use Stranezze\Http\Response;
use Stranezze\Http\Router;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond(array $payload, int $status = 200): never
{
    Response::json($payload, $status);
}

$pdo = database();
$controllers = [
    'AuthController' => new AuthController($pdo),
    'ObservationsController' => new ObservationsController($pdo),
    'StatsController' => new StatsController($pdo),
    'ExportController' => new ExportController($pdo),
];

(new Router(new Request(), $controllers, new AuthMiddleware()))->dispatch();
