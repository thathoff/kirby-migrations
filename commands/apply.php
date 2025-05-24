<?php

namespace Thathoff\KirbyMigrations;

use Kirby\CLI\CLI;

return [
    'description' => 'Apply all pending migrations',
    'args' => [
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

        $pending = $migrator->getStatus('pending');
        if (!count($pending)) {
            $cli->success('No new migrations available.');
            return;
        }

        $cli->red()->bold('The following migration(s) will be applied:');
        $cli->out('  ' . implode("\n  ", $pending));
        $cli->nl();

        if (!$cli->arg('force')) {
            $result = $cli->confirm('Are you sure you want to continue?');

            if (!$result->confirmed()) {
                $cli->error('Aborting.');
                return;
            }
        }

        $applied = $migrator->applyPendingMigrations();
        $cli->success('Applied ' . count($applied) . ' migration(s).');
    }
];
