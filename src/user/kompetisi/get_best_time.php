<?php
// src/user/kompetisi/get_best_time.php
require_once '../../config/database.php';

// Ambil data dari JavaScript
$swimmer_id = $_GET['swimmer_id'] ?? 0;
$nomor_lomba = $_GET['nomor_lomba'] ?? ''; // Contoh: "50m Gaya Bebas"

if ($swimmer_id && $nomor_lomba) {
    // Cari waktu terbaik yang cocok persis dengan nama nomor lomba
    // Kita urutkan dari waktu terpendek (tercepat) atau tanggal terbaru
    $query = "SELECT waktu_terbaik FROM athlete_records 
              WHERE swimmer_id = '$swimmer_id' 
              AND nomor_lomba LIKE '%$nomor_lomba%' 
              ORDER BY created_at DESC LIMIT 1";
              
    $result = mysqli_query($conn, $query);
    $data = mysqli_fetch_assoc($result);

    if ($data) {
        echo json_encode(['status' => 'success', 'time' => $data['waktu_terbaik']]);
    } else {
        echo json_encode(['status' => 'empty']); // Tidak ada record
    }
} else {
    echo json_encode(['status' => 'error']);
}
?>