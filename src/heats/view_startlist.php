<?php
// PERBAIKAN: Cek dulu apakah session sudah aktif agar tidak error "Ignoring session_start"
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

$event_id = $_GET['event_id'];

// Ambil Info Event
$stmtEvent = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmtEvent->execute([$event_id]);
$event = $stmtEvent->fetch();

if (!$event) {
    die("Event tidak ditemukan.");
}

// Ambil Data Heat
$stmtHeats = $pdo->prepare("SELECT * FROM heats WHERE event_id = ? ORDER BY heat_number ASC");
$stmtHeats->execute([$event_id]);
$heats = $stmtHeats->fetchAll();

$startList = [];
foreach($heats as $heat) {
    // Ambil detail atlet per lintasan
    $stmtEntries = $pdo->prepare("
        SELECT heat_entries.*, swimmers.nama_atlet, clubs.nama_klub, entries.seed_time
        FROM heat_entries
        JOIN swimmers ON heat_entries.swimmer_id = swimmers.id
        JOIN clubs ON swimmers.club_id = clubs.id
        JOIN entries ON (entries.swimmer_id = swimmers.id AND entries.event_id = ?)
        WHERE heat_entries.heat_id = ?
        ORDER BY heat_entries.lane_number ASC
    ");
    $stmtEntries->execute([$event_id, $heat['id']]);
    $startList[$heat['heat_number']] = $stmtEntries->fetchAll();
}

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php';
?>

<div class="p-6 sm:ml-64 mt-16">
    <div class="flex justify-between items-center mb-6">
        <div>
            <a href="/swim-meet/src/events/index.php" class="text-blue-600 hover:underline mb-2 block">&larr; Kembali ke Daftar Event</a>
            <h1 class="text-2xl font-bold text-gray-800">Start List: <?= htmlspecialchars($event['nama_event']) ?></h1>
            <p class="text-gray-500">Jarak: <?= $event['jarak'] ?>m | Gaya: <?= $event['gaya'] ?></p>
        </div>
        <button onclick="window.print()" class="bg-gray-800 text-white px-4 py-2 rounded flex items-center gap-2">
            🖨️ Cetak PDF
        </button>
    </div>

    <?php if(empty($startList)): ?>
        <div class="bg-yellow-100 p-4 rounded text-yellow-800 border border-yellow-200">
            <strong>Belum ada seri.</strong> <br>
            Silakan kembali ke halaman sebelumnya dan klik tombol hijau "Buat Seri (Seed)".
        </div>
    <?php else: ?>
        <?php foreach($startList as $heatNum => $entries): ?>
            <div class="mb-8 bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden break-inside-avoid">
                <div class="bg-blue-50 px-6 py-3 border-b border-blue-100 flex justify-between items-center">
                    <h3 class="font-bold text-blue-800 text-lg">SERI <?= $heatNum ?> (Heat <?= $heatNum ?>)</h3>
                    <span class="text-xs text-blue-600 bg-blue-200 px-2 py-1 rounded">8 Lintasan</span>
                </div>
                <table class="w-full text-sm text-left text-gray-600">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-3 w-16 text-center">Lintasan</th>
                            <th class="px-6 py-3">Nama Atlet</th>
                            <th class="px-6 py-3">Klub</th>
                            <th class="px-6 py-3 text-right">Waktu (Seed)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for($lane=1; $lane<=8; $lane++): ?>
                            <?php 
                                $atlet = null;
                                foreach($entries as $e) { if($e['lane_number'] == $lane) $atlet = $e; }
                            ?>
                            <tr class="border-b hover:bg-gray-50 <?= $lane==4 || $lane==5 ? 'bg-yellow-50' : '' ?>">
                                <td class="px-6 py-3 text-center font-bold text-gray-400">L<?= $lane ?></td>
                                <td class="px-6 py-3 font-medium text-gray-900">
                                    <?= $atlet ? htmlspecialchars($atlet['nama_atlet']) : '<span class="text-gray-300">- Kosong -</span>' ?>
                                </td>
                                <td class="px-6 py-3"><?= $atlet ? htmlspecialchars($atlet['nama_klub']) : '' ?></td>
                                <td class="px-6 py-3 text-right font-mono"><?= $atlet ? $atlet['seed_time'] : '' ?></td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
