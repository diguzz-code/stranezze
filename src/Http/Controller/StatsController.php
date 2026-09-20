<?php
declare(strict_types=1);

namespace Stranezze\Http\Controller;

use PDO;
use Stranezze\Http\Request;
use Stranezze\Http\Response;

final class StatsController
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function index(Request $request): never
    {
        $total = (int)$this->pdo->query('SELECT COUNT(*) FROM observations')->fetchColumn();
        $favorites = (int)$this->pdo->query('SELECT COUNT(*) FROM observations WHERE is_favorite = 1')->fetchColumn();
        $categories = $this->pdo->query('SELECT category, COUNT(*) AS amount FROM observations GROUP BY category ORDER BY amount DESC')->fetchAll();
        Response::json(['total' => $total, 'favorites' => $favorites, 'categories' => $categories]);
    }
}