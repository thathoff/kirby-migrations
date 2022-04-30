<?php

namespace Thathoff\KirbyMigrations;

use Kirby\Cms\App;
use ReflectionClass;

abstract class Migration
{
    protected $kirby;

    public function __construct(App $kirby)
    {
        $this->kirby = $kirby;
    }

    public function getName(): string
    {
        return (new ReflectionClass($this))->getShortName();
    }

    abstract public function up();

    public function down()
    {
        // this method is optional
    }
}
