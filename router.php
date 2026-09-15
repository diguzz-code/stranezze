<?php
declare(strict_types=1);

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($requestPath === '/api' || str_starts_with($requestPath, '/api/')) {
    require __DIR__ . '/api/index.php';
    exit;
}

$publicPath = __DIR__ . '/public' . ($requestPath === '/' ? '/index.html' : $requestPath);
if (is_file($publicPath)) {
    $mimeTypes = [
        '.css' => 'text/css; charset=utf-8',
        '.js' => 'text/javascript; charset=utf-8',
        '.html' => 'text/html; charset=utf-8',
    ];
    $extension = strtolower((string)pathinfo($publicPath, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($mimeTypes['.' . $extension] ?? 'application/octet-stream'));
    readfile($publicPath);
    exit;
}
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "Pagina non trovata";
