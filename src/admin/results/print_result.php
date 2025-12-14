<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

// Menangkap parameter dari URL
$catId = $_GET['category_id'] ?? 0;
$stage = $_GET['stage'] ?? 'Prelims';
$uid = $_SESSION['user_id'];

// --- 1. AMBIL DATA EVENT & BRANDING (Global) ---
$stmtUser = $pdo->prepare("SELECT nama_lengkap as event_name, event_start_date, event_end_date, logo_left, logo_right, lane_count FROM users WHERE id = ?");
$stmtUser->execute([$uid]);
$user = $stmtUser->fetch();

// --- 2. AMBIL DAFTAR SPONSOR ---
$stmtSponsors = $pdo->prepare("SELECT image_path FROM event_sponsors WHERE user_id = ?");
$stmtSponsors->execute([$uid]);
$sponsors = $stmtSponsors->fetchAll();

// --- 3. AMBIL DATA ACARA SPESIFIK ---
$stmtEvent = $pdo->prepare("SELECT * FROM event_categories WHERE id = ? AND user_id = ?");
$stmtEvent->execute([$catId, $uid]);
$info = $stmtEvent->fetch();

if (!$info) {
    die("<script>alert('Data acara tidak ditemukan.'); window.location.href='index.php';</script>");
}

// --- 4. AMBIL HASIL LOMBA (Urut berdasarkan Rank) ---
$sqlRes = "SELECT rl.*, s.nama_atlet, u.nama_lengkap as nama_klub, u.location as kabupaten 
           FROM race_lines rl 
           JOIN swimmers s ON rl.swimmer_id = s.id 
           JOIN users u ON s.user_id = u.id 
           JOIN race_heats rh ON rl.heat_id = rh.id
           WHERE rh.category_id = ? AND rh.stage = ? 
           AND rl.result_time IS NOT NULL AND rl.result_time != ''
           ORDER BY (CASE WHEN rl.status = 'OK' THEN 0 ELSE 1 END) ASC, rl.rank ASC";
