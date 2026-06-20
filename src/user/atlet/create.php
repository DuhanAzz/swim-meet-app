<?php
// src/user/atlet/create.php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../../public/login.php"); exit;
}

/**
 * ========================================================
 * FUNGSI GENERATE UID ATLET
 * Format: [Inisial1][Inisial2][TahunLahir][Gender][Urutan]
 * ========================================================
 */
function generateSwimmerUID($pdo, $nama_atlet, $tanggal_lahir, $jenis_kelamin) {
    // 1. Bersihkan nama dari karakter aneh, jadikan huruf besar
    $nama_bersih = preg_replace('/[^A-Za-z\s]/', '', strtoupper(trim($nama_atlet)));
    $kata = explode(' ', $nama_bersih);
    
    // 2. Kode Inisial 1
    $huruf1 = isset($kata[0][0]) ? $kata[0][0] : 'A';
    $kode1 = str_pad(ord($huruf1) - 64, 2, '0', STR_PAD_LEFT); 
    
    // 3. Kode Inisial 2 (Jika namanya cuma 1 kata, ambil huruf kedua dari kata pertama)
    if (isset($kata[1]) && !empty($kata[1])) {
        $huruf2 = $kata[1][0];
    } else {
        $huruf2 = isset($kata[0][1]) ? $kata[0][1] : 'X'; 
    }
    $kode2 = str_pad(ord($huruf2) - 64, 2, '0', STR_PAD_LEFT);
    
    // 4. Tahun Lahir
    $tahun = date('Y', strtotime($tanggal_lahir));
    
    // 5. Kode Jenis Kelamin (L = 1, P = 9)
    $kode_jk = (strtoupper($jenis_kelamin) == 'L' || strtoupper($jenis_kelamin) == 'M') ? '1' : '9';
    
    // BASE UID SEMENTARA
    $base_uid = $kode1 . $kode2 . $tahun . $kode_jk;
    
    // 6. Cek ke database untuk mencegah bentrok
    $stmt = $pdo->prepare("SELECT uid FROM swimmers WHERE uid LIKE ? ORDER BY uid DESC LIMIT 1");
    $stmt->execute([$base_uid . '%']);
    $last_uid = $stmt->fetchColumn();
    
    $digit_akhir = 0;
    if ($last_uid) {
        $last_digit = (int) substr($last_uid, -1);
        $digit_akhir = $last_digit + 1;
        if ($digit_akhir > 9) {
            $digit_akhir = 9; 
        }
    }
    
    return $base_uid . $digit_akhir;
}

// ========================================================
// PROSES FORM SUBMIT
// ========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId       = $_SESSION['user_id'];
    $nama_atlet   = trim(strtoupper($_POST['nama_atlet']));
    $jenis_kelamin= $_POST['jenis_kelamin'];
    $tanggal_lahir= $_POST['tanggal_lahir'];
    $asal_sekolah = trim(strtoupper($_POST['asal_sekolah']));

    if (!empty($nama_atlet) && !empty($jenis_kelamin) && !empty($tanggal_lahir)) {
        try {
            // 🔥 Generate UID Baru disini 🔥
            $uid_baru = generateSwimmerUID($pdo, $nama_atlet, $tanggal_lahir, $jenis_kelamin);

            // Perubahan: Tambahkan kolom "uid" pada query INSERT
            $sql = "INSERT INTO swimmers (uid, user_id, nama_atlet, jenis_kelamin, tanggal_lahir, asal_sekolah, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            
            // Masukkan variabel $uid_baru di urutan paling depan sesuai tanda "?"
            $stmt->execute([$uid_baru, $userId, $nama_atlet, $jenis_kelamin, $tanggal_lahir, $asal_sekolah]);
            
            header("Location: index.php?msg=added"); exit;
        } catch (PDOException $e) {
            $error = "Gagal menyimpan data: " . $e->getMessage();
        }
    } else {
        $error = "Data penting wajib diisi!";
    }
}

include __DIR__ . '/../../../views/layout/topbar.php';
include __DIR__ . '/../../../views/layout/sidebar.php';
?>
<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen">
    <div class="max-w-2xl mx-auto bg-white rounded-2xl shadow-sm border border-slate-200 p-8">
        <h2 class="text-2xl font-black uppercase italic mb-6">Tambah Atlet Baru</h2>
        <?php if(isset($error)): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-4 text-sm font-bold"><?= $error ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="mb-4">
                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase">Nama Lengkap</label>
                <input type="text" name="nama_atlet" required class="w-full border-2 border-slate-100 rounded-xl p-3 focus:border-blue-500 outline-none uppercase">
            </div>
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-2 uppercase">Jenis Kelamin</label>
                    <select name="jenis_kelamin" required class="w-full border-2 border-slate-100 rounded-xl p-3 focus:border-blue-500 outline-none">
                        <option value="L">PUTRA (Laki-laki)</option>
                        <option value="P">PUTRI (Perempuan)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-2 uppercase">Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" required class="w-full border-2 border-slate-100 rounded-xl p-3 focus:border-blue-500 outline-none">
                </div>
            </div>
            <div class="mb-6">
                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase">Asal Sekolah / Klub</label>
                <input type="text" name="asal_sekolah" class="w-full border-2 border-slate-100 rounded-xl p-3 focus:border-blue-500 outline-none uppercase" placeholder="Contoh: SMPN 1 YOGYAKARTA">
            </div>
            <div class="flex gap-4">
                <a href="index.php" class="w-1/3 text-center py-3 rounded-xl border border-slate-200 font-bold text-slate-500 hover:bg-slate-50">Batal</a>
                <button type="submit" class="w-2/3 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl uppercase">Simpan Atlet</button>
            </div>
        </form>
    </div>
</div>