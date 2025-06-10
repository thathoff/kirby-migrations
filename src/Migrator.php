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
        $migrations = $this->getAllMigrations();

        $statusOut = [
            'migration_directories' => $this->getMigrationDirs(),
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
    public function create(string $name, string $pluginName = 'site'): string
    {
        $name = 'Migration' . date('YmdHis') . $this->normalizeName($name);

        $filePath = $this->getMigrationFilePath($name, $pluginName);
        if (file_exists($filePath)) {
            throw new Exception('Migration already exists');
        }

        file_put_contents($filePath, $this->getTemplate($name));
        return $filePath;
    }

    /**
     * Run all pending migrations
     *
     * @return string[]
     */
    public function applyPendingMigrations(): array
    {
        // reset list of applied migrations
        $this->lastBatch = [];

        // run all pending migrations
        $pending = $this->getStatus('pending');
        foreach ($pending as $name) {
            $migration = $this->getMigration($name);
            $this->applyMigration($migration);
        }

        return $this->lastBatch ?? [];
    }

    /**
     * Rollback the last batch of migrations
     *
     * @param string[]|null $migrationsToRollback (optional), if provided, only roll back these migrations
     *
     * @return string[]
     */
    public function rollbackMigrations(?array $migrationsToRollback = null): array
    {
        $batchToRollback = $migrationsToRollback ?? $this->getLastBatch();
        $rolledBack = [];
        foreach ($batchToRollback as $name) {
            $migration = $this->getMigration($name);
            $this->rollbackMigration($migration);
            $rolledBack[] = $migration->getName();
        }

        return $rolledBack;
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

    private function getMigrationFile(string $name): ?string
    {
        $allMigrations = array_flip($this->getAllMigrations());
        return $allMigrations[$name] ?? null;
    }

    private function getMigrationFilePath(string $name, string $pluginName): string
    {
        $allMigrationFolders = $this->getMigrationDirs();

        // check if plugin has a migrations directory
        if (!isset($allMigrationFolders[$pluginName])) {
            throw new Exception('Plugin ' . $pluginName . ' does not have a migrations directory. Enable migrations in the plugin to create a migration.');
        }

        $filePath = $allMigrationFolders[$pluginName] . '/' . $name . '.php';
        return $filePath;
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
     * @return array<string, string>
     */
    private function getMigrationsFromDir(string $dir): array
    {
        $migrations = [];
        $files = glob($dir . '/*.php');

        if (!$files) {
            return $migrations;
        }

        foreach ($files as $file) {
            $name = basename($file, '.php');
            $migrations[$file] = $name;
        }

        return $migrations;
    }

    /**
     * @return array<string, string>
     */
    private function getAllMigrations(): array
    {
        $migrations = [];

        foreach ($this->getMigrationDirs() as $dir) {
            $migrations = array_merge($migrations, $this->getMigrationsFromDir($dir));
        }

        return $migrations;
    }


    /**
     * @return array<string>
     */
    private function getMigrationDirs(): array
    {
        $pluginMigrations = $this->getPluginMigrationDirs();
        $migrations = ['site' => $this->migrationsDir];

        return array_merge($pluginMigrations, $migrations);
    }

    /**
     * @return array<string, string>
     */
    private function getPluginMigrationDirs(): array
    {
        $plugins = kirby()->plugins();
        $migrations = [];

        foreach ($plugins as $plugin) {
            $options = $plugin->extends();

            if (!isset($options['migrations'])) {
                continue;
            }

            // use migrations dir from plugin is just true we assume the migrations dir by default
            if ($options['migrations'] === true) {
                $options['migrations'] = $plugin->root() . DIRECTORY_SEPARATOR . 'migrations';
            }

            // check if migrations dir exists
            if (file_exists($options['migrations']) && is_dir($options['migrations'])) {
                $migrations[$plugin->id()] = $options['migrations'];
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
