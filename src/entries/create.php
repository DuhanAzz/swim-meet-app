<?php
session_start();
// PERBAIKAN: Path Database
require_once __DIR__ . '/../config/database.php';

try {
    // 1. Ambil semua data Atlet
    $stmtSwimmers = $pdo->query("
        SELECT swimmers.id, swimmers.nama_atlet, clubs.nama_klub 
        FROM swimmers 
        JOIN clubs ON swimmers.club_id = clubs.id 
        ORDER BY swimmers.nama_atlet ASC
    ");
    $swimmers = $stmtSwimmers->fetchAll();

    // 2. Ambil semua data Event
    $stmtEvents = $pdo->query("SELECT * FROM events ORDER BY jarak ASC, gaya ASC");
    $events = $stmtEvents->fetchAll();
    
    // View tetap di ../../views
    require_once __DIR__ . '/../../views/entries/create.php';

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
