<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

try {
    $stmt = $pdo->query("SELECT * FROM clubs ORDER BY nama_klub ASC");
    $clubs = $stmt->fetchAll();
    
    // Panggil View
    require_once __DIR__ . '/../../../views/master/clubs/index.php';

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
