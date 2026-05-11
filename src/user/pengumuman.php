<?php
// FILE: src/user/pengumuman.php
session_start();
// PERBAIKAN PATH DATABASE (Asumsi file ini ada di dalam folder src/user/)
require_once __DIR__ . '/../config/database.php';

// Cek Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../public/login.php"); exit;
}
$user_id = $_SESSION['user_id'];

// Ambil ID Klub (Jika ada)
$stmt = $pdo->prepare("SELECT id FROM clubs WHERE user_id = ?");
$stmt->execute([$user_id]);
$club = $stmt->fetch();
$club_id = $club['id'] ?? 0;

// PERBAIKAN: e.nama_event DIGANTI MENJADI e.event_name
$sql = "SELECT d.*, e.event_name, u.nama_lengkap as penyelenggara
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
<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    <div class="mb-8">
        <h1 class="text-3xl font-black text-slate-800 uppercase italic">Pengumuman & Dokumen</h1>
        <p class="text-slate-500 text-sm font-bold uppercase tracking-widest mt-1">Unduh informasi penting dari penyelenggara lomba</p>
    </div>
    
    <?php if(empty($docs)): ?>
        <div class="bg-white p-12 text-center rounded-3xl shadow-sm border border-slate-200">
            <span class="text-6xl mb-4 block grayscale opacity-30">📭</span>
            <p class="font-bold text-slate-400">Belum ada pengumuman atau dokumen yang diunggah.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach($docs as $d): 
                $isJoined = in_array($d['event_id'], $joinedEvents);
            ?>
            <div class="bg-white p-6 rounded-3xl border <?= $isJoined ? 'border-2 border-blue-400 shadow-blue-100 shadow-lg' : 'border-slate-200 shadow-sm' ?> flex items-center justify-between group hover:-translate-y-1 transition-all">
                <div class="flex items-start gap-4">
                    <div class="<?= $isJoined ? 'bg-blue-100 text-blue-600' : 'bg-slate-100 text-slate-400' ?> p-4 rounded-2xl text-2xl">
                        📄
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <span class="text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded bg-slate-100 text-slate-500"><?= $d['kategori'] ?></span>
                            <?php if($isJoined): ?>
                                <span class="text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded bg-blue-600 text-white shadow-sm">Event Diikuti</span>
                            <?php endif; ?>
                        </div>
                        <h3 class="font-black text-slate-800 text-lg uppercase group-hover:text-blue-600 transition leading-tight mb-1">
                            <?= htmlspecialchars($d['judul_file']) ?>
                        </h3>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                            Oleh: <?= htmlspecialchars($d['penyelenggara']) ?> 
                            <?= $d['event_name'] ? '<br>📌 ' . htmlspecialchars($d['event_name']) : '' ?>
                        </p>
                    </div>
                </div>
                
                <a href="../../public/<?= $d['file_path'] ?>" target="_blank" class="flex-shrink-0 bg-slate-900 text-white w-12 h-12 flex items-center justify-center rounded-xl font-black shadow-lg hover:bg-blue-600 hover:scale-105 transition-all">
                    ↓
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>