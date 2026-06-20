<?php
// FILE: src/master/dashboard.php
session_start();

// --- 1. KONEKSI DATABASE ---
// PERBAIKAN: Menggunakan ../config karena folder config ada di dalam src
require_once __DIR__ . '/../config/database.php';

// --- 2. CEK AKSES MASTER ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'master') {
    // Sesuaikan path redirect login jika perlu
    header("Location: " . BASE_URL . "/public/login.php"); exit;
}

// --- 3. LOGIC DATA (DATA GATHERING) ---
$stats = [
    'eo' => 0,
    'clubs' => 0,
    'athletes' => 0,
    'entries' => 0,
    'revenue' => 0
];
$liveEvents = [];
$recentUsers = [];
$systemStatus = 0; 
$heroTitle = 'SwimMeet App'; 

try {
    // A. Statistik Dasar User
    $stats['eo']       = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    $stats['clubs']    = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
    
    // Hitung User Pending
    $stats['pending_users'] = 0;
    try {
        $stats['pending_users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE account_status = 'pending'")->fetchColumn();
    } catch (Exception $e) {}

    // Cek tabel swimmers
    $stats['athletes'] = 0;
    $stats['pending_uids'] = 0;
    try {
        $stats['athletes'] = $pdo->query("SELECT COUNT(*) FROM swimmers")->fetchColumn();
        $stats['pending_uids'] = $pdo->query("SELECT COUNT(*) FROM swimmers WHERE uid IS NULL OR trim(uid) = '' OR uid = '-' OR uid LIKE 'SW%' OR uid = '0'")->fetchColumn();
    } catch (Exception $e) {}
    
    // B. Hitung Entries
    $countActive = 0;
    try {
        $countActive = $pdo->query("SELECT COUNT(*) FROM event_entries")->fetchColumn();
    } catch (Exception $e) { /* Abaikan */ }

    $countArchive = 0;
    try {
        $countArchive = $pdo->query("SELECT COUNT(*) FROM event_entries_archive")->fetchColumn();
    } catch (Exception $e) { /* Abaikan */ }
    
    $stats['entries'] = $countActive + $countArchive;

    // C. Statistik Keuangan
    try {
        $stats['revenue'] = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'Paid'")->fetchColumn() ?: 0;
    } catch (Exception $e) { $stats['revenue'] = 0; }

    // D. Cek Status Maintenance & Web Settings
    try {
        $settings = $pdo->query("SELECT * FROM site_settings WHERE id=1")->fetch();
        if ($settings) {
            $systemStatus = $settings['maintenance_mode'] ?? 0;
            $heroTitle    = $settings['app_name'] ?? 'SwimMeet App';
        }
    } catch (Exception $e) { /* Abaikan */ }

    // E. Event Live / Mendatang
    $sqlLive = "
        SELECT e.*, u.nama_lengkap as eo_name 
        FROM events e 
        LEFT JOIN users u ON e.user_id = u.id 
        WHERE e.event_status != 'Done' 
        AND e.event_date_start >= CURDATE()
        ORDER BY e.event_date_start ASC 
        LIMIT 5
    ";
    $liveEvents = $pdo->query($sqlLive)->fetchAll();

    // F. User Terbaru
    $sqlRecent = "
        SELECT id, username, role, created_at, nama_lengkap, email, account_status
        FROM users 
        ORDER BY created_at DESC 
        LIMIT 5
    ";
    $recentUsers = $pdo->query($sqlRecent)->fetchAll();

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// --- 4. TAMPILAN ---
// Pastikan folder views ada di root (swim-meet/views), jadi mundur 2 langkah benar
include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center mb-8 gap-6">
        <div>
            <h1 class="text-3xl font-black text-slate-800 uppercase italic tracking-tighter">
                Master Dashboard
            </h1>
            <p class="text-sm text-slate-500 font-medium">
                Selamat Datang, Super Admin! Berikut laporan sistem hari ini.
            </p>
        </div>
        
        <div class="flex gap-3">
            <a href="maintenance/system_health.php" class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-xl font-bold text-xs uppercase hover:bg-slate-100 transition shadow-sm flex items-center gap-2">
                <span>🛡️</span> System Health
            </a>
        </div>
    </div>

    <!-- ACTION REQUIRED ALERTS -->
    <?php if($stats['pending_users'] > 0 || $stats['pending_uids'] > 0): ?>
    <div class="mb-8 space-y-4">
        <?php if($stats['pending_users'] > 0): ?>
        <div class="bg-gradient-to-r from-amber-500 to-orange-500 rounded-2xl p-6 text-white shadow-lg flex items-center justify-between group">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center text-2xl">⚠️</div>
                <div>
                    <h3 class="font-black text-lg">Action Required: <?= $stats['pending_users'] ?> Akun Pending</h3>
                    <p class="text-sm text-orange-100 font-medium mt-1">Ada pengguna (Klub/EO) baru yang menunggu persetujuan Anda untuk bisa login.</p>
                </div>
            </div>
            <a href="users/index.php" class="bg-white text-orange-600 px-6 py-3 rounded-xl font-black text-xs uppercase tracking-widest hover:bg-orange-50 transition transform group-hover:scale-105 shadow-md">Tinjau Sekarang</a>
        </div>
        <?php endif; ?>

        <?php if($stats['pending_uids'] > 0): ?>
        <div class="bg-gradient-to-r from-blue-600 to-indigo-700 rounded-2xl p-6 text-white shadow-lg flex items-center justify-between group">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center text-2xl">🆔</div>
                <div>
                    <h3 class="font-black text-lg">System Alert: <?= $stats['pending_uids'] ?> Atlet Tanpa UID</h3>
                    <p class="text-sm text-blue-100 font-medium mt-1">Ada atlet yang terdaftar namun belum memiliki UID (atau format UID masih lama/salah).</p>
                </div>
            </div>
            <a href="swimmers/index.php" class="bg-white text-blue-700 px-6 py-3 rounded-xl font-black text-xs uppercase tracking-widest hover:bg-blue-50 transition transform group-hover:scale-105 shadow-md">Generate UID</a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        
        <div class="bg-gradient-to-br from-emerald-600 to-teal-800 rounded-2xl p-6 text-white shadow-lg relative overflow-hidden group">
            <div class="relative z-10">
                <p class="text-emerald-100 text-[10px] font-black uppercase tracking-widest mb-1">Total Pendapatan</p>
                <h2 class="text-2xl font-black">Rp <?= number_format($stats['revenue'], 0, ',', '.') ?></h2>
                <div class="mt-4 text-[10px] font-bold bg-white/20 inline-block px-2 py-1 rounded">All Events</div>
            </div>
            <div class="absolute -right-4 -bottom-4 opacity-10 group-hover:scale-110 transition duration-500 text-white">
                <svg class="w-24 h-24" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1.41 16.09V20h-2.67v-1.93c-1.71-.36-3.15-1.46-3.27-3.4h1.96c.1 1.05 1.18 1.91 2.53 1.91 1.29 0 2.13-.81 2.13-1.88 0-1.1-.68-1.57-1.75-2.25-1.55-.98-2.69-1.66-2.69-3.5 0-1.81 1.4-2.97 3.09-3.32V4h2.67v1.93c1.71.36 3.15 1.46 3.27 3.4h-1.96c-.1-1.05-1.18-1.91-2.53-1.91-1.29 0-2.13.81-2.13 1.88 0 1.1.68 1.57 1.75 2.25 1.55.98 2.69 1.66 2.69 3.5 0 1.81-1.4 2.97-3.09 3.32z"/></svg>
            </div>
        </div>

        <div class="bg-gradient-to-br from-white to-slate-50 rounded-2xl p-6 border-b-4 border-blue-500 shadow-sm hover:shadow-lg transition-all duration-300 group">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">Database Atlet</p>
                    <h2 class="text-3xl font-black text-slate-800 group-hover:text-blue-600 transition"><?= number_format($stats['athletes']) ?></h2>
                    <p class="text-[10px] text-slate-400 mt-1">Total terdaftar di sistem</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center text-2xl shadow-inner">🏊</div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-white to-slate-50 rounded-2xl p-6 border-b-4 border-purple-500 shadow-sm hover:shadow-lg transition-all duration-300 group">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">Total User</p>
                    <h2 class="text-3xl font-black text-slate-800 group-hover:text-purple-600 transition"><?= number_format($stats['eo'] + $stats['clubs']) ?></h2>
                    <p class="text-[10px] text-slate-400 mt-1"><?= $stats['clubs'] ?> Klub / <?= $stats['eo'] ?> EO</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 text-purple-600 rounded-xl flex items-center justify-center text-2xl shadow-inner">👥</div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-white to-slate-50 rounded-2xl p-6 border-b-4 border-slate-400 shadow-sm hover:shadow-lg transition-all duration-300 group">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">Status Server</p>
                    <?php if($systemStatus == 0): ?>
                        <h2 class="text-xl font-black text-emerald-600 flex items-center gap-2">
                            <span class="w-3 h-3 bg-emerald-500 rounded-full animate-pulse"></span> ONLINE
                        </h2>
                        <p class="text-[10px] text-slate-400 mt-1">Publik dapat mengakses.</p>
                    <?php else: ?>
                        <h2 class="text-xl font-black text-red-600 flex items-center gap-2">
                            <span class="w-3 h-3 bg-red-500 rounded-full animate-pulse"></span> MAINTENANCE
                        </h2>
                        <p class="text-[10px] text-slate-400 mt-1">Hanya Master akses.</p>
                    <?php endif; ?>
                </div>
                <div class="w-10 h-10 bg-slate-100 text-slate-600 rounded-lg flex items-center justify-center text-xl">🖥️</div>
            </div>
        </div>

    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-2 space-y-8">
            
            <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 p-8">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-black text-slate-800 uppercase italic text-sm tracking-widest">🗓️ Kompetisi Mendatang</h3>
                    <a href="events/index.php" class="text-[10px] font-bold text-blue-600 hover:underline">Lihat Semua</a>
                </div>

                <div class="space-y-4">
                    <?php if(empty($liveEvents)): ?>
                        <div class="text-center py-8 text-slate-400 text-xs italic bg-slate-50 rounded-xl border border-dashed border-slate-200">
                            Tidak ada event aktif/mendatang.
                        </div>
                    <?php else: ?>
                        <?php foreach($liveEvents as $ev): ?>
                        <div class="flex items-center justify-between p-4 bg-slate-50 border border-slate-100 rounded-2xl hover:bg-white hover:shadow-md transition group">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-xl flex flex-col items-center justify-center font-bold text-[10px] leading-tight shadow-sm">
                                    <span><?= date('M', strtotime($ev['event_date_start'])) ?></span>
                                    <span class="text-lg"><?= date('d', strtotime($ev['event_date_start'])) ?></span>
                                </div>
                                <div>
                                    <h4 class="font-black text-slate-800 text-sm uppercase group-hover:text-blue-600 transition"><?= htmlspecialchars($ev['event_name']) ?></h4>
                                    <p class="text-[10px] text-slate-500 font-bold uppercase">
                                        📍 <?= htmlspecialchars(substr($ev['event_location'], 0, 30)) ?>... 
                                        <span class="text-slate-300 mx-1">|</span> 
                                        EO: <?= htmlspecialchars($ev['eo_name'] ?? 'Unknown') ?>
                                    </p>
                                </div>
                            </div>
                            <?php 
                                $statusClass = 'bg-slate-100 text-slate-600';
                                if($ev['event_status'] == 'Registration') $statusClass = 'bg-emerald-100 text-emerald-700';
                                if($ev['event_status'] == 'Draft') $statusClass = 'bg-yellow-100 text-yellow-700';
                            ?>
                            <span class="hidden sm:block px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wide <?= $statusClass ?>">
                                <?= $ev['event_status'] ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-slate-50 px-8 py-4 border-b border-slate-100">
                    <h3 class="font-black text-slate-800 uppercase italic text-sm tracking-widest">👤 Registrasi User Terbaru</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <tbody class="divide-y divide-slate-50">
                            <?php foreach($recentUsers as $u): ?>
                            <tr class="hover:bg-blue-50/30 transition">
                                <td class="px-8 py-4">
                                    <div class="flex items-center gap-2">
                                        <div class="font-bold text-slate-700"><?= htmlspecialchars($u['nama_lengkap']) ?></div>
                                        <?php if(($u['account_status']??'') == 'pending'): ?>
                                            <span class="w-2 h-2 rounded-full bg-orange-500 animate-pulse" title="Menunggu Verifikasi"></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-[10px] text-slate-400">@<?= htmlspecialchars($u['username']) ?></div>
                                </td>
                                <td class="px-8 py-4">
                                    <div class="flex flex-col gap-1 items-start">
                                        <span class="px-2 py-1 rounded text-[9px] font-black uppercase 
                                            <?= $u['role']=='admin' ? 'bg-slate-800 text-white' : 'bg-blue-100 text-blue-600' ?>">
                                            <?= $u['role'] == 'admin' ? 'Event Org' : 'Club' ?>
                                        </span>
                                        <?php if(($u['account_status']??'') == 'pending'): ?>
                                            <span class="px-2 py-1 rounded text-[9px] font-black uppercase bg-orange-100 text-orange-600 border border-orange-200">
                                                Pending
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 rounded text-[9px] font-black uppercase bg-emerald-100 text-emerald-600">
                                                Verified
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-8 py-4 text-right text-[10px] text-slate-400 font-mono">
                                    <?= date('d/m/Y H:i', strtotime($u['created_at'])) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <div class="space-y-8">
            
            <div class="bg-slate-800 rounded-[2rem] p-8 text-white shadow-xl">
                <h3 class="font-black uppercase italic text-sm tracking-widest mb-6 text-slate-400">⚡ Akses Cepat</h3>
                <div class="grid grid-cols-2 gap-4">
                    <a href="users/index.php" class="bg-slate-700 hover:bg-blue-600 p-4 rounded-xl text-center transition group">
                        <div class="text-2xl mb-2 group-hover:scale-110 transition">👥</div>
                        <span class="text-[9px] font-bold uppercase tracking-wider">User Manager</span>
                    </a>
                    <a href="finance/revenue.php" class="bg-slate-700 hover:bg-emerald-600 p-4 rounded-xl text-center transition group">
                        <div class="text-2xl mb-2 group-hover:scale-110 transition">💰</div>
                        <span class="text-[9px] font-bold uppercase tracking-wider">Keuangan</span>
                    </a>
                    <a href="settings/public_page.php" class="bg-slate-700 hover:bg-indigo-600 p-4 rounded-xl text-center transition group">
                        <div class="text-2xl mb-2 group-hover:scale-110 transition">🎨</div>
                        <span class="text-[9px] font-bold uppercase tracking-wider">Editor Web</span>
                    </a>
                    <a href="maintenance/data_cleanup.php" class="bg-slate-700 hover:bg-red-600 p-4 rounded-xl text-center transition group">
                        <div class="text-2xl mb-2 group-hover:scale-110 transition">🧹</div>
                        <span class="text-[9px] font-bold uppercase tracking-wider">Bersihkan Data</span>
                    </a>
                </div>
            </div>

            <div class="bg-white rounded-[2rem] border border-slate-200 p-8 text-center">
                <div class="w-16 h-16 bg-slate-100 rounded-full mx-auto flex items-center justify-center text-3xl mb-4">
                    🚀
                </div>
                <h4 class="font-black text-slate-800 uppercase tracking-tight"><?= htmlspecialchars($heroTitle) ?></h4>
                <p class="text-xs text-slate-500 mt-2">Versi 1.0.0 (Beta)</p>
                <div class="mt-6 pt-6 border-t border-slate-100">
                    <p class="text-[10px] text-slate-400 uppercase font-bold">Waktu Server</p>
                    <p class="text-lg font-mono font-bold text-slate-700"><?= date('H:i') ?> <span class="text-xs text-slate-400">WIB</span></p>
                </div>
            </div>

        </div>

    </div>

</div>