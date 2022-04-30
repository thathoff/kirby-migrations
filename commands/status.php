<?php

namespace Thathoff\KirbyMigrations;

$migrator = new Migrator($kirby);

$status = $migrator->getStatus();

echo "KIRBY MIGRATIONS STATUS\n\n";
foreach ($status as $key => $value) {
    echo strtoupper(str_replace("_", " ", $key)) . "\n";

    if (!count($value)) {
        echo "  No migrations found.\n\n";
        continue;
    }

    echo "  " . implode("\n  ", $value) . "\n\n";
}
