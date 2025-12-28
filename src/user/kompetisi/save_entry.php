<?php
// src/user/kompetisi/save_entry.php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];
$eventId = $_POST['event_id'] ?? 0;
$swimmerId = $_POST['swimmer_id'] ?? 0;
$categoryId = $_POST['category_id'] ?? 0;
$time = trim($_POST['entry_time'] ?? '');

if (!$eventId || !$swimmerId || !$categoryId) {
    die("Data tidak lengkap.");
}

try {
    // ---------------------------------------------------------
    // PERBAIKAN: AMBIL CLUB_ID DARI DATA SWIMMER TERLEBIH DAHULU
    // ---------------------------------------------------------
    $stmtGetClub = $pdo->prepare("SELECT club_id FROM swimmers WHERE id = ?");
    $stmtGetClub->execute([$swimmerId]);
    $swimmerData = $stmtGetClub->fetch();
    
    // Jika kolom club_id di tabel swimmers null, kita set 0 atau ambil dari user_id (tergantung struktur DB Anda)
    // Di sini saya asumsikan tabel swimmers punya kolom club_id.
    $clubId = $swimmerData['club_id'] ?? 0; 
    
    // Jika ternyata 0, coba ambil id dari session (jika user adalah club)
    if ($clubId == 0) {
        $clubId = $uid; 
    }

    // ---------------------------------------------------------
    // PROSES SIMPAN / HAPUS
    // ---------------------------------------------------------

    // 1. Cek apakah ini permintaan HAPUS?
    if ($time === 'DELETE' || $time === '') {
        $stmt = $pdo->prepare("DELETE FROM event_entries WHERE user_id = ? AND event_id = ? AND swimmer_id = ? AND category_id = ?");
        $stmt->execute([$uid, $eventId, $swimmerId, $categoryId]);
    } 
    // 2. Jika bukan, maka SIMPAN / UPDATE
    else {
        // Cek dulu sudah ada belum datanya
        $stmtCek = $pdo->prepare("SELECT id FROM event_entries WHERE user_id = ? AND event_id = ? AND swimmer_id = ? AND category_id = ?");
        $stmtCek->execute([$uid, $eventId, $swimmerId, $categoryId]);
        $exists = $stmtCek->fetch();

        if ($exists) {
            // UPDATE: Tidak perlu update club_id, cukup waktunya saja
            $stmtUpd = $pdo->prepare("UPDATE event_entries SET entry_time = ?, updated_at = NOW() WHERE id = ?");
            $stmtUpd->execute([$time, $exists['id']]);
        } else {
            // INSERT: Wajib menyertakan club_id agar tidak error 1364
            $stmtIns = $pdo->prepare("INSERT INTO event_entries (user_id, event_id, swimmer_id, category_id, entry_time, club_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmtIns->execute([$uid, $eventId, $swimmerId, $categoryId, $time, $clubId]);
        }
    }
    
    // Redirect kembali ke halaman register
    header("Location: register_event.php?event_id=" . $eventId);
    exit;

} catch (Exception $e) {
    // Tampilkan error jika masih ada masalah lain
    die("Error Database: " . $e->getMessage());
}
?>