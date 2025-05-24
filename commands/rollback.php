<?php

namespace Thathoff\KirbyMigrations;

use Kirby\CLI\CLI;

return [
    'description' => 'Rollback the last batch of migrations',
    'args' => [
        'migration' => [
            'description' => 'Only roll back this specific migration.',
            'required' => false,
        ],
        'force' => [
            'description' => 'Run the migrations without asking for confirmation.',
            'prefix' => 'f',
            'longPrefix' => 'force',
            'required' => false,
            'noValue' => true,
        ],
    ],
    'command' => static function (CLI $cli): void {
        $migrator = new Migrator($cli);
        $migrationName = $cli->arg('migration');

        $migrationsToRollback = $migrationName?
            [$migrationName] :
            $migrator->getStatus('last_batch');

        if (!count($migrationsToRollback)) {
            $cli->error('No migrations found to rollback.');
            return;
        }

        $cli->red()->bold('The following migration(s) will be rolled back:');
        $cli->out('  ' . implode("\n  ", $migrationsToRollback));
        $cli->nl();

        if (!$cli->arg('force')) {
            $result = $cli->confirm('Are you sure you want to continue?');
            if (!$result->confirmed()) {
                $cli->error('Aborting.');
                return;
            }
        }

        $rolledBackMigrations = $migrator->rollbackMigrations($migrationsToRollback);
        $cli->success('Rolled back ' . count($rolledBackMigrations) . ' migration(s).');
    }
];
