<?php

namespace Thathoff\KirbyMigrations;

$migrator = new Migrator($kirby);

$pending = $migrator->getStatus('pending');

if (!count($pending)) {
    echo "No new migrations available.\n";
    exit(0);
}

$force = in_array('-f', $argv);

echo "The following migrations will be applied:\n";
echo "  " . implode("\n  ", $pending) . "\n\n";

if (!$force) {
    $result = readline("Are you sure you want to continue? [y/N] ");

    if (!$result || strtolower($result) == 'n') {
        echo "Aborting.\n";
        exit(0);
    }
}

$migrator->applyPendingMigrations();
echo "Applied " . count($pending) . " migrations.\n";
