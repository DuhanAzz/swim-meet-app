<?php
// src/admin/dashboard.php
session_start();
require_once __DIR__ . '/../../src/config/database.php';

// 1. CEK KEAMANAN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];

// 2. AMBIL DATA PROFIL ADMIN
$stmtProfile = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtProfile->execute([$uid]);
$prof = $stmtProfile->fetch();

// Mode Lomba (Default Langsung Final jika kosong)
$adminMode = $prof['competition_system'] ?? 'Langsung Final'; 

// --- 3. HITUNG STATISTIK (DARI DB YANG BENAR) ---

// A. Total Atlet (Dihitung dari entries yang terdaftar di event admin ini)
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT swimmer_id) FROM event_entries WHERE user_id = ?");
$stmt->execute([$uid]);
$totalSwimmers = $stmt->fetchColumn();

// B. Total Splash (Jumlah nomor lomba yang diikuti atlet)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM event_entries WHERE user_id = ?");
$stmt->execute([$uid]);
$totalEntries = $stmt->fetchColumn();

// C. Total Klub (Dari entries)
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT club_id) FROM event_entries WHERE user_id = ?");
$stmt->execute([$uid]);
$totalClubs = $stmt->fetchColumn();

// D. Keuangan Masuk (Dari tabel 'payments' status 'Paid')
$stmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE status = 'Paid'"); 
// Catatan: Idealnya ditambah WHERE user_id jika payments ada kolom penerima, 
// tapi berdasarkan struktur DB Anda, tabel payments punya user_id (pengirim).
// Kita asumsikan semua payment di sistem ini milik admin utama dulu.
$stmt->execute();
$totalIncome = $stmt->fetchColumn() ?: 0;

