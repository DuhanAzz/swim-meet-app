<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$catId = $_GET['category_id'] ?? 0;
$stage = $_GET['stage'] ?? 'Prelims'; // Menangkap parameter Prelims atau Final
$uid = $_SESSION['user_id'];

// --- 1. AMBIL DATA ACARA & BRANDING DARI USER ---
$sql = "SELECT u.nama_lengkap AS event_name, u.venue_name, u.location, u.event_start_date, u.event_end_date, 
               u.logo_left, u.logo_right, u.lane_count, ec.* FROM event_categories ec 
        JOIN users u ON ec.user_id = u.id 
        WHERE ec.id = ? AND ec.user_id = ?";
$stmtCat = $pdo->prepare($sql);
$stmtCat->execute([$catId, $uid]);
$info = $stmtCat->fetch();

if (!$info) die("Data acara tidak ditemukan.");

// --- 2. AMBIL DAFTAR SPONSOR ---
$stmtSponsors = $pdo->prepare("SELECT image_path FROM event_sponsors WHERE user_id = ?");
$stmtSponsors->execute([$uid]);
$sponsors = $stmtSponsors->fetchAll();

// --- 3. AMBIL DATA SERI (HEATS) BERDASARKAN BABAK (STAGE) ---
$stmtHeats = $pdo->prepare("SELECT * FROM race_heats WHERE category_id = ? AND stage = ? ORDER BY heat_number ASC");
$stmtHeats->execute([$catId, $stage]);
$heats = $stmtHeats->fetchAll();

