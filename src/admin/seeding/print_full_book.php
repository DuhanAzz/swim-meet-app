<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];

// 1. Ambil Info Event & Branding
$user = $pdo->query("SELECT * FROM users WHERE id = $uid")->fetch();

// 2. Ambil Semua Sponsor
$stmtSponsors = $pdo->prepare("SELECT image_path FROM event_sponsors WHERE user_id = ?");
$stmtSponsors->execute([$uid]);
$sponsors = $stmtSponsors->fetchAll();

// 3. Ambil Semua Acara yang SUDAH memiliki Heats
$stmtEvents = $pdo->prepare("SELECT ec.* FROM event_categories ec 
                             WHERE ec.user_id = ? 
                             AND EXISTS (SELECT 1 FROM race_heats WHERE category_id = ec.id) 
                             ORDER BY ec.event_no ASC");
$stmtEvents->execute([$uid]);
$allEvents = $stmtEvents->fetchAll();

if (empty($allEvents)) {
    $_SESSION['toast_type'] = 'warning'; 
    $_SESSION['toast_message'] = 'Belum ada acara yang disusun lintasannya (Seeding).';
    header("Location: index.php"); exit;
}

// Sertakan layout dashboard (TopBar dan Sidebar)
include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>
<style>
    /* --- TAMPILAN LAYAR (Preview) --- */
    * { box-sizing: border-box; }
    body { font-family: 'Courier New', Courier, monospace; color: #000; }

    .paper { 
        background: white; 
        width: 210mm; /* Lebar A4 */
        min-height: 297mm; 
        padding: 20mm; /* Margin yang lega */
        margin: 40px auto; 
        display: flex; 
        flex-direction: column; 
        position: relative;
        box-shadow: 0 0 15px rgba(0,0,0,0.1);
    }

    /* --- HEADER PROPORSIONAL --- */
    .header-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        width: 100%;
    }
    .header-logo { width: 90px; height: 90px; display: flex; align-items: center; justify-content: center; }
    .header-logo img { max-width: 100%; max-height: 100%; object-fit: contain; }
    
    .header-text { text-align: center; flex: 1; margin: 0 20px; }
    .header-text h2 { margin: 0; font-size: 18px; font-weight: bold; letter-spacing: 1px; }
    .header-text p { margin: 5px 0; font-size: 11px; font-weight: bold; color: #444; }
    .header-text h1 { margin: 15px 0 0 0; font-size: 34px; font-weight: bold; letter-spacing: 6px; }

    .double-line { border-top: 5px double #000; margin: 15px 0; }

    /* --- DETAIL ACARA --- */
    .event-meta { display: flex; justify-content: space-between; font-weight: bold; font-size: 15px; margin-bottom: 5px; text-transform: uppercase; }
    .event-title-box { 
        text-align: center; 
        font-size: 21px; 
        font-weight: 900; 
        border: 2.5px solid #000; 
        padding: 12px; 
        margin: 15px 0 35px 0; 
        text-transform: uppercase; 
        font-style: italic;
        background: #fdfdfd;
    }

    /* --- TABEL PROPORSIONAL (REVISI FINAL PROPORSIONAL) --- */
    .table-report { 
        width: 100%; 
        border-collapse: collapse; 
        font-size: 13px; 
        margin-bottom: 45px; 
        table-layout: fixed; 
    }
    .table-report th { 
        border-bottom: 2px solid #000; 
        padding: 12px 5px; /* Padding lebih besar agar teks header tidak tumpang tindih */
        text-align: left; 
        font-weight: bold; 
        text-transform: uppercase;
    }
    .table-report td { 
        padding: 8px 5px; 
        vertical-align: top; 
        border-bottom: 1px solid #eee; 
        word-wrap: break-word;
        white-space: normal;
    }
    
    /* Penentuan Lebar Kolom yang Dioptimalkan */
    .col-ln { width: 35px; text-align: center; } /* Paling minimal */
    .col-nama { width: 240px; } /* Paling lebar untuk nama lengkap */
    .col-kab { width: 90px; } /* Dikurangi */
    .col-sekolah { width: 180px; } /* Ditingkatkan sedikit */
    .col-prestasi { width: 80px; text-align: center; } /* Cukup untuk waktu */
    .col-seri { width: 60px; text-align: center; } /* Minimalis untuk juri */

    /* --- FOOTER & SPONSOR --- */
    .sponsor-footer { 
        margin-top: auto; 
        padding-top: 20px; 
        border-top: 1px solid #eee;
        display: flex; 
        justify-content: center; 
        align-items: center; 
        gap: 30px; 
        flex-wrap: wrap;
    }
    .sponsor-footer img { height: 32px; width: auto; filter: grayscale(1); opacity: 0.6; }
    .print-meta { margin-top: 15px; font-size: 9px; text-align: right; color: #999; font-weight: bold; text-transform: uppercase; }

    /* --- PRINT CONFIG (Kunci) --- */
    @media print {
        /* Sembunyikan semua elemen dashboard */
        #logo-sidebar, nav, header, aside, .no-print, [role="navigation"], .pt-24 { 
            display: none !important; 
        }

        /* Atur layout body/html untuk cetak */
        body, html {
            background: white !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            height: auto !important;
            overflow: visible !important;
        }

        /* Hapus offset sidebar */
        .sm\:ml-64, main, .p-6 { 
            margin: 0 !important; 
            padding: 0 !important; 
            width: 100% !important;
            display: block !important;
            position: relative !important;
        }

        /* Kontainer Paper */
        .paper { 
            margin: 0 !important; 
            width: 100% !important; 
            box-shadow: none !important; 
            border: none !important; 
            page-break-after: always; 
        }
        .paper:last-child { page-break-after: auto; }
        @page { size: A4 portrait; margin: 10mm; }
        
        /* Pastikan tabel tidak terpotong di tengah seri */
        .table-report { page-break-inside: avoid; }
        tr { page-break-inside: avoid; }
    }
</style>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen">
    
    <div class="max-w-full mb-10 no-print">
        <div class="flex flex-col md:flex-row justify-between items-center gap-6 bg-white p-6 rounded-[2rem] border border-slate-200 shadow-sm">
            <div>
                <h1 class="text-2xl font-black uppercase italic text-slate-900 leading-none">Buku Acara Lengkap Preview</h1>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-2">Pratinjau <?= count($allEvents) ?> nomor lomba yang telah disusun</p>
            </div>
            <div class="flex gap-3">
                <a href="index.php" class="bg-white border-2 border-slate-100 px-6 py-3 rounded-2xl font-black text-[10px] uppercase text-slate-400 hover:text-slate-600 transition">← Kembali ke Seeding</a>
                <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-black px-10 py-4 rounded-2xl text-[10px] uppercase shadow-xl shadow-blue-100 transition transform active:scale-95 flex items-center gap-2">
                    <span>🖨️</span> CETAK SEMUA SEKARANG
                </button>
            </div>
        </div>
    </div>

    <?php foreach($allEvents as $info): 
        // Ambil data Seri (Heats)
        $stmtHeats = $pdo->prepare("SELECT * FROM race_heats WHERE category_id = ? ORDER BY heat_number ASC");
        $stmtHeats->execute([$info['id']]); 
        $heats = $stmtHeats->fetchAll();
    ?>
    
    <div class="paper">
        <div class="header-container">
            <div class="header-logo">
                <?php if(!empty($user['logo_left'])): ?><img src="../../../public/<?= $user['logo_left'] ?>"><?php endif; ?>
            </div>
            <div class="header-text">
                <h2><?= strtoupper(htmlspecialchars($user['nama_lengkap'])) ?></h2>
                <p><?= date('d F Y', strtotime($user['event_start_date'])) ?> - <?= date('d F Y', strtotime($user['event_end_date'])) ?></p>
                <h1>BUKU ACARA</h1>
            </div>
            <div class="header-logo">
                <?php if(!empty($user['logo_right'])): ?><img src="../../../public/<?= $user['logo_right'] ?>"><?php endif; ?>
            </div>
        </div>

        <div class="double-line"></div>
        
        <div class="event-meta">
            <span>ACARA <?= $info['event_no'] ?></span>
            <span><?= date('d F Y', strtotime($info['event_date'])) ?></span>
        </div>
        
        <div class="event-title-box">
            <?= $info['distance'] ?> M <?= htmlspecialchars($info['style']) ?> <?= ($info['gender'] == 'Male' ? 'PUTRA' : 'PUTRI') ?>
        </div>

        <?php foreach($heats as $h): 
            $stmtL = $pdo->prepare("SELECT rl.*, s.nama_atlet, u.nama_lengkap as klub, u.location as kab 
                                    FROM race_lines rl JOIN swimmers s ON rl.swimmer_id = s.id 
                                    JOIN users u ON s.user_id = u.id WHERE rl.heat_id = ? ORDER BY rl.lane_number ASC");
            $stmtL->execute([$h['id']]); $lanes = $stmtL->fetchAll();
            $mapped = []; foreach($lanes as $l) { $mapped[$l['lane_number']] = $l; }
        ?>
        <table class="table-report">
            <thead>
                <tr>
                    <th class="col-ln">LN</th>
                    <th class="col-nama">NAMA ATLET</th>
                    <th class="col-kab">KABUPATEN</th>
                    <th class="col-sekolah">ASAL SEKOLAH / KLUB</th>
                    <th class="col-prestasi">PRESTASI</th>
                    <th class="col-seri">SERI <?= $h['heat_number'] ?></th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $laneCount = $user['lane_count'] ?: 8;
                for($i=1; $i<=$laneCount; $i++): 
                    $sw = $mapped[$i] ?? null;
                ?>
                <tr>
                    <td class="col-ln"><b><?= $i ?></b></td>
                    <td class="col-nama"><b><?= $sw ? strtoupper(htmlspecialchars($sw['nama_atlet'])) : '<KOSONG>' ?></b></td>
                    <td class="col-kab"><?= $sw ? strtoupper(htmlspecialchars($sw['kab'] ?: '-')) : '' ?></td>
                    <td class="col-sekolah"><?= $sw ? strtoupper(htmlspecialchars($sw['klub'])) : '' ?></td>
                    <td class="col-prestasi"><b><?= $sw ? $sw['entry_time'] : '' ?></b></td>
                    <td class="col-seri" style="color: #999;">_ _ _ _ _</td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
        <?php endforeach; ?>

        <?php if(!empty($sponsors)): ?>
        <div class="sponsor-footer">
            <?php foreach($sponsors as $sp): ?>
                <img src="../../../public/<?= $sp['image_path'] ?>">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="print-meta">
            Generated by SwimMeet Registration System: <?= date('d/m/Y H:i') ?>
        </div>
    </div>
    
    <?php endforeach; ?>
    </div>