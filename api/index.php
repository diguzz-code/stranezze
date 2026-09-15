<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

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
    $pdo = database();
    if ($method === 'GET') {
        $query = trim((string)($_GET['q'] ?? ''));
        $statement = $query === ''
            ? $pdo->query('SELECT * FROM observations ORDER BY observed_on DESC, id DESC')
            : $pdo->prepare('SELECT * FROM observations WHERE title LIKE :query OR content LIKE :query OR place LIKE :query ORDER BY observed_on DESC, id DESC');
        if ($query !== '') {
            $statement->execute(['query' => '%' . $query . '%']);
        }
        respond(['items' => $statement->fetchAll()]);
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
