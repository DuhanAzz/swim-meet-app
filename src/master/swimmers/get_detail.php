<?php
// FILE: src/master/swimmers/get_detail.php
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['records' => []]);
    exit;
}

$id = $_GET['id'];
$records = [];

try {
    // Coba ambil data (Jika tabel belum ada, akan masuk catch)
    $stmtRecord = $pdo->prepare("
        SELECT nomor_lomba, waktu_terbaik, tanggal_dicapai 
        FROM athlete_records 
        WHERE swimmer_id = ? 
        ORDER BY tanggal_dicapai DESC
    ");
    $stmtRecord->execute([$id]);
    $records = $stmtRecord->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Jika tabel tidak ada, abaikan error dan kirim array kosong
    // $records tetap []
}

echo json_encode(['records' => $records]);
?>