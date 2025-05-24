<?php

namespace Thathoff\KirbyMigrations;

use Kirby\Cms\App;
use ReflectionClass;

abstract class Migration
{
    protected App $kirby;

    public function __construct(App $kirby)
    {
        $this->kirby = $kirby;
    }

    public function getName(): string
    {
        return (new ReflectionClass($this))->getShortName();
    }

    abstract public function up(): void;

    public function down(): void
    {
        // this method is optional
    }
}
