<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$catId = $_GET['category_id'] ?? 0;
$stage = $_GET['stage'] ?? 'Prelims';
$uid = $_SESSION['user_id'];

// --- 1. AMBIL DATA ACARA (Sama seperti Start List) ---
$sql = "SELECT u.nama_lengkap AS event_name, u.venue_name, u.location, u.event_start_date, u.event_end_date, 
               u.logo_left, u.logo_right, ec.* FROM event_categories ec 
        JOIN users u ON ec.user_id = u.id 
        WHERE ec.id = ? AND ec.user_id = ?";
$stmtInfo = $pdo->prepare($sql);
$stmtInfo->execute([$catId, $uid]);
$info = $stmtInfo->fetch();

if (!$info) {
    die("<script>alert('Data acara tidak ditemukan.'); window.close();</script>");
}

// --- 2. AMBIL SPONSOR ---
$stmtSponsors = $pdo->prepare("SELECT image_path FROM event_sponsors WHERE user_id = ?");
$stmtSponsors->execute([$uid]);
$sponsors = $stmtSponsors->fetchAll();

// --- 3. AMBIL HASIL LOMBA ---
// Logic: Urutkan berdasarkan Status (OK dulu), lalu Rank, lalu Waktu
$sqlRes = "SELECT rl.*, s.nama_atlet, u.nama_lengkap as nama_klub, u.location as kabupaten 
           FROM race_lines rl 
           JOIN swimmers s ON rl.swimmer_id = s.id 
           JOIN users u ON s.user_id = u.id 
           JOIN race_heats rh ON rl.heat_id = rh.id
           WHERE rh.category_id = ? AND rh.stage = ? 
           AND rl.result_time IS NOT NULL AND rl.result_time != ''
           ORDER BY (CASE WHEN rl.status = 'OK' THEN 0 ELSE 1 END) ASC, rl.rank ASC, rl.result_time ASC";
