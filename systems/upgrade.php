<?php

if (!defined("UPGRADABLE")) {
    exit();
}

function rrmdir($dir)
{
    $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
        if (is_dir("$dir/$file")) {
            rrmdir("$dir/$file");
        } else {
            unlink("$dir/$file");
        }
    }
    return rmdir($dir);
}

switch ($version) {
    case '4.0.0':
        // $this->core->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `mlite_test` (
        //         `id` int(11) NOT NULL AUTO_INCREMENT,
        //         `name` varchar(30) DEFAULT NULL,
        //         PRIMARY KEY (`id`) USING BTREE
        // ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");
        $return = '4.0.1';
    case '4.0.1':
        $return = '4.0.2';
    case '4.0.2':
        $return = '4.0.3';
    case '4.0.3':
        $return = '4.0.4';
    case '4.0.4':
        $return = '4.0.5';
    case '4.0.5':
        $return = '4.0.6';
    case '4.0.6':
        $return = '4.0.7';
   case '4.0.7':
        $return = '4.0.8';
    case '4.0.8':
        $return = '4.0.9';
    case '4.0.9':
        $return = '4.1.0';
    case '4.1.0':
        $return = '4.1.1';
    case '4.1.1':
        $return = '4.1.2';
    }
return $return;
