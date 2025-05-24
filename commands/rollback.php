<?php

namespace Thathoff\KirbyMigrations;

$migrator = new Migrator($kirby);

$lastBatch = $migrator->getStatus('last_batch');

if (!count($lastBatch)) {
    echo "No migrations found to rollback.\n";
    exit(0);
}

$force = in_array('-f', $argv);

echo "The following migrations will be rolled back:\n";
echo '  ' . implode("\n  ", $lastBatch) . "\n\n";

if (!$force) {
    $result = readline('Are you sure you want to continue? [y/N] ');

    if (!$result || strtolower($result) == 'n') {
        echo "Aborting.\n";
        exit(0);
    }
}

$migrator->rollbackMigrations();
echo 'Rolled back ' . count($lastBatch) . " migrations.\n";
