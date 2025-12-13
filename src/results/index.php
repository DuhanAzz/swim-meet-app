<?php
session_start();
// PERBAIKAN: Cukup satu '../' karena config ada di dalam folder src juga
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') die("Akses Ditolak.");
$user_id = $_SESSION['user_id'];

// Ambil Event milik Admin
$stmt = $pdo->prepare("SELECT * FROM events WHERE user_id = ? ORDER BY tanggal_lomba ASC, jam_lomba ASC");
$stmt->execute([$user_id]);
$events = $stmt->fetchAll();

// Path ke views tetap mundur 2 langkah karena views ada di luar src
include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 mt-16 bg-slate-50 min-h-screen font-sans">
    
    <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-800 tracking-tight uppercase">Input Result & Buku Hasil</h1>
            <p class="text-sm text-slate-500">Susunan acara, input waktu, dan cetak laporan.</p>
        </div>
        <a href="#" href="../admin/preview_buku_hasil.php" target="_blank" class="bg-slate-800 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg hover:bg-slate-900 transition flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            Generate Buku Hasil (Full)
        </a>
    </div>

    <div class="space-y-4">
        <?php if(empty($events)): ?>
            <div class="bg-white p-8 rounded-xl border border-slate-200 text-center text-slate-500 italic">
                Belum ada nomor lomba. Silakan buat di menu Dashboard atau Verifikasi.
            </div>
        <?php else: ?>
            <?php foreach($events as $e): ?>
            <div class="bg-white p-4 rounded-xl border border-slate-200 flex flex-col md:flex-row items-center justify-between hover:border-blue-400 transition group">
                <div class="flex items-center gap-4">
                    <div class="bg-blue-50 text-blue-700 font-black text-lg w-12 h-12 flex items-center justify-center rounded-lg">
                        <?= $e['id'] ?>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 text-lg group-hover:text-blue-600 transition"><?= htmlspecialchars($e['nama_event']) ?></h3>
                        <p class="text-xs text-slate-500"><?= $e['jarak'] ?>m <?= $e['gaya'] ?> • <?= $e['jenis_kelamin']=='L'?'Putra':'Putri' ?> • KU <?= $e['batas_umur_bawah'] ?>-<?= $e['batas_umur_atas'] ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-3 mt-4 md:mt-0">
                    <a href="input_result.php?event_id=<?= $e['id'] ?>" class="px-4 py-2 bg-blue-600 text-white text-sm font-bold rounded-lg hover:bg-blue-700 transition shadow-md">
                        Input / Edit Waktu
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
