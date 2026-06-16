<?php
// src/user/atlet/records.php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];
$atlet_id = $_GET['id'] ?? 0;

// Validasi Kepemilikan Atlet
$stmtCek = $pdo->prepare("SELECT * FROM swimmers WHERE id = ? AND user_id = ?");
$stmtCek->execute([$atlet_id, $uid]);
$atlet = $stmtCek->fetch();

if (!$atlet) { die("Atlet tidak ditemukan atau Anda tidak memiliki akses."); }

// HANDLE TAMBAH RECORD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_record'])) {
    $nomor_lomba = $_POST['distance'] . 'M ' . strtoupper($_POST['stroke']);
    $waktu = $_POST['time_record'];
    $date  = $_POST['record_date'];

    try {
        $stmtIns = $pdo->prepare("INSERT INTO athlete_records (swimmer_id, nomor_lomba, waktu_terbaik, tanggal_dicapai) VALUES (?, ?, ?, ?)");
        $stmtIns->execute([$atlet_id, $nomor_lomba, $waktu, $date]);
        header("Location: records.php?id=$atlet_id&msg=added"); exit;
    } catch(PDOException $e) {
        $error = "Gagal menambah rekor!";
    }
}

// HANDLE HAPUS RECORD
if (isset($_GET['delete_id'])) {
    $delId = $_GET['delete_id'];
    $stmtDel = $pdo->prepare("DELETE FROM athlete_records WHERE id = ? AND swimmer_id = ?");
    $stmtDel->execute([$delId, $atlet_id]);
    header("Location: records.php?id=$atlet_id&msg=deleted"); exit;
}

// AMBIL DATA REKOR
$stmtRec = $pdo->prepare("SELECT * FROM athlete_records WHERE swimmer_id = ? ORDER BY tanggal_dicapai DESC");
$stmtRec->execute([$atlet_id]);
$records = $stmtRec->fetchAll();

include __DIR__ . '/../../../views/layout/topbar.php';
include __DIR__ . '/../../../views/layout/sidebar.php';
?>
<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen">
    <div class="mb-6 flex gap-3 items-center">
        <a href="index.php" class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-4 py-2 rounded-lg font-bold text-sm">⬅ Kembali</a>
        <div>
            <h1 class="text-2xl font-black uppercase italic">Kelola Rekor Waktu</h1>
            <p class="text-xs font-bold text-slate-500 uppercase">Atlet: <?= htmlspecialchars($atlet['nama_atlet']) ?></p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 h-fit">
            <h3 class="font-black text-sm uppercase mb-4 text-slate-800">Tambah Best Time</h3>
            <form method="POST" action="">
                <div class="grid grid-cols-2 gap-2 mb-3">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Jarak</label>
                        <select name="distance" class="w-full border-2 border-slate-100 rounded-lg p-2 text-xs font-bold outline-none">
                            <option value="25">25M</option>
                            <option value="50">50M</option>
                            <option value="100">100M</option>
                            <option value="200">200M</option>
                            <option value="400">400M</option>
                            <option value="800">800M</option>
                            <option value="1500">1500M</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Gaya</label>
                        <select name="stroke" class="w-full border-2 border-slate-100 rounded-lg p-2 text-xs font-bold outline-none">
                            <option value="BEBAS">Bebas</option>
                            <option value="KUPU-KUPU">Kupu-Kupu</option>
                            <option value="PUNGGUNG">Punggung</option>
                            <option value="DADA">Dada</option>
                            <option value="GANTI">Ganti</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Catatan Waktu</label>
                    <input type="text" name="time_record" placeholder="00:00.00" required class="w-full border-2 border-slate-100 rounded-lg p-2 text-xs font-mono font-bold outline-none placeholder:text-slate-300 text-center">
                </div>
                <div class="mb-4">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Tanggal Dicapai</label>
                    <input type="date" name="record_date" required class="w-full border-2 border-slate-100 rounded-lg p-2 text-xs font-bold outline-none text-center">
                </div>
                <button type="submit" name="add_record" class="w-full bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-2 rounded-lg text-xs uppercase transition">Simpan Rekor</button>
            </form>
        </div>

        <div class="md:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="p-4 text-xs font-black text-slate-400 uppercase">Nomor Lomba</th>
                        <th class="p-4 text-xs font-black text-slate-400 uppercase text-center">Best Time</th>
                        <th class="p-4 text-xs font-black text-slate-400 uppercase">Tanggal</th>
                        <th class="p-4 text-xs font-black text-slate-400 uppercase text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if(empty($records)): ?>
                        <tr><td colspan="4" class="p-8 text-center text-sm font-bold text-slate-400 italic">Belum ada rekor waktu dicatat.</td></tr>
                    <?php else: ?>
                        <?php foreach($records as $r): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="p-4 font-bold text-sm uppercase text-slate-700"><?= htmlspecialchars($r['nomor_lomba']) ?></td>
                                <td class="p-4 text-center font-mono font-black text-lg text-blue-600"><?= htmlspecialchars($r['waktu_terbaik']) ?></td>
                                <td class="p-4 text-xs font-bold text-slate-500"><?= date('d M Y', strtotime($r['tanggal_dicapai'])) ?></td>
                                <td class="p-4 text-center">
                                    <a href="?id=<?= $atlet_id ?>&delete_id=<?= $r['id'] ?>" onclick="return confirm('Hapus rekor ini?')" class="text-red-500 bg-red-50 hover:bg-red-500 hover:text-white px-3 py-1.5 rounded-md text-xs font-bold transition">Hapus</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>