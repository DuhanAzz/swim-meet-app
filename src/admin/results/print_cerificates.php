<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$lineId = $_GET['id'] ?? 0;

// Ambil data detail untuk sertifikat
$sql = "SELECT rl.*, s.nama_atlet, u_klub.nama_lengkap as nama_klub, 
               ec.distance, ec.style, ec.age_group, ec.gender,
               u_admin.nama_lengkap as event_name, u_admin.event_start_date, u_admin.location
        FROM race_lines rl
        JOIN race_heats rh ON rl.heat_id = rh.id
        JOIN swimmers s ON rl.swimmer_id = s.id
        JOIN event_categories ec ON rh.category_id = ec.id
        JOIN event_entries ee ON (rl.swimmer_id = ee.swimmer_id AND ec.id = ee.category_id)
        JOIN users u_klub ON ee.club_id = u_klub.id
        JOIN users u_admin ON ec.user_id = u_admin.id
        WHERE rl.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$lineId]);
$d = $stmt->fetch();

if (!$d) die("Data tidak ditemukan.");

$rankText = ($d['rank'] == 1) ? "PERTAMA (I)" : (($d['rank'] == 2) ? "KEDUA (II)" : "KETIGA (III)");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Sertifikat - <?= $d['nama_atlet'] ?></title>
    <style>
        @page { size: A4 landscape; margin: 0; }
        body { margin: 0; padding: 0; font-family: 'Georgia', serif; background: #fff; }
        
        .certificate-container {
            width: 297mm; height: 210mm; padding: 20mm; box-sizing: border-box;
            position: relative; border: 15px solid #0F172A; /* Bingkai Mewah */
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            text-align: center; color: #1e293b; background: #fff;
        }

        /* Watermark Background / Border tambahan */
        .inner-border {
            position: absolute; top: 10mm; left: 10mm; right: 10mm; bottom: 10mm;
            border: 2px solid #e2e8f0; pointer-events: none;
        }

        .header-event { font-size: 14pt; font-weight: bold; text-transform: uppercase; margin-bottom: 5mm; letter-spacing: 3px; color: #3b82f6; }
        .title { font-size: 42pt; font-weight: 900; margin: 0; text-transform: uppercase; font-family: 'Arial Black', sans-serif; letter-spacing: -1px; }
        .sub-title { font-size: 18pt; margin: 5mm 0 15mm 0; font-style: italic; color: #64748b; }
        
        .awarded-to { font-size: 12pt; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 2mm; }
        .name { font-size: 32pt; font-weight: bold; border-bottom: 2px solid #1e293b; display: inline-block; padding: 0 20mm 2mm 20mm; margin-bottom: 10mm; text-transform: uppercase; }
        
        .achievement { font-size: 16pt; line-height: 1.6; max-width: 80%; margin: 0 auto; }
        .highlight { font-weight: bold; color: #000; }

        .footer { margin-top: 20mm; width: 100%; display: flex; justify-content: space-around; align-items: flex-end; }
        .signature-box { width: 60mm; text-align: center; }
        .signature-line { border-top: 1px solid #000; margin-top: 20mm; padding-top: 2mm; font-weight: bold; text-transform: uppercase; font-size: 10pt; }

        @media print {
            body { -webkit-print-color-adjust: exact; }
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="certificate-container">
        <div class="inner-border"></div>

        <div class="header-event"><?= htmlspecialchars($d['event_name']) ?></div>
        <h1 class="title">PIAGAM PENGHARGAAN</h1>
        <div class="sub-title">Certificate of Achievement</div>

        <div class="awarded-to">Diberikan Kepada :</div>
        <div class="name"><?= htmlspecialchars($d['nama_atlet']) ?></div>

        <div class="achievement">
            Atas prestasinya sebagai <span class="highlight italic">JUARA <?= $rankText ?></span><br>
            Pada nomor pertandingan <span class="highlight"><?= $d['distance'] ?>m <?= $d['style'] ?> (<?= $d['gender'] ?>)</span><br>
            Dengan catatan waktu <span class="highlight"><?= $d['result_time'] ?></span>
        </div>

        <div class="footer">
            <div class="signature-box">
                <p style="font-size: 10pt;"><?= htmlspecialchars($d['location']) ?>, <?= date('d F Y', strtotime($d['event_start_date'])) ?></p>
                <div class="signature-line">Ketua Panitia Pelaksana</div>
            </div>
            <div class="signature-box" style="opacity: 0.1;">
                <img src="/swim-meet/public/img/logo.png" style="width: 30mm; margin-bottom: -10mm;">
            </div>
            <div class="signature-box">
                <p style="font-size: 10pt;">Mengetahui,</p>
                <div class="signature-line">Referee / Juri Utama</div>
            </div>
        </div>
    </div>

</body>
</html>