<?php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') die("Akses Ditolak.");

$admin_id = $_GET['id'] ?? 0;

// 1. Ambil Info Event Utama
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role='admin'");
$stmt->execute([$admin_id]);
$eventInfo = $stmt->fetch();

if(!$eventInfo) { echo "Event tidak ditemukan."; exit; }

// 2. Ambil Daftar Lomba
$stmt = $pdo->prepare("SELECT * FROM events WHERE user_id = ? ORDER BY nomor_acara ASC");
$stmt->execute([$admin_id]);
$raceList = $stmt->fetchAll();

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 mt-16 bg-slate-50 min-h-screen font-sans">
    
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 mb-8 relative overflow-hidden">
        <div class="absolute top-0 right-0 w-64 h-64 bg-blue-50 rounded-full -mr-20 -mt-20 opacity-50"></div>
        <a href="explore.php" class="text-slate-400 hover:text-blue-600 font-bold text-sm mb-4 inline-block">&larr; Kembali</a>
        <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tight relative z-10"><?= htmlspecialchars($eventInfo['nama_lengkap']) ?></h1>
        <div class="flex gap-4 mt-2 text-sm text-slate-600 relative z-10">
            <span>📅 <?= date('d F Y', strtotime($eventInfo['event_start_date'])) ?></span>
            <span>📍 <?= htmlspecialchars($eventInfo['location']) ?></span>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-blue-50/50">
            <h3 class="font-bold text-slate-700">Daftar Nomor Perlombaan</h3>
            
            <a href="registration.php?id=<?= $admin_id ?>" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-6 rounded-lg shadow-lg flex items-center gap-2 transition transform hover:-translate-y-0.5">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                Registrasi Atlet (Tampilan Tim) &rarr;
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 border-b text-slate-500 font-bold uppercase text-xs">
                    <tr>
                        <th class="px-6 py-4 text-center">No</th>
                        <th class="px-6 py-4">Nomor Lomba</th>
                        <th class="px-6 py-4">Kategori</th>
                        <th class="px-6 py-4 text-center">Biaya</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach($raceList as $race): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4 text-center font-black text-slate-300"><?= $race['nomor_acara'] ?></td>
                        <td class="px-6 py-4 font-bold text-slate-800">
                            <?= htmlspecialchars($race['nama_event']) ?>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 rounded text-xs font-bold <?= $race['jenis_kelamin']=='L'?'bg-blue-100 text-blue-700':'bg-pink-100 text-pink-700' ?>">
                                <?= $race['jenis_kelamin']=='L'?'Putra':'Putri' ?>
                            </span>
                            <span class="text-xs ml-2 text-slate-500">KU: <?= $race['batas_umur_bawah'] ?>-<?= $race['batas_umur_atas'] ?></span>
                        </td>
                        <td class="px-6 py-4 text-center font-mono text-slate-600">
                            Rp<?= number_format($race['harga_pendaftaran'],0,',','.') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
