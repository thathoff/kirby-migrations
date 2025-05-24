<?php

namespace Thathoff\KirbyMigrations;

class MigrationName extends Migration
{
    /**
     * Update the content or database with changes.
     *
     * Use $this->kirby to access the Kirby instance and $this->cli to access the Kirby CLI instance.
     */
    public function up(): void
    {
        // do something
        $this->cli->success('Migration up');
    }

    /**
     * Rollback your changes here (optional).
     *
     * Use $this->kirby to access the Kirby instance and $this->cli to access the Kirby CLI instance.
     */
    public function down(): void
    {
        // do something
        $this->cli->success('Migration down');
    }
}
