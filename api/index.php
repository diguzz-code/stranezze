<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requestData(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        respond(['error' => 'Corpo JSON mancante.'], 400);
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
        respond(['error' => 'JSON non valido.'], 400);
    }
    return $data;
}

function validateData(array $data): array
{
    $categories = ['quotidiana', 'natura', 'persone', 'tecnologia', 'altro'];
    $title = trim((string)($data['title'] ?? ''));
    $content = trim((string)($data['content'] ?? ''));
    $category = (string)($data['category'] ?? '');
    $observedOn = (string)($data['observed_on'] ?? '');
    $place = trim((string)($data['place'] ?? ''));
    $favorite = filter_var($data['is_favorite'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

    if ($title === '' || strlen($title) > 80 || $content === '' || strlen($content) > 500) {
        respond(['error' => 'Titolo e testo sono obbligatori e rispettano i limiti previsti.'], 422);
    }
    if (!in_array($category, $categories, true) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $observedOn)) {
        respond(['error' => 'Categoria o data non valide.'], 422);
    }
    $date = DateTime::createFromFormat('!Y-m-d', $observedOn);
    if (!$date || $date->format('Y-m-d') !== $observedOn || $observedOn > date('Y-m-d')) {
        respond(['error' => 'La data deve essere valida e non futura.'], 422);
    }
    if (strlen($place) > 80 || $favorite === null) {
        respond(['error' => 'Luogo o preferito non validi.'], 422);
    }
    return [$title, $content, $category, $observedOn, $place, $favorite ? 1 : 0];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

try {
    if ($method === 'POST' && ($_GET['action'] ?? '') === 'login') {
        $data = requestData();
        $password = (string)($data['password'] ?? '');
        if ($password === '' || !login($password)) {
            respond(['error' => 'Password non valida.'], 401);
        }
        respond(['authenticated' => true, 'csrf_token' => csrfToken()]);
    }

    if ($method === 'POST' && ($_GET['action'] ?? '') === 'logout') {
        requireAuth();
        requireCsrf();
        logout();
        respond(['authenticated' => false]);
    }

    if ($method === 'GET' && ($_GET['action'] ?? '') === 'session') {
        startSecureSession();
        respond([
            'authenticated' => !empty($_SESSION['authenticated']),
            'csrf_token' => !empty($_SESSION['authenticated']) ? csrfToken() : null,
        ]);
    }

    requireAuth();
    if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        requireCsrf();
    }

    $pdo = database();
    if ($method === 'GET' && ($_GET['action'] ?? '') === 'stats') {
        $total = (int)$pdo->query('SELECT COUNT(*) FROM observations')->fetchColumn();
        $favorites = (int)$pdo->query('SELECT COUNT(*) FROM observations WHERE is_favorite = 1')->fetchColumn();
        $categories = $pdo->query('SELECT category, COUNT(*) AS amount FROM observations GROUP BY category ORDER BY amount DESC')->fetchAll();
        respond(['total' => $total, 'favorites' => $favorites, 'categories' => $categories]);
    }

    if ($method === 'GET' && ($_GET['action'] ?? '') === 'export') {
        $items = $pdo->query('SELECT title, content, category, observed_on, place, is_favorite, created_at FROM observations ORDER BY observed_on DESC, id DESC')->fetchAll();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="stranezze-export.csv"');
        $output = fopen('php://output', 'wb');
        fputcsv($output, ['Titolo', 'Testo', 'Categoria', 'Data', 'Luogo', 'Preferita', 'Creata']);
        foreach ($items as $item) {
            fputcsv($output, $item);
        }
        fclose($output);
        exit;
    }

    if ($method === 'GET') {
        $query = trim((string)($_GET['q'] ?? ''));
        $category = trim((string)($_GET['category'] ?? ''));
        $favorite = ($_GET['favorite'] ?? '') === '1';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(50, max(5, (int)($_GET['per_page'] ?? 10)));
        $conditions = [];
        $parameters = [];
        if ($query !== '') {
            $conditions[] = '(title LIKE :query OR content LIKE :query OR place LIKE :query)';
            $parameters['query'] = '%' . $query . '%';
        }
        if ($category !== '') {
            $conditions[] = 'category = :category';
            $parameters['category'] = $category;
        }
        if ($favorite) {
            $conditions[] = 'is_favorite = 1';
        }
        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $totalStatement = $pdo->prepare('SELECT COUNT(*) FROM observations' . $where);
        $totalStatement->execute($parameters);
        $total = (int)$totalStatement->fetchColumn();
        $statement = $pdo->prepare('SELECT * FROM observations' . $where . ' ORDER BY observed_on DESC, id DESC LIMIT :limit OFFSET :offset');
        foreach ($parameters as $key => $value) {
            $statement->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();
        respond(['items' => $statement->fetchAll(), 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'pages' => max(1, (int)ceil($total / $perPage))]]);
    }

    if ($method === 'POST' || $method === 'PUT') {
        [$title, $content, $category, $observedOn, $place, $favorite] = validateData(requestData());
        if ($method === 'POST') {
            $statement = $pdo->prepare('INSERT INTO observations (title, content, category, observed_on, place, is_favorite) VALUES (:title, :content, :category, :observed_on, :place, :is_favorite)');
            $statement->execute(['title' => $title, 'content' => $content, 'category' => $category, 'observed_on' => $observedOn, 'place' => $place, 'is_favorite' => $favorite]);
            respond(['item' => (int)$pdo->lastInsertId()], 201);
        }
        if (!$id) {
            respond(['error' => 'Id mancante.'], 400);
        }
        $statement = $pdo->prepare('UPDATE observations SET title = :title, content = :content, category = :category, observed_on = :observed_on, place = :place, is_favorite = :is_favorite, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $statement->execute(['title' => $title, 'content' => $content, 'category' => $category, 'observed_on' => $observedOn, 'place' => $place, 'is_favorite' => $favorite, 'id' => $id]);
        if ($statement->rowCount() === 0) {
            respond(['error' => 'Osservazione non trovata.'], 404);
        }
        respond(['ok' => true]);
    }

    if ($method === 'DELETE') {
        if (!$id) {
            respond(['error' => 'Id mancante.'], 400);
        }
        $statement = $pdo->prepare('DELETE FROM observations WHERE id = :id');
        $statement->execute(['id' => $id]);
        if ($statement->rowCount() === 0) {
            respond(['error' => 'Osservazione non trovata.'], 404);
        }
        respond(['ok' => true]);
    }
    respond(['error' => 'Metodo non consentito.'], 405);
} catch (Throwable $error) {
    error_log($error->getMessage());
    respond(['error' => 'Errore interno.'], 500);
}
