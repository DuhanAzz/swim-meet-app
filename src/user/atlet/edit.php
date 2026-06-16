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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama   = trim(strtoupper($_POST['nama_atlet']));
    $sekolah= trim(strtoupper($_POST['asal_sekolah']));
    $jk     = $_POST['jenis_kelamin']; 
    $tgl    = $_POST['tanggal_lahir'];

    try {
        $sql = "UPDATE swimmers SET nama_atlet=?, asal_sekolah=?, jenis_kelamin=?, tanggal_lahir=? WHERE id=? AND user_id=?";
        $update = $pdo->prepare($sql);
        $update->execute([$nama, $sekolah, $jk, $tgl, $id, $_SESSION['user_id']]);
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