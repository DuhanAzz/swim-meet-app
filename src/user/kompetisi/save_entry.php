<?php
// FILE: src/user/kompetisi/save_entry.php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];
$eventId = $_POST['event_id'] ?? 0;
$swimmerId = $_POST['swimmer_id'] ?? 0;
$selectedNumbers = $_POST['numbers'] ?? []; // Array dari Checkbox
$times = $_POST['times'] ?? []; // Array input waktu

if (!$eventId || !$swimmerId) {
    die("Data tidak lengkap. Pastikan Anda memilih atlet dan event dengan benar.");
}

try {
    $pdo->beginTransaction();

    // 1. Tentukan Club ID
    $stmtGetClub = $pdo->prepare("SELECT club_id FROM swimmers WHERE id = ?");
    $stmtGetClub->execute([$swimmerId]);
    $swimmerData = $stmtGetClub->fetch();
    
    // Jika tidak ada club_id di atlet, jadikan UID user pendaftar sebagai club_id-nya
    $clubId = !empty($swimmerData['club_id']) ? $swimmerData['club_id'] : $uid; 

    // 2. SAPU BERSIH: Hapus semua entri atlet ini di event ini 
    // Ini memastikan jika user meng-uncheck nomor lomba, datanya benar-benar terhapus
    $stmtDel = $pdo->prepare("DELETE FROM event_entries WHERE user_id = ? AND event_id = ? AND swimmer_id = ?");
    $stmtDel->execute([$uid, $eventId, $swimmerId]);

    // 3. MASUKKAN BARU: Loop nomor lomba yang dicentang
    if (!empty($selectedNumbers)) {
        $stmtIns = $pdo->prepare("INSERT INTO event_entries (user_id, event_id, swimmer_id, category_id, entry_time, club_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW())");
        
        foreach ($selectedNumbers as $categoryId) {
            $time = trim($times[$categoryId] ?? '');
            if ($time === '') $time = 'NT'; // Default ke NT (No Time) jika dikosongkan

            $stmtIns->execute([$uid, $eventId, $swimmerId, $categoryId, $time, $clubId]);
        }
    }
    
    $pdo->commit();
    
    // PERBAIKAN: Arahkan kembali ke file yang benar
    header("Location: registration.php?event_id=" . $eventId);
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    die("Error Database: " . $e->getMessage());
}
?>