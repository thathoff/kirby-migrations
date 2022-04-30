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
        'stateFile' => null,
    ],
]);