$stmtRes = $pdo->prepare($sqlRes);
$stmtRes->execute([$catId, $stage]);
$results = $stmtRes->fetchAll();

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<style>
    /* --- TAMPILAN MONITOR (IDENTIK 1:1) --- */
    body { background: #f1f5f9; }
    
    .report-paper {
        background: white;
        width: 210mm; 
        min-height: 297mm;
        padding: 20mm;
        margin: 40px auto;
        font-family: 'Courier New', Courier, monospace;
        color: #000;
        box-shadow: 0 0 50px rgba(0,0,0,0.1);
        border: 1px solid #e2e8f0;
        position: relative;
    }
    .double-line { border-top: 4px double #000; margin: 15px 0; }
    
    .table-report { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 40px; table-layout: fixed; }
    .table-report th { border-bottom: 2px solid #000; padding: 10px 5px; text-align: left; font-weight: bold; text-transform: uppercase; }
    .table-report td { padding: 8px 5px; vertical-align: top; border-bottom: 1px solid #f2f2f2; overflow: hidden; }
    .center { text-align: center; }
    
    /* Lebar Kolom yang Disinkronkan */
    .col-rank  { width: 45px; }
    .col-nama  { width: 220px; }
    .col-kab   { width: 140px; }
    .col-klub  { width: 180px; }
    .col-hasil { width: 90px; }
    
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
            padding: 15mm !important;
            margin: 0 !important;
            display: block !important;
        }
        .table-report { page-break-inside: auto; }
        tr { page-break-inside: avoid; page-break-after: auto; }
        @page { size: A4 portrait; margin: 0; }
    }
</style>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen">
    
    <div class="max-w-full mb-10 no-print">
        <div class="flex flex-col md:flex-row justify-between items-center gap-6 bg-white p-6 rounded-[2rem] border border-slate-200 shadow-sm">
            <div>
                <h1 class="text-2xl font-black uppercase italic text-slate-900 leading-none">Official Result Preview</h1>
                <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-widest mt-2">Babak: <?= strtoupper($stage) ?> #<?= $info['event_no'] ?? '0' ?></p>
            </div>
            <div class="flex gap-3">
                <a href="index.php?category_id=<?= $catId ?>&stage=<?= $stage ?>" class="bg-white border-2 border-slate-100 px-6 py-3 rounded-2xl font-black text-[10px] uppercase text-slate-400 hover:text-slate-600 transition">← Kembali</a>
                <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-black px-10 py-4 rounded-2xl text-[10px] uppercase shadow-xl shadow-blue-100 transition transform active:scale-95 flex items-center gap-2">
                    <span>🖨️</span> CETAK HASIL PDF
                </button>
            </div>
        </div>
    </div>

    <div class="report-paper">
        
        <div class="flex justify-between items-center mb-10 px-4">
            <div class="w-28 h-28 flex items-center justify-center">
                <?php if(!empty($user['logo_left'])): ?>
                    <img src="../../../public/<?= $user['logo_left'] ?>" class="max-w-full max-h-full object-contain">
                <?php endif; ?>
            </div>

            <div class="text-center flex-1 mx-6">
                <h2 class="text-xl font-bold uppercase m-0 leading-tight"><?= strtoupper(htmlspecialchars($user['event_name'] ?? '')) ?></h2>
                <p class="font-bold text-sm mt-1">
                    <?= date('d F Y', strtotime($user['event_start_date'] ?? 'today')) ?> - <?= date('d F Y', strtotime($user['event_end_date'] ?? 'today')) ?>
                </p>
            </div>

            <div class="w-28 h-28 flex items-center justify-center">
                <?php if(!empty($user['logo_right'])): ?>
                    <img src="../../../public/<?= $user['logo_right'] ?>" class="max-w-full max-h-full object-contain">
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
            <br>
            <span class="text-lg bg-slate-900 text-white px-4 py-1 not-italic inline-block mt-2 tracking-widest">BABAK <?= strtoupper($stage) ?></span>
        </div>

        <table class="table-report">
            <thead>
                <tr>
                    <th class="col-rank center">RANK</th>
                    <th class="col-nama">NAMA ATLET</th>
                    <th class="col-kab">KABUPATEN / KOTA</th>
                    <th class="col-klub">ASAL SEKOLAH / KLUB</th>
                    <th class="col-hasil center">HASIL</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($results)): ?>
                    <tr><td colspan="5" class="center py-10 italic">Data hasil belum tersedia.</td></tr>
                <?php else: foreach($results as $r): ?>
                    <tr>
                        <td class="center font-bold text-lg">
                            <?= ($r['status'] == 'OK') ? ($r['rank'] ?? '-') : '-' ?>
                        </td>
                        <td class="font-bold uppercase">
                            <?= strtoupper(htmlspecialchars($r['nama_atlet'] ?? '')) ?>
                        </td>
                        <td class="uppercase">
                            <?= strtoupper(htmlspecialchars($r['kabupaten'] ?? '-')) ?>
                        </td>
                        <td class="uppercase">
                            <?= strtoupper(htmlspecialchars($r['nama_klub'] ?? '')) ?>
                        </td>
                        <td class="center font-bold font-mono">
                            <?= ($r['status'] == 'OK') ? ($r['result_time'] ?? '') : ($r['status'] ?? '') ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <?php if(!empty($sponsors)): ?>
        <div class="sponsor-footer">
            <?php foreach($sponsors as $sp): ?>
                <img src="../../../public/<?= $sp['image_path'] ?>">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="mt-10 text-[9px] italic flex justify-between border-t pt-4 text-slate-400 uppercase font-bold">
            <span>Official Result List Generated by SwimMeet System</span>
            <span>Printed: <?= date('d/m/Y H:i:s') ?></span>
        </div>
    </div>
</div>