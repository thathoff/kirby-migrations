<?php

use Kirby\Cms\App as Kirby;

@include_once __DIR__ . '/vendor/autoload.php';

load([
    'Thathoff\KirbyMigrations\Migrator' => 'src/Migrator.php',
    'Thathoff\KirbyMigrations\Migration' => 'src/Migration.php',
], __DIR__);

Kirby::plugin('thathoff/migrations', [
    'options' => [
        'dir' => null,
        'stateFile' => null
    ],
    'commands' => [
        'migrations' => require __DIR__ . '/commands/status.php',
        'migrations:apply' => require __DIR__ . '/commands/apply.php',
        'migrations:create' => require  __DIR__ . '/commands/create.php',
        'migrations:status' => require __DIR__ . '/commands/status.php',
        'migrations:rollback' => require __DIR__ . '/commands/rollback.php',
    ],
]);
