<?php
// FILE: src/master/swimmers/store.php
session_start();
require_once __DIR__ . '/../../config/database.php';

// =================================================================
// [DEBUG MODE] - HAPUS BARIS INI JIKA SUDAH MUNCUL ANGKA UID
// die("BERHASIL MASUK STORE.PHP! File sudah benar terhubung."); 
// =================================================================

function charToCode($char) {
    $char = strtoupper($char);
    $ord = ord($char);
    if ($ord >= 65 && $ord <= 90) {
        $val = $ord - 64; 
        return str_pad($val, 2, '0', STR_PAD_LEFT);
    }
    return '00'; 
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $club_id          = $_POST['club_id'] ?? null;
    $nama_atlet       = trim($_POST['nama_atlet']);
    $asal_sekolah     = $_POST['asal_sekolah'] ?? ''; 
    $jenis_kelamin    = $_POST['jenis_kelamin'];
    $tanggal_lahir    = $_POST['tanggal_lahir'];
    $nomor_registrasi = $_POST['nomor_registrasi'] ?? '-';
    
    // --- GENERATOR UID 10 DIGIT ---
    $cleanName = preg_replace('/[^A-Z ]/', '', strtoupper($nama_atlet));
    $words = array_values(array_filter(explode(' ', $cleanName)));
    
    if (count($words) >= 2) {
        $char1 = substr($words[0], 0, 1); 
        $char2 = substr($words[1], 0, 1); 
    } else {
        $char1 = substr($words[0], 0, 1); 
        $char2 = substr($words[0], 1, 1) ?: 'X'; 
    }
    
    $codeName = charToCode($char1) . charToCode($char2);
    $year = date('Y', strtotime($tanggal_lahir));
    $genderCode = ($jenis_kelamin == 'L') ? '1' : '9';
    $baseID = $codeName . $year . $genderCode;

    $finalUID = '';
    for ($i = 0; $i <= 9; $i++) {
        $tryID = $baseID . $i;
        $cek = $pdo->prepare("SELECT COUNT(*) FROM swimmers WHERE uid = ?");
        $cek->execute([$tryID]);
        if ($cek->fetchColumn() == 0) {
            $finalUID = $tryID; break;
        }
    }

    if (empty($finalUID)) die("ERROR: UID Gagal dibuat (Penuh/Duplikat).");

    // --- UPLOAD FOTO ---
    $fotoName = null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $newName = time() . '_' . $club_id . '_' . uniqid() . '.' . $ext;
            $dest = __DIR__ . '/../../../public/uploads/swimmers/' . $newName;
            if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0755, true);
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $dest)) {
                $fotoName = $newName;
            }
        }
    }

    // --- SIMPAN DATABASE ---
    try {
        // Query disesuaikan dengan struktur tabel Anda
        // Kolom user_id sepertinya otomatis (default), jadi kita insert UID, Club, Nama, dll
        $sql = "INSERT INTO swimmers (
                    uid, club_id, nama_atlet, asal_sekolah, 
                    jenis_kelamin, tanggal_lahir, nomor_registrasi, foto
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $finalUID, 
            $club_id, 
            $nama_atlet, 
            $asal_sekolah,
            $jenis_kelamin, 
            $tanggal_lahir, 
            $nomor_registrasi, 
            $fotoName
        ]);

        header("Location: index.php?msg=success");
        exit();

    } catch (PDOException $e) {
        echo "Error SQL: " . $e->getMessage();
        die();
    }
}
?>