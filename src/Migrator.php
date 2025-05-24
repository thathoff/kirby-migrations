<?php

namespace Thathoff\KirbyMigrations;

use Exception;
use Kirby\Cms\App;

class Migrator
{
    /**
     * The current Kirby instance
     *
     * @var App
     */
    private $kirby;

    /**
     * List of applied migrations
     *
     * @var array
     */
    private $applied;

    /**
     * List of applied migrations
     *
     * @var array
     */
    private $lastBatch;

    /**
     * Create a new migrator instance
     *
     * @param App $kirby
     */
    public function __construct(App $kirby)
    {
        $this->kirby = $kirby;

        // set directory for migrations
        $this->migrationsDir = rtrim($kirby->option('thathoff.migrations.dir', $kirby->root('site') . '/migrations'), '/');

        // set state file from option
        $this->stateFile = $kirby->option('thathoff.migrations.stateFile', $this->migrationsDir . '/.migrations');

        // make sure state file is writeable
        if (!is_writable($this->stateFile) && !is_writable(dirname($this->stateFile))) {
            throw new Exception('The migrations state file is not writable: ' . $this->stateFile);
        }
    }

    /**
     * Get the current state of the migrations
     *
     * @param string $type (optional), any of the following: 'applied', 'missing', 'last_batch', 'pending'
     *
     * @return array
     */
    public function getStatus(?string $type = null)
    {
        $migrations = $this->getMigrations();

        $statusOut = [
            'applied' => [],
            'missing' => [],
            'last_batch' => $this->getLastBatch(),
            'pending' => [],
        ];

        foreach ($migrations as $migrationName) {
            if ($this->isMigrationApplied($migrationName)) {
                $statusOut['applied'][] = $migrationName;
                continue;
            }

            $statusOut['pending'][] = $migrationName;
        }

        foreach ($this->getApplied() as $migrationName) {
            if (in_array($migrationName, $statusOut['applied'])) {
                continue;
            }

            $statusOut['missing'][] = $migrationName;
        }

        if ($type === null) {
            return $statusOut;
        }

        return $statusOut[$type] ?? null;
    }

    /**
     * Create a new migration file
     *
     * @return string path to the new migration file
     */
    public function create($name)
    {
        $name = 'Migration' . date('YmdHis') . $this->normalizeName($name);

        $filePath = $this->getMigrationFile($name);
        if (file_exists($filePath)) {
            throw new Exception('Migration already exists');
        }

        file_put_contents($filePath, $this->getTemplate($name));
        return $filePath;
    }

    /**
     * Run all pending migrations
     *
     * @return void
     */
    public function applyPendingMigrations(): void
    {
        // reset list of applied migrations
        $this->lastBatch = [];

        // run all pending migrations
        $pending = $this->getStatus('pending');
        foreach ($pending as $name) {
            $migration = $this->getMigration($name);
            $this->applyMigration($migration);
        }
    }

    /**
     * Rollback the last batch of migrations
     *
     * @return void
     */
    public function rollbackMigrations(): void
    {
        $lastBatch = $this->getLastBatch();

        foreach ($lastBatch as $name) {
            $migration = $this->getMigration($name);
            $this->rollbackMigration($migration);
        }
    }

    private function applyMigration(Migration $migration)
    {
        $migration->up();

        $this->applied[] = $migration->getName();
        $this->lastBatch[] = $migration->getName();
        $this->writeStatus();
    }

    private function rollbackMigration(Migration $migration)
    {
        $migration->down();

        $this->applied = array_values(array_diff($this->applied, [$migration->getName()]));
        $this->lastBatch = array_values(array_diff($this->lastBatch, [$migration->getName()]));
        $this->writeStatus();
    }

    private function getMigrationFile($name)
    {
        return $this->migrationsDir . '/' . $name . '.php';
    }

    private function getMigration($name)
    {
        $filePath = $this->getMigrationFile($name);
        require $filePath;

        $className = 'Thathoff\KirbyMigrations\\' . $name;
        return new $className($this->kirby);
    }

    private function getTemplate($name)
    {
        $template = file_get_contents(__DIR__ . '/../templates/Migration.php');
        return str_replace('MigrationName', $name, $template);
    }

    private function normalizeName($name)
    {
        // make sure name has no slashes
        $name = str_replace('/', '', $name);

        // remove whitespace
        $name = trim($name);

        // convert name to camel case
        $name = str_replace(['-', '_'], ' ', $name);
        $name = ucwords($name);
        $name = str_replace(' ', '', $name);

        return $name;
    }

    private function getMigrations()
    {
        $migrations = [];
        $files = glob($this->migrationsDir . '/*.php');

        foreach ($files as $file) {
            $name = basename($file, '.php');
            $migrations[] = $name;
        }

        return $migrations;
    }

    private function isMigrationApplied(string $name): bool
    {
        $applied = $this->getApplied();
        return in_array($name, $applied);
    }

    private function getApplied(): array
    {
        if ($this->applied === null) {
            $this->readStatusFile();
        }

        return $this->applied;
    }

    private function getLastBatch(): array
    {
        if ($this->lastBatch === null) {
            $this->readStatusFile();
        }

        return $this->lastBatch;
    }

    private function readStatusFile()
    {
        $data = [];

        if (file_exists($this->stateFile)) {
            $data = json_decode(file_get_contents($this->stateFile), true);
        }

        $this->applied = $data['applied'] ?? [];
        $this->lastBatch = $data['lastBatch'] ?? [];
    }

    private function writeStatus(): bool
    {
        $data = [
            'applied' => $this->getApplied(),
            'lastBatch' => $this->getLastBatch(),
        ];

        if (file_put_contents($this->stateFile, json_encode($data, JSON_PRETTY_PRINT))) {
            return true;
        }

        return false;
    }
}
