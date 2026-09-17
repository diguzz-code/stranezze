<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

if (!class_exists(\Stranezze\Support\AutoloadProbe::class)) {
    fwrite(STDERR, "Autoload PSR-4 non funzionante.\n");
    exit(1);
}

echo "Autoload PSR-4 funzionante.\n";
