<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') die("Akses Ditolak.");
$id = $_GET['id'] ?? null;
if (!$id) { header("Location: index.php"); exit; }

$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $_SESSION['user_id']]);
$event = $stmt->fetch();
if (!$event) die("Data tidak ditemukan.");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Ambil Input
    $nomor  = $_POST['nomor_acara'];
    $jarak  = $_POST['jarak'];
    $gaya   = $_POST['gaya'];
    $bawah  = $_POST['batas_umur_bawah'];
    $atas   = $_POST['batas_umur_atas'];
    $jk     = $_POST['jenis_kelamin'];
    
    // 2. GENERATE ULANG NAMA (Agar konsisten jika ada perubahan jarak/gaya)
    $ku_label = "KU " . $bawah . "-" . $atas;
    $nama_generated = "$nomor - $jarak" . "M " . "$gaya - $ku_label";

    $tgl    = $_POST['tanggal_lomba'];
    $harga  = $_POST['harga_pendaftaran'];

    $sql = "UPDATE events SET nomor_acara=?, nama_event=?, jarak=?, gaya=?, jenis_kelamin=?, tanggal_lomba=?, batas_umur_bawah=?, batas_umur_atas=?, harga_pendaftaran=? WHERE id=?";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nomor, $nama_generated, $jarak, $gaya, $jk, $tgl, $bawah, $atas, $harga, $id]);
        $_SESSION['toast_type'] = 'success'; $_SESSION['toast_message'] = 'Data lomba diperbarui & Nama diregenerate!';
        header("Location: index.php"); exit();
    } catch (PDOException $e) {
        $_SESSION['toast_type'] = 'error'; $_SESSION['toast_message'] = 'Gagal: ' . $e->getMessage();
    }
}

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 mt-16 bg-slate-50 min-h-screen font-sans">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <div><h1 class="text-2xl font-black text-slate-800 tracking-tight uppercase">Edit Nomor Lomba</h1><p class="text-sm text-slate-500">Perbarui detail perlombaan.</p></div>
        <a href="index.php" class="text-slate-500 hover:text-blue-600 font-bold text-sm flex items-center gap-2 transition hover:-translate-x-1"><span>&larr;</span> Kembali</a>
    </div>

    <div class="bg-white p-8 rounded-xl shadow-sm border border-slate-200 max-w-4xl">
        <form method="POST" class="space-y-8">
            
            <div class="p-6 bg-yellow-50/50 rounded-xl border border-yellow-100">
                <h3 class="text-sm font-bold text-yellow-800 uppercase tracking-wider mb-4 border-b border-yellow-200 pb-2">
                    <span class="mr-2">✏️</span> Edit Komponen (Nama akan berubah otomatis)
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-slate-700 font-bold mb-2 text-xs uppercase">No. Acara</label>
                        <input type="number" name="nomor_acara" value="<?= htmlspecialchars($event['nomor_acara'] ?? '') ?>" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-center font-black text-lg" required>
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-slate-700 font-bold mb-2 text-xs uppercase">Jarak</label>
                        <select name="jarak" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg font-bold bg-white">
                            <?php foreach(['25','50','100','200','400','800','1500','4x50','4x100'] as $j): ?>
                                <option value="<?= $j ?>" <?= ($event['jarak'] == $j) ? 'selected' : '' ?>><?= $j ?> Meter</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-4">
                        <label class="block text-slate-700 font-bold mb-2 text-xs uppercase">Gaya</label>
                        <select name="gaya" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg font-bold bg-white">
                            <?php foreach(['Gaya Bebas','Gaya Dada','Gaya Punggung','Gaya Kupu-kupu','Gaya Ganti','Estafet Bebas','Estafet Ganti','Kaki Bebas'] as $g): ?>
                                <option value="<?= $g ?>" <?= ($event['gaya'] == $g) ? 'selected' : '' ?>><?= $g ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-slate-700 font-bold mb-2 text-xs uppercase">KU (Min-Max)</label>
                        <div class="flex items-center gap-2">
                            <input type="number" name="batas_umur_bawah" value="<?= $event['batas_umur_bawah'] ?>" class="w-full px-2 py-2.5 border border-slate-300 rounded-lg text-center text-sm" required>
                            <span>-</span>
                            <input type="number" name="batas_umur_atas" value="<?= $event['batas_umur_atas'] ?>" class="w-full px-2 py-2.5 border border-slate-300 rounded-lg text-center text-sm" required>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-4 border-b pb-2">Detail Lain</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-slate-700 font-bold mb-2 text-sm">Jenis Kelamin</label>
                        <select name="jenis_kelamin" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-bold bg-slate-50">
                            <option value="L" <?= ($event['jenis_kelamin']=='L')?'selected':'' ?>>Putra (Male)</option>
                            <option value="P" <?= ($event['jenis_kelamin']=='P')?'selected':'' ?>>Putri (Female)</option>
                            <option value="Campuran" <?= ($event['jenis_kelamin']=='Campuran')?'selected':'' ?>>Campuran</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-700 font-bold mb-2 text-sm">Tanggal</label>
                        <input type="date" name="tanggal_lomba" value="<?= $event['tanggal_lomba'] ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" required>
                    </div>
                    <div>
                        <label class="block text-slate-700 font-bold mb-2 text-sm">Biaya (Rp)</label>
                        <input type="number" name="harga_pendaftaran" value="<?= $event['harga_pendaftaran'] ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" required>
                    </div>
                </div>
            </div>

            <div class="flex justify-end pt-4"><button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-8 rounded-lg shadow-lg transition transform hover:-translate-y-0.5">Simpan Perubahan</button></div>
        </form>
    </div>
</div>
