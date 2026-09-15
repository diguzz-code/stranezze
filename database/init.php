<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$dataDirectory = $root . DIRECTORY_SEPARATOR . 'data';
$databasePath = $dataDirectory . DIRECTORY_SEPARATOR . 'stranezze.sqlite';

if (!is_dir($dataDirectory) && !mkdir($dataDirectory, 0700, true) && !is_dir($dataDirectory)) {
    fwrite(STDERR, "Impossibile creare la cartella data.\n");
    exit(1);
}

$pdo = new PDO('sqlite:' . $databasePath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('PRAGMA foreign_keys = ON');
$schema = file_get_contents($root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql');
if ($schema === false) {
    fwrite(STDERR, "Schema SQL non trovato.\n");
    exit(1);
}
$pdo->exec($schema);
fwrite(STDOUT, "Database pronto: {$databasePath}\n");
