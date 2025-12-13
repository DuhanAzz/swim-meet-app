<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$catId = $_GET['category_id'] ?? 0;
$uid = $_SESSION['user_id'];

// Ambil Detail Lomba & Kategori
$stmt = $pdo->prepare("SELECT u.nama_lengkap as event_name, u.venue_name, u.location, ec.* FROM users u JOIN event_categories ec ON u.id = ec.user_id 
                       WHERE ec.id = ?");
$stmt->execute([$catId]);
$info = $stmt->fetch();

// Ambil Heat Final
$stmtH = $pdo->prepare("SELECT * FROM race_heats WHERE category_id = ? AND stage = 'Final' LIMIT 1");
$stmtH->execute([$catId]);
$heat = $stmtH->fetch();

$lanes = [];
if ($heat) {
    $stmtL = $pdo->prepare("SELECT rl.*, s.nama_atlet, u.nama_lengkap as nama_klub 
                            FROM race_lines rl 
                            JOIN swimmers s ON rl.swimmer_id = s.id 
                            JOIN users u ON s.user_id = u.id
                            WHERE rl.heat_id = ? ORDER BY rl.lane_number ASC");
    $stmtL->execute([$heat['id']]);
    $lanes = $stmtL->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Print Final - <?= $info['distance'] ?>m <?= $info['style'] ?></title>
    <style>
        body { font-family: 'Courier New', monospace; padding: 40px; color: #000; }
        .header { text-align: center; border-bottom: 4px double #000; padding-bottom: 20px; margin-bottom: 30px; }
        .title { font-size: 24px; font-weight: bold; margin: 0; text-transform: uppercase; }
        .sub-title { font-size: 18px; margin: 10px 0; font-weight: bold; }
        .meta { font-size: 12px; margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { border-bottom: 2px solid #000; padding: 10px; text-align: left; font-size: 14px; text-transform: uppercase; }
        td { padding: 12px 10px; border-bottom: 1px solid #eee; font-size: 14px; }
        .center { text-align: center; }
        .right { text-align: right; }
        .font-bold { font-weight: bold; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body onload="window.print()">
    <div class="header">
        <p class="title"><?= htmlspecialchars($info['event_name']) ?></p>
        <p class="meta"><?= htmlspecialchars($info['venue_name']) ?>, <?= htmlspecialchars($info['location']) ?></p>
        <div class="sub-title">
            START LIST: BABAK FINAL<br>
            <?= $info['distance'] ?>m <?= $info['style'] ?> - <?= $info['gender'] ?> (<?= $info['age_group'] ?>)
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th width="50" class="center">LANE</th>
                <th>NAMA ATLET</th>
                <th>KLUB / SEKOLAH</th>
                <th width="120" class="right">ENTRY TIME</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $L = 8; // Adjust based on config if needed
            $mappedLanes = array_fill(1, $L, null);
            foreach($lanes as $l) { $mappedLanes[$l['lane_number']] = $l; }

            for($i=1; $i<=$L; $i++): 
                $sw = $mappedLanes[$i];
            ?>
            <tr>
                <td class="center font-bold"><?= $i ?></td>
                <td class="font-bold"><?= $sw ? strtoupper(htmlspecialchars($sw['nama_atlet'])) : '----------' ?></td>
                <td><?= $sw ? strtoupper(htmlspecialchars($sw['nama_klub'])) : '----------' ?></td>
                <td class="right font-bold"><?= $sw ? $sw['entry_time'] : '--:--.--' ?></td>
            </tr>
            <?php endfor; ?>
        </tbody>
    </table>

    <div style="margin-top: 50px; font-size: 10px; text-align: right;">
        Dicetak pada: <?= date('d/m/Y H:i:s') ?> | SWIMMEET Timing System
    </div>
</body>
</html>