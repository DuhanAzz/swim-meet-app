<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Akses ditolak.");
}

$uid = $_SESSION['user_id'];
$catId = $_GET['category_id'] ?? 0;

if (!$catId) die("Kategori belum dipilih.");

// 1. Ambil Info Kategori & Event
$stmtCat = $pdo->prepare("
    SELECT ec.*, u.nama_lengkap as event_name, u.location, u.event_start_date 
    FROM event_categories ec
    JOIN users u ON ec.user_id = u.id
    WHERE ec.id = ?
");
$stmtCat->execute([$catId]);
$info = $stmtCat->fetch();

// 2. Ambil Start List
$stmtH = $pdo->prepare("SELECT * FROM race_heats WHERE category_id = ? ORDER BY heat_number ASC");
$stmtH->execute([$catId]);
$rawHeats = $stmtH->fetchAll();

$heats = [];
foreach($rawHeats as $h) {
    // PERBAIKAN SQL DI SINI:
    // Mengganti 'u.nama_klub' menjadi 'u.nama_lengkap as nama_klub'
    $sqlL = "SELECT rl.*, s.nama_atlet, u.nama_lengkap as nama_klub 
             FROM race_lines rl 
             JOIN swimmers s ON rl.swimmer_id = s.id 
             JOIN users u ON s.user_id = u.id
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
    <title>Start List - <?= $info['distance'] ?>m <?= $info['style'] ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .header h1 { margin: 0; font-size: 18px; text-transform: uppercase; }
        .header p { margin: 5px 0 0; font-size: 12px; color: #555; }
        
        .race-title { font-size: 16px; font-weight: bold; margin: 20px 0 10px; padding: 5px; background: #eee; border: 1px solid #ccc; }
        
        .heat-container { break-inside: avoid; margin-bottom: 20px; }
        .heat-header { font-weight: bold; font-size: 14px; margin-bottom: 5px; border-bottom: 1px solid #000; padding-bottom: 2px; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background-color: #f9f9f9; font-size: 10px; text-transform: uppercase; }
        td { font-size: 11px; }
        .ln { width: 30px; text-align: center; font-weight: bold; }
        .seed { width: 80px; text-align: right; font-family: 'Courier New', monospace; }
        .club { width: 30%; }
        
        /* Print Settings */
        @media print {
            @page { size: A4; margin: 1cm; }
            .no-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="no-print" style="margin-bottom: 20px; text-align: center;">
        <button onclick="window.close()" style="padding: 10px 20px; cursor: pointer; background: #333; color: white; border: none; font-weight: bold;">Tutup Jendela</button>
        <p style="margin-top: 5px; color: red;">*Jendela cetak akan muncul otomatis. Jika tidak, tekan Ctrl + P</p>
    </div>

    <div class="header">
        <h1><?= htmlspecialchars($info['event_name']) ?></h1>
        <p>Lokasi: <?= htmlspecialchars($info['location']) ?> | Tanggal: <?= date('d F Y', strtotime($info['event_start_date'])) ?></p>
    </div>

    <div class="race-title">
        NOMOR LOMBA: <?= $info['distance'] ?>m <?= htmlspecialchars($info['style']) ?> - <?= $info['gender'] == 'Male' ? 'PUTRA' : ($info['gender'] == 'Female' ? 'PUTRI' : 'MIXED') ?> (<?= htmlspecialchars($info['age_group']) ?>)
    </div>

    <?php if(empty($heats)): ?>
        <p style="text-align: center; font-style: italic;">Belum ada start list.</p>
    <?php else: ?>
        <?php foreach($heats as $h): ?>
            <div class="heat-container">
                <div class="heat-header">SERI <?= $h['heat_number'] ?></div>
                <table>
                    <thead>
                        <tr>
                            <th class="ln">LN</th>
                            <th>NAMA ATLET</th>
                            <th class="club">ASAL KLUB / SEKOLAH</th>
                            <th class="seed">WAKTU</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $lanes = array_fill(1, 8, null);
                        foreach($h['lanes'] as $l) $lanes[$l['lane_number']] = $l;
                        
                        for($i=1; $i<=8; $i++): 
                            $swimmer = $lanes[$i];
                        ?>
                        <tr>
                            <td class="ln"><?= $i ?></td>
                            <td><?= $swimmer ? strtoupper($swimmer['nama_atlet']) : '&nbsp;' ?></td>
                            <td class="club"><?= $swimmer ? strtoupper($swimmer['nama_klub']) : '&nbsp;' ?></td>
                            <td class="seed"><?= $swimmer ? $swimmer['entry_time'] : '' ?></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div style="margin-top: 30px; font-size: 10px; color: #777; border-top: 1px solid #ccc; padding-top: 5px;">
        Dicetak pada: <?= date('d-m-Y H:i:s') ?> oleh Sistem SwimMeet Manager.
    </div>

</body>
</html>