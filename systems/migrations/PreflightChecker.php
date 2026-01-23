<?php
class PreflightChecker {
    private PDO $db;
    public function __construct(PDO $db){$this->db=$db;}
    public function run(): void {
        if ($this->db->query("SELECT @@default_storage_engine")->fetchColumn() !== 'InnoDB')
            throw new Exception("Engine bukan InnoDB");
        if ($this->db->query("SELECT @@FOREIGN_KEY_CHECKS")->fetchColumn() != 1)
            throw new Exception("FK check off");
    }
}