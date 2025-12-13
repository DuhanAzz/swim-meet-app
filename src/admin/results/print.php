<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Akses ditolak.");
}

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

// 2. Ambil Hasil Akhir (Ranking)
// PERBAIKAN SQL DI SINI: Menggunakan 'u.nama_lengkap as nama_klub'
$sql = "SELECT rl.*, s.nama_atlet, u.nama_lengkap as nama_klub, rh.heat_number
        FROM race_lines rl
        JOIN race_heats rh ON rl.heat_id = rh.id
        JOIN swimmers s ON rl.swimmer_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE rh.category_id = ? 
        ORDER BY 
            CASE WHEN rl.rank IS NULL THEN 1 ELSE 0 END, -- Null rank di bawah
            rl.rank ASC, 
            rl.result_time ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$catId]);
$results = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Result - <?= $info['distance'] ?>m <?= $info['style'] ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .header h1 { margin: 0; font-size: 18px; text-transform: uppercase; }
        .header p { margin: 5px 0 0; font-size: 12px; color: #555; }
        
        .race-title { font-size: 16px; font-weight: bold; margin: 20px 0 10px; padding: 5px; background: #eee; border: 1px solid #ccc; text-align: center; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background-color: #f9f9f9; font-size: 10px; text-transform: uppercase; }
        td { font-size: 12px; }
        
        .rank { width: 40px; text-align: center; font-weight: bold; font-size: 14px; }
        .time { width: 100px; text-align: right; font-family: 'Courier New', monospace; font-weight: bold; }
        .heat { width: 50px; text-align: center; color: #777; font-size: 10px; }
        
        .top3 { background-color: #fffbeb; } /* Highlight Juara */

        @media print {
            @page { size: A4; margin: 1cm; }
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="no-print" style="margin-bottom: 20px; text-align: center;">
        <button onclick="window.close()" style="padding: 10px 20px; cursor: pointer; background: #333; color: white; border: none; font-weight: bold;">Tutup</button>
    </div>

    <div class="header">
        <h1><?= htmlspecialchars($info['event_name']) ?></h1>
        <p>HASIL PERTANDINGAN RESMI (OFFICIAL RESULT)</p>
        <p><?= date('d F Y', strtotime($info['event_start_date'])) ?> • <?= htmlspecialchars($info['location']) ?></p>
    </div>

    <div class="race-title">
        <?= $info['distance'] ?>m <?= htmlspecialchars($info['style']) ?> - <?= $info['gender'] == 'Male' ? 'PUTRA' : ($info['gender'] == 'Female' ? 'PUTRI' : 'MIXED') ?> (<?= htmlspecialchars($info['age_group']) ?>)
    </div>

    <?php if(empty($results)): ?>
        <p style="text-align: center; font-style: italic; margin-top: 50px;">Belum ada hasil yang diinput.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th class="rank">POS</th>
                    <th>NAMA ATLET</th>
                    <th>ASAL KLUB / SEKOLAH</th>
                    <th class="heat">SERI</th>
                    <th class="time">WAKTU</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($results as $r): 
                    $isTop3 = ($r['rank'] >= 1 && $r['rank'] <= 3);
                ?>
                <tr class="<?= $isTop3 ? 'top3' : '' ?>">
                    <td class="rank">
                        <?= $r['rank'] ? $r['rank'] : '-' ?>
                    </td>
                    <td>
                        <?= strtoupper($r['nama_atlet']) ?>
                        <?php if($r['rank']==1) echo ' 🥇'; ?>
                        <?php if($r['rank']==2) echo ' 🥈'; ?>
                        <?php if($r['rank']==3) echo ' 🥉'; ?>
                    </td>
                    <td><?= strtoupper($r['nama_klub']) ?></td>
                    <td class="heat">S<?= $r['heat_number'] ?></td>
                    <td class="time"><?= $r['result_time'] ? $r['result_time'] : 'DNS' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <table style="border: none; margin-top: 40px; width: 100%;">
        <tr style="border: none;">
            <td style="border: none; width: 70%;"></td>
            <td style="border: none; text-align: center;">
                <p>Mengetahui,<br>Referee / Ketua Panitia</p>
                <br><br><br>
                <p>__________________________</p>
            </td>
        </tr>
    </table>

</body>
</html>