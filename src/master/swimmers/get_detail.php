<?php
// FILE: src/master/swimmers/get_detail.php
require_once __DIR__ . '/../../config/database.php';

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'No ID']);
    exit;
}

$id = $_GET['id'];

// 1. AMBIL REKOR LATIHAN (Dari tabel athlete_records)
$stmtRecord = $pdo->prepare("
    SELECT nomor_lomba, waktu_terbaik, tanggal_dicapai 
    FROM athlete_records 
    WHERE swimmer_id = ? 
    ORDER BY tanggal_dicapai DESC
");
$stmtRecord->execute([$id]);
$records = $stmtRecord->fetchAll(PDO::FETCH_ASSOC);

// 2. AMBIL RIWAYAT LOMBA (Opsional: Dari tabel entries/results jika ada)
// (Sementara kita kosongkan dulu agar tidak error jika tabel belum siap)
$history = []; 

// Kirim data JSON ke Javascript
header('Content-Type: application/json');
echo json_encode([
    'records' => $records,
    'history' => $history
]);
?>