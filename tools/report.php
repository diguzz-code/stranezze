#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Produce un report read-only dal database Stranezze.
 *
 * @return array{totale: int, preferite: int, per_categoria: array<string, int>}
 */
function buildReport(string $databasePath): array
{
    $connection = new PDO(
        'sqlite:' . $databasePath,
        null,
        null,
        [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY,
        ],
    );

    try {
        $total = (int)$connection
            ->query('SELECT COUNT(*) FROM observations')
            ->fetchColumn();
        $favorites = (int)$connection
            ->query('SELECT COUNT(*) FROM observations WHERE is_favorite = 1')
            ->fetchColumn();

        $categories = [];
        $statement = $connection->query(
            'SELECT category, COUNT(*) AS count '
            . 'FROM observations GROUP BY category ORDER BY category'
        );
        foreach ($statement as $row) {
            $categories[(string)$row['category']] = (int)$row['count'];
        }

        return [
            'totale' => $total,
            'preferite' => $favorites,
            'per_categoria' => $categories,
        ];
    } finally {
        $connection = null;
    }
}

/** @return array{database: string, json: bool} */
function parseArguments(array $arguments): array
{
    $database = null;
    $json = false;

    foreach (array_slice($arguments, 1) as $argument) {
        if ($argument === '--json') {
            $json = true;
            continue;
        }

        if ($database !== null || str_starts_with($argument, '-')) {
            throw new InvalidArgumentException('uso: report.php [database] [--json]');
        }

        $database = $argument;
    }

    return [
        'database' => $database
            ?? (getenv('STRANEZZE_DB_PATH') ?: dirname(__DIR__) . '/data/stranezze.sqlite'),
        'json' => $json,
    ];
}

function main(array $arguments): int
{
    try {
        $options = parseArguments($arguments);
        $databasePath = $options['database'];

        if (!is_file($databasePath) || !is_readable($databasePath)) {
            throw new RuntimeException('database non trovato o non leggibile');
        }

        $report = buildReport($databasePath);

        if ($options['json']) {
            echo json_encode(
                $report,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ) . PHP_EOL;
        } else {
            echo "Osservazioni: {$report['totale']}" . PHP_EOL;
            echo "Preferite: {$report['preferite']}" . PHP_EOL;
            echo "Per categoria:" . PHP_EOL;
            foreach ($report['per_categoria'] as $category => $count) {
                echo "- {$category}: {$count}" . PHP_EOL;
            }
        }

        return 0;
    } catch (Throwable $error) {
        fwrite(STDERR, "Errore: {$error->getMessage()}" . PHP_EOL);
        return 1;
    }
}

exit(main($argv));