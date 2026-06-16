<?php
// FILE: tembak_uid.php
// Taruh di folder paling luar (sejajar dengan folder src / public)
require_once __DIR__ . '/src/config/database.php';

echo "<h2 style='font-family:sans-serif; color:#1e293b;'>🚀 MEMULAI PROSES FIX & UPDATE UID KE MYSQL...</h2>";
echo "<table border='1' cellpadding='8' style='border-collapse: collapse; font-family:sans-serif; font-size:13px;'>";
echo "<tr style='background:#f1f5f9;'><th>ID</th><th>Nama Atlet</th><th>Status Update</th></tr>";

// Ambil SEMUA atlet yang UID-nya bermasalah (Kosong, Spasi, Strip, atau format lama 'SW')
$stmt = $pdo->query("SELECT id, nama_atlet, tanggal_lahir, jenis_kelamin FROM swimmers WHERE uid IS NULL OR trim(uid) = '' OR uid = '-' OR uid LIKE 'SW%' OR uid = '0'");
$swimmers = $stmt->fetchAll();

function getAlphaIndex($char) {
    $char = strtoupper(trim($char));
    if ($char >= 'A' && $char <= 'Z') return str_pad(ord($char) - 64, 2, '0', STR_PAD_LEFT);
    return '00';
}

foreach ($swimmers as $row) {
    $nama = trim($row['nama_atlet']);
    $words = explode(' ', preg_replace('/\s+/', ' ', $nama));
    
    $char1 = $words[0][0] ?? 'A';
    $char2 = (count($words) > 1) ? ($words[1][0] ?? 'A') : ($words[0][1] ?? 'A');
    
    $part1 = getAlphaIndex($char1);
    $part2 = getAlphaIndex($char2);
    $tahunLahir = !empty($row['tanggal_lahir']) ? date('Y', strtotime($row['tanggal_lahir'])) : '0000';
    $jk = strtoupper($row['jenis_kelamin'] ?? 'L');
    $genderDigit = ($jk == 'L' || $jk == 'M') ? '1' : '9';
    
    $baseUid = $part1 . $part2 . $tahunLahir . $genderDigit;
    
    // 🔥 ANTISIPASI DUPLICATE/KEMBAR: Cari apakah baseUid ini sudah pernah dipakai
    $stmtCek = $pdo->prepare("SELECT uid FROM swimmers WHERE uid LIKE ? ORDER BY uid DESC LIMIT 1");
    $stmtCek->execute([$baseUid . '%']);
    $last_uid = $stmtCek->fetchColumn();
    
    if ($last_uid) {
        $last_digit = (int) substr($last_uid, -1);
        $twinDigit = min(9, $last_digit + 1); // naikkan jadi 1, 2, dst jika kembar
    } else {
        $twinDigit = 0;
    }
    
    $newUid = $baseUid . $twinDigit;
    
    // Tembak langsung per baris ke MySQL agar urutannya terbaca real-time oleh loop berikutnya
    try {
        $update = $pdo->prepare("UPDATE swimmers SET uid = ? WHERE id = ?");
        $update->execute([$newUid, $row['id']]);
        echo "<tr><td>{$row['id']}</td><td>{$nama}</td><td style='color:green; font-weight:bold;'>✅ Sukses disimpan: {$newUid}</td></tr>";
    } catch (Exception $e) {
        echo "<tr><td>{$row['id']}</td><td>{$nama}</td><td style='color:red;'>❌ Gagal MySQL: {$e->getMessage()}</td></tr>";
    }
}

echo "</table>";
echo "<h3 style='font-family:sans-serif; color:#2563eb;'>🎉 SELESAI! Silakan buka kembali halaman Web atau phpMyAdmin Anda untuk mengecek hasilnya.</h3>";
?>