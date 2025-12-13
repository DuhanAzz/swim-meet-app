<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

try {
    // Ambil daftar klub untuk Dropdown (Hanya ID dan Nama)
    $stmt = $pdo->query("SELECT id, nama_klub FROM clubs ORDER BY nama_klub ASC");
    $clubs = $stmt->fetchAll();
    
    // Panggil View
    require_once __DIR__ . '/../../../views/master/swimmers/create.php';

} catch (PDOException $e) {
    die("Error mengambil data klub: " . $e->getMessage());
}
?>
