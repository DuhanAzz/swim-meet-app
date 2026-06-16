<?php
// src/user/atlet/create.php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../../public/login.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId       = $_SESSION['user_id'];
    $nama_atlet   = trim(strtoupper($_POST['nama_atlet']));
    $jenis_kelamin= $_POST['jenis_kelamin'];
    $tanggal_lahir= $_POST['tanggal_lahir'];
    $asal_sekolah = trim(strtoupper($_POST['asal_sekolah']));

    if (!empty($nama_atlet) && !empty($jenis_kelamin) && !empty($tanggal_lahir)) {
        try {
            $sql = "INSERT INTO swimmers (user_id, nama_atlet, jenis_kelamin, tanggal_lahir, asal_sekolah, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId, $nama_atlet, $jenis_kelamin, $tanggal_lahir, $asal_sekolah]);
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