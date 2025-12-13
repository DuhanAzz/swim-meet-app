<?php
session_start();
require_once __DIR__ . '/../../src/config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];

// --- 1. HITUNG TOTAL ATLET (Yang mendaftar di event ini) ---
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT swimmer_id) FROM event_entries WHERE event_id = ?");
$stmt->execute([$uid]);
$totalSwimmers = $stmt->fetchColumn();

// --- 2. HITUNG TOTAL ENTRY (Jumlah nomor lomba) ---
$stmt = $pdo->prepare("SELECT COUNT(*) FROM event_entries WHERE event_id = ?");
$stmt->execute([$uid]);
$totalEntries = $stmt->fetchColumn();

// --- 3. HITUNG KEUANGAN (Verified Only) ---
$stmt = $pdo->prepare("SELECT SUM(total_amount) FROM event_payments WHERE event_id = ? AND status = 'Verified'");
$stmt->execute([$uid]);
$totalIncome = $stmt->fetchColumn() ?: 0;

// --- 4. HITUNG PEMBAYARAN PENDING (Butuh Verifikasi) ---
$stmt = $pdo->prepare("SELECT COUNT(*) FROM event_payments WHERE event_id = ? AND status = 'Pending'");
$stmt->execute([$uid]);
$pendingPayment = $stmt->fetchColumn();

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="mb-8 flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tight">Event Dashboard</h1>
            <p class="text-sm text-slate-500">Ringkasan status kompetisi Anda saat ini.</p>
        </div>
        <div class="text-right">
            <span class="bg-blue-100 text-blue-800 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wide">
                <?= date('d F Y') ?>
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Atlet</p>
                <h3 class="text-3xl font-black text-slate-800 mt-1"><?= number_format($totalSwimmers) ?></h3>
            </div>
            <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center text-2xl">🏊</div>
        </div>

        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Splash</p>
                <h3 class="text-3xl font-black text-slate-800 mt-1"><?= number_format($totalEntries) ?></h3>
            </div>
            <div class="w-12 h-12 rounded-full bg-purple-50 flex items-center justify-center text-2xl">⚡</div>
        </div>

        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Pemasukan (Valid)</p>
                <h3 class="text-2xl font-black text-slate-800 mt-1">Rp<?= number_format($totalIncome/1000, 0) ?>k</h3>
            </div>
            <div class="w-12 h-12 rounded-full bg-green-50 flex items-center justify-center text-2xl">💰</div>
        </div>

        <a href="keuangan/index.php" class="bg-white p-6 rounded-2xl border <?= $pendingPayment > 0 ? 'border-red-400 ring-2 ring-red-100' : 'border-slate-200' ?> shadow-sm flex items-center justify-between group hover:shadow-md transition">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Butuh Verifikasi</p>
                <h3 class="text-3xl font-black <?= $pendingPayment > 0 ? 'text-red-600' : 'text-slate-800' ?> mt-1">
                    <?= $pendingPayment ?>
                </h3>
            </div>
            <div class="w-12 h-12 rounded-full bg-red-50 flex items-center justify-center text-xl group-hover:scale-110 transition">🔔</div>
        </a>

    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        
        <div class="bg-gradient-to-br from-slate-800 to-slate-900 rounded-2xl p-8 text-white shadow-xl relative overflow-hidden group">
            <div class="relative z-10">
                <h3 class="text-xl font-bold mb-2">Kelola Pertandingan</h3>
                <p class="text-slate-300 text-sm mb-6 max-w-xs">Atur lintasan (Seeding) dan input hasil waktu pertandingan.</p>
                <div class="flex gap-3">
                    <a href="seeding/index.php" class="bg-white text-slate-900 px-4 py-2 rounded-lg font-bold text-xs hover:bg-blue-50 transition">Start List</a>
                    <a href="results/index.php" class="bg-slate-700 text-white px-4 py-2 rounded-lg font-bold text-xs hover:bg-slate-600 transition">Input Hasil</a>
                </div>
            </div>
            <div class="absolute right-0 bottom-0 opacity-10 transform translate-x-4 translate-y-4">
                <span class="text-9xl">⏱️</span>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
            <h3 class="font-bold text-slate-700 mb-4 border-b pb-2">Jalan Pintas</h3>
            <div class="grid grid-cols-2 gap-4">
                <a href="events/index.php" class="flex flex-col items-center justify-center p-4 border rounded-xl hover:bg-slate-50 transition cursor-pointer group">
                    <span class="text-2xl mb-2 group-hover:-translate-y-1 transition">🏆</span>
                    <span class="text-xs font-bold text-slate-600">Nomor Lomba</span>
                </a>
                <a href="entries/index.php" class="flex flex-col items-center justify-center p-4 border rounded-xl hover:bg-slate-50 transition cursor-pointer group">
                    <span class="text-2xl mb-2 group-hover:-translate-y-1 transition">📋</span>
                    <span class="text-xs font-bold text-slate-600">Data Peserta</span>
                </a>
                <a href="keuangan/index.php" class="flex flex-col items-center justify-center p-4 border rounded-xl hover:bg-slate-50 transition cursor-pointer group">
                    <span class="text-2xl mb-2 group-hover:-translate-y-1 transition">💸</span>
                    <span class="text-xs font-bold text-slate-600">Keuangan</span>
                </a>
                <a href="upload.php" class="flex flex-col items-center justify-center p-4 border rounded-xl hover:bg-slate-50 transition cursor-pointer group">
                    <span class="text-2xl mb-2 group-hover:-translate-y-1 transition">📂</span>
                    <span class="text-xs font-bold text-slate-600">Upload Dokumen</span>
                </a>
            </div>
        </div>

    </div>

</div>
