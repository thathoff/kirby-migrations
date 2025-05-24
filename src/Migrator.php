<?php

namespace Thathoff\KirbyMigrations;

use Exception;
use Kirby\CLI\CLI;
use Kirby\Cms\App;

class Migrator
{
    /**
     * The current Kirby instance
     */
    private App $kirby;

    /**
     * The current CLI instance
     */
    private CLI $cli;

    /**
     * List of applied migrations
     *
     * @var string[]|null
     */
    private ?array $applied = null;

    /**
     * List of applied migrations of last batch
     *
     * @var string[]|null
     */
    private ?array $lastBatch = null;

    private string $migrationsDir;
    private string $stateFile;

    /**
     * Create a new migrator instance
     */
    public function __construct(CLI $cli)
    {
        $this->cli = $cli;

        if (!$cli->kirby()) {
            throw new Exception('Kirby instance not found. Please make sure Kirby CLI can initialize the Kirby instance.');
        }
        $this->kirby = $cli->kirby();

        // set directory for migrations
        $this->migrationsDir = rtrim($this->kirby->option('thathoff.migrations.dir', $this->kirby->root('site') . '/migrations'), '/');

        // set state file from option
        $this->stateFile = $this->kirby->option('thathoff.migrations.stateFile', $this->migrationsDir . '/.migrations');

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
     * @return ($type is null ? array<"applied" | "missing" | "last_batch" | "pending", string[]> : string[])
     */
    public function getStatus(?string $type = null): array
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

        if (!isset($statusOut[$type])) {
            throw new Exception('Invalid status type: ' . $type . '. Valid types are: ' . implode(', ', array_keys($statusOut)));
        }

        return $statusOut[$type];
    }

    /**
     * Create a new migration file
     */
    public function create(string $name): string
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
     */
    public function rollbackMigrations(): void
    {
        $lastBatch = $this->getLastBatch();

        foreach ($lastBatch as $name) {
            $migration = $this->getMigration($name);
            $this->rollbackMigration($migration);
        }
    }

    private function applyMigration(Migration $migration): void
    {
        $migration->up();

        $this->applied[] = $migration->getName();
        $this->lastBatch[] = $migration->getName();
        $this->writeStatus();
    }

    private function rollbackMigration(Migration $migration): void
    {
        $migration->down();

        $this->applied = array_values(array_diff($this->getApplied(), [$migration->getName()]));
        $this->lastBatch = array_values(array_diff($this->getLastBatch(), [$migration->getName()]));
        $this->writeStatus();
    }

    private function getMigrationFile(string $name): string
    {
        return $this->migrationsDir . '/' . $name . '.php';
    }

    private function getMigration(string $name): Migration
    {
        $filePath = $this->getMigrationFile($name);
        require $filePath;

        $className = 'Thathoff\KirbyMigrations\\' . $name;

        if (!class_exists($className)) {
            throw new Exception('Migration class not found: ' . $className);
        }

        if (!is_subclass_of($className, Migration::class)) {
            throw new Exception('Migration class is not a subclass of ' . Migration::class . ': ' . $className);
        }

        return new $className($this->kirby, $this->cli);
    }

    private function getTemplate(string $name): string
    {
        if (!$template = file_get_contents(__DIR__ . '/../templates/Migration.php')) {
            throw new Exception('Migration template cannot be loaded.');
        }

        return str_replace('MigrationName', $name, $template);
    }

    private function normalizeName(string $name): string
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

    /**
     * @return string[]
     */
    private function getMigrations(): array
    {
        $migrations = [];
        $files = glob($this->migrationsDir . '/*.php');

        if ($files) {
            foreach ($files as $file) {
                $name = basename($file, '.php');
                $migrations[] = $name;
            }
        }

        return $migrations;
    }

    private function isMigrationApplied(string $name): bool
    {
        $applied = $this->getApplied();
        return in_array($name, $applied);
    }

    /**
     * @return string[]
     */
    private function getApplied(): array
    {
        if ($this->applied === null) {
            $this->readStatusFile();
        }

        return $this->applied ?? [];
    }

    /**
     * @return string[]
     */
    private function getLastBatch(): array
    {
        if ($this->lastBatch === null) {
            $this->readStatusFile();
        }

        return $this->lastBatch ?? [];
    }

    private function readStatusFile(): void
    {
        $data = [];

        if (file_exists($this->stateFile)) {
            $fileContent = file_get_contents($this->stateFile) ?: '[]';
            $data = json_decode($fileContent, true) ?? [];
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
