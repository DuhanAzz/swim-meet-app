<?php
// FILE: src/admin/seeding/list_clubs_recap.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// Cek Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$event_id = $_GET['event_id'] ?? 0;
if ($event_id == 0) {
    die("Event ID tidak valid.");
}

// Ambil info nama event
$stmtEvt = $pdo->prepare("SELECT event_name FROM events WHERE id = ?");
$stmtEvt->execute([$event_id]);
$event = $stmtEvt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    die("Event tidak ditemukan.");
}

// Ambil daftar klub yang memiliki peserta di event ini
$sql = "SELECT u.id as club_user_id, u.nama_lengkap as nama_klub, u.location as kota,
               COUNT(DISTINCT s.id) as total_atlet
        FROM users u
        JOIN swimmers s ON u.id = s.user_id
        JOIN event_entries ee ON s.id = ee.swimmer_id
        WHERE ee.event_id = ? AND ee.category_id IS NOT NULL
        GROUP BY u.id
        ORDER BY u.nama_lengkap ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$event_id]);
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">
    <div class="max-w-4xl mx-auto mb-6 flex flex-col md:flex-row justify-between items-start gap-4">
        <div>
            <h1 class="text-3xl font-black uppercase tracking-tighter italic text-slate-900 leading-none">Recap Starting List</h1>
            <p class="text-sm text-slate-500 font-bold uppercase tracking-widest mt-2"><?= htmlspecialchars($event['event_name']) ?></p>
        </div>
        <a href="index.php?event_id=<?= $event_id ?>" class="px-6 py-3 bg-white text-slate-600 rounded-xl font-bold text-xs uppercase border border-slate-200 hover:bg-slate-50 transition shadow-sm inline-flex items-center gap-2">
            🔙 Kembali
        </a>
    </div>

    <div class="max-w-4xl mx-auto space-y-4">
        <?php if(empty($clubs)): ?>
            <div class="text-center py-20 bg-white rounded-2xl border border-slate-200">
                <p class="text-slate-400 font-bold italic">Belum ada klub yang terdaftar pada event ini.</p>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-[2rem] border border-slate-200 shadow-sm overflow-hidden">
                <table class="w-full text-left">
                    <thead class="bg-slate-900 text-white">
                        <tr>
                            <th class="py-4 px-6 text-xs font-black uppercase tracking-widest">Nama Klub</th>
                            <th class="py-4 px-6 text-xs font-black uppercase tracking-widest text-center w-32">Total Atlet</th>
                            <th class="py-4 px-6 text-xs font-black uppercase tracking-widest text-right w-40">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach($clubs as $c): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="py-4 px-6">
                                    <div class="font-bold text-sm text-slate-800 uppercase"><?= htmlspecialchars($c['nama_klub']) ?></div>
                                    <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider"><?= htmlspecialchars($c['kota'] ?? '-') ?></div>
                                </td>
                                <td class="py-4 px-6 text-center font-mono font-black text-slate-700">
                                    <?= $c['total_atlet'] ?>
                                </td>
                                <td class="py-4 px-6 text-right">
                                    <a href="print_recap_club.php?event_id=<?= $event_id ?>&club_id=<?= $c['club_user_id'] ?>" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-600 hover:text-white rounded-lg text-[10px] font-black uppercase tracking-widest transition shadow-sm">
                                        👁️ View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
