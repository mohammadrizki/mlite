<?php

return [
    'name'          => 'Clinical Pathway',
    'description'   => 'Modul Clinical Pathway berbasis data historis, template harian, mapping ICD, dan generator otomatis.',
    'author'        => 'Basoro',
    'category'      => 'rekammedik',
    'version'       => '1.2',
    'compatibility' => '6.*.*',
    'icon'          => 'stethoscope',
    'install'       => function () use ($core) {
        $isSqlite = defined('DBDRIVER') && DBDRIVER === 'sqlite';
        try {
            $pdo = $core->db()->pdo();
        } catch (\Throwable $e) {
            return;
        }

        $expectedTables = [
            'mlite_clinical_pathway',
            'mlite_clinical_pathway_activity',
            'mlite_clinical_pathway_audit',
            'mlite_clinical_pathway_compliance',
            'mlite_clinical_pathway_cppt_template',
            'mlite_clinical_pathway_day',
            'mlite_clinical_pathway_diagnosis',
            'mlite_clinical_pathway_execution',
            'mlite_clinical_pathway_patient',
            'mlite_clinical_pathway_variance',
        ];

        $tableExists = static function ($name) use ($pdo, $isSqlite) {
            try {
                if ($isSqlite) {
                    $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = ?");
                    $stmt->execute([$name]);
                    return (bool) $stmt->fetchColumn();
                }
                $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
                $stmt->execute([$name]);
                return (bool) $stmt->fetchColumn();
            } catch (\Throwable $e) {
                return false;
            }
        };

        $runSql = static function ($sql) use ($pdo) {
            try {
                $pdo->exec($sql);
                return true;
            } catch (\Throwable $e) {
                return false;
            }
        };

        if ($isSqlite) {
            if (!$tableExists('mlite_clinical_pathway')) {
                $runSql("CREATE TABLE mlite_clinical_pathway (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    kode_cp TEXT NOT NULL UNIQUE,
                    nama_cp TEXT NOT NULL,
                    jenis_layanan TEXT NOT NULL DEFAULT 'Ranap' CHECK(jenis_layanan IN('Ralan','Ranap')),
                    target_los INTEGER NOT NULL DEFAULT 0,
                    target_tarif REAL NOT NULL DEFAULT 0,
                    confidence_score REAL NOT NULL DEFAULT 0.00,
                    evidence_note TEXT,
                    guideline_note TEXT,
                    aktif TEXT NOT NULL DEFAULT 'Ya' CHECK(aktif IN('Ya','Tidak')),
                    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S','now')),
                    updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S','now'))
                )");
                $runSql("CREATE INDEX IF NOT EXISTS cp_nama_cp ON mlite_clinical_pathway(nama_cp)");
                $runSql("CREATE INDEX IF NOT EXISTS cp_jenis_layanan ON mlite_clinical_pathway(jenis_layanan)");
                $runSql("CREATE INDEX IF NOT EXISTS cp_aktif ON mlite_clinical_pathway(aktif)");
                $runSql("DROP TRIGGER IF EXISTS tr_cp_updated_at");
                $runSql("CREATE TRIGGER tr_cp_updated_at AFTER UPDATE ON mlite_clinical_pathway
                         FOR EACH ROW WHEN NEW.updated_at = OLD.updated_at
                         BEGIN
                           UPDATE mlite_clinical_pathway SET updated_at = strftime('%Y-%m-%d %H:%M:%S','now') WHERE id = OLD.id;
                         END");
            }
            if (!$tableExists('mlite_clinical_pathway_diagnosis')) {
                $runSql("CREATE TABLE mlite_clinical_pathway_diagnosis (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    clinical_pathway_id INTEGER NOT NULL,
                    kd_penyakit TEXT NOT NULL,
                    prioritas INTEGER NOT NULL DEFAULT 1,
                    tipe TEXT NOT NULL DEFAULT 'Utama' CHECK(tipe IN('Utama','Sekunder')),
                    UNIQUE(clinical_pathway_id, kd_penyakit, tipe),
                    FOREIGN KEY(clinical_pathway_id) REFERENCES mlite_clinical_pathway(id) ON DELETE CASCADE
                )");
                $runSql("CREATE INDEX IF NOT EXISTS cp_diag_kd_penyakit ON mlite_clinical_pathway_diagnosis(kd_penyakit)");
            }
            if (!$tableExists('mlite_clinical_pathway_day')) {
                $runSql("CREATE TABLE mlite_clinical_pathway_day (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    clinical_pathway_id INTEGER NOT NULL,
                    hari_ke INTEGER NOT NULL,
                    label_hari TEXT,
                    tujuan_harian TEXT,
                    UNIQUE(clinical_pathway_id, hari_ke),
                    FOREIGN KEY(clinical_pathway_id) REFERENCES mlite_clinical_pathway(id) ON DELETE CASCADE
                )");
            }
            if (!$tableExists('mlite_clinical_pathway_activity')) {
                $runSql("CREATE TABLE mlite_clinical_pathway_activity (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    clinical_pathway_day_id INTEGER NOT NULL,
                    kategori TEXT NOT NULL,
                    uraian_kegiatan TEXT,
                    sumber_tabel TEXT,
                    item_kode TEXT,
                    item_nama TEXT NOT NULL,
                    keterangan TEXT,
                    evidence_frequency INTEGER NOT NULL DEFAULT 0,
                    evidence_percentage REAL NOT NULL DEFAULT 0.00,
                    evidence_status TEXT NOT NULL DEFAULT 'Opsional' CHECK(evidence_status IN('Wajib','Direkomendasikan','Opsional')),
                    wajib TEXT NOT NULL DEFAULT 'Ya' CHECK(wajib IN('Ya','Tidak')),
                    urutan INTEGER NOT NULL DEFAULT 0,
                    FOREIGN KEY(clinical_pathway_day_id) REFERENCES mlite_clinical_pathway_day(id) ON DELETE CASCADE
                )");
                $runSql("CREATE INDEX IF NOT EXISTS cp_act_day_id ON mlite_clinical_pathway_activity(clinical_pathway_day_id)");
                $runSql("CREATE INDEX IF NOT EXISTS cp_act_kategori ON mlite_clinical_pathway_activity(kategori)");
                $runSql("CREATE INDEX IF NOT EXISTS cp_act_item_kode ON mlite_clinical_pathway_activity(item_kode)");
            }
            if (!$tableExists('mlite_clinical_pathway_patient')) {
                $runSql("CREATE TABLE mlite_clinical_pathway_patient (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    no_rawat TEXT NOT NULL UNIQUE,
                    clinical_pathway_id INTEGER NOT NULL,
                    kd_penyakit TEXT,
                    tanggal_mulai TEXT NOT NULL,
                    tanggal_selesai TEXT,
                    status TEXT NOT NULL DEFAULT 'Aktif' CHECK(status IN('Draft','Aktif','Selesai','Drop')),
                    auto_generated TEXT NOT NULL DEFAULT 'Ya' CHECK(auto_generated IN('Ya','Tidak')),
                    FOREIGN KEY(clinical_pathway_id) REFERENCES mlite_clinical_pathway(id) ON DELETE RESTRICT
                )");
                $runSql("CREATE INDEX IF NOT EXISTS cp_pat_cp_id ON mlite_clinical_pathway_patient(clinical_pathway_id)");
                $runSql("CREATE INDEX IF NOT EXISTS cp_pat_kd_penyakit ON mlite_clinical_pathway_patient(kd_penyakit)");
                $runSql("CREATE INDEX IF NOT EXISTS cp_pat_status ON mlite_clinical_pathway_patient(status)");
            }
            if (!$tableExists('mlite_clinical_pathway_execution')) {
                $runSql("CREATE TABLE mlite_clinical_pathway_execution (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    clinical_pathway_patient_id INTEGER NOT NULL,
                    clinical_pathway_activity_id INTEGER NOT NULL,
                    hari_ke INTEGER NOT NULL,
                    tanggal_rencana TEXT,
                    tanggal_realisasi TEXT,
                    status TEXT NOT NULL DEFAULT 'Planned' CHECK(status IN('Planned','Completed','Missed','Variance')),
                    sumber_data TEXT,
                    sumber_referensi TEXT,
                    petugas TEXT,
                    catatan TEXT,
                    UNIQUE(clinical_pathway_patient_id, clinical_pathway_activity_id, hari_ke),
                    FOREIGN KEY(clinical_pathway_patient_id) REFERENCES mlite_clinical_pathway_patient(id) ON DELETE CASCADE,
                    FOREIGN KEY(clinical_pathway_activity_id) REFERENCES mlite_clinical_pathway_activity(id) ON DELETE CASCADE
                )");
                $runSql("CREATE INDEX IF NOT EXISTS cp_exec_act_id ON mlite_clinical_pathway_execution(clinical_pathway_activity_id)");
                $runSql("CREATE INDEX IF NOT EXISTS cp_exec_status ON mlite_clinical_pathway_execution(status)");
                $runSql("CREATE INDEX IF NOT EXISTS cp_exec_tgl_rencana ON mlite_clinical_pathway_execution(tanggal_rencana)");
            }
            if (!$tableExists('mlite_clinical_pathway_variance')) {
                $runSql("CREATE TABLE mlite_clinical_pathway_variance (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    clinical_pathway_patient_id INTEGER NOT NULL,
                    clinical_pathway_execution_id INTEGER,
                    kategori_variance TEXT NOT NULL CHECK(kategori_variance IN('Diagnosis','LOS','Obat','Tindakan','Lab','Radiologi','Nutrisi','Edukasi','Outcome','Administrasi')),
                    penyebab TEXT,
                    deskripsi TEXT NOT NULL,
                    severity TEXT NOT NULL DEFAULT 'Sedang' CHECK(severity IN('Rendah','Sedang','Tinggi')),
                    tanggal_variance TEXT NOT NULL,
                    status_tindak_lanjut TEXT NOT NULL DEFAULT 'Open' CHECK(status_tindak_lanjut IN('Open','Closed')),
                    FOREIGN KEY(clinical_pathway_patient_id) REFERENCES mlite_clinical_pathway_patient(id) ON DELETE CASCADE,
                    FOREIGN KEY(clinical_pathway_execution_id) REFERENCES mlite_clinical_pathway_execution(id) ON DELETE SET NULL
                )");
                $runSql("CREATE INDEX IF NOT EXISTS cp_var_pat_id ON mlite_clinical_pathway_variance(clinical_pathway_patient_id)");
                $runSql("CREATE INDEX IF NOT EXISTS cp_var_exec_id ON mlite_clinical_pathway_variance(clinical_pathway_execution_id)");
                $runSql("CREATE INDEX IF NOT EXISTS cp_var_kategori ON mlite_clinical_pathway_variance(kategori_variance)");
            }
            if (!$tableExists('mlite_clinical_pathway_compliance')) {
                $runSql("CREATE TABLE mlite_clinical_pathway_compliance (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    clinical_pathway_patient_id INTEGER NOT NULL UNIQUE,
                    planned_activity INTEGER NOT NULL DEFAULT 0,
                    completed_activity INTEGER NOT NULL DEFAULT 0,
                    missed_activity INTEGER NOT NULL DEFAULT 0,
                    compliance_percentage REAL NOT NULL DEFAULT 0.00,
                    kategori_kepatuhan TEXT NOT NULL DEFAULT 'Tidak Patuh' CHECK(kategori_kepatuhan IN('Sangat Patuh','Patuh','Kurang Patuh','Tidak Patuh')),
                    last_calculated_at TEXT,
                    FOREIGN KEY(clinical_pathway_patient_id) REFERENCES mlite_clinical_pathway_patient(id) ON DELETE CASCADE
                )");
            }
            if (!$tableExists('mlite_clinical_pathway_audit')) {
                $runSql("CREATE TABLE mlite_clinical_pathway_audit (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    clinical_pathway_patient_id INTEGER,
                    clinical_pathway_id INTEGER,
                    aksi TEXT NOT NULL,
                    referensi TEXT,
                    deskripsi TEXT,
                    user_aksi TEXT,
                    created_at TEXT NOT NULL
                )");
                $runSql("CREATE INDEX IF NOT EXISTS cp_aud_pat_id ON mlite_clinical_pathway_audit(clinical_pathway_patient_id)");
                $runSql("CREATE INDEX IF NOT EXISTS cp_aud_cp_id ON mlite_clinical_pathway_audit(clinical_pathway_id)");
                $runSql("CREATE INDEX IF NOT EXISTS cp_aud_aksi ON mlite_clinical_pathway_audit(aksi)");
            }
            if (!$tableExists('mlite_clinical_pathway_cppt_template')) {
                $runSql("CREATE TABLE mlite_clinical_pathway_cppt_template (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    kd_penyakit TEXT NOT NULL,
                    ppra TEXT NOT NULL,
                    subjective TEXT NOT NULL,
                    objective TEXT NOT NULL,
                    assessment TEXT NOT NULL,
                    plan TEXT NOT NULL,
                    aktif TEXT NOT NULL DEFAULT 'Ya' CHECK(aktif IN('Ya','Tidak')),
                    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S','now')),
                    updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S','now')),
                    UNIQUE(kd_penyakit, ppra)
                )");
                $runSql("CREATE INDEX IF NOT EXISTS cppt_aktif ON mlite_clinical_pathway_cppt_template(aktif)");
                $runSql("DROP TRIGGER IF EXISTS tr_cppt_updated_at");
                $runSql("CREATE TRIGGER tr_cppt_updated_at AFTER UPDATE ON mlite_clinical_pathway_cppt_template
                         FOR EACH ROW WHEN NEW.updated_at = OLD.updated_at
                         BEGIN
                           UPDATE mlite_clinical_pathway_cppt_template SET updated_at = strftime('%Y-%m-%d %H:%M:%S','now') WHERE id = OLD.id;
                         END");
            }
        } else {
            $charset = "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC";
            if (!$tableExists('mlite_clinical_pathway')) {
                $runSql("CREATE TABLE `mlite_clinical_pathway` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `kode_cp` varchar(30) NOT NULL,
                    `nama_cp` varchar(150) NOT NULL,
                    `jenis_layanan` enum('Ralan','Ranap') NOT NULL DEFAULT 'Ranap',
                    `target_los` int(11) NOT NULL DEFAULT 0,
                    `target_tarif` double NOT NULL DEFAULT 0,
                    `confidence_score` decimal(5,2) NOT NULL DEFAULT 0.00,
                    `evidence_note` text,
                    `guideline_note` text,
                    `aktif` enum('Ya','Tidak') NOT NULL DEFAULT 'Ya',
                    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `kode_cp` (`kode_cp`),
                    KEY `nama_cp` (`nama_cp`),
                    KEY `jenis_layanan` (`jenis_layanan`),
                    KEY `aktif` (`aktif`)
                ) {$charset}");
            }
            if (!$tableExists('mlite_clinical_pathway_diagnosis')) {
                $runSql("CREATE TABLE `mlite_clinical_pathway_diagnosis` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `clinical_pathway_id` int(11) NOT NULL,
                    `kd_penyakit` varchar(10) NOT NULL,
                    `prioritas` tinyint(4) NOT NULL DEFAULT 1,
                    `tipe` enum('Utama','Sekunder') NOT NULL DEFAULT 'Utama',
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `cp_diagnosis_unique` (`clinical_pathway_id`,`kd_penyakit`,`tipe`),
                    KEY `kd_penyakit` (`kd_penyakit`),
                    CONSTRAINT `cp_diag_cp_fk` FOREIGN KEY (`clinical_pathway_id`) REFERENCES `mlite_clinical_pathway` (`id`) ON DELETE CASCADE
                ) {$charset}");
            }
            if (!$tableExists('mlite_clinical_pathway_day')) {
                $runSql("CREATE TABLE `mlite_clinical_pathway_day` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `clinical_pathway_id` int(11) NOT NULL,
                    `hari_ke` int(11) NOT NULL,
                    `label_hari` varchar(100) DEFAULT NULL,
                    `tujuan_harian` text,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `cp_day_unique` (`clinical_pathway_id`,`hari_ke`),
                    CONSTRAINT `cp_day_cp_fk` FOREIGN KEY (`clinical_pathway_id`) REFERENCES `mlite_clinical_pathway` (`id`) ON DELETE CASCADE
                ) {$charset}");
            }
            if (!$tableExists('mlite_clinical_pathway_activity')) {
                $runSql("CREATE TABLE `mlite_clinical_pathway_activity` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `clinical_pathway_day_id` int(11) NOT NULL,
                    `kategori` varchar(100) NOT NULL,
                    `uraian_kegiatan` varchar(255) DEFAULT NULL,
                    `sumber_tabel` varchar(50) DEFAULT NULL,
                    `item_kode` varchar(50) DEFAULT NULL,
                    `item_nama` varchar(255) NOT NULL,
                    `keterangan` text,
                    `evidence_frequency` int(11) NOT NULL DEFAULT 0,
                    `evidence_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
                    `evidence_status` enum('Wajib','Direkomendasikan','Opsional') NOT NULL DEFAULT 'Opsional',
                    `wajib` enum('Ya','Tidak') NOT NULL DEFAULT 'Ya',
                    `urutan` int(11) NOT NULL DEFAULT 0,
                    PRIMARY KEY (`id`),
                    KEY `clinical_pathway_day_id` (`clinical_pathway_day_id`),
                    KEY `kategori` (`kategori`),
                    KEY `item_kode` (`item_kode`),
                    CONSTRAINT `cp_act_day_fk` FOREIGN KEY (`clinical_pathway_day_id`) REFERENCES `mlite_clinical_pathway_day` (`id`) ON DELETE CASCADE
                ) {$charset}");
            }
            if (!$tableExists('mlite_clinical_pathway_patient')) {
                $runSql("CREATE TABLE `mlite_clinical_pathway_patient` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `no_rawat` varchar(17) NOT NULL,
                    `clinical_pathway_id` int(11) NOT NULL,
                    `kd_penyakit` varchar(10) DEFAULT NULL,
                    `tanggal_mulai` datetime NOT NULL,
                    `tanggal_selesai` datetime DEFAULT NULL,
                    `status` enum('Draft','Aktif','Selesai','Drop') NOT NULL DEFAULT 'Aktif',
                    `auto_generated` enum('Ya','Tidak') NOT NULL DEFAULT 'Ya',
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `no_rawat` (`no_rawat`),
                    KEY `clinical_pathway_id` (`clinical_pathway_id`),
                    KEY `kd_penyakit` (`kd_penyakit`),
                    KEY `status` (`status`),
                    CONSTRAINT `cp_pat_cp_fk` FOREIGN KEY (`clinical_pathway_id`) REFERENCES `mlite_clinical_pathway` (`id`) ON DELETE RESTRICT
                ) {$charset}");
            }
            if (!$tableExists('mlite_clinical_pathway_execution')) {
                $runSql("CREATE TABLE `mlite_clinical_pathway_execution` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `clinical_pathway_patient_id` int(11) NOT NULL,
                    `clinical_pathway_activity_id` int(11) NOT NULL,
                    `hari_ke` int(11) NOT NULL,
                    `tanggal_rencana` date DEFAULT NULL,
                    `tanggal_realisasi` datetime DEFAULT NULL,
                    `status` enum('Planned','Completed','Missed','Variance') NOT NULL DEFAULT 'Planned',
                    `sumber_data` varchar(50) DEFAULT NULL,
                    `sumber_referensi` varchar(100) DEFAULT NULL,
                    `petugas` varchar(20) DEFAULT NULL,
                    `catatan` text,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `cp_exec_unique` (`clinical_pathway_patient_id`,`clinical_pathway_activity_id`,`hari_ke`),
                    KEY `clinical_pathway_activity_id` (`clinical_pathway_activity_id`),
                    KEY `status` (`status`),
                    KEY `tanggal_rencana` (`tanggal_rencana`),
                    CONSTRAINT `cp_exec_pat_fk` FOREIGN KEY (`clinical_pathway_patient_id`) REFERENCES `mlite_clinical_pathway_patient` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `cp_exec_act_fk` FOREIGN KEY (`clinical_pathway_activity_id`) REFERENCES `mlite_clinical_pathway_activity` (`id`) ON DELETE CASCADE
                ) {$charset}");
            }
            if (!$tableExists('mlite_clinical_pathway_variance')) {
                $runSql("CREATE TABLE `mlite_clinical_pathway_variance` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `clinical_pathway_patient_id` int(11) NOT NULL,
                    `clinical_pathway_execution_id` int(11) DEFAULT NULL,
                    `kategori_variance` enum('Diagnosis','LOS','Obat','Tindakan','Lab','Radiologi','Nutrisi','Edukasi','Outcome','Administrasi') NOT NULL,
                    `penyebab` varchar(255) DEFAULT NULL,
                    `deskripsi` text NOT NULL,
                    `severity` enum('Rendah','Sedang','Tinggi') NOT NULL DEFAULT 'Sedang',
                    `tanggal_variance` datetime NOT NULL,
                    `status_tindak_lanjut` enum('Open','Closed') NOT NULL DEFAULT 'Open',
                    PRIMARY KEY (`id`),
                    KEY `clinical_pathway_patient_id` (`clinical_pathway_patient_id`),
                    KEY `clinical_pathway_execution_id` (`clinical_pathway_execution_id`),
                    KEY `kategori_variance` (`kategori_variance`),
                    CONSTRAINT `cp_var_pat_fk` FOREIGN KEY (`clinical_pathway_patient_id`) REFERENCES `mlite_clinical_pathway_patient` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `cp_var_exec_fk` FOREIGN KEY (`clinical_pathway_execution_id`) REFERENCES `mlite_clinical_pathway_execution` (`id`) ON DELETE SET NULL
                ) {$charset}");
            }
            if (!$tableExists('mlite_clinical_pathway_compliance')) {
                $runSql("CREATE TABLE `mlite_clinical_pathway_compliance` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `clinical_pathway_patient_id` int(11) NOT NULL,
                    `planned_activity` int(11) NOT NULL DEFAULT 0,
                    `completed_activity` int(11) NOT NULL DEFAULT 0,
                    `missed_activity` int(11) NOT NULL DEFAULT 0,
                    `compliance_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
                    `kategori_kepatuhan` enum('Sangat Patuh','Patuh','Kurang Patuh','Tidak Patuh') NOT NULL DEFAULT 'Tidak Patuh',
                    `last_calculated_at` datetime DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `clinical_pathway_patient_id` (`clinical_pathway_patient_id`),
                    CONSTRAINT `cp_comp_pat_fk` FOREIGN KEY (`clinical_pathway_patient_id`) REFERENCES `mlite_clinical_pathway_patient` (`id`) ON DELETE CASCADE
                ) {$charset}");
            }
            if (!$tableExists('mlite_clinical_pathway_audit')) {
                $runSql("CREATE TABLE `mlite_clinical_pathway_audit` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `clinical_pathway_patient_id` int(11) DEFAULT NULL,
                    `clinical_pathway_id` int(11) DEFAULT NULL,
                    `aksi` varchar(100) NOT NULL,
                    `referensi` varchar(100) DEFAULT NULL,
                    `deskripsi` text,
                    `user_aksi` varchar(50) DEFAULT NULL,
                    `created_at` datetime NOT NULL,
                    PRIMARY KEY (`id`),
                    KEY `clinical_pathway_patient_id` (`clinical_pathway_patient_id`),
                    KEY `clinical_pathway_id` (`clinical_pathway_id`),
                    KEY `aksi` (`aksi`)
                ) {$charset}");
            }
            if (!$tableExists('mlite_clinical_pathway_cppt_template')) {
                $runSql("CREATE TABLE `mlite_clinical_pathway_cppt_template` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `kd_penyakit` varchar(10) NOT NULL,
                    `ppra` varchar(100) NOT NULL,
                    `subjective` text NOT NULL,
                    `objective` text NOT NULL,
                    `assessment` text NOT NULL,
                    `plan` text NOT NULL,
                    `aktif` enum('Ya','Tidak') NOT NULL DEFAULT 'Ya',
                    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `cppt_template_kd_penyakit_ppra` (`kd_penyakit`,`ppra`),
                    KEY `cppt_template_aktif` (`aktif`)
                ) {$charset}");
            }
        }
    },
    'uninstall'     => function () use ($core) {
    }
];
