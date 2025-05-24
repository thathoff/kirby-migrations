<?php

namespace Thathoff\KirbyMigrations;

use Kirby\CLI\CLI;
use Kirby\Cms\App;
use ReflectionClass;

abstract class Migration
{
    protected App $kirby;
    protected CLI $cli;

    public function __construct(App $kirby, CLI $cli)
    {
        $this->kirby = $kirby;
        $this->cli = $cli;
    }

    public function getName(): string
    {
        return (new ReflectionClass($this))->getShortName();
    }

    abstract public function up(): void;

    public function down(): void
    {
        // this method is optional so we implement it as empty
    }
}
