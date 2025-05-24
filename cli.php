#!/usr/bin/env php
<?php

namespace Thathoff\KirbyMigrations;

use Kirby\Cms\App as Kirby;

// this php is only ment to me executed by the CLI
if (php_sapi_name() !== 'cli') {
    echo "This script is meant to be run via CLI.\n";
    exit(1);
}

// check if we can find kirby
if (!file_exists('kirby/bootstrap.php')) {
    echo "Could not find kirby/bootstrap.php. Please make sure to run this script from the root of your kirby project.\n";
    exit(1);
}

include 'kirby/bootstrap.php';
$kirby = new Kirby();

// get first parameter (command)
$baseCommand = array_shift($argv);

// get second parameter from $argv as the main command
$command = array_shift($argv);

// check if we can load the command and execute
$commandsDir = __DIR__ . '/commands/';
$commandFile =  $commandsDir . $command . '.php';
if ($command && file_exists($commandFile)) {
    require $commandFile;
    exit(0);
}

// command not found, display help
echo "Command not found, available commands:\n";

$commands = glob($commandsDir . '*.php');

if (empty($commands)) {
    echo "No commands found in $commandsDir\n";
    exit(1);
}

foreach ($commands as $command) {
    $command = basename($command, '.php');
    echo "  $command\n";
}
exit(1);