$stmtRes = $pdo->prepare($sqlRes);
$stmtRes->execute([$catId, $stage]);
$results = $stmtRes->fetchAll();

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<style>
    /* --- TAMPILAN KERTAS DI LAYAR (Background Gelap) --- */
    .report-container {
        display: flex;
        justify-content: center;
        background-color: #525659; /* Warna abu gelap ala PDF Viewer */
        padding: 40px 0;
        min-height: 100vh;
    }

    .report-paper {
        background: white;
        width: 210mm; /* LEBAR A4 FIXED */
        min-height: 297mm; /* TINGGI A4 MINIMUM */
        padding: 15mm 20mm; /* Margin Kanan Kiri 2cm */
        margin: 0 auto;
        box-shadow: 0 0 15px rgba(0,0,0,0.3);
        position: relative;
        font-family: 'Courier New', Courier, monospace;
        color: #000;
    }

    /* --- STYLE UTAMA --- */
    .double-line { border-top: 4px double #000; margin: 10px 0 20px 0; }
    
    .table-report { 
        width: 100%; 
        border-collapse: collapse; 
        font-size: 11px; 
        margin-bottom: 30px; 
    }
    
    .table-report th { 
        border-top: 2px solid #000;
        border-bottom: 2px solid #000; 
        padding: 8px 4px; 
        text-align: left; 
        font-weight: 800; 
        text-transform: uppercase;
    }
    
    .table-report td { 
        padding: 6px 4px; 
        vertical-align: middle; 
        border-bottom: 1px solid #ddd; 
        font-weight: 600;
    }
    
    .table-report tr:last-child td { border-bottom: 2px solid #000; }
    
    .center { text-align: center; }
    .text-right { text-align: right; }

    /* --- SPONSOR FOOTER --- */
    .sponsor-footer {
        margin-top: 40px;
        padding-top: 10px;
        border-top: 1px dotted #ccc;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
    }
    .sponsor-footer img { height: 30px; width: auto; filter: grayscale(100%); opacity: 0.6; }

    /* --- PENGATURAN CETAK (PRINT) --- */
    @media print {
        @page { size: A4 portrait; margin: 0; }
        body { background: white; margin: 0; padding: 0; -webkit-print-color-adjust: exact; }
        
        /* Sembunyikan elemen UI Admin */
        nav, aside, header, .no-print, .pt-24, .sm\:ml-64 { display: none !important; }
        
        /* Reset Container */
        .report-container { 
            background: white; 
            padding: 0; 
            display: block; 
            min-height: auto;
        }
        
        .report-paper {
            width: 100%;
            margin: 0;
            padding: 10mm 15mm;
            box-shadow: none;
            border: none;
            page-break-after: always;
        }

        .table-report { page-break-inside: auto; }
        tr { page-break-inside: avoid; page-break-after: auto; }
    }
</style>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen">
    
    <div class="max-w-7xl mx-auto mb-6 no-print">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4 bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div>
                <h1 class="text-xl font-bold text-slate-800">OFFICIAL RESULT PREVIEW</h1>
                <p class="text-xs font-bold text-emerald-600 uppercase">MODE: <?= strtoupper($stage) ?> | EVENT #<?= $info['event_no'] ?></p>
            </div>
            <div class="flex gap-2">
                <a href="index.php?category_id=<?= $catId ?>&stage=<?= $stage ?>" class="px-5 py-2 rounded-lg font-bold text-xs uppercase bg-slate-100 text-slate-500 hover:bg-slate-200 transition">Kembali</a>
                <button onclick="window.print()" class="px-6 py-2 rounded-lg font-bold text-xs uppercase bg-blue-600 text-white shadow hover:bg-blue-700 transition flex items-center gap-2">
                    <span>🖨️</span> Cetak PDF
                </button>
            </div>
        </div>
    </div>

    <div class="report-container">
        <div class="report-paper">
            
            <div class="flex justify-between items-center mb-6">
                <div class="w-20 h-20 flex items-center justify-center">
                    <?php if(!empty($info['logo_left'])): ?>
                        <img src="../../../public/<?= $info['logo_left'] ?>" class="max-w-full max-h-full object-contain">
                    <?php endif; ?>
                </div>

                <div class="text-center flex-1 mx-4">
                    <h2 class="text-lg font-black uppercase leading-tight tracking-wide"><?= strtoupper(htmlspecialchars($info['event_name'] ?? '')) ?></h2>
                    <p class="text-xs font-bold mt-1 text-slate-600 uppercase">
                        <?= htmlspecialchars($info['location'] ?? '') ?>
                    </p>
                    <div class="h-1 w-24 bg-black mx-auto my-2"></div>
                    <h1 class="text-3xl font-black uppercase tracking-tighter">OFFICIAL RESULT</h1>
                </div>

                <div class="w-20 h-20 flex items-center justify-center">
                    <?php if(!empty($info['logo_right'])): ?>
                        <img src="../../../public/<?= $info['logo_right'] ?>" class="max-w-full max-h-full object-contain">
                    <?php endif; ?>
                </div>
            </div>

            <div class="double-line"></div>
            
            <div class="flex justify-between items-end mb-6 font-bold uppercase text-xs border-b pb-2">
                <div>
                    <span class="block text-slate-500 text-[10px]">Nomor Acara</span>
                    <span class="text-xl">#<?= $info['event_no'] ?></span>
                </div>
                <div class="text-center">
                    <span class="block text-slate-500 text-[10px]">Kategori</span>
                    <span class="text-base"><?= $info['distance'] ?>M <?= $info['style'] ?> <?= ($info['gender'] == 'Male') ? 'PUTRA' : 'PUTRI' ?></span>
                </div>
                <div class="text-right">
                    <span class="block text-slate-500 text-[10px]">Babak</span>
                    <span class="text-base bg-black text-white px-2 py-0.5"><?= strtoupper($stage) ?></span>
                </div>
            </div>

            <table class="table-report">
                <thead>
                    <tr>
                        <th style="width: 10%;" class="center">RANK</th>
                        <th style="width: 35%;">NAMA ATLET</th>
                        <th style="width: 20%;">KABUPATEN</th>
                        <th style="width: 20%;">KLUB / SEKOLAH</th>
                        <th style="width: 15%;" class="center">WAKTU</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($results)): ?>
                        <tr><td colspan="5" class="center py-10 italic text-slate-400">Belum ada hasil yang diinputkan.</td></tr>
                    <?php else: foreach($results as $r): ?>
                        <tr>
                            <td class="center font-black text-sm">
                                <?= ($r['status'] == 'OK') ? ($r['rank'] ?? '-') : '' ?>
                            </td>
                            <td>
                                <div class="font-bold text-black uppercase"><?= htmlspecialchars($r['nama_atlet'] ?? '') ?></div>
                            </td>
                            <td class="text-[10px] uppercase">
                                <?= htmlspecialchars($r['kabupaten'] ?? '-') ?>
                            </td>
                            <td class="text-[10px] uppercase truncate">
                                <?= htmlspecialchars($r['nama_klub'] ?? '') ?>
                            </td>
                            <td class="center font-bold font-mono text-sm">
                                <?php 
                                    if($r['status'] == 'OK') {
                                        echo htmlspecialchars($r['result_time']);
                                    } else {
                                        echo '<span class="text-red-600">'.$r['status'].'</span>';
                                    }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>

            <div class="mt-auto">
                <?php if(!empty($sponsors)): ?>
                <div class="sponsor-footer">
                    <?php foreach($sponsors as $sp): ?>
                        <img src="../../../public/<?= $sp['image_path'] ?>">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div class="flex justify-between mt-4 text-[9px] font-bold text-slate-400 border-t pt-2 uppercase">
                    <span>Generated by SwimMeet Manager</span>
                    <span>Printed: <?= date('d/m/Y H:i') ?></span>
                </div>
            </div>

        </div>
    </div>
</div>