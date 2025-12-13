<?php
ob_start(); // Tambahan pengaman
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'master') die("Akses Ditolak.");
if (!isset($_GET['id'])) { header("Location: index.php"); exit(); }

$id = $_GET['id'];

try {
    $stmt = $pdo->prepare("SELECT role, event_image FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    if ($user) {
        $redirectRole = $user['role']; 

        if ($redirectRole == 'master') {
            $_SESSION['toast_type'] = 'error';
            $_SESSION['toast_message'] = 'Dilarang menghapus akun Master!';
            header("Location: index.php");
            exit();
        }

        if (!empty($user['event_image'])) {
            $filePath = __DIR__ . '/../../../public/' . $user['event_image'];
            if (file_exists($filePath)) unlink($filePath);
        }

        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);

        $_SESSION['toast_type'] = 'warning'; 
        $_SESSION['toast_message'] = 'Akun telah dihapus secara permanen.'; 
        
        header("Location: index.php?role=" . $redirectRole);
        exit();
    } else {
        header("Location: index.php");
        exit();
    }

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
