<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];

// --- 1. STATISTIK ATLET ---
$stmt1 = $pdo->prepare("SELECT COUNT(*) FROM swimmers WHERE user_id = ?");
$stmt1->execute([$uid]);
$totalSwimmers = $stmt1->fetchColumn();

// --- 2. STATISTIK NOMOR LOMBA (SPLASH) ---
$stmt2 = $pdo->prepare("SELECT COUNT(*) FROM event_entries WHERE club_id = ?");
$stmt2->execute([$uid]);
$totalEntries = $stmt2->fetchColumn();

// --- 3. KOMPETISI YANG DIIKUTI ---
$stmt3 = $pdo->prepare("SELECT COUNT(DISTINCT event_id) FROM event_entries WHERE club_id = ?");
$stmt3->execute([$uid]);
$totalEvents = $stmt3->fetchColumn();

// --- 4. STATUS PEMBAYARAN TERAKHIR ---
$stmt4 = $pdo->prepare("
    SELECT ep.*, u.nama_lengkap as event_name 
    FROM event_payments ep 
    JOIN users u ON ep.event_id = u.id 
    WHERE ep.club_id = ? 
    ORDER BY ep.created_at DESC LIMIT 1
");
$stmt4->execute([$uid]);
$lastPayment = $stmt4->fetch();

// --- 5. DAFTAR ATLET TERBARU ---
$stmt5 = $pdo->prepare("SELECT nama_atlet, jenis_kelamin, tanggal_lahir FROM swimmers WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt5->execute([$uid]);
$recentSwimmers = $stmt5->fetchAll();

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tight">Dashboard Klub</h1>
            <p class="text-sm text-slate-500 font-medium">Selamat datang kembali, <span class="text-blue-600 font-bold"><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></span> 👋</p>
        </div>
        <div class="flex gap-2">
            <a href="kompetisi/explore.php" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl font-bold text-xs shadow-lg transition transform hover:-translate-y-1">
                🚀 Cari Kompetisi
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between group hover:border-blue-500 transition">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Atlet</p>
                <h3 class="text-3xl font-black text-slate-800 mt-1"><?= $totalSwimmers ?></h3>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-2xl group-hover:scale-110 transition">🏊</div>
        </div>

        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between group hover:border-purple-500 transition">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Nomor Lomba</p>
                <h3 class="text-3xl font-black text-slate-800 mt-1"><?= $totalEntries ?></h3>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center text-2xl group-hover:scale-110 transition">⚡</div>
        </div>

        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between group hover:border-orange-500 transition">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Event Aktif</p>
                <h3 class="text-3xl font-black text-slate-800 mt-1"><?= $totalEvents ?></h3>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-orange-50 text-orange-600 flex items-center justify-center text-2xl group-hover:scale-110 transition">🏆</div>
        </div>

        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between group hover:border-green-500 transition">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Status Bayar</p>
                <div class="mt-2">
                    <?php if($lastPayment): ?>
                        <?php if($lastPayment['status'] == 'Verified'): ?>
                            <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-[10px] font-black uppercase">Lunas ✅</span>
                        <?php elseif($lastPayment['status'] == 'Pending'): ?>
                            <span class="bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-[10px] font-black uppercase animate-pulse">Checking</span>
                        <?php else: ?>
                            <span class="bg-red-100 text-red-700 px-3 py-1 rounded-full text-[10px] font-black uppercase">Ditolak</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-slate-300 text-[10px] font-bold">Belum ada data</span>
                    <?php     endif; ?>
                </div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-green-50 text-green-600 flex items-center justify-center text-2xl group-hover:scale-110 transition">💸</div>
        </div>

    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                <h3 class="font-black text-slate-800 text-sm uppercase tracking-tight">Atlet Terbaru</h3>
                <a href="atlet/index.php" class="text-blue-600 font-bold text-xs hover:underline">Lihat Semua</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-400 font-bold uppercase text-[10px]">
                        <tr>
                            <th class="px-6 py-3">Nama Atlet</th>
                            <th class="px-6 py-3 text-center">Gender</th>
                            <th class="px-6 py-3 text-right">Usia</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if(empty($recentSwimmers)): ?>
                            <tr><td colspan="3" class="px-6 py-10 text-center text-slate-400 italic">Belum ada data atlet.</td></tr>
                        <?php else: ?>
                            <?php foreach($recentSwimmers as $rs): 
                                $age = date_diff(date_create($rs['tanggal_lahir']), date_create('today'))->y;
                            ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-6 py-4 font-bold text-slate-700 uppercase"><?= htmlspecialchars($rs['nama_atlet']) ?></td>
                                <td class="px-6 py-4 text-center">
                                    <span class="<?= $rs['jenis_kelamin']=='Male' ? 'text-blue-600' : 'text-pink-600' ?> font-black text-xs">
                                        <?= $rs['jenis_kelamin']=='Male' ? 'L' : 'P' ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right font-mono font-bold text-slate-500"><?= $age ?> TH</td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-gradient-to-br from-slate-800 to-slate-900 rounded-2xl p-6 text-white shadow-xl relative overflow-hidden">
                <div class="relative z-10">
                    <h3 class="font-black text-lg mb-2 uppercase italic tracking-tighter">Butuh Bantuan?</h3>
                    <p class="text-slate-400 text-xs mb-6 leading-relaxed">Jika Anda mengalami kendala saat pendaftaran atau pembayaran, silakan hubungi tim support kami.</p>
                    <a href="#" class="inline-block w-full bg-white text-slate-900 text-center py-3 rounded-xl font-black text-xs uppercase tracking-widest hover:bg-blue-400 hover:text-white transition">Hubungi Panitia</a>
                </div>
                <div class="absolute -right-4 -bottom-4 opacity-10 text-8xl">📞</div>
            </div>

            <div class="bg-blue-600 rounded-2xl p-6 text-white shadow-lg">
                <h3 class="font-black text-sm uppercase mb-4 tracking-widest">Informasi Pendaftaran</h3>
                <ul class="text-xs space-y-3 opacity-90 font-medium">
                    <li class="flex gap-2"><span>✅</span> Pastikan data atlet sudah benar.</li>
                    <li class="flex gap-2"><span>✅</span> Cek kelompok umur sebelum mendaftar.</li>
                    <li class="flex gap-2"><span>✅</span> Upload bukti transfer tepat waktu.</li>
                </ul>
            </div>
        </div>

    </div>

</div>