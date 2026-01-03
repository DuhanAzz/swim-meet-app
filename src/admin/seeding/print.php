<?php
// src/admin/seeding/print_buku_acara.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Akses ditolak.");
}

$uid = $_SESSION['user_id'];
$catId = (int)($_GET['category_id'] ?? 0);

if (!$catId) die("Kategori belum dipilih.");

// 1. Ambil Info Kategori & Event (FIXED JOIN)
$stmtCat = $pdo->prepare("
    SELECT en.*, e.nama_event, e.lokasi, e.venue_name, e.event_start_date, e.logo_left, e.logo_right, e.id as event_id
    FROM event_numbers en
    JOIN events e ON en.organizer_id = e.id
    WHERE en.id = ?
");
$stmtCat->execute([$catId]);
$info = $stmtCat->fetch();

if (!$info) die("Data nomor lomba tidak ditemukan.");

// 2. Ambil Sponsor untuk Footer
$stmtSpon = $pdo->prepare("SELECT image_path FROM event_sponsors WHERE event_id = ?");
$stmtSpon->execute([$info['event_id']]);
$sponsors = $stmtSpon->fetchAll(PDO::FETCH_COLUMN);

// 3. Ambil Start List (Heat & Lane)
$stmtH = $pdo->prepare("SELECT * FROM race_heats WHERE category_id = ? ORDER BY heat_number ASC");
$stmtH->execute([$catId]);
$rawHeats = $stmtH->fetchAll();

$heats = [];
foreach($rawHeats as $h) {
    // Ambil detail per lintasan
    $sqlL = "SELECT rl.*, s.nama_atlet, s.tanggal_lahir, c.club_name 
             FROM race_lines rl 
             JOIN swimmers s ON rl.swimmer_id = s.id 
             LEFT JOIN clubs c ON rl.club_id = c.id
             WHERE rl.heat_id = ? ORDER BY rl.lane_number ASC";
             
    $stmtL = $pdo->prepare($sqlL);
    $stmtL->execute([$h['id']]);
    $h['lanes'] = $stmtL->fetchAll();
    $heats[] = $h;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>BUKU ACARA - <?= htmlspecialchars($info['nama_event']) ?></title>
    <style>
        /* CSS KHUSUS CETAK BUKU ACARA */
        @page { size: A4; margin: 1.5cm; }
        body { font-family: 'Arial Narrow', Arial, sans-serif; font-size: 11px; margin: 0; padding: 0; color: #000; }
        
        /* Layout Header */
        .header-table { width: 100%; border-bottom: 2px solid #000; margin-bottom: 10px; }
        .logo-img { max-height: 70px; max-width: 90px; object-fit: contain; }
        .event-info { text-align: center; }
        .event-info h1 { margin: 0; font-size: 16px; font-weight: 900; text-transform: uppercase; }
        .event-info p { margin: 2px 0; font-size: 10px; font-weight: bold; }
        
        /* Banner Nomor Lomba */
        .race-banner { background: #000; color: #fff; padding: 6px 12px; margin: 15px 0 10px; display: flex; justify-content: space-between; align-items: center; }
        .race-banner h2 { margin: 0; font-size: 12px; text-transform: uppercase; italic; }
        
        .seri-label { background: #f0f0f0; padding: 4px 10px; font-weight: 900; border-left: 5px solid #000; margin-top: 15px; font-size: 11px; }

        /* Tabel Data */
        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        th { border-bottom: 2px solid #333; padding: 6px 4px; text-align: left; font-size: 9px; text-transform: uppercase; }
        td { border-bottom: 1px solid #ddd; padding: 5px 4px; }
        .col-ln { width: 30px; text-align: center; font-weight: bold; }
        .col-time { width: 80px; text-align: center; font-family: monospace; font-weight: bold; }
        .col-birth { width: 60px; text-align: center; }
        
        /* Footer Sponsor Fixed */
        .footer-sponsor { position: fixed; bottom: 0; left: 0; right: 0; height: 50px; text-align: center; border-top: 1px solid #eee; padding-top: 5px; background: white; }
        .footer-sponsor img { height: 30px; margin: 0 10px; filter: grayscale(100%); opacity: 0.6; }
        
        .no-print { background: #444; color: white; padding: 10px; text-align: center; margin-bottom: 20px; }
        @media print { 
            .no-print { display: none; } 
            .footer-sponsor { position: fixed; bottom: 0; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="no-print">
        <button onclick="window.print()" style="font-weight:bold; cursor:pointer;">🖨️ CETAK SEKARANG</button>
        <button onclick="window.close()" style="margin-left:10px;">Tutup</button>
    </div>

    <table class="header-table">
        <tr>
            <td width="100">
                <?php if($info['logo_left']): ?>
                    <img src="../../../public/<?= $info['logo_left'] ?>" class="logo-img">
                <?php endif; ?>
            </td>
            <td class="event-info">
                <h1><?= htmlspecialchars($info['nama_event']) ?></h1>
                <p><?= htmlspecialchars($info['venue_name']) ?> | <?= htmlspecialchars($info['lokasi']) ?></p>
                <p>TANGGAL: <?= date('d F Y', strtotime($info['event_start_date'])) ?></p>
                <div style="margin-top:5px; border:1px solid #000; display:inline-block; padding:2px 10px; font-weight:900;">START LIST</div>
            </td>
            <td width="100" align="right">
                <?php if($info['logo_right']): ?>
                    <img src="../../../public/<?= $info['logo_right'] ?>" class="logo-img">
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <div class="race-banner">
        <h2>EVENT #<?= $catId ?> - <?= $info['distance'] ?>M <?= strtoupper($info['stroke']) ?> - <?= strtoupper($info['age_group']) ?></h2>
        <span>FINAL</span>
    </div>

    <?php if(empty($heats)): ?>
        <p style="text-align:center; margin-top:50px; color:#999;">Belum ada data seeding untuk nomor lomba ini.</p>
    <?php else: ?>
        <?php foreach($heats as $h): ?>
            <div style="break-inside: avoid; margin-bottom: 20px;">
                <div class="seri-label">SERI <?= $h['heat_number'] ?></div>
                <table>
                    <thead>
                        <tr>
                            <th class="col-ln">LN</th>
                            <th>NAMA ATLET</th>
                            <th class="col-birth">LAHIR</th>
                            <th>TIM / KLUB</th>
                            <th class="col-time">WAKTU DAFTAR</th>
                            <th width="60">HASIL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $lanes = array_fill(1, 8, null);
                        foreach($h['lanes'] as $l) $lanes[$l['lane_number']] = $l;
                        
                        for($i=1; $i<=8; $i++): 
                            $sw = $lanes[$i];
                        ?>
                        <tr>
                            <td class="col-ln"><?= $i ?></td>
                            <td><?= $sw ? strtoupper(htmlspecialchars($sw['nama_atlet'])) : '<span style="color:#eee">---</span>' ?></td>
                            <td class="col-birth"><?= $sw ? date('Y', strtotime($sw['tanggal_lahir'])) : '-' ?></td>
                            <td><?= $sw ? strtoupper(htmlspecialchars($sw['club_name'] ?? 'PERSONAL')) : '-' ?></td>
                            <td class="col-time"><?= ($sw && $sw['entry_time']) ? $sw['entry_time'] : 'NT' ?></td>
                            <td style="border-bottom: 1px dotted #ccc;"></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if(!empty($sponsors)): ?>
    <div class="footer-sponsor">
        <div style="font-size: 8px; color: #999; margin-bottom: 3px;">SUPPORTED BY:</div>
        <?php foreach($sponsors as $img): ?>
            <img src="../../../public/<?= $img ?>">
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</body>
</html>