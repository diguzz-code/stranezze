<?php
declare(strict_types=1);

$databasePath = getenv('STRANEZZE_DB_PATH') ?: __DIR__ . '/data/stranezze.sqlite';

return [
    'paths' => [
        'migrations' => __DIR__ . '/migrations',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'development' => [
            'adapter' => 'sqlite',
            'name' => $databasePath,
        ],
        'production' => [
            'adapter' => 'sqlite',
            'name' => $databasePath,
        ],
    ],
];
