<?php
session_start();
require_once __DIR__ . '/../../src/config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'master') {
    header("Location: ../../public/login.php"); exit;
}

// --- 1. STATISTIK UTAMA ---
// Total Event Organizer
$totalEO = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();

// Total Klub
$totalClub = $pdo->query("SELECT COUNT(*) FROM clubs")->fetchColumn();

// Total Atlet (Seluruh Sistem)
$totalAtlet = $pdo->query("SELECT COUNT(*) FROM swimmers")->fetchColumn();

// --- 2. CEK EVENT YANG SEDANG BERLANGSUNG (LIVE) ---
$today = date('Y-m-d');
$stmtLive = $pdo->prepare("SELECT * FROM users WHERE role = 'admin' AND event_start_date <= ? AND (event_end_date >= ? OR event_end_date IS NULL) ORDER BY event_start_date ASC");
$stmtLive->execute([$today, $today]);
$liveEvents = $stmtLive->fetchAll();

// --- 3. CEK PENGATURAN HALAMAN PUBLIK ---
$webSet = $pdo->query("SELECT * FROM site_settings WHERE id=1")->fetch();
$heroTitle = $webSet['hero_title'] ?? 'Belum Diatur';
$heroImg = $webSet['hero_image'] ?? '';

// --- 4. USER TERBARU (5 Terakhir) ---
$recentUsers = $pdo->query("SELECT u.username, u.nama_lengkap, u.role, u.created_at, c.nama_klub 
                            FROM users u 
                            LEFT JOIN clubs c ON u.id = c.user_id 
                            ORDER BY u.created_at DESC LIMIT 5")->fetchAll();

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tight">System Overview</h1>
            <p class="text-sm text-slate-500">Pantau performa sistem dan aktivitas kompetisi secara real-time.</p>
        </div>
        <div class="flex gap-2">
            <a href="../../public/view_result.php" target="_blank" class="bg-white border border-slate-300 text-slate-700 px-4 py-2 rounded-lg font-bold text-xs hover:bg-slate-50 transition shadow-sm flex items-center gap-2">
                <span>👁️</span> Cek Web Publik
            </a>
            <a href="settings/public_page.php" class="bg-slate-800 text-white px-4 py-2 rounded-lg font-bold text-xs hover:bg-slate-900 transition shadow-lg flex items-center gap-2">
                <span>⚙️</span> Atur Web
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        
        <div class="bg-gradient-to-br from-blue-600 to-blue-800 rounded-2xl p-6 text-white shadow-lg relative overflow-hidden">
            <div class="absolute right-0 top-0 p-4 opacity-10">
                <svg class="w-24 h-24" fill="currentColor" viewBox="0 0 20 20"><path d="M5 4a2 2 0 012-2h6a2 2 0 012 2v14l-5-2.5L5 18V4z"></path></svg>
            </div>
            <p class="text-blue-200 text-xs font-bold uppercase tracking-wider mb-1">Kompetisi Live</p>
            <h2 class="text-4xl font-black"><?= count($liveEvents) ?></h2>
            <p class="text-xs text-blue-200 mt-2">Sedang berlangsung hari ini</p>
        </div>

        <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm relative group hover:border-blue-200 transition">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-slate-400 text-xs font-bold uppercase tracking-wider mb-1">Total Atlet</p>
                    <h2 class="text-3xl font-black text-slate-800"><?= number_format($totalAtlet) ?></h2>
                </div>
                <div class="bg-blue-50 p-2 rounded-lg text-blue-600 text-xl">🏊</div>
            </div>
            <div class="mt-4 pt-4 border-t border-slate-50 flex items-center gap-2 text-xs text-slate-500">
                <span class="text-green-500 font-bold">Data Global</span> dari seluruh klub.
            </div>
        </div>

        <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm relative group hover:border-green-200 transition">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-slate-400 text-xs font-bold uppercase tracking-wider mb-1">Total Klub</p>
                    <h2 class="text-3xl font-black text-slate-800"><?= $totalClub ?></h2>
                </div>
                <div class="bg-green-50 p-2 rounded-lg text-green-600 text-xl">🏢</div>
            </div>
            <div class="mt-4 pt-4 border-t border-slate-50 flex items-center gap-2 text-xs text-slate-500">
                <span class="text-green-500 font-bold">+1</span> minggu ini.
            </div>
        </div>

        <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm relative group hover:border-purple-200 transition">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-slate-400 text-xs font-bold uppercase tracking-wider mb-1">Event Organizer</p>
                    <h2 class="text-3xl font-black text-slate-800"><?= $totalEO ?></h2>
                </div>
                <div class="bg-purple-50 p-2 rounded-lg text-purple-600 text-xl">🏆</div>
            </div>
            <div class="mt-4 pt-4 border-t border-slate-50 flex items-center gap-2 text-xs text-slate-500">
                Akun admin aktif.
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
        
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold text-slate-800">Status Halaman Publik</h3>
                <span class="bg-green-100 text-green-700 px-2 py-1 rounded text-[10px] font-bold uppercase">Online</span>
            </div>
            
            <div class="relative w-full h-32 rounded-xl overflow-hidden bg-slate-800 mb-4 group">
                <?php if($heroImg): ?>
                    <img src="../../public/<?= $heroImg ?>" class="w-full h-full object-cover opacity-60 group-hover:scale-105 transition duration-700">
                <?php else: ?>
                    <div class="w-full h-full bg-gradient-to-r from-slate-700 to-slate-900"></div>
                <?php endif; ?>
                <div class="absolute inset-0 flex items-center justify-center p-4 text-center">
                    <h4 class="text-white font-black text-sm uppercase tracking-wider drop-shadow-md line-clamp-2"><?= htmlspecialchars($heroTitle) ?></h4>
                </div>
            </div>

            <div class="mt-auto">
                <p class="text-xs text-slate-500 mb-3">Ini adalah tampilan banner yang dilihat pengunjung saat ini.</p>
                <a href="settings/public_page.php" class="block w-full text-center py-2 rounded-lg border border-slate-200 text-slate-600 text-xs font-bold hover:bg-slate-50 transition">Ubah Tampilan</a>
            </div>
        </div>

        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <h3 class="font-bold text-slate-800 mb-4 flex items-center gap-2">
                <span class="animate-pulse w-3 h-3 bg-red-500 rounded-full"></span>
                Sedang Berlangsung (Live Events)
            </h3>
            
            <div class="space-y-3">
                <?php if(empty($liveEvents)): ?>
                    <div class="p-8 text-center bg-slate-50 rounded-xl border border-dashed border-slate-200">
                        <div class="text-3xl mb-2">💤</div>
                        <p class="text-sm text-slate-500 font-bold">Tidak ada event yang aktif hari ini.</p>
                        <p class="text-xs text-slate-400">Semua sistem standby.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($liveEvents as $ev): ?>
                    <div class="flex items-center justify-between p-4 bg-blue-50/50 border border-blue-100 rounded-xl hover:bg-blue-50 transition">
                        <div>
                            <h4 class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($ev['nama_lengkap']) ?></h4>
                            <p class="text-xs text-slate-500 mt-1">📍 <?= htmlspecialchars($ev['location']) ?> • 📅 <?= date('d M Y', strtotime($ev['event_start_date'])) ?></p>
                        </div>
                        <a href="../../public/view_result.php" target="_blank" class="bg-blue-600 text-white px-3 py-1.5 rounded text-xs font-bold hover:bg-blue-700 shadow">
                            Pantau Hasil
                        </a>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <h3 class="font-bold text-slate-800 text-sm uppercase tracking-wide">Pendaftaran Akun Terakhir</h3>
            <a href="users/index.php?role=user" class="text-blue-600 text-xs font-bold hover:underline">Lihat Semua &rarr;</a>
        </div>
        <table class="w-full text-left text-sm">
            <thead class="bg-white text-slate-400 font-bold uppercase text-[10px] border-b border-slate-100">
                <tr>
                    <th class="px-6 py-3">User / Klub</th>
                    <th class="px-6 py-3">Role</th>
                    <th class="px-6 py-3">Username</th>
                    <th class="px-6 py-3 text-right">Waktu Daftar</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach($recentUsers as $u): ?>
                <tr class="hover:bg-slate-50 transition">
                    <td class="px-6 py-3">
                        <div class="font-bold text-slate-700"><?= htmlspecialchars($u['nama_klub'] ?? $u['nama_lengkap']) ?></div>
                        <?php if($u['role']=='user'): ?>
                            <div class="text-[10px] text-slate-400">CP: <?= htmlspecialchars($u['nama_lengkap']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-3">
                        <span class="px-2 py-1 rounded text-[10px] font-bold uppercase tracking-wider
                            <?= $u['role']=='admin'?'bg-purple-100 text-purple-700':($u['role']=='user'?'bg-green-100 text-green-700':'bg-slate-100 text-slate-700') ?>">
                            <?= $u['role'] ?>
                        </span>
                    </td>
                    <td class="px-6 py-3 font-mono text-xs text-slate-500">@<?= htmlspecialchars($u['username']) ?></td>
                    <td class="px-6 py-3 text-right text-slate-400 text-xs"><?= date('d/m/Y H:i', strtotime($u['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>
</div>
