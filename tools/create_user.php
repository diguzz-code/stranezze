#!/usr/bin/env php
<?php
declare(strict_types=1);

use Stranezze\Infrastructure\UserRepository;
use Stranezze\Infrastructure\DatabaseFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

function usage(): void
{
    fwrite(STDERR, "Uso: php tools/create_user.php <username> <password> [--role=admin|user]\n");
}

function inputError(string $message): never
{
    fwrite(STDERR, "Errore: {$message}\n");
    usage();
    exit(1);
}

$arguments = array_slice($argv, 1);
if (count($arguments) < 2 || count($arguments) > 3) {
    usage();
    exit(1);
}

$username = $arguments[0];
$password = $arguments[1];
$role = 'user';

if (isset($arguments[2])) {
    if (!str_starts_with($arguments[2], '--role=')) {
        inputError('opzione non valida.');
    }

    $role = substr($arguments[2], strlen('--role='));
}

if ($username === '' || strlen($username) > 80) {
    inputError('username non valido. Deve contenere da 1 a 80 caratteri.');
}

if (strlen($password) < 8) {
    inputError('password troppo corta. Deve contenere almeno 8 caratteri.');
}

if (!in_array($role, ['admin', 'user'], true)) {
    inputError('role non valido. Usa admin oppure user.');
}

$databasePath = getenv('STRANEZZE_DB_PATH') ?: dirname(__DIR__) . '/data/stranezze.sqlite';

try {
    if (!is_file($databasePath)) {
        throw new RuntimeException('Database non inizializzato. Esegui prima le migration.');
    }

    $pdo = DatabaseFactory::create($databasePath);

    $repository = new UserRepository($pdo);
    if ($repository->findByUsername($username) !== null) {
        inputError('username già esistente.');
    }

    $user = $repository->create($username, password_hash($password, PASSWORD_DEFAULT), $role);
    printf("Utente creato: id=%d, username=%s, role=%s\n", $user->id, $user->username, $user->role);
} catch (Throwable $error) {
    fwrite(STDERR, "Errore database: impossibile creare l'utente.\n");
    exit(2);
}
