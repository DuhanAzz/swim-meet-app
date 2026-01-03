<?php
// src/admin/seeding/print_buku_acara.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// 1. CEK KEAMANAN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Akses ditolak.");
}

// AMBIL ID NOMOR LOMBA
$cat_id = $_GET['event_id'] ?? ($_GET['category_id'] ?? null);
if (!$cat_id) { die("Kategori belum dipilih."); }

// 2. CONFIG CETAK
$pc = $_SESSION['print_config'] ?? [
    'show_event_no' => true, 'show_date' => true, 'show_event_name' => true,
    'show_group' => true, 'show_gender' => true, 'show_pool' => true, 'show_round' => true
];

// 3. AMBIL INFO NOMOR LOMBA
$stmtRace = $pdo->prepare("SELECT * FROM event_numbers WHERE id = ?");
$stmtRace->execute([$cat_id]);
$raceInfo = $stmtRace->fetch(PDO::FETCH_ASSOC);

if (!$raceInfo) die("Nomor lomba tidak ditemukan.");

// AMBIL HEADER EVENT
$stmtEvent = $pdo->prepare("SELECT * FROM events WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmtEvent->execute([$raceInfo['organizer_id']]);
$eventProfile = $stmtEvent->fetch(PDO::FETCH_ASSOC);

// SETUP DATA HEADER
$header_title = strtoupper($eventProfile['nama_event'] ?? 'NAMA EVENT BELUM DISET');
$venue_name   = strtoupper($eventProfile['venue_name'] ?? ($eventProfile['lokasi'] ?? ''));
$event_date   = !empty($eventProfile['event_start_date']) ? $eventProfile['event_start_date'] : date('Y-m-d');
$total_lintasan = (int)($eventProfile['lane_count'] ?? 8);
$pool_type    = strtoupper($eventProfile['pool_type'] ?? 'LCM');
$parentEventId = $eventProfile['id'] ?? 0;

// LOGO
$logo_left  = !empty($eventProfile['logo_left']) ? '../../../public/' . $eventProfile['logo_left'] : null;
$logo_right = !empty($eventProfile['logo_right']) ? '../../../public/' . $eventProfile['logo_right'] : null;

// TANGGAL
$display_date = strtoupper(date('d F Y', strtotime($event_date)));
$event_year   = date('Y', strtotime($event_date));

// KOP TANGGAL
if(!empty($eventProfile['event_end_date']) && strtotime($eventProfile['event_start_date']) != strtotime($eventProfile['event_end_date'])) {
    $header_date_range = date('d', strtotime($eventProfile['event_start_date'])) . ' - ' . date('d F Y', strtotime($eventProfile['event_end_date']));
} else {
    $header_date_range = $display_date;
}

// 4. JUDUL DINAMIS
$judul_parts = [];
if(!empty($pc['show_event_name'])) {
    $cleanStroke = trim(str_ireplace(['Gaya', 'GAYA'], '', $raceInfo['stroke'] ?? ''));
    $judul_parts[] = ($raceInfo['distance'] ?? '') . " M GAYA " . strtoupper($cleanStroke);
}
if(!empty($pc['show_group']))      $judul_parts[] = ($raceInfo['age_group'] ?? '-'); 
if(!empty($pc['show_gender']))     $judul_parts[] = (in_array($raceInfo['jenis_kelamin'], ['L','Male'])) ? 'PUTRA' : 'PUTRI';
if(!empty($pc['show_pool']))       $judul_parts[] = $pool_type;

$judul_tengah_dinamis = implode(" - ", $judul_parts);
$nomor_acara_dinamis  = !empty($pc['show_event_no']) ? "#" . $raceInfo['event_number'] : "";
$babak_dinamis        = !empty($pc['show_round']) ? "FINAL" : "";

// 5. AMBIL DATA PESERTA
try {
    $sql = "SELECT ee.heat as heat_no, ee.lane as lane_no, ee.entry_time,
            s.nama_atlet, s.tanggal_lahir, 
            c.nama_klub as club_name, s.asal_sekolah
            FROM event_entries ee
            JOIN swimmers s ON ee.swimmer_id = s.id
            LEFT JOIN clubs c ON s.club_id = c.id
            LEFT JOIN payments p ON p.user_id = ee.club_id AND p.event_id = ee.event_id
            WHERE ee.category_id = ? AND ee.heat IS NOT NULL 
            AND (p.status = 'Paid' OR p.status = 'Verified') 
            ORDER BY ee.heat ASC, ee.lane ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cat_id]);
    $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) { die("Error Database: " . $e->getMessage()); }

