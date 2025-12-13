<?php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';
$user_id = $_SESSION['user_id'];

// Ambil ID Klub
$stmt = $pdo->prepare("SELECT id FROM clubs WHERE user_id = ?");
$stmt->execute([$user_id]);
$club = $stmt->fetch();
$club_id = $club['id'] ?? 0;

// Logic: Cari Admin/Event yang mana atlet klub ini pernah masuk
$sql = "SELECT DISTINCT u.nama_lengkap, u.location, u.event_start_date 
        FROM users u
        JOIN events e ON e.user_id = u.id
        JOIN heats h ON h.event_id = e.id
        JOIN heat_entries he ON he.heat_id = h.id
        JOIN swimmers s ON he.swimmer_id = s.id
        WHERE s.club_id = ?
        ORDER BY u.event_start_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$club_id]);
$histories = $stmt->fetchAll();

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>
<div class="p-6 sm:ml-64 mt-16 bg-slate-50 min-h-screen font-sans">
    <h1 class="text-2xl font-black text-slate-800 uppercase mb-6">📜 Riwayat Kompetisi</h1>
    
    <?php if(empty($histories)): ?>
        <div class="bg-white p-12 text-center rounded-xl border border-slate-200 italic text-slate-400">
            Belum ada kompetisi yang diikuti.
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach($histories as $h): ?>
            <div class="bg-white p-5 rounded-xl border border-slate-200 flex items-center gap-4">
                <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center text-2xl">🏁</div>
                <div>
                    <h3 class="font-bold text-slate-800 text-lg"><?= htmlspecialchars($h['nama_lengkap']) ?></h3>
                    <p class="text-sm text-slate-500">📍 <?= htmlspecialchars($h['location']) ?> • 📅 <?= date('d M Y', strtotime($h['event_start_date'])) ?></p>
                </div>
                <div class="ml-auto">
                    <span class="bg-gray-100 text-gray-600 px-3 py-1 rounded text-xs font-bold">Selesai</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
