<?php
class MigrationRunner {
    private PDO $db;
    private string $path;

    public function __construct(PDO $db, string $path) {
        $this->db = $db;
        $this->path = rtrim($path,'/');
    }

    public function runOneVersion(string $current): string {
        foreach ($this->getMigrations() as $version => $file) {
            if (version_compare($version, $current, '>')) {
                $this->run($version, $file);
                return $version;
            }
        }
        return $current;
    }

    private function run(string $version, string $file): void {
        $this->db->beginTransaction();
        try {
            $migration = require $file;
            $migration['up']($this->db);
            $stmt = $this->db->prepare(
                "INSERT INTO schema_migrations(version, migrated_at) VALUES (?,NOW())"
            );
            $stmt->execute([$version]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function getMigrations(): array {
        $map = [];
        foreach (glob($this->path.'/*.php') as $f) {
            $map[basename($f,'.php')] = $f;
        }
        uksort($map,'version_compare');
        return $map;
    }
}