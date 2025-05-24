<?php

namespace Thathoff\KirbyMigrations;


namespace Thathoff\KirbyMigrations;

use Kirby\CLI\CLI;

return [
    'description' => 'Create a new migration',
    'args' => [
        'name' => [
            'description' => 'The name of the new migration',
            'required' => true,
        ],
    ],
    'command' => static function (CLI $cli): void {
        $migrator = new Migrator($cli);
        $filename = $migrator->create($cli->arg('name'));
        $cli->success("Created migration: $filename");
    }
];
