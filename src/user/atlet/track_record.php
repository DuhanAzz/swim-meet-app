<?php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') die("Akses Ditolak.");

$swimmer_id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM swimmers WHERE id = ?");
$stmt->execute([$swimmer_id]);
$swimmer = $stmt->fetch();
if(!$swimmer) die("Atlet tidak ditemukan.");

// Tambah Record
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $event = $_POST['nama_event'];
    $nomor = $_POST['nomor_lomba'];
    $waktu = $_POST['waktu'];
    $tgl   = $_POST['tanggal'];
    $lok   = $_POST['lokasi'];
    
    $stmt = $pdo->prepare("INSERT INTO swimmer_records (swimmer_id, nama_event, nomor_lomba, waktu, tanggal, lokasi) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$swimmer_id, $event, $nomor, $waktu, $tgl, $lok]);
    header("Location: track_record.php?id=$swimmer_id"); exit;
}

// List Records
$records = $pdo->prepare("SELECT * FROM swimmer_records WHERE swimmer_id = ? ORDER BY tanggal DESC");
$records->execute([$swimmer_id]);
$rows = $records->fetchAll();

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 mt-16 bg-slate-50 min-h-screen font-sans">
    <div class="flex items-center gap-4 mb-6">
        <a href="index.php" class="text-slate-400 hover:text-slate-600 text-2xl">&larr;</a>
        <div>
            <h1 class="text-2xl font-black text-slate-800 uppercase">Track Record</h1>
            <p class="text-blue-600 font-bold"><?= htmlspecialchars($swimmer['nama_atlet']) ?></p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 h-fit">
            <h3 class="font-bold text-slate-700 mb-4 border-b pb-2">Tambah Catatan Waktu</h3>
            <form method="POST" class="space-y-4">
                <div><label class="block text-xs font-bold text-slate-500">Nama Event</label><input type="text" name="nama_event" class="w-full border rounded p-2 text-sm" placeholder="Contoh: Kejurda 2024" required></div>
                <div><label class="block text-xs font-bold text-slate-500">Nomor Lomba</label><input type="text" name="nomor_lomba" class="w-full border rounded p-2 text-sm" placeholder="Contoh: 50m Bebas" required></div>
                <div><label class="block text-xs font-bold text-slate-500">Waktu (MM:SS.ms)</label><input type="text" name="waktu" class="w-full border rounded p-2 text-sm font-mono" placeholder="00:29.50" required></div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="block text-xs font-bold text-slate-500">Tanggal</label><input type="date" name="tanggal" class="w-full border rounded p-2 text-sm"></div>
                    <div><label class="block text-xs font-bold text-slate-500">Lokasi</label><input type="text" name="lokasi" class="w-full border rounded p-2 text-sm"></div>
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white font-bold py-2 rounded hover:bg-blue-700">Simpan Record</button>
            </form>
        </div>

        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 border-b text-slate-500 font-bold uppercase text-xs">
                    <tr>
                        <th class="px-4 py-3">Event</th>
                        <th class="px-4 py-3">Nomor</th>
                        <th class="px-4 py-3 text-right">Waktu</th>
                        <th class="px-4 py-3 text-right">Tanggal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if(empty($rows)): ?>
                        <tr><td colspan="4" class="p-6 text-center text-slate-400 italic">Belum ada catatan waktu.</td></tr>
                    <?php else: ?>
                        <?php foreach($rows as $r): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-bold text-slate-700"><?= htmlspecialchars($r['nama_event']) ?></td>
                            <td class="px-4 py-3"><?= htmlspecialchars($r['nomor_lomba']) ?></td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-blue-600"><?= $r['waktu'] ?></td>
                            <td class="px-4 py-3 text-right text-xs text-slate-500"><?= date('d M Y', strtotime($r['tanggal'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
