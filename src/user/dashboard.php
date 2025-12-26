<?php
// src/user/dashboard.php
session_start();
require_once __DIR__ . '/../../src/config/database.php';

// CEK LOGIN
// Kita izinkan role 'club' atau 'user' agar aman
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'club' && $_SESSION['role'] !== 'user')) {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];

// 1. STATISTIK: TOTAL ATLET
$stmt = $pdo->prepare("SELECT COUNT(*) FROM swimmers WHERE user_id = ?");
$stmt->execute([$uid]);
$totalSwimmers = $stmt->fetchColumn();

// 2. STATISTIK: TOTAL EVENT YANG DIIKUTI
// Menghitung berapa banyak baris di tabel event_entries milik klub ini
// Asumsi: club_id disimpan di event_entries
$stmtEntries = $pdo->prepare("SELECT COUNT(*) FROM event_entries WHERE club_id = ?");
$stmtEntries->execute([$uid]);
$totalEntries = $stmtEntries->fetchColumn();

// 3. STATISTIK: STATUS PEMBAYARAN TERAKHIR
$stmtPay = $pdo->prepare("SELECT status FROM payments WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmtPay->execute([$uid]);
$lastPaymentStatus = $stmtPay->fetchColumn(); 
if(!$lastPaymentStatus) $lastPaymentStatus = 'Belum Bayar';

// --- LOAD VIEWS ---
include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">

    <div class="mb-8">
        <h1 class="text-3xl font-black uppercase tracking-tighter italic text-slate-900">Dashboard Klub</h1>
        <p class="text-sm text-slate-500 font-bold uppercase tracking-widest mt-1">Selamat Datang, <?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Coach') ?></p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
        
        <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 flex items-center justify-between">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Atlet</p>
                <h3 class="text-4xl font-black text-slate-800"><?= $totalSwimmers ?></h3>
            </div>
            <div class="text-4xl grayscale opacity-30">🏊</div>
        </div>

        <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 flex items-center justify-between">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Nomor Lomba</p>
                <h3 class="text-4xl font-black text-slate-800"><?= $totalEntries ?></h3>
            </div>
            <div class="text-4xl grayscale opacity-30">⚡</div>
        </div>

        <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 flex items-center justify-between">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Status Pembayaran</p>
                <h3 class="text-xl font-black <?= ($lastPaymentStatus == 'Paid' || $lastPaymentStatus == 'Verified') ? 'text-emerald-500' : 'text-orange-500' ?>">
                    <?= $lastPaymentStatus ?>
                </h3>
            </div>
            <div class="text-4xl grayscale opacity-30">💳</div>
        </div>
    </div>

    <h3 class="font-black text-slate-800 uppercase text-sm tracking-tight mb-4 ml-2">Menu Cepat</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        <a href="atlet/index.php" class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100 hover:shadow-lg hover:-translate-y-1 transition group">
            <span class="text-4xl mb-4 block group-hover:scale-110 transition">📋</span>
            <h4 class="font-black text-lg text-slate-800 uppercase italic">Data Atlet</h4>
            <p class="text-xs text-slate-500 mt-2 font-medium">Input biodata perenang baru.</p>
        </a>

        <a href="kompetisi/registration.php" class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100 hover:shadow-lg hover:-translate-y-1 transition group">
            <span class="text-4xl mb-4 block group-hover:scale-110 transition">🎯</span>
            <h4 class="font-black text-lg text-slate-800 uppercase italic">Daftar Lomba</h4>
            <p class="text-xs text-slate-500 mt-2 font-medium">Pilih nomor lomba per atlet.</p>
        </a>

        <a href="pembayaran.php" class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100 hover:shadow-lg hover:-translate-y-1 transition group">
            <span class="text-4xl mb-4 block group-hover:scale-110 transition">💳</span>
            <h4 class="font-black text-lg text-slate-800 uppercase italic">Pembayaran</h4>
            <p class="text-xs text-slate-500 mt-2 font-medium">Upload bukti transfer.</p>
        </a>

    </div>

</div>