<?php
return [
 'up'=>function(PDO $db){
   $db->exec("CREATE TABLE IF NOT EXISTS mlite_surat_rujukan (
     id INT AUTO_INCREMENT PRIMARY KEY,
     nomor_surat VARCHAR(100),
     no_rawat VARCHAR(100)
   ) ENGINE=InnoDB");
 }
];