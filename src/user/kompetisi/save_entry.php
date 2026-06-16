<?php
// FILE: src/user/kompetisi/save_entry.php
session_start();
require_once __DIR__ . '/../../config/database.php';

// 1. CEK LOGIN & METHOD
if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../../public/login.php"); 
    exit;
}

$uid = $_SESSION['user_id'];
$eventId = $_POST['event_id'] ?? 0;
$swimmerId = $_POST['swimmer_id'] ?? 0;

// Ambil data array dari input form (checkbox dan input text)
$selectedNumbers = $_POST['numbers'] ?? []; // Array ID dari Checkbox
$times = $_POST['times'] ?? []; // Array input waktu (entry time)

if (!$eventId || !$swimmerId) {
    die("Data tidak lengkap. Pastikan Anda memilih atlet dan event dengan benar.");
}

try {
    // Mulai Transaksi Database (Biar aman kalau ada error di tengah jalan)
    $pdo->beginTransaction();

    // 2. TENTUKAN CLUB ID
    $stmtGetClub = $pdo->prepare("SELECT club_id FROM swimmers WHERE id = ?");
    $stmtGetClub->execute([$swimmerId]);
    $swimmerData = $stmtGetClub->fetch();
    
    // Jika tidak ada club_id di atlet, jadikan UID user pendaftar sebagai club_id-nya
    $clubId = !empty($swimmerData['club_id']) ? $swimmerData['club_id'] : $uid; 

    // 3. SAPU BERSIH (Hapus data lama)
    // Menghapus data pendaftaran atlet ini pada event ini saja
    $stmtDel = $pdo->prepare("DELETE FROM event_entries WHERE user_id = ? AND event_id = ? AND swimmer_id = ?");
    $stmtDel->execute([$uid, $eventId, $swimmerId]);

    // 4. MASUKKAN DATA BARU (Berdasarkan checkbox yang dicentang)
    if (!empty($selectedNumbers)) {
        $stmtIns = $pdo->prepare("INSERT INTO event_entries (user_id, event_id, swimmer_id, category_id, entry_time, club_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW())");
        
        foreach ($selectedNumbers as $categoryId) {
            // Ambil waktu dari array times, jika kosong jadikan 'NT' (No Time)
            $time = trim($times[$categoryId] ?? '');
            if ($time === '') {
                $time = 'NT'; 
            }

            $stmtIns->execute([$uid, $eventId, $swimmerId, $categoryId, $time, $clubId]);
        }
    }
    
    // Simpan permanen ke database
    $pdo->commit();
    
    // 5. KEMBALI KE HALAMAN REGISTRATION
    // Arahkan kembali ke halaman registration.php dengan membawa event_id yang benar
    header("Location: registration.php?event_id=" . $eventId);
    exit;

} catch (Exception $e) {
    // Jika ada error, batalkan semua perubahan
    $pdo->rollBack();
    die("Error Database: " . $e->getMessage());
}
?>