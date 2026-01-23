<?php

class MigrationStatus
{
    public static function hasPending(PDO $db): bool
    {
        $dbVersion = $db->query("
            SELECT IFNULL(MAX(version),'0.0.0')
            FROM schema_migrations
        ")->fetchColumn();

        return version_compare($dbVersion, APP_VERSION, '<');
    }
}
