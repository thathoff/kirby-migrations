<?php

namespace Thathoff\KirbyMigrations;

$migrator = new Migrator($kirby);

$name = $argv[0] ?? null;


if (!$name) {
    echo "Usage: php cli.php create <name>\n";
    exit(1);
}

$filename = $migrator->create($name);

echo "Create migration: $filename\n";
