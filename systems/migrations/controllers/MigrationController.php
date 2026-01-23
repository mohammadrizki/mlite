<?php
class MigrationController {

    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function start() {
        (new PreflightChecker($this->db))->run();
        $this->db->exec("
            INSERT INTO migration_state(id,status,updated_at)
            VALUES (1,'running',NOW())
            ON DUPLICATE KEY UPDATE status='running',updated_at=NOW()
        ");
        return ['status'=>'started'];
    }

    public function runNext() {
        $state = $this->db->query("SELECT * FROM migration_state WHERE id=1")->fetch();
        if ($state['status'] !== 'running') return ['error'=>'not running'];

        $current = $this->getCurrentVersion();
        $runner = new MigrationRunner($this->db, __DIR__.'/../migrations');
        $next = $runner->runOneVersion($current);

        if ($next === $current) {
            $this->db->exec("UPDATE migration_state SET status='done' WHERE id=1");
        }

        return ['version'=>$next];
    }

    private function getCurrentVersion(): string {
        return $this->db->query("
            SELECT IFNULL(MAX(version),'0.0.0') FROM schema_migrations
        ")->fetchColumn();
    }
}