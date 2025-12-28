<?php
// src/admin/entries/process_reject.php
session_start();
require_once __DIR__ . '/../../config/database.php';

// 1. CEK LOGIN ADMIN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Akses ditolak.");
}

// 2. AMBIL DATA
$entryId = $_GET['entry_id'] ?? 0;
$clubId  = $_GET['club_id'] ?? 0;
$eventId = $_GET['event_id'] ?? 0;

if ($entryId == 0) {
    die("Data tidak valid.");
}

try {
    // 3. HAPUS ENTRY DARI DATABASE
    $stmt = $pdo->prepare("DELETE FROM event_entries WHERE id = ?");
    $stmt->execute([$entryId]);

    // 4. REDIRECT KEMBALI KE HALAMAN DETAIL
    header("Location: detail_club.php?id=$clubId&event_id=$eventId&msg=rejected");
    exit;

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>