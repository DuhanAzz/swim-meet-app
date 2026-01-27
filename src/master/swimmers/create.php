<?php
// FILE: src/master/swimmers/create.php
session_start();
require_once __DIR__ . '/../../config/database.php';

// =================================================================================
// BAGIAN 1: PROSES PENYIMPANAN DATA (POST)
// =================================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    try {
        // 1. AMBIL INPUT
        $club_id       = $_POST['club_id'];
        $nama_atlet    = trim($_POST['nama_atlet']);
        $asal_sekolah  = $_POST['asal_sekolah'] ?? '-'; 
        $jenis_kelamin = $_POST['jenis_kelamin'];
        $tanggal_lahir = $_POST['tanggal_lahir'];
        
        // --- PERBAIKAN DISINI ---
        // Sebelumnya 'active' (Ditolak DB), kita ganti jadi 'pending' (Sesuai data lama Anda)
        $status  = 'pending'; 
        
        // Ambil ID User yang login (Jika tidak ada session, set NULL atau 1 sebagai default admin)
        $user_id = $_SESSION['user_id'] ?? 1; 

        // 2. GENERATOR UID (SMART 10 DIGIT)
        // ------------------------------------------------
        function charToCode($char) {
            $char = strtoupper($char);
            $ord = ord($char);
            return ($ord >= 65 && $ord <= 90) ? str_pad($ord - 64, 2, '0', STR_PAD_LEFT) : '00';
        }

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

        if (empty($finalUID)) throw new Exception("GAGAL: UID Penuh/Duplikat.");

        // 3. SIMPAN KE DATABASE
        $sql = "INSERT INTO swimmers (
                    uid, 
                    user_id, 
                    club_id, 
                    nama_atlet, 
                    asal_sekolah, 
                    jenis_kelamin, 
                    tanggal_lahir, 
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $finalUID, 
            $user_id,
            $club_id, 
            $nama_atlet, 
            $asal_sekolah, 
            $jenis_kelamin, 
            $tanggal_lahir,
            $status
        ]);

        header("Location: index.php?msg=success");
        exit();

    } catch (Exception $e) {
        $error_msg = "Error: " . $e->getMessage();
    }
}

// =================================================================================
// BAGIAN 2: PERSIAPAN TAMPILAN
// =================================================================================
try {
    $stmt = $pdo->query("SELECT * FROM clubs");
    $clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error Ambil Data Klub: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Atlet</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background: #f9f9f9; }
        .container { max-width: 500px; margin: 0 auto; background: white; padding: 20px; border: 1px solid #ddd; }
        label { display: block; margin-top: 10px; font-weight: bold; }
        input, select { width: 100%; padding: 8px; margin-top: 5px; box-sizing: border-box; }
        button { margin-top: 20px; width: 100%; padding: 10px; background: #007bff; color: white; border: none; cursor: pointer; }
        .alert { background: #f8d7da; color: #721c24; padding: 10px; margin-bottom: 15px; }
    </style>
</head>
<body>

<div class="container">
    <h2>Tambah Atlet</h2>

    <?php if (isset($error_msg)): ?>
        <div class="alert"><?= $error_msg ?></div>
    <?php endif; ?>

    <form action="" method="POST">
        
        <label>Klub:</label>
        <select name="club_id" required>
            <option value="">-- Pilih Klub --</option>
            <?php foreach ($clubs as $club): ?>
                <?php 
                    $namaKlub = $club['name'] ?? $club['nama'] ?? $club['club_name'] ?? $club['nama_klub'] ?? 'Nama Tidak Terbaca';
                ?>
                <option value="<?= $club['id'] ?>"><?= htmlspecialchars($namaKlub) ?></option>
            <?php endforeach; ?>
        </select>

        <label>Nama Atlet:</label>
        <input type="text" name="nama_atlet" required placeholder="Nama Lengkap">

        <label>Asal Sekolah:</label>
        <input type="text" name="asal_sekolah" placeholder="Nama Sekolah">

        <label>Jenis Kelamin:</label>
        <select name="jenis_kelamin" required>
            <option value="L">Laki-laki</option>
            <option value="P">Perempuan</option>
        </select>

        <label>Tanggal Lahir:</label>
        <input type="date" name="tanggal_lahir" required>

        <button type="submit">SIMPAN DATA</button>
        <p style="text-align: center; margin-top: 10px;"><a href="index.php">Batal</a></p>
    </form>
</div>

</body>
</html>