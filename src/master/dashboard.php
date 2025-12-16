<?php
session_start();
require_once __DIR__ . '/../../src/config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'master') {
    header("Location: ../../public/login.php"); exit;
}

// --- 1. STATISTIK UTAMA (DATA GLOBAL) ---
$totalEO = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$totalClub = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$totalAtlet = $pdo->query("SELECT COUNT(*) FROM swimmers")->fetchColumn();
$totalEntries = $pdo->query("SELECT COUNT(*) FROM event_entries")->fetchColumn();

// --- 2. CEK EVENT YANG SEDANG BERLANGSUNG (LIVE) ---
$today = date('Y-m-d');
$stmtLive = $pdo->prepare("SELECT * FROM users WHERE role = 'admin' AND event_start_date <= ? AND (event_end_date >= ? OR event_end_date IS NULL) ORDER BY event_start_date ASC");
$stmtLive->execute([$today, $today]);
$liveEvents = $stmtLive->fetchAll();

// --- 3. PENGATURAN HALAMAN PUBLIK ---
$webSet = $pdo->query("SELECT * FROM site_settings WHERE id=1")->fetch();
$heroTitle = $webSet['hero_title'] ?? 'SwimMeet Competition System';
$heroImg = $webSet['hero_image'] ?? '';

// --- 4. AKTIVITAS SISTEM TERBARU (Gabungan Pendaftaran & User) ---
$recentActivities = $pdo->query("SELECT u.nama_lengkap, u.role, u.created_at, u.username
                                 FROM users u 
                                 ORDER BY u.created_at DESC LIMIT 6")->fetchAll();

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center mb-10 gap-6">
        <div>
            <h1 class="text-4xl font-black text-slate-900 uppercase italic tracking-tighter leading-none">Master Control</h1>
            <p class="text-sm text-slate-500 font-bold uppercase tracking-widest mt-2">Pusat Kendali Ekosistem Digital SwimMeet</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="settings/backup.php" class="bg-white border border-slate-200 text-slate-700 px-5 py-3 rounded-2xl font-black text-[10px] uppercase hover:bg-slate-900 hover:text-white transition shadow-sm flex items-center gap-2">
                <span>💾</span> Backup Database
            </a>
            <a href="settings/public_page.php" class="bg-blue-600 text-white px-5 py-3 rounded-2xl font-black text-[10px] uppercase hover:bg-blue-700 transition shadow-lg shadow-blue-200 flex items-center gap-2">
                <span>🌐</span> Kelola Landing Page
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
        
        <div class="bg-slate-900 rounded-[2rem] p-8 text-white shadow-2xl relative overflow-hidden group">
            <div class="absolute -right-4 -top-4 opacity-10 group-hover:scale-110 transition duration-500">
                <svg class="w-32 h-32" fill="currentColor" viewBox="0 0 20 20"><path d="M5 4a2 2 0 012-2h6a2 2 0 012 2v14l-5-2.5L5 18V4z"></path></svg>
            </div>
            <p class="text-slate-400 text-[10px] font-black uppercase tracking-[0.2em] mb-2">Kompetisi Live</p>
            <div class="flex items-end gap-2">
                <h2 class="text-5xl font-black italic"><?= count($liveEvents) ?></h2>
                <span class="text-xs font-bold text-emerald-400 mb-2 uppercase animate-pulse">● Active</span>
            </div>
        </div>

        <div class="bg-white rounded-[2rem] p-8 border border-slate-200 shadow-sm group hover:border-blue-500 transition-all duration-500">
            <p class="text-slate-400 text-[10px] font-black uppercase tracking-[0.2em] mb-4">Total Atlet Terdaftar</p>
            <div class="flex justify-between items-center">
                <h2 class="text-4xl font-black text-slate-900 italic"><?= number_format($totalAtlet) ?></h2>
                <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center text-2xl group-hover:bg-blue-600 group-hover:text-white transition">🏊</div>
            </div>
        </div>

        <div class="bg-white rounded-[2rem] p-8 border border-slate-200 shadow-sm group hover:border-emerald-500 transition-all duration-500">
            <p class="text-slate-400 text-[10px] font-black uppercase tracking-[0.2em] mb-4">Klub / Sekolah</p>
            <div class="flex justify-between items-center">
                <h2 class="text-4xl font-black text-slate-900 italic"><?= $totalClub ?></h2>
                <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-2xl group-hover:bg-emerald-600 group-hover:text-white transition">🏢</div>
            </div>
        </div>

        <div class="bg-white rounded-[2rem] p-8 border border-slate-200 shadow-sm group hover:border-purple-500 transition-all duration-500">
            <p class="text-slate-400 text-[10px] font-black uppercase tracking-[0.2em] mb-4">Total Partisipasi</p>
            <div class="flex justify-between items-center">
                <h2 class="text-4xl font-black text-slate-900 italic"><?= number_format($totalEntries) ?></h2>
                <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-2xl flex items-center justify-center text-2xl group-hover:bg-purple-600 group-hover:text-white transition">📈</div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-10">
        
        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 p-8 flex flex-col">
            <div class="flex justify-between items-center mb-6">
                <h3 class="font-black text-slate-800 uppercase italic text-sm tracking-widest">Public Landing Page</h3>
                <span class="bg-emerald-500 text-white px-3 py-1 rounded-full text-[8px] font-black uppercase">Live</span>
            </div>
            
            <div class="relative w-full aspect-video rounded-[2rem] overflow-hidden bg-slate-900 mb-6 group cursor-pointer">
                <?php if($heroImg): ?>
                    <img src="../../public/<?= $heroImg ?>" class="w-full h-full object-cover opacity-50 group-hover:scale-110 transition duration-700">
                <?php else: ?>
                    <div class="w-full h-full bg-gradient-to-br from-slate-700 to-slate-900"></div>
                <?php endif; ?>
                <div class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center">
                    <p class="text-white font-black text-xs uppercase tracking-[0.2em] drop-shadow-lg"><?= htmlspecialchars($heroTitle) ?></p>
                </div>
            </div>

            <div class="space-y-4">
                <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                    <p class="text-[10px] font-bold text-slate-500 uppercase">Logo Sistem</p>
                    <p class="text-xs font-black text-slate-800 uppercase mt-1">SwimMeet Default Logo</p>
                </div>
                <a href="settings/public_page.php" class="block w-full text-center py-4 rounded-2xl bg-slate-100 text-slate-600 text-[10px] font-black uppercase hover:bg-slate-900 hover:text-white transition tracking-widest">Konfigurasi Visual</a>
            </div>
        </div>

        <div class="lg:col-span-2 bg-white rounded-[2.5rem] shadow-sm border border-slate-200 p-8">
            <div class="flex justify-between items-center mb-8">
                <h3 class="font-black text-slate-800 uppercase italic text-sm tracking-widest flex items-center gap-3">
                    <span class="animate-ping w-2 h-2 bg-red-500 rounded-full"></span>
                    Kompetisi Berjalan
                </h3>
                <a href="users/index.php?role=admin" class="text-[10px] font-black text-blue-600 uppercase hover:underline">Kelola Semua EO &rarr;</a>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php if(empty($liveEvents)): ?>
                    <div class="col-span-2 p-12 text-center bg-slate-50 rounded-[2rem] border-2 border-dashed border-slate-200">
                        <p class="text-sm text-slate-400 font-black uppercase tracking-widest italic">Belum ada kompetisi aktif hari ini.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($liveEvents as $ev): ?>
                    <div class="group p-6 bg-white border border-slate-100 rounded-[2rem] hover:shadow-xl hover:border-blue-500 transition-all duration-300">
                        <div class="flex justify-between items-start mb-4">
                            <span class="bg-blue-50 text-blue-600 px-3 py-1 rounded-full text-[8px] font-black uppercase italic tracking-tighter">Event Organizer</span>
                            <span class="text-[9px] font-bold text-slate-300">#<?= $ev['id'] ?></span>
                        </div>
                        <h4 class="font-black text-slate-800 text-sm uppercase leading-tight group-hover:text-blue-600 transition"><?= htmlspecialchars($ev['nama_lengkap']) ?></h4>
                        <div class="mt-4 flex flex-col gap-1">
                            <div class="flex items-center gap-2 text-[10px] font-bold text-slate-400 uppercase">
                                <span>📍</span> <?= htmlspecialchars($ev['location']) ?>
                            </div>
                            <div class="flex items-center gap-2 text-[10px] font-bold text-slate-400 uppercase">
                                <span>📅</span> <?= date('d M Y', strtotime($ev['event_start_date'])) ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-8 border-b border-slate-100 flex justify-between items-center bg-slate-50/30">
            <h3 class="font-black text-slate-800 uppercase italic text-sm tracking-widest">Log Aktivitas Pengguna</h3>
            <div class="flex gap-2">
                <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-[8px] font-black uppercase">EO Active: <?= $totalEO ?></span>
                <span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-full text-[8px] font-black uppercase">Clubs: <?= $totalClub ?></span>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-white text-slate-400 font-black uppercase text-[10px] border-b border-slate-100">
                    <tr>
                        <th class="px-8 py-5 tracking-widest">Identitas</th>
                        <th class="px-8 py-5 tracking-widest">Tingkat Akses</th>
                        <th class="px-8 py-5 tracking-widest">Username</th>
                        <th class="px-8 py-5 text-right tracking-widest">Tanggal Bergabung</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach($recentActivities as $u): ?>
                    <tr class="hover:bg-slate-50/50 transition duration-300">
                        <td class="px-8 py-5">
                            <div class="font-black text-slate-800 uppercase italic"><?= htmlspecialchars($u['nama_lengkap']) ?></div>
                        </td>
                        <td class="px-8 py-5">
                            <span class="px-4 py-1.5 rounded-full text-[9px] font-black uppercase tracking-wider
                                <?= $u['role']=='admin'?'bg-slate-900 text-white':($u['role']=='user'?'bg-blue-50 text-blue-600 border border-blue-100':'bg-slate-100 text-slate-500') ?>">
                                <?= $u['role'] == 'admin' ? 'Event Organizer' : 'Klub Member' ?>
                            </span>
                        </td>
                        <td class="px-8 py-5 font-mono text-xs text-slate-400">@<?= htmlspecialchars($u['username']) ?></td>
                        <td class="px-8 py-5 text-right text-slate-400 font-bold text-xs italic"><?= date('d/m/Y H:i', strtotime($u['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="p-6 bg-slate-50/50 text-center">
            <a href="users/index.php" class="text-[10px] font-black text-slate-400 uppercase tracking-widest hover:text-blue-600 transition">Lihat Seluruh Database Pengguna &rarr;</a>
        </div>
    </div>

</div>