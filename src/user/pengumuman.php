<?php
session_start();
require_once __DIR__ . '/../../src/config/database.php';
$user_id = $_SESSION['user_id'];

// Ambil ID Klub
$stmt = $pdo->prepare("SELECT id FROM clubs WHERE user_id = ?");
$stmt->execute([$user_id]);
$club = $stmt->fetch();
$club_id = $club['id'] ?? 0;

// Ambil Semua Dokumen
$sql = "SELECT d.*, e.nama_event, u.nama_lengkap as penyelenggara
        FROM documents d
        LEFT JOIN events e ON d.event_id = e.id
        JOIN users u ON d.user_id = u.id
        ORDER BY d.created_at DESC";
$docs = $pdo->query($sql)->fetchAll();

// Cek Event mana yang diikuti user
$joinedEvents = [];
if($club_id) {
    $stmt = $pdo->prepare("SELECT DISTINCT event_id FROM heats h JOIN heat_entries he ON h.id = he.heat_id JOIN swimmers s ON he.swimmer_id = s.id WHERE s.club_id = ?");
    $stmt->execute([$club_id]);
    $joinedEvents = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>
<div class="p-6 sm:ml-64 mt-16 bg-slate-50 min-h-screen font-sans">
    <h1 class="text-2xl font-black text-slate-800 uppercase mb-6">Papan Pengumuman</h1>
    
    <div class="grid grid-cols-1 gap-4">
        <?php foreach($docs as $d): 
            $isJoined = in_array($d['event_id'], $joinedEvents);
        ?>
        <div class="bg-white p-5 rounded-xl border <?= $isJoined ? 'border-l-4 border-l-blue-500 border-slate-200 shadow-md' : 'border-slate-200' ?> flex items-center justify-between group">
            <div class="flex items-start gap-4">
                <div class="bg-slate-100 p-3 rounded-lg text-2xl">📄</div>
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-slate-100 text-slate-500"><?= $d['kategori'] ?></span>
                        <?php if($isJoined): ?>
                            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-blue-100 text-blue-600">Event Diikuti</span>
                        <?php endif; ?>
                    </div>
                    <h3 class="font-bold text-slate-800 text-lg group-hover:text-blue-600 transition"><?= htmlspecialchars($d['judul_file']) ?></h3>
                    <p class="text-sm text-slate-500">
                        Oleh: <?= htmlspecialchars($d['penyelenggara']) ?> 
                        <?= $d['nama_event'] ? '• Untuk: ' . htmlspecialchars($d['nama_event']) : '' ?>
                    </p>
                </div>
            </div>
            <a href="../../public/<?= $d['file_path'] ?>" target="_blank" class="bg-blue-50 text-blue-600 px-4 py-2 rounded-lg font-bold text-sm hover:bg-blue-600 hover:text-white transition">Unduh</a>
        </div>
        <?php endforeach; ?>
    </div>
</div>