foreach ($heats as &$h) {
    $stmtLines = $pdo->prepare("SELECT rl.*, s.nama_atlet, u.nama_lengkap as nama_klub, u.location as kabupaten 
                                FROM race_lines rl 
                                JOIN swimmers s ON rl.swimmer_id = s.id 
                                JOIN users u ON s.user_id = u.id 
                                WHERE rl.heat_id = ? ORDER BY rl.lane_number ASC");
    $stmtLines->execute([$h['id']]);
    $h['lanes'] = $stmtLines->fetchAll();
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<style>
    /* --- TAMPILAN MONITOR --- */
    .report-paper {
        background: white;
        width: 100%; 
        min-height: 297mm;
        padding: 20mm;
        margin: 0 auto;
        font-family: 'Courier New', Courier, monospace;
        color: #000;
        box-shadow: 0 0 50px rgba(0,0,0,0.1);
        border: 1px solid #e2e8f0;
    }
    .double-line { border-top: 4px double #000; margin: 15px 0; }
    .table-report { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 40px; table-layout: fixed; }
    .table-report th { border-bottom: 2px solid #000; padding: 10px 5px; text-align: left; font-weight: bold; }
    .table-report td { padding: 8px 5px; vertical-align: top; border-bottom: 1px solid #f2f2f2; overflow: hidden; }
    .center { text-align: center; }
    
    .sponsor-footer {
        margin-top: 50px;
        padding-top: 20px;
        border-top: 1px solid #eee;
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        align-items: center;
        gap: 30px;
    }
    .sponsor-footer img {
        height: 40px;
        width: auto;
        filter: grayscale(1);
        opacity: 0.7;
    }

    /* --- FIX CETAK (PRINT) --- */
    @media print {
        #logo-sidebar, nav, header, aside, .no-print, [role="navigation"], .pt-24 { 
            display: none !important; 
        }
        body, html {
            background: white !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            height: auto !important;
            overflow: visible !important;
        }
        .sm\:ml-64, main, .p-6 { 
            margin: 0 !important; 
            padding: 0 !important; 
            width: 100% !important;
            display: block !important;
            position: relative !important;
        }
        .report-paper {
            box-shadow: none !important;
            border: none !important;
            width: 100% !important;
            padding: 0 !important;
            margin: 0 !important;
            display: block !important;
        }
        .table-report { page-break-inside: auto; }
        tr { page-break-inside: avoid; page-break-after: auto; }
        @page { size: A4 portrait; margin: 15mm; }
    }
</style>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen">
    
    <div class="max-w-full mb-10 no-print">
        <div class="flex flex-col md:flex-row justify-between items-center gap-6 bg-white p-6 rounded-[2rem] border border-slate-200 shadow-sm">
            <div>
                <h1 class="text-2xl font-black uppercase italic text-slate-900 leading-none">Start List Preview</h1>
                <p class="text-[10px] font-bold text-blue-600 uppercase tracking-widest mt-2">Mode: <?= strtoupper($stage) ?> #<?= $info['event_no'] ?></p>
            </div>
            <div class="flex gap-3">
                <a href="index.php" class="bg-white border-2 border-slate-100 px-6 py-3 rounded-2xl font-black text-[10px] uppercase text-slate-400 hover:text-slate-600 transition">← Kembali</a>
                <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-black px-10 py-4 rounded-2xl text-[10px] uppercase shadow-xl shadow-blue-100 transition transform active:scale-95 flex items-center gap-2">
                    <span>🖨️</span> CETAK / DOWNLOAD PDF
                </button>
            </div>
        </div>
    </div>

    <div class="report-paper">
        
        <div class="flex justify-between items-center mb-10 px-4">
            <div class="w-28 h-28 flex items-center justify-center">
                <?php if(!empty($info['logo_left'])): ?>
                    <img src="../../../public/<?= $info['logo_left'] ?>" class="max-w-full max-h-full object-contain">
                <?php endif; ?>
            </div>

            <div class="text-center flex-1 mx-6">
                <h2 class="text-xl font-bold uppercase m-0 leading-tight"><?= strtoupper(htmlspecialchars($info['event_name'] ?? '')) ?></h2>
                <p class="font-bold text-sm mt-1">
                    <?= date('d F Y', strtotime($info['event_start_date'] ?? 'today')) ?> - <?= date('d F Y', strtotime($info['event_end_date'] ?? 'today')) ?>
                </p>
                <h1 class="text-4xl font-bold mt-4 uppercase tracking-[0.3em]">Buku Acara</h1>
            </div>

            <div class="w-28 h-28 flex items-center justify-center">
                <?php if(!empty($info['logo_right'])): ?>
                    <img src="../../../public/<?= $info['logo_right'] ?>" class="max-w-full max-h-full object-contain">
                <?php endif; ?>
            </div>
        </div>

        <div class="double-line"></div>
        
        <div class="flex justify-between font-bold text-sm uppercase mb-2">
            <span>ACARA <?= $info['event_no'] ?? '0' ?></span>
            <span><?= !empty($info['event_date']) ? date('d F Y', strtotime($info['event_date'])) : '' ?></span>
        </div>
        
        <div class="text-center font-black text-xl uppercase mb-10 italic">
            <?= $info['distance'] ?? '0' ?> M <?= strtoupper(htmlspecialchars($info['style'] ?? '')) ?> <?= strtoupper(htmlspecialchars(($info['gender'] ?? '') == 'Male' ? 'PUTRA' : 'PUTRI')) ?>
            <?php if($stage == 'Final'): ?>
                <div class="text-lg bg-slate-900 text-white px-4 py-1 not-italic inline-block mt-2 tracking-widest">BABAK FINAL</div>
            <?php endif; ?>
        </div>

        <?php if(empty($heats)): ?>
            <p class="text-center py-20 italic">Data seri <?= $stage ?> belum disusun.</p>
        <?php else: foreach($heats as $h): ?>
            <table class="table-report">
                <thead>
                    <tr>
                        <th style="width: 45px;" class="center">LN</th>
                        <th style="width: 220px;">NAMA ATLET</th>
                        <th style="width: 140px;">KABUPATEN / KOTA</th>
                        <th style="width: 180px;">ASAL SEKOLAH / KLUB</th>
                        <th style="width: 90px;" class="center">PRESTASI</th>
                        <th style="width: 120px;" class="center">SERI <?= $h['heat_number'] ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $L = $info['lane_count'] ?: 8;
                    $mapped = []; foreach($h['lanes'] as $l) { $mapped[$l['lane_number']] = $l; }
                    for($i=1; $i<=$L; $i++): $sw = $mapped[$i] ?? null;
                    ?>
                    <tr>
                        <td class="center font-bold"><?= $i ?></td>
                        <td class="font-bold"><?= $sw ? strtoupper(htmlspecialchars($sw['nama_atlet'] ?? '')) : '<KOSONG>' ?></td>
                        <td><?= $sw ? strtoupper(htmlspecialchars($sw['kabupaten'] ?? '-')) : '' ?></td>
                        <td><?= $sw ? strtoupper(htmlspecialchars($sw['nama_klub'] ?? '')) : '' ?></td>
                        <td class="center font-bold"><?= $sw ? ($sw['entry_time'] ?? '') : '' ?></td>
                        <td class="center text-slate-400 font-black">[ . . . . . . ]</td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        <?php endforeach; endif; ?>

        <?php if(!empty($sponsors)): ?>
        <div class="sponsor-footer">
            <?php foreach($sponsors as $sp): ?>
                <img src="../../../public/<?= $sp['image_path'] ?>">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="mt-10 text-[9px] italic flex justify-between border-t pt-4 text-slate-400 uppercase font-bold">
            <span>Official <?= strtoupper($stage) ?> Start List Generated by SwimMeet System</span>
            <span>Waktu Cetak: <?= date('d/m/Y H:i:s') ?></span>
        </div>
    </div>
</div>