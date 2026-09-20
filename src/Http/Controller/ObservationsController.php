<?php
declare(strict_types=1);

namespace Stranezze\Http\Controller;

use DateTime;
use PDO;
use Stranezze\Http\Request;
use Stranezze\Http\Response;

final class ObservationsController
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function index(Request $request): never
    {
        $query = trim((string)$request->query('q', ''));
        $category = trim((string)$request->query('category', ''));
        $favorite = $request->query('favorite', '') === '1';
        $page = max(1, (int)$request->query('page', 1));
        $perPage = min(50, max(5, (int)$request->query('per_page', 10)));
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
        $totalStatement = $this->pdo->prepare('SELECT COUNT(*) FROM observations' . $where);
        $totalStatement->execute($parameters);
        $total = (int)$totalStatement->fetchColumn();
        $statement = $this->pdo->prepare('SELECT * FROM observations' . $where . ' ORDER BY observed_on DESC, id DESC LIMIT :limit OFFSET :offset');
        foreach ($parameters as $key => $value) {
            $statement->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();
        Response::json(['items' => $statement->fetchAll(), 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'pages' => max(1, (int)ceil($total / $perPage))]]);
    }

    public function create(Request $request): never
    {
        [$title, $content, $category, $observedOn, $place, $favorite] = $this->validateData($request->input());
        $statement = $this->pdo->prepare('INSERT INTO observations (title, content, category, observed_on, place, is_favorite) VALUES (:title, :content, :category, :observed_on, :place, :is_favorite)');
        $statement->execute([
            'title' => $title,
            'content' => $content,
            'category' => $category,
            'observed_on' => $observedOn,
            'place' => $place,
            'is_favorite' => $favorite,
        ]);
        Response::json(['item' => (int)$this->pdo->lastInsertId()], 201);
    }

    public function update(Request $request): never
    {
        [$title, $content, $category, $observedOn, $place, $favorite] = $this->validateData($request->input());
        $id = $request->id();
        if (!$id) {
            Response::json(['error' => 'Id mancante.'], 400);
        }
        $statement = $this->pdo->prepare('UPDATE observations SET title = :title, content = :content, category = :category, observed_on = :observed_on, place = :place, is_favorite = :is_favorite, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $statement->execute(['title' => $title, 'content' => $content, 'category' => $category, 'observed_on' => $observedOn, 'place' => $place, 'is_favorite' => $favorite, 'id' => $id]);
        if ($statement->rowCount() === 0) {
            Response::json(['error' => 'Osservazione non trovata.'], 404);
        }
        Response::json(['ok' => true]);
    }

    public function delete(Request $request): never
    {
        $id = $request->id();
        if (!$id) {
            Response::json(['error' => 'Id mancante.'], 400);
        }
        $statement = $this->pdo->prepare('DELETE FROM observations WHERE id = :id');
        $statement->execute(['id' => $id]);
        if ($statement->rowCount() === 0) {
            Response::json(['error' => 'Osservazione non trovata.'], 404);
        }
        Response::json(['ok' => true]);
    }

    /** @param array<string, mixed> $data @return array{0: string, 1: string, 2: string, 3: string, 4: string, 5: int} */
    private function validateData(array $data): array
    {
        $categories = ['quotidiana', 'natura', 'persone', 'tecnologia', 'altro'];
        $title = trim((string)($data['title'] ?? ''));
        $content = trim((string)($data['content'] ?? ''));
        $category = (string)($data['category'] ?? '');
        $observedOn = (string)($data['observed_on'] ?? '');
        $place = trim((string)($data['place'] ?? ''));
        $favorite = filter_var($data['is_favorite'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($title === '' || strlen($title) > 80 || $content === '' || strlen($content) > 500) {
            Response::json(['error' => 'Titolo e testo sono obbligatori e rispettano i limiti previsti.'], 422);
        }
        if (!in_array($category, $categories, true) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $observedOn)) {
            Response::json(['error' => 'Categoria o data non valide.'], 422);
        }
        $date = DateTime::createFromFormat('!Y-m-d', $observedOn);
        if (!$date || $date->format('Y-m-d') !== $observedOn || $observedOn > date('Y-m-d')) {
            Response::json(['error' => 'La data deve essere valida e non futura.'], 422);
        }
        if (strlen($place) > 80 || $favorite === null) {
            Response::json(['error' => 'Luogo o preferito non validi.'], 422);
        }
        return [$title, $content, $category, $observedOn, $place, $favorite ? 1 : 0];
    }
}