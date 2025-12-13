<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

try {
    // JOIN TABLE: Ambil data atlet BESERTA nama klubnya
    // Kita select swimmers.* (semua data atlet) dan clubs.nama_klub
    $sql = "SELECT swimmers.*, clubs.nama_klub, clubs.kode_klub 
            FROM swimmers 
            JOIN clubs ON swimmers.club_id = clubs.id 
            ORDER BY swimmers.nama_atlet ASC";
            
    $stmt = $pdo->query($sql);
    $swimmers = $stmt->fetchAll();
    
    require_once __DIR__ . '/../../../views/master/swimmers/index.php';

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
