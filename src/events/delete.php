<?php
session_start();
// PERBAIKAN: Cukup '../'
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') die("Akses Ditolak.");
if (!isset($_GET['id'])) { header("Location: index.php"); exit(); }

$id = $_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("DELETE FROM events WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['toast_type'] = 'warning';
        $_SESSION['toast_message'] = 'Nomor lomba telah dihapus.';
    } else {
        $_SESSION['toast_type'] = 'error';
        $_SESSION['toast_message'] = 'Gagal menghapus (Data tidak ditemukan).';
    }
} catch (PDOException $e) {
    $_SESSION['toast_type'] = 'error';
    $_SESSION['toast_message'] = 'Error: ' . $e->getMessage();
}

header("Location: index.php");
exit();
?>
