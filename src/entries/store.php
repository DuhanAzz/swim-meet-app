<?php
session_start();
// PERBAIKAN: Path Database
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $sql = "INSERT INTO entries (event_id, swimmer_id, seed_time) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            $_POST['event_id'],
            $_POST['swimmer_id'],
            $_POST['seed_time']
        ]);

        header("Location: index.php");
        exit();

    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
             echo "<script>alert('Gagal: Atlet ini SUDAH TERDAFTAR di nomor lomba tersebut!'); window.history.back();</script>";
        } else {
            echo "Error: " . $e->getMessage();
        }
    }
}
?>