// 6. SPONSOR
$stmtSpon = $pdo->prepare("SELECT image_path FROM event_sponsors WHERE event_id = ?");
$stmtSpon->execute([$parentEventId]);
$sponsors = $stmtSpon->fetchAll(PDO::FETCH_COLUMN);

// HELPER
function formatLahir($tgl, $year) {
    if(!$tgl || $tgl == '0000-00-00') return '-';
    $by = date('Y', strtotime($tgl));
    return $by . " (" . ($year - $by) . ")";
}
function shortenName($name) {
    $name = trim(preg_replace('/\s+/', ' ', $name ?? ''));
    if (strlen($name) > 22) return substr($name, 0, 22) . '...';
    return $name;
}

$heats = [];
foreach ($raw_data as $row) $heats[$row['heat_no']][$row['lane_no']] = $row;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>StartList_<?= $nomor_acara_dinamis ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700;900&family=Courier+Prime:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: 'Roboto Condensed', sans-serif; 
            margin: 0; padding: 0; background: white; color: #000; 
            font-size: 10pt; /* Font sedikit dikecilkan agar muat */
        }
        
        /* SETTING A4 AGAR SPONSOR MUAT */
        @page { 
            size: A4; 
            /* Margin Bawah agak besar (20mm) untuk tempat Footer Fixed */
            margin: 10mm 10mm 20mm 10mm; 
        }

        /* HEADER */
        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 3px double #000; padding-bottom: 5px; margin-bottom: 10px;
        }
        .logo-box { width: 70px; height: 70px; display: flex; align-items: center; justify-content: center; }
        .logo-box img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .header-text { text-align: center; flex: 1; padding: 0 5px; }
        .header-text h1 { margin: 0; font-size: 14pt; font-weight: 900; text-transform: uppercase; line-height: 1.1; }
        .header-text p { margin: 2px 0; font-size: 9pt; font-weight: bold; text-transform: uppercase; color: #333; }
        .buku-acara-badge { 
            display: inline-block; border: 2px solid #000; padding: 2px 10px; margin-top: 5px; 
            font-weight: 900; letter-spacing: 2px; font-size: 10pt;
        }

        /* INFO BAR */
        .event-info-grid {
            display: grid; grid-template-columns: 80px 1fr 80px; align-items: center;
            border-bottom: 2px solid #000; margin-bottom: 10px; padding-bottom: 5px;
        }
        .info-num { font-size: 16pt; font-weight: 900; line-height: 1; }
        .info-date { font-size: 8pt; font-weight: bold; }
        .info-title { text-align: center; font-size: 11pt; font-weight: 800; text-transform: uppercase; }
        .info-round { text-align: right; font-weight: bold; background: #eee; padding: 2px 5px; border-radius: 4px; font-size: 8pt; }

        /* TABLES */
        .heat-wrapper { margin-bottom: 15px; page-break-inside: avoid; }
        .heat-title { text-align: right; font-weight: bold; border-bottom: 1px solid #000; font-size: 9pt; margin-bottom: 2px; }
        
        table { width: 100%; border-collapse: collapse; font-size: 8.5pt; table-layout: fixed; }
        th { 
            background: #f0f0f0; border-top: 1px solid #000; border-bottom: 1px solid #000; 
            padding: 3px; text-align: left; font-weight: bold; text-transform: uppercase; 
        }
        td { border-bottom: 1px solid #ddd; padding: 2px 3px; white-space: nowrap; overflow: hidden; text-transform: uppercase; }
        
        .c-center { text-align: center; }
        .c-right { text-align: right; }
        .font-mono { font-family: 'Courier Prime', monospace; font-weight: bold; }
        .dots { letter-spacing: 2px; color: #ccc; }

        /* FOOTER SPONSOR FIXED */
        .footer-sponsor {
            position: fixed; 
            bottom: 0; left: 0; right: 0; 
            text-align: center;
            background: white; 
            padding-top: 2px;
            height: 15mm; /* Tinggi pasti footer */
            z-index: 1000;
        }
        .footer-line { border-top: 3px double #000; width: 100%; margin-bottom: 3px; }
        .footer-sponsor img { 
            height: 25px; /* PERKECIL LOGO SPONSOR */
            margin: 0 10px; 
            filter: grayscale(100%); 
            opacity: 0.8; 
            vertical-align: middle;
        }

        .no-print-bar {
            background: #333; color: #fff; padding: 10px; text-align: center;
            position: fixed; top: 0; left: 0; width: 100%; z-index: 9999;
            font-family: sans-serif;
        }
        @media print {
            .no-print-bar { display: none; }
            /* Memaksa footer tetap di bawah setiap halaman */
            .footer-sponsor { position: fixed; bottom: 0; }
        }
    </style>
</head>
<body>

    <div class="no-print-bar">
        <button onclick="window.print()" style="padding:5px 15px; cursor:pointer; font-weight:bold;">🖨️ CETAK</button>
    </div>

    <div class="page-header">
        <div class="logo-box">
            <?php if($logo_left): ?><img src="<?= $logo_left ?>"><?php endif; ?>
        </div>
        <div class="header-text">
            <h1><?= htmlspecialchars($header_title) ?></h1>
            <?php if($venue_name): ?><p><?= htmlspecialchars($venue_name) ?></p><?php endif; ?>
            <p><?= htmlspecialchars($header_date_range) ?></p>
            <div class="buku-acara-badge">BUKU ACARA</div>
        </div>
        <div class="logo-box">
            <?php if($logo_right): ?><img src="<?= $logo_right ?>"><?php endif; ?>
        </div>
    </div>

    <div class="event-info-grid">
        <div class="info-left">
            <div class="info-num"><?= $nomor_acara_dinamis ?></div>
            <div class="info-date"><?= $display_date ?></div>
        </div>
        <div class="info-title">
            <?= $judul_tengah_dinamis ?>
        </div>
        <div class="info-right">
            <span class="info-round"><?= $babak_dinamis ?></span>
        </div>
    </div>

    <?php if(empty($heats)): ?>
        <div style="text-align: center; padding: 50px; font-style: italic; color: #777;">
            Data belum tersedia atau belum di-seeding.
        </div>
    <?php else: ?>
        <?php foreach($heats as $heatNo => $lanesData): ?>
            <div class="heat-wrapper">
                <div class="heat-title">SERI <?= str_pad($heatNo, 2, '0', STR_PAD_LEFT) ?></div>
                <table>
                    <colgroup>
                        <col style="width: 5%">
                        <col style="width: 35%">
                        <col style="width: 10%">
                        <col style="width: 25%">
                        <col style="width: 15%">
                        <col style="width: 10%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="c-center">LN</th>
                            <th>NAMA ATLET</th>
                            <th class="c-center">LAHIR</th>
                            <th>TIM / SEKOLAH</th>
                            <th class="c-right">ENTRY</th>
                            <th class="c-right">HASIL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for($ln = 1; $ln <= $total_lintasan; $ln++): $s = $lanesData[$ln] ?? null; ?>
                        <tr>
                            <td class="c-center font-mono"><?= $ln ?></td>
                            <?php if($s): ?>
                                <td style="font-weight: bold;" title="<?= $s['nama_atlet'] ?>">
                                    <?= shortenName($s['nama_atlet']) ?>
                                </td>
                                <td class="c-center font-mono" style="color:#555">
                                    <?= formatLahir($s['tanggal_lahir'], $event_year) ?>
                                </td>
                                <td>
                                    <?= shortenName(!empty($s['club_name']) ? $s['club_name'] : $s['asal_sekolah']) ?>
                                </td>
                                <td class="c-right font-mono">
                                    <?= ($s['entry_time'] == '99:99.99' || !$s['entry_time']) ? 'NT' : $s['entry_time'] ?>
                                </td>
                                <td class="c-right font-mono dots">.......</td>
                            <?php else: ?>
                                <td colspan="5" style="color:#ddd; font-size: 8pt; font-style: italic;">&lt; KOSONG &gt;</td>
                            <?php endif; ?>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if(!empty($sponsors)): ?>
    <div class="footer-sponsor">
        <div class="footer-line"></div>
        <?php foreach($sponsors as $img): ?>
            <img src="../../../public/<?= $img ?>">
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</body>
</html>