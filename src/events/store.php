<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $sql = "INSERT INTO events (nama_event, jarak, gaya, jenis_kelamin, batas_umur_bawah, batas_umur_atas, biaya_pendaftaran) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            $_POST['nama_event'],
            $_POST['jarak'],
            $_POST['gaya'],
            $_POST['jenis_kelamin'],
            $_POST['batas_umur_bawah'],
            $_POST['batas_umur_atas'],
            $_POST['biaya_pendaftaran']
        ]);

        header("Location: index.php");
        exit();
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>
