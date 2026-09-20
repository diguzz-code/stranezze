<?php
declare(strict_types=1);

use Stranezze\Infrastructure\UserRepository;
use Stranezze\Infrastructure\DatabaseFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

$databasePath = getenv('STRANEZZE_DB_PATH') ?: dirname(__DIR__) . '/data/stranezze.sqlite';
$pdo = DatabaseFactory::create($databasePath);

$repository = new UserRepository($pdo);
$username = 'repository-test-' . bin2hex(random_bytes(4));

$pdo->beginTransaction();
try {
    $created = $repository->create($username, password_hash('test-password', PASSWORD_DEFAULT));
    if ($created->id === null || $created->username !== $username) {
        throw new RuntimeException('Creazione utente fallita.');
    }

    $byUsername = $repository->findByUsername($username);
    $byId = $repository->findById($created->id);
    if ($byUsername?->id !== $created->id || $byId?->username !== $username) {
        throw new RuntimeException('Ricerca utente fallita.');
    }

    $pdo->rollBack();
    echo "UserRepository OK\n";
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}