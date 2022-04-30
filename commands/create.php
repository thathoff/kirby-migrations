<?php

namespace Thathoff\KirbyMigrations;

$migrator = new Migrator($kirby);

$name = $argv[0] ?? null;
$filename = $migrator->create($name);

echo "Create migration: $filename\n";
