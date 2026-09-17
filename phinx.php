<?php
declare(strict_types=1);

$databasePath = getenv('STRANEZZE_DB_PATH') ?: __DIR__ . '/data/stranezze.sqlite';
$customAdapter = \Stranezze\Infrastructure\Phinx\SQLiteAdapter::class;
\Phinx\Db\Adapter\AdapterFactory::instance()->registerAdapter($customAdapter, $customAdapter);

return [
    'paths' => [
        'migrations' => __DIR__ . '/migrations',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'development' => [
            'adapter' => $customAdapter,
            'name' => $databasePath,
        ],
        'production' => [
            'adapter' => $customAdapter,
            'name' => $databasePath,
        ],
    ],
];
