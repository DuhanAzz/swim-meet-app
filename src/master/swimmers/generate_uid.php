<?php
// FILE: src/master/swimmers/generate_uid.php
require_once __DIR__ . '/../../config/database.php';

// Fungsi Konversi Huruf ke Angka 2 Digit (A=01 ... Z=26)
function charToCode($char) {
    $char = strtoupper($char);
    $ord = ord($char);
    // Pastikan karakter A-Z
    if ($ord >= 65 && $ord <= 90) {
        $val = $ord - 64; 
        return str_pad($val, 2, '0', STR_PAD_LEFT);
    }
    return '00'; // Jika karakter aneh
}

// 1. Ambil data atlet
$sql = "SELECT id, nama_atlet, jenis_kelamin, tanggal_lahir FROM swimmers WHERE uid IS NULL OR uid = ''";
$stmt = $pdo->query($sql);
$swimmers = $stmt->fetchAll();

echo "<html><body style='font-family: monospace; padding: 20px; background: #f0f9ff;'>";
echo "<h2>🚀 START GENERATOR UID (FORMAT 10 DIGIT)</h2>";
echo "<p>Rumus: [Inisial Kata 1 & 2] + [Tahun] + [Gender] + [Seq]</p>";
echo "<div style='background: white; padding: 20px; border-radius: 10px; border: 1px solid #cbd5e1;'>";

$count = 0;

foreach ($swimmers as $s) {
    
    // A. LOGIKA INISIAL NAMA [NNNN]
    // Bersihkan nama dari simbol, sisakan Huruf dan Spasi
    $cleanName = preg_replace('/[^A-Z ]/', '', strtoupper($s['nama_atlet']));
    $words = explode(' ', $cleanName); // Pecah jadi array per kata
    
    // Hapus spasi kosong (akibat spasi ganda)
    $words = array_filter($words); 
    $words = array_values($words); // Re-index array

    if (count($words) >= 2) {
        // KASUS 1: Dua Kata atau Lebih (Contoh: Budi Santoso)
        // Ambil huruf pertama kata ke-1 dan huruf pertama kata ke-2
        $char1 = substr($words[0], 0, 1); // B
        $char2 = substr($words[1], 0, 1); // S
    } else {
        // KASUS 2: Satu Kata Saja (Contoh: Budi)
        // Ambil huruf pertama dan huruf kedua dari kata tersebut
        $char1 = substr($words[0], 0, 1); // B
        $char2 = substr($words[0], 1, 1); // U
        if(empty($char2)) $char2 = 'X'; // Jaga-jaga kalau nama cuma 1 huruf "A"
    }

    $codeName = charToCode($char1) . charToCode($char2);

    // B. LOGIKA TAHUN [YYYY]
    $year = date('Y', strtotime($s['tanggal_lahir']));

    // C. LOGIKA GENDER [G]
    $gender = ($s['jenis_kelamin'] == 'L') ? '1' : '9';

    // D. RANGKAI FORMAT DASAR (9 Digit)
    $baseID = $codeName . $year . $gender;

    // E. LOGIKA SEQUENCE [S] (0-9)
    $finalID = '';
    $found = false;

    for ($seq = 0; $seq <= 9; $seq++) {
        $tryID = $baseID . $seq;

        // Cek ke Database: Apakah ID ini sudah dipakai orang LAIN?
        $check = $pdo->prepare("SELECT id FROM swimmers WHERE uid = ? AND id != ?");
        $check->execute([$tryID, $s['id']]);

        if ($check->rowCount() == 0) {
            $finalID = $tryID;
            $found = true;
            break; // Ketemu slot kosong!
        }
    }

    if (!$found) {
        // Darurat jika ada > 10 orang kembar identik (Sangat jarang)
        $finalID = $baseID . 'X'; 
    }

    // F. UPDATE DATABASE
    $upd = $pdo->prepare("UPDATE swimmers SET uid = ? WHERE id = ?");
    $upd->execute([$finalID, $s['id']]);

    // TAMPILKAN LOG
    echo "<div style='border-bottom: 1px dashed #eee; padding: 5px 0;'>";
    echo "Nama: <b>{$s['nama_atlet']}</b> | ";
    echo "Inisial: <span style='color:orange'>$char1 & $char2</span> | ";
    echo "UID: <b style='color:blue; font-size:1.1em;'>$finalID</b>";
    echo "</div>";

    $count++;
}

echo "</div>";
echo "<h3>✅ SELESAI! $count atlet berhasil mendapatkan Smart ID.</h3>";
echo "</body></html>";
?>