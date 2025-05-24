<?php

namespace Thathoff\KirbyMigrations;

namespace Thathoff\KirbyMigrations;

use Kirby\CLI\CLI;

return [
    'description' => 'Show the status of the migrations',
    'args' => [],
    'command' => static function (CLI $cli): void {
        $migrator = new Migrator($cli);

        $status = $migrator->getStatus();

        $cli->bold()->underline('Kirby Migrations Status')->nl();

        foreach ($status as $key => $value) {
            $cli->bold(ucwords(str_replace('_', ' ', $key)) . ':');

            if (!count($value)) {
                $cli->green('  No migrations found.')->nl();
                continue;
            }

            $cli->out('  ' . implode("\n  ", $value));
            $cli->nl();
        }
    }
];
