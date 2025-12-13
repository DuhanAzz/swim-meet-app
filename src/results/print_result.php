<?php
session_start();
require_once __DIR__ . '/../../src/config/database.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'master')) die("Akses Ditolak.");

$event_id = $_GET['event_id'] ?? 0;

// Data Event
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch();
if (!$event) die("Event tidak ditemukan");

// Data Hasil (Ranking) - Diurutkan berdasarkan Rank
$stmt = $pdo->prepare("SELECT 
    he.lane_number, he.final_time, he.rank, he.status,
    s.nama_atlet, c.nama_klub 
    FROM heat_entries he
    JOIN heats h ON he.heat_id = h.id
    JOIN swimmers s ON he.swimmer_id = s.id
    JOIN clubs c ON s.club_id = c.id
    WHERE h.event_id = ?
    ORDER BY 
        CASE WHEN he.rank IS NULL THEN 1 ELSE 0 END, -- Yang ada rank di atas
        he.rank ASC, 
        he.final_time ASC");
$stmt->execute([$event_id]);
$results = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Official Result - <?= htmlspecialchars($event['nama_event']) ?></title>
    <style>
        body { font-family: 'Arial', sans-serif; font-size: 12px; color: #000; }
        .header { text-align: center; margin-bottom: 25px; border-bottom: 3px double #000; padding-bottom: 10px; }
        .header h1 { margin: 0; font-size: 20px; text-transform: uppercase; letter-spacing: 1px; }
        .header h2 { margin: 5px 0; font-size: 14px; font-weight: normal; }
        .info { display: flex; justify-content: space-between; margin-bottom: 15px; font-weight: bold; font-size: 11px; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border-bottom: 1px solid #ccc; padding: 6px 4px; text-align: left; }
        th { border-top: 2px solid #000; border-bottom: 2px solid #000; font-weight: bold; text-transform: uppercase; font-size: 11px; }
        
        .rank-col { font-weight: bold; font-size: 13px; text-align: center; width: 40px; }
        .time-col { font-family: 'Courier New', monospace; font-weight: bold; text-align: right; width: 100px; }
        .status-dq { color: red; font-style: italic; }
        
        /* Footer Tanda Tangan */
        .signature { margin-top: 50px; display: flex; justify-content: space-between; page-break-inside: avoid; }
        .sig-box { text-align: center; width: 200px; }
        .sig-line { margin-top: 60px; border-top: 1px solid #000; }

        @media print {
            @page { margin: 1.5cm; size: A4; }
            .no-print { display: none; }
            body { background: #fff; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="no-print" style="margin-bottom: 20px; text-align: center; background: #f0f0f0; padding: 10px;">
        <button onclick="window.history.back()" style="padding: 8px 15px; cursor: pointer;">&larr; Kembali</button>
        <button onclick="window.print()" style="padding: 8px 15px; cursor: pointer; font-weight: bold; background: #000; color: #fff; border: none;">🖨️ CETAK HASIL</button>
    </div>

    <div class="header">
        <h1>HASIL RESMI (OFFICIAL RESULT)</h1>
        <h2>SET SYSTEM CHAMPIONSHIP</h2>
    </div>

    <div class="info">
        <span>NOMOR: <?= htmlspecialchars($event['nama_event']) ?></span>
        <span>KATEGORI: <?= $event['jenis_kelamin']=='L'?'PUTRA':'PUTRI' ?> (<?= $event['batas_umur_bawah'] ?>-<?= $event['batas_umur_atas'] ?> TH)</span>
        <span>JARAK: <?= $event['jarak'] ?>M <?= $event['gaya'] ?></span>
    </div>

    <table>
        <thead>
            <tr>
                <th class="rank-col">Rank</th>
                <th>Nama Atlet</th>
                <th>Klub / Kontingen</th>
                <th class="text-center" width="50">Ln</th>
                <th class="time-col">Waktu</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($results as $r): ?>
            <tr>
                <td class="rank-col">
                    <?php 
                        if ($r['status'] !== 'OK') echo '-';
                        elseif ($r['rank']) echo $r['rank'];
                        else echo '';
                    ?>
                </td>
                <td><?= htmlspecialchars($r['nama_atlet']) ?></td>
                <td><?= htmlspecialchars($r['nama_klub']) ?></td>
                <td style="text-align: center; font-size: 10px; color: #666;"><?= $r['lane_number'] ?></td>
                <td class="time-col">
                    <?php 
                        if ($r['status'] == 'OK') echo $r['final_time'];
                        else echo "<span class='status-dq'>".$r['status']."</span>";
                    ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="font-size: 10px; margin-top: 5px;">
        Total Peserta: <?= count($results) ?> | Dicetak: <?= date('d/m/Y H:i') ?>
    </div>

    <div class="signature">
        <div class="sig-box">
            <div>Ketua Perlombaan</div>
            <div class="sig-line">Nama & Tanda Tangan</div>
        </div>
        <div class="sig-box">
            <div>Wasit Utama (Referee)</div>
            <div class="sig-line">Nama & Tanda Tangan</div>
        </div>
    </div>

</body>
</html>
