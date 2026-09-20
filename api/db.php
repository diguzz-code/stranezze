<?php
declare(strict_types=1);

use Stranezze\Infrastructure\DatabaseFactory;

require_once dirname(__DIR__) . '/vendor/autoload.php';

function database(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $path = getenv('STRANEZZE_DB_PATH') ?: dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'stranezze.sqlite';
    if (!is_file($path)) {
        throw new RuntimeException('Database non inizializzato. Esegui: php -c php.ini database/init.php');
    }

    return $pdo = DatabaseFactory::create($path);
}
