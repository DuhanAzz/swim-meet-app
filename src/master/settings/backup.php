<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'master') die("Akses Ditolak.");

// Konfigurasi DB
$host = 'localhost';
$user = 'root';
$pass = ''; 
$name = 'swim_meet';

// Nama File Backup
$backup_name = $name . "_" . date("Y-m-d_H-i-s") . ".sql";
$tables = array();

// Ambil semua tabel
$stmt = $pdo->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

$content = "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\r\nSET time_zone = \"+00:00\";\r\n\r\n";

foreach ($tables as $table) {
    // Struktur Tabel
    $stmt = $pdo->query("SHOW CREATE TABLE $table");
    $row = $stmt->fetch(PDO::FETCH_NUM);
    $content .= "\n\n" . $row[1] . ";\n\n";

    // Isi Data
    $stmt = $pdo->query("SELECT * FROM $table");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $content .= "INSERT INTO $table VALUES(";
        for ($j = 0; $j < count($row); $j++) {
            $row[$j] = addslashes($row[$j]);
            $row[$j] = str_replace("\n", "\\n", $row[$j]);
            if (isset($row[$j])) { $content .= '"' . $row[$j] . '"'; } else { $content .= '""'; }
            if ($j < (count($row) - 1)) { $content .= ','; }
        }
        $content .= ");\n";
    }
}

// Download File
header('Content-Type: application/octet-stream');
header("Content-Transfer-Encoding: Binary");
header("Content-disposition: attachment; filename=\"" . $backup_name . "\"");
echo $content; exit;
?>
