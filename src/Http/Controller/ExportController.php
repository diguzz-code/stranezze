<?php
declare(strict_types=1);

namespace Stranezze\Http\Controller;

use PDO;
use Stranezze\Http\Request;
use Stranezze\Http\Response;

final class ExportController
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function download(Request $request): never
    {
        $items = $this->pdo->query('SELECT title, content, category, observed_on, place, is_favorite, created_at FROM observations ORDER BY observed_on DESC, id DESC')->fetchAll();
        Response::csv('stranezze-export.csv', ['Titolo', 'Testo', 'Categoria', 'Data', 'Luogo', 'Preferita', 'Creata'], $items);
    }
}