// E. Pembayaran Pending
$stmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE status = 'Pending'");
$stmt->execute();
$pendingPayment = $stmt->fetchColumn();

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">
    
    <div class="mb-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-4xl font-black uppercase tracking-tighter italic text-slate-900">Event Dashboard</h1>
            <div class="flex items-center gap-3 mt-1">
                <p class="text-sm text-slate-500 font-bold uppercase tracking-widest">Ringkasan Kompetisi Anda</p>
                <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-tighter border-2 <?= $adminMode == 'Penyisihan' ? 'bg-orange-50 border-orange-200 text-orange-600' : 'bg-blue-50 border-blue-200 text-blue-600' ?>">
                    Sistem: <?= $adminMode ?>
                </span>
            </div>
        </div>
        <div class="bg-white px-6 py-3 rounded-2xl shadow-sm border border-slate-200 flex items-center gap-3">
            <span class="text-xl">📅</span>
            <span class="text-xs font-black uppercase tracking-widest text-slate-600"><?= date('d F Y') ?></span>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
        
        <div class="bg-white p-7 rounded-[2.5rem] border border-slate-200 shadow-sm flex items-center justify-between group hover:border-blue-500 transition-all">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">Total Atlet</p>
                <h3 class="text-4xl font-black text-slate-900 leading-none"><?= number_format($totalSwimmers) ?></h3>
            </div>
            <div class="w-14 h-14 rounded-3xl bg-blue-50 text-blue-600 flex items-center justify-center text-3xl group-hover:scale-110 transition-transform">🏊</div>
        </div>

        <div class="bg-white p-7 rounded-[2.5rem] border border-slate-200 shadow-sm flex items-center justify-between group hover:border-purple-500 transition-all">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">Total Splash</p>
                <h3 class="text-4xl font-black text-slate-900 leading-none"><?= number_format($totalEntries) ?></h3>
            </div>
            <div class="w-14 h-14 rounded-3xl bg-purple-50 text-purple-600 flex items-center justify-center text-3xl group-hover:scale-110 transition-transform">⚡</div>
        </div>

        <div class="bg-white p-7 rounded-[2.5rem] border border-slate-200 shadow-sm flex items-center justify-between group hover:border-emerald-500 transition-all">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">Klub Terdaftar</p>
                <h3 class="text-4xl font-black text-slate-900 leading-none"><?= number_format($totalClubs) ?></h3>
            </div>
            <div class="w-14 h-14 rounded-3xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-3xl group-hover:scale-110 transition-transform">🏰</div>
        </div>

        <div class="bg-white p-7 rounded-[2.5rem] border border-slate-200 shadow-sm flex items-center justify-between group hover:border-red-500 transition-all relative overflow-hidden">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">Pemasukan Valid</p>
                <h3 class="text-2xl font-black text-slate-900 leading-none mt-2">Rp <?= number_format($totalIncome/1000, 0) ?>k</h3>
            </div>
            <div class="w-14 h-14 rounded-3xl bg-red-50 text-red-600 flex items-center justify-center text-3xl group-hover:rotate-12 transition-transform">💰</div>
            <?php if($pendingPayment > 0): ?>
                <div class="absolute top-3 right-3 flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-red-600"></span>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-8 mb-10">
        <div class="xl:col-span-2 bg-slate-900 rounded-[3rem] p-10 text-white shadow-2xl relative overflow-hidden">
            <div class="relative z-10">
                <div class="flex items-center gap-4 mb-6">
                    <span class="p-3 bg-blue-500 rounded-2xl text-2xl">⚡</span>
                    <div>
                        <h3 class="text-2xl font-black uppercase italic tracking-tighter">Manajemen Perlombaan</h3>
                        <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mt-1">Seeding, Start List & Race Times</p>
                    </div>
                </div>
                
                <p class="text-slate-400 text-sm mb-10 max-w-md leading-relaxed font-medium">
                    Kelola penyusunan lintasan otomatis (Spearhead) dan input hasil waktu pertandingan untuk menentukan juara secara real-time.
                </p>
                
                <div class="flex flex-wrap gap-4">
                    <a href="seeding/index.php" class="bg-white text-slate-900 px-8 py-4 rounded-2xl font-black text-[10px] uppercase tracking-widest hover:bg-blue-500 hover:text-white transition shadow-lg shadow-white/5">
                        Start List Utama
                    </a>
                    <a href="results/index.php" class="bg-slate-800 text-slate-300 px-8 py-4 rounded-2xl font-black text-[10px] uppercase tracking-widest hover:bg-slate-700 transition">
                        Input Hasil Waktu
                    </a>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-[3rem] p-10 border border-slate-200 shadow-sm flex flex-col justify-between group">
            <div>
                <h3 class="text-xl font-black uppercase text-slate-800 mb-2 italic tracking-tight">Awards & Reports</h3>
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-8 leading-relaxed">Pantau perolehan medali dan cetak piagam secara instan.</p>
                
                <div class="space-y-4">
                    <a href="results/medal_tally.php" class="flex items-center gap-4 p-4 bg-yellow-50/50 border border-yellow-100 rounded-2xl hover:bg-yellow-100 transition group-hover:scale-[1.02]">
                        <span class="text-2xl">🥇</span>
                        <div>
                            <div class="font-black text-slate-800 text-[10px] uppercase tracking-widest">Klasemen Medali</div>
                            <div class="text-[9px] text-yellow-600 font-bold uppercase italic">Medal Tally</div>
                        </div>
                    </a>
                    <a href="results/certificates.php" class="flex items-center gap-4 p-4 bg-blue-50/50 border border-blue-100 rounded-2xl hover:bg-blue-100 transition group-hover:scale-[1.02]">
                        <span class="text-2xl">📜</span>
                        <div>
                            <div class="font-black text-slate-800 text-[10px] uppercase tracking-widest">E-Certificate</div>
                            <div class="text-[9px] text-blue-600 font-bold uppercase italic">Cetak Piagam Otomatis</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-[3rem] border border-slate-200 p-10 shadow-sm">
        <div class="flex items-center gap-3 mb-8 border-b border-slate-50 pb-6">
            <span class="text-xl">🚀</span>
            <h3 class="font-black uppercase text-xs tracking-widest text-slate-400 italic">Global Shortcuts</h3>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-6">
            <a href="settings/event_profile.php" class="flex flex-col items-center justify-center p-6 border-2 border-slate-50 rounded-[2rem] hover:bg-slate-50 hover:border-blue-100 transition-all group shadow-sm hover:shadow-md">
                <span class="text-3xl mb-3 transform group-hover:-translate-y-2 transition">⚙️</span>
                <span class="text-[10px] font-black text-slate-600 uppercase tracking-widest">Event Config</span>
            </a>
            <a href="events/index.php" class="flex flex-col items-center justify-center p-6 border-2 border-slate-50 rounded-[2rem] hover:bg-slate-50 hover:border-blue-100 transition-all group shadow-sm hover:shadow-md">
                <span class="text-3xl mb-3 transform group-hover:-translate-y-2 transition">🏆</span>
                <span class="text-[10px] font-black text-slate-600 uppercase tracking-widest">Nomor Lomba</span>
            </a>
            </div>
    </div>
</div>