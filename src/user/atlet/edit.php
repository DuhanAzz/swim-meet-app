<?php
// src/user/atlet/edit.php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../../public/login.php"); exit;
}

$id = $_GET['id'] ?? null;
if (!$id) { header("Location: index.php"); exit; }

$stmt = $pdo->prepare("SELECT * FROM swimmers WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $_SESSION['user_id']]);
$atlet = $stmt->fetch();

if (!$atlet) { die("Data tidak ditemukan."); }

/**
 * ========================================================
 * FUNGSI GENERATE UID ATLET (Copy dari create.php)
 * ========================================================
 */
function generateSwimmerUID($pdo, $nama_atlet, $tanggal_lahir, $jenis_kelamin) {
    $nama_bersih = preg_replace('/[^A-Za-z\s]/', '', strtoupper(trim($nama_atlet)));
    $kata = explode(' ', $nama_bersih);
    
    $huruf1 = isset($kata[0][0]) ? $kata[0][0] : 'A';
    $kode1 = str_pad(ord($huruf1) - 64, 2, '0', STR_PAD_LEFT); 
    
    if (isset($kata[1]) && !empty($kata[1])) {
        $huruf2 = $kata[1][0];
        $kode2 = str_pad(ord($huruf2) - 64, 2, '0', STR_PAD_LEFT);
    } else {
        $kode2 = '00'; 
    }
    
    $tahun = date('Y', strtotime($tanggal_lahir));
    $kode_jk = (strtoupper($jenis_kelamin) == 'L' || strtoupper($jenis_kelamin) == 'M') ? '1' : '9';
    
    $base_uid = $kode1 . $kode2 . $tahun . $kode_jk;
    
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama   = trim(strtoupper($_POST['nama_atlet']));
    $sekolah= trim(strtoupper($_POST['asal_sekolah']));
    $jk     = $_POST['jenis_kelamin']; 
    $tgl    = $_POST['tanggal_lahir'];

    try {
        // 🔥 LOGIKA PENGECEKAN UID 🔥
        // Cek apakah di database atlet ini kolom 'uid'-nya masih kosong/NULL
        if (empty($atlet['uid'])) {
            // Jika kosong (Data Lama), buatkan UID baru!
            $uid_baru = generateSwimmerUID($pdo, $nama, $tgl, $jk);
            
            $sql = "UPDATE swimmers SET uid=?, nama_atlet=?, asal_sekolah=?, jenis_kelamin=?, tanggal_lahir=? WHERE id=? AND user_id=?";
            $update = $pdo->prepare($sql);
            $update->execute([$uid_baru, $nama, $sekolah, $jk, $tgl, $id, $_SESSION['user_id']]);
        } else {
            // Jika sudah ada UID, JANGAN UBAH UID-nya (Cukup update nama dll)
            $sql = "UPDATE swimmers SET nama_atlet=?, asal_sekolah=?, jenis_kelamin=?, tanggal_lahir=? WHERE id=? AND user_id=?";
            $update = $pdo->prepare($sql);
            $update->execute([$nama, $sekolah, $jk, $tgl, $id, $_SESSION['user_id']]);
        }

        header("Location: index.php?msg=updated"); exit;
    } catch (PDOException $e) {
        $error = "Gagal update: " . $e->getMessage();
    }
}

include __DIR__ . '/../../../views/layout/topbar.php';
include __DIR__ . '/../../../views/layout/sidebar.php';
?>
<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen">
    <div class="max-w-2xl mx-auto bg-white rounded-2xl shadow-sm border border-slate-200 p-8">
        <h2 class="text-2xl font-black uppercase italic mb-6">Edit Data Atlet</h2>
        
        <?php if(isset($error)): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-4 text-sm font-bold"><?= $error ?></div>
        <?php endif; ?>

        <?php if(!empty($atlet['uid'])): ?>
            <div class="mb-6 p-4 bg-blue-50 border border-blue-100 rounded-xl flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-black text-blue-400 uppercase tracking-widest mb-1">UID Atlet (ID Unik)</p>
                    <p class="font-mono text-lg font-bold text-blue-900"><?= htmlspecialchars($atlet['uid']) ?></p>
                </div>
                <div class="text-3xl opacity-20">🪪</div>
            </div>
        <?php else: ?>
            <div class="mb-6 p-4 bg-amber-50 border border-amber-100 rounded-xl">
                <p class="text-xs font-bold text-amber-700">⚠️ Atlet ini belum memiliki UID. Menyimpan perubahan data ini akan otomatis membuatkan UID baru untuk atlet.</p>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-4">
                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase">Nama Lengkap</label>
                <input type="text" name="nama_atlet" required value="<?= htmlspecialchars($atlet['nama_atlet']) ?>" class="w-full border-2 border-slate-100 rounded-xl p-3 focus:border-blue-500 outline-none uppercase">
            </div>
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-2 uppercase">Jenis Kelamin</label>
                    <select name="jenis_kelamin" required class="w-full border-2 border-slate-100 rounded-xl p-3 focus:border-blue-500 outline-none">
                        <option value="L" <?= (in_array(strtoupper($atlet['jenis_kelamin']), ['L','M','MALE'])) ? 'selected' : '' ?>>PUTRA (Laki-laki)</option>
                        <option value="P" <?= (in_array(strtoupper($atlet['jenis_kelamin']), ['P','F','FEMALE'])) ? 'selected' : '' ?>>PUTRI (Perempuan)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-2 uppercase">Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" required value="<?= htmlspecialchars($atlet['tanggal_lahir']) ?>" class="w-full border-2 border-slate-100 rounded-xl p-3 focus:border-blue-500 outline-none">
                </div>
            </div>
            <div class="mb-6">
                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase">Asal Sekolah / Klub</label>
                <input type="text" name="asal_sekolah" value="<?= htmlspecialchars($atlet['asal_sekolah'] ?? '') ?>" class="w-full border-2 border-slate-100 rounded-xl p-3 focus:border-blue-500 outline-none uppercase">
            </div>
            <div class="flex gap-4">
                <a href="index.php" class="w-1/3 text-center py-3 rounded-xl border border-slate-200 font-bold text-slate-500 hover:bg-slate-50">Batal</a>
                <button type="submit" class="w-2/3 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl uppercase">Update Data</button>
            </div>
        </form>
    </div>
</div>