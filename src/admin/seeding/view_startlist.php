<?php
// FILE: src/admin/seeding/view_startlist.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { die("Akses Ditolak"); }

// === AMBIL ID KATEGORI / NOMOR LOMBA ===
// Di index.php, tombol view mengirimkan parameter category_id
$target_id = $_GET['category_id'] ?? ($_GET['event_id'] ?? null);
if (!$target_id) die("<div style='padding:20px; text-align:center;'>Error: Parameter ID Nomor Lomba tidak ditemukan.</div>");

// DETEKSI KONFIGURASI DARI URL (Suntikan dari index.php)
$isSubmitted = !empty($_GET);
$hasConfig = false;
foreach($_GET as $k => $v) if(strpos($k, 'cfg_') === 0 || strpos($k, 'col_') === 0) $hasConfig = true;

$pc = [
    'show_event_no'   => $isSubmitted ? isset($_GET['cfg_event_no']) : true,
    'show_date'       => $isSubmitted ? isset($_GET['cfg_date']) : true,
    'show_event_name' => $isSubmitted ? isset($_GET['cfg_event_name']) : true,
    'show_group'      => $isSubmitted ? isset($_GET['cfg_group']) : true,
    'show_gender'     => $isSubmitted ? isset($_GET['cfg_gender']) : true,
    'show_pool'       => $isSubmitted ? isset($_GET['cfg_pool']) : true,
    'show_round'      => $isSubmitted ? isset($_GET['cfg_round']) : true,
    'show_records'    => $isSubmitted ? isset($_GET['cfg_show_records']) : true,
    'show_event_records' => $isSubmitted ? isset($_GET['cfg_show_event_records']) : true
];

$cc = [
    'uid'   => $hasConfig ? isset($_GET['col_uid']) : true,
    'lahir' => $hasConfig ? isset($_GET['col_lahir']) : true,
    'ku'    => $hasConfig ? isset($_GET['col_ku']) : true,
    'tim'   => $hasConfig ? isset($_GET['col_tim']) : true,
    'waktu' => $hasConfig ? isset($_GET['col_waktu']) : true,
    'hasil' => $hasConfig ? isset($_GET['col_hasil']) : true,
];

$activeColumnsCount = 2; // LN & NAMA ATLET (Pasti tampil)
if ($cc['uid'])   $activeColumnsCount++;
if ($cc['lahir']) $activeColumnsCount++;
if ($cc['ku'])    $activeColumnsCount++;
if ($cc['tim'])   $activeColumnsCount++;
if ($cc['waktu']) $activeColumnsCount++;
if ($cc['hasil']) $activeColumnsCount++;

// 1. INFO EVENT & 1 NOMOR LOMBA SAJA
$sqlInfo = "SELECT en.*, 
            e.id as parent_event_id, e.event_name, e.event_location, e.event_city, e.event_date_start, e.event_date_end, 
            e.lane_count, e.used_lanes, e.record_package_id, e.logo_left, e.logo_right, e.participation_type, e.pool_type
            FROM event_numbers en
            JOIN events e ON en.event_id = e.id 
            WHERE en.id = ?";
$stmtRace = $pdo->prepare($sqlInfo);
$stmtRace->execute([$target_id]);
$raceInfo = $stmtRace->fetch(PDO::FETCH_ASSOC);

if (!$raceInfo) die("<div style='padding:20px; text-align:center;'>Data nomor lomba tidak ditemukan.</div>");

$eventName  = strtoupper($raceInfo['event_name'] ?? 'EVENT NAME');
$loc  = $raceInfo['event_location'] ?? '-';
if (!empty($raceInfo['event_city'])) $loc .= ' - ' . $raceInfo['event_city'];
$venueName  = strtoupper($loc);
$eventDate  = $raceInfo['event_date_start'];
$logoLeft   = !empty($raceInfo['logo_left']) ? '../../../public/' . $raceInfo['logo_left'] : null;
$logoRight  = !empty($raceInfo['logo_right']) ? '../../../public/' . $raceInfo['logo_right'] : null;
$totalLane  = (int)($raceInfo['lane_count'] ?? 8);

$activeLanes = [];
if (!empty($raceInfo['used_lanes'])) {
    $activeLanes = explode(',', $raceInfo['used_lanes']);
    $activeLanes = array_map('trim', $activeLanes);
    $activeLanes = array_map('intval', $activeLanes);
    sort($activeLanes);
} else {
    for ($i = 1; $i <= $totalLane; $i++) {
        $activeLanes[] = $i;
    }
}

$partType   = $raceInfo['participation_type'] ?? 'club';
$rawPool    = $raceInfo['pool_type'] ?? '50m'; 
$poolLabel  = ($rawPool === '25m') ? 'SCM' : 'LCM';

$eventYear   = date('Y', strtotime($eventDate)); 
$displayDate = strtoupper(date('d F Y', strtotime($eventDate)));
if(!empty($raceInfo['event_date_end']) && $raceInfo['event_date_end'] != '0000-00-00' && $raceInfo['event_date_end'] != $eventDate) {
    $dateRange = date('d', strtotime($eventDate)) . ' - ' . date('d F Y', strtotime($raceInfo['event_date_end']));
} else {
    $dateRange = $displayDate;
}
$dateRange = strtoupper($dateRange);

// SPONSOR
$stmtSpon = $pdo->prepare("SELECT image_path FROM event_sponsors WHERE event_id = ?");
$stmtSpon->execute([$raceInfo['parent_event_id']]); 
$sponsors = $stmtSpon->fetchAll(PDO::FETCH_COLUMN);

// KELOMPOK UMUR
$stmtAge = $pdo->prepare("SELECT group_name, min_age, max_age FROM event_age_groups WHERE event_id = ?");
$stmtAge->execute([$raceInfo['parent_event_id']]);
$ageGroups = $stmtAge->fetchAll(PDO::FETCH_ASSOC);

function getKUName($dob, $evtYear, $groups) {
    if(!$dob || $dob == '0000-00-00') return '-';
    $age = $evtYear - (int)date('Y', strtotime($dob));
    foreach($groups as $g) {
        if ($age >= $g['min_age'] && $age <= $g['max_age']) return $g['group_name'];
    }
    return $age . " TH";
}

function getTeamName($row, $type) {
    $club = $row['club_name'] ?? ''; $school = $row['asal_sekolah'] ?? '';
    if (stripos($type, 'sekolah') !== false || stripos($type, 'school') !== false) return $school ?: '-';
    return $club ?: '-';
}

// FORMAT JUDUL SESUAI KOMPONEN PRINT_FULL_BOOK
$cleanStroke = trim(str_ireplace(['Gaya', 'GAYA'], '', $raceInfo['stroke']));
$genderLabel = (in_array($raceInfo['jenis_kelamin'], ['L','Male'])) ? 'PUTRA' : 'PUTRI';
$tglMain = !empty($raceInfo['schedule_date']) ? strtoupper(date('d F Y', strtotime($raceInfo['schedule_date']))) : $displayDate;
$jamMain = !empty($raceInfo['schedule_time']) ? date('H:i', strtotime($raceInfo['schedule_time'])) . ' WIB' : '08:00 WIB';

$judulParts = [];
if ($pc['show_event_name']) $judulParts[] = $raceInfo['distance']."M ".strtoupper($cleanStroke); 
if ($pc['show_group'])      $judulParts[] = $raceInfo['age_group']; 
if ($pc['show_gender'])     $judulParts[] = strtoupper($genderLabel); 
if ($pc['show_pool'])       $judulParts[] = $poolLabel; 

$judulAcaraTengah = empty($judulParts) ? "EVENT" : implode(" - ", $judulParts);
$jadwalKiri = $tglMain . " | " . $jamMain;

// 2. AMBIL DATA PESERTA (HANYA UNTUK 1 KATEGORI INI)
$heats = [];
$sql = "SELECT es.heat_prelim as heat_no, es.lane_prelim as lane_no, es.time_prelim as entry_time,
        s.uid, s.nama_atlet, s.tanggal_lahir, c.nama_klub as club_name, s.asal_sekolah
        FROM event_entries ee
        JOIN event_seeding es ON es.entry_id = ee.id
        JOIN swimmers s ON ee.swimmer_id = s.id
        LEFT JOIN clubs c ON ee.club_id = c.id
        WHERE ee.category_id = ? 
        ORDER BY es.heat_prelim ASC, es.lane_prelim ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$target_id]);
$rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rawData as $row) {
    $heats[$row['heat_no']][$row['lane_no']] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Preview Start List - Acara #<?= htmlspecialchars($raceInfo['event_number']) ?></title>
    <style>
        /* RESET & PRINT COLOR */
        * { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        body { margin: 0; padding: 20px; font-family: 'Arial Narrow', sans-serif; background: #525659; }
        
        /* TOMBOL NAVIGASI DI LUAR KERTAS (TIDAK TERCETAK) */
        .no-print { display: flex; justify-content: space-between; align-items: center; width: 210mm; margin: 0 auto 15px auto; }
        .btn { padding: 10px 20px; font-weight: bold; text-decoration: none; border-radius: 6px; font-family: Arial, sans-serif; font-size: 10pt; box-shadow: 0 4px 6px rgba(0,0,0,0.2); cursor: pointer; border: none; }
        .btn-back { background: #374151; color: white; }
        .btn-print { background: #2563eb; color: white; }
        .btn:hover { opacity: 0.9; transform: translateY(-1px); }

        /* KERTAS A4 - KANVAS PREVIEW */
        .page-wrapper { background: white; width: 210mm; margin: 0 auto; padding: 0 10mm; min-height: 297mm; position: relative; box-shadow: 0 0 15px rgba(0,0,0,0.5); display: flex; flex-direction: column; }
        
        /* HEADER FIXED STYLE (IDENTIK DENGAN PRINT_FULL_BOOK) */
        .header-fixed { border-bottom: 3px double #000; padding: 10mm 0 5px 0; margin-bottom: 10px; display: grid; grid-template-columns: 110px 1fr 110px; align-items: flex-end; }
        .header-center { display: flex; flex-direction: column; align-items: center; justify-content: flex-end; text-align: center; line-height: 1.2; color: #000; }
        .header-line-1 { font-size: 14pt; font-weight: 900; text-transform: uppercase; margin-bottom: 2px; }
        .header-line-2 { font-size: 9pt; font-weight: bold; text-transform: uppercase; }
        .header-line-3 { font-size: 9pt; font-weight: bold; text-transform: uppercase; }
        .header-line-4 { height: 3px; } 
        .header-line-5 { font-size: 18pt; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; color: #000; margin-top: 2px; margin-bottom: 0px; line-height: 1; }
        .logo-img { max-height: 80px; max-width: 100%; object-fit: contain; margin-bottom: 2px; }
        
        /* FOOTER SPONSOR (BOTTOM OF PAGE) */
        .footer-fixed { margin-top: auto; border-top: 2px double #000; padding: 5mm 0; display: flex; justify-content: center; align-items: center; }
        .footer-fixed img { height: 40px; margin: 0 10px; object-fit: contain; }

        /* JUDUL NOMOR ACARA (IDENTIK DENGAN PRINT_FULL_BOOK) */
        .event-header { position: relative; display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #000; padding: 2px 0; margin-top: 10px; margin-bottom: 0px; background: #fff; min-height: 35px; }
        .eh-left-group { display: flex; flex-direction: column; justify-content: center; width: 180px; line-height: 1.1; z-index: 2; position: relative; background: white; }
        .eh-number { font-size: 14pt; font-weight: 900; margin-bottom: 2px; color: #000; }
        .eh-date { font-size: 8pt; font-weight: bold; font-style: normal; color: #000; }
        .eh-center { position: absolute; left: 50%; bottom: 3px; transform: translateX(-50%); text-align: center; width: 60%; z-index: 1; }
        .eh-title  { font-size: 11pt; font-weight: 800; text-transform: uppercase; color: #000; }
        .eh-right  { font-size: 10pt; font-weight: 900; width: 80px; text-align: right; z-index: 2; position: relative; background: white; color: #000; }
        
        /* REKOR CONTAINER DI ANTARA 2 LINES */
        .event-records-container { border-bottom: 1px solid #000; padding: 4px 0; margin-bottom: 10px; font-size: 8pt; font-weight: bold; line-height: 1.3; text-align: left; }
        .rec-row { display: flex; justify-content: flex-start; text-transform: uppercase; }
        .rec-label { width: 140px; font-weight: 900; color: #000; }
        .rec-details { flex: 1; color: #000; }

        /* TABEL SERI / HEAT BOLD SEPERTI PRINT_FULL_BOOK */
        .heat-title { text-align: right; font-size: 9pt; font-weight: bold; text-transform: uppercase; margin-top: 12px; margin-bottom: 2px; color: #000; }
        
        .data-table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 2px; font-size: 8pt; }
        .data-table th { background-color: #e5e7eb; color: #000; font-weight: bold; font-size: 8pt; text-transform: uppercase; padding: 2px 2px; border-top: 1px solid #000; border-bottom: 2px solid #000; text-align: center; }
        .data-table td { padding: 4px 4px; border-bottom: 1px solid #ccc; vertical-align: middle; font-weight: bold !important; color: #000; } 
        
        .col-ln { width: 5%; text-align: center; background: #f8f9fa; border-right: 1px solid #eee; font-weight: bold; white-space: nowrap; }
        .col-uid { width: 12%; text-align: center; white-space: nowrap; font-family: 'Courier New', monospace; }
        .col-nama { text-align: left; padding-left: 5px; white-space: normal; line-height: 1.1; }
        .col-lahir { width: 8%; text-align: center; white-space: nowrap; }
        .col-ku { width: 10%; text-align: center; white-space: nowrap; }
        .col-tim { width: 22%; text-align: left; padding-left: 5px; white-space: normal; line-height: 1.1; }
        .col-waktu { width: 10%; text-align: right; padding-right: 5px; white-space: nowrap; font-family: 'Courier New', monospace; }
        .col-hasil { width: 12%; text-align: right; color: #000; letter-spacing: 0px; white-space: nowrap; }
        
        .data-table tr:nth-child(even) { background-color: #f9fafb; }

        @media print {
            @page { size: A4; margin: 0; }
            body { background: white; margin: 0; padding: 0; }
            .no-print { display: none !important; }
            .page-wrapper { margin: 0; width: 100%; box-shadow: none; padding: 0 10mm; min-height: 100vh; page-break-after: always; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <a href="index.php?event_id=<?= $raceInfo['parent_event_id'] ?>" class="btn btn-back">← KEMBALI</a>
        <button onclick="window.print()" class="btn btn-print">🖨️ CETAK HALAMAN INI</button>
    </div>

    <div class="page-wrapper">
        
        <div class="header-fixed">
            <div style="text-align: left;"><?php if($logoLeft): ?><img src="<?= $logoLeft ?>" class="logo-img"><?php endif; ?></div>
            <div class="header-center">
                <div class="header-line-1"><?= htmlspecialchars($eventName) ?></div>
                <div class="header-line-2"><?= htmlspecialchars($venueName) ?></div>
                <div class="header-line-3"><?= htmlspecialchars($dateRange) ?></div>
                <div class="header-line-4"></div>
                <div class="header-line-5">BUKU ACARA</div>
            </div>
            <div style="text-align: right;"><?php if($logoRight): ?><img src="<?= $logoRight ?>" class="logo-img"><?php endif; ?></div>
        </div>

        <div class="content-body">
            
            <?php if(empty($heats)): ?>
                <div style="text-align:center; padding: 50px; font-weight:bold; color: #aaa;">BELUM ADA ATLET DI-SEEDING UNTUK NOMOR INI</div>
            <?php else: ?>
                
                <div class="event-header">
                    <div class="eh-left-group">
                        <?php if($pc['show_event_no']): ?><div class="eh-number">ACARA #<?= $raceInfo['event_number'] ?></div><?php endif; ?>
                        <?php if($pc['show_date']): ?><div class="eh-date"><?= $jadwalKiri ?></div><?php endif; ?>
                    </div>
                    <div class="eh-center"><div class="eh-title"><?= $judulAcaraTengah ?></div></div>
                    <div class="eh-right"><?= $pc['show_round'] ? 'FINAL' : '' ?></div>
                </div>

                <div class="event-records-container" style="border:none; padding:0; margin-bottom:10px;">
                    <?php 
                    if($pc['show_records']): 
                        $stmtRec = $pdo->prepare("SELECT record_type, holder_name, record_time, location, record_year FROM master_records WHERE distance = ? AND stroke = ? AND jenis_kelamin = ? AND (age_group = ? OR record_type = 'rekornas') ORDER BY record_type ASC, id ASC");
                        $stmtRec->execute([$raceInfo['distance'], $raceInfo['stroke'], $raceInfo['jenis_kelamin'], $raceInfo['age_group']]);
                        $records = $stmtRec->fetchAll(PDO::FETCH_ASSOC);

                        // 2. Ambil Rekor Acuan (Dari event_historical_records) jika event ini punya acuan rekor
                        if ($pc['show_event_records'] && !empty($raceInfo['record_package_id'])) {
                            $stmtPkg = $pdo->prepare("
                                SELECT 'rekor_event' as record_type, ehr.holder_name, ehr.record_time, 
                                       e.event_city as location, 
                                       COALESCE(YEAR(e.event_date_start), ehr.event_year) as record_year 
                                FROM event_historical_records ehr 
                                LEFT JOIN events e ON ehr.source_event_id = e.id
                                WHERE ehr.package_id = ? 
                                  AND ehr.distance = ? 
                                  AND REPLACE(REPLACE(REPLACE(LOWER(ehr.stroke), 'gaya', ''), ' ', ''), '-', '') = REPLACE(REPLACE(REPLACE(LOWER(?), 'gaya', ''), ' ', ''), '-', '') 
                                  AND LOWER(TRIM(ehr.jenis_kelamin)) = LOWER(TRIM(?)) 
                                  AND LOWER(TRIM(ehr.age_group)) = LOWER(TRIM(?))
                            ");
                            $stmtPkg->execute([
                                $raceInfo['record_package_id'], 
                                $raceInfo['distance'], 
                                $raceInfo['stroke'], 
                                $raceInfo['jenis_kelamin'], 
                                $raceInfo['age_group']
                            ]);
                            $pkgRecords = $stmtPkg->fetchAll(PDO::FETCH_ASSOC);
                            $records = array_merge($records, $pkgRecords);

                            // TEMPORARY DEBUG:
                            $debugData = "PkgID: " . $raceInfo['record_package_id'] . "\n" .
                                         "Dist: " . $raceInfo['distance'] . "\n" .
                                         "Stroke: " . $raceInfo['stroke'] . "\n" .
                                         "JK: " . $raceInfo['jenis_kelamin'] . "\n" .
                                         "Age: " . $raceInfo['age_group'] . "\n" .
                                         "PkgRecords: " . json_encode($pkgRecords) . "\n" .
                                         "MasterRecords: " . json_encode($records);
                            file_put_contents(__DIR__ . '/debug_records.txt', $debugData);
                        }
                        
                        if(!empty($records)):
                            ?>
                            <table style="width: 100%; border-collapse: collapse; font-size: 8pt; font-family: 'Arial Narrow', sans-serif; font-weight: bold; border-bottom: 1px solid #000; text-transform: uppercase;">
                                <thead>
                                    <tr style="border-bottom: 1px solid #000;">
                                        <th style="text-align: left; padding: 2px 0; width: 140px; color: #000;">REKOR</th>
                                        <th style="text-align: left; padding: 2px 0; color: #000;">NAMA ATLET</th>
                                        <th style="text-align: left; padding: 2px 0; width: 180px; color: #000;">LOKASI</th>
                                        <th style="text-align: center; padding: 2px 0; width: 60px; color: #000;">TAHUN</th>
                                        <th style="text-align: right; padding: 2px 0; width: 80px; color: #000;">WAKTU</th>
                                    </tr>
                                </thead>
                                <tbody>
                            <?php
                            foreach($records as $rec):
                                $tipeLabel = strtoupper(str_replace('_', ' ', $rec['record_type']));
                                if($tipeLabel === 'REKORNAS') $tipeLabel = 'REKOR NAS';
                                
                                $lokasiDisplay = !empty($rec['location']) ? strtoupper($rec['location']) : '-';
                                $tahunDisplay = !empty($rec['record_year']) ? $rec['record_year'] : '-';
                                ?>
                                <tr>
                                    <td style="padding: 2px 0; color: #000;"><?= $tipeLabel ?></td>
                                    <td style="padding: 2px 0; color: #000;"><?= strtoupper($rec['holder_name']) ?></td>
                                    <td style="padding: 2px 0; color: #000;"><?= $lokasiDisplay ?></td>
                                    <td style="text-align: center; padding: 2px 0; color: #000;"><?= $tahunDisplay ?></td>
                                    <td style="text-align: right; padding: 2px 0; color: #000;"><?= $rec['record_time'] ?></td>
                                </tr>
                                <?php 
                            endforeach;
                            ?>
                                </tbody>
                            </table>
                            <?php
                        else:
                            echo "<div style='color:#aaa; font-style:italic;'>NO MASTER RECORD DATA FOUND</div>";
                        endif;
                    else:
                        echo "<div style='height:2px;'></div>";
                    endif; 
                    ?>
                </div>

                <?php foreach($heats as $heatNo => $lanes): ?>
                    <div class="heat-title">SERI <?= $heatNo ?></div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="col-ln">LN</th>
                                <?php if($cc['uid']): ?><th class="col-uid">UID</th><?php endif; ?>
                                <th class="col-nama">NAMA ATLET</th>
                                <?php if($cc['lahir']): ?><th class="col-lahir">LAHIR</th><?php endif; ?>
                                <?php if($cc['ku']): ?><th class="col-ku">KU</th><?php endif; ?>
                                <?php if($cc['tim']): ?><th class="col-tim">TIM</th><?php endif; ?>
                                <?php if($cc['waktu']): ?><th class="col-waktu">WAKTU</th><?php endif; ?>
                                <?php if($cc['hasil']): ?><th class="col-hasil">HASIL</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($activeLanes as $ln): $s = $lanes[$ln] ?? null; ?>
                            <tr>
                                <td class="col-ln"><?= $ln ?></td>
                                <?php if($s): ?>
                                    <?php if($cc['uid']): ?><td class="col-uid"><?= htmlspecialchars($s['uid'] ?? '-') ?></td><?php endif; ?>
                                    <td class="col-nama"><?= htmlspecialchars($s['nama_atlet']) ?></td>
                                    <?php if($cc['lahir']): ?><td class="col-lahir"><?= ($s['tanggal_lahir'] && $s['tanggal_lahir']!='0000-00-00') ? date('Y', strtotime($s['tanggal_lahir'])) : '-' ?></td><?php endif; ?>
                                    <?php if($cc['ku']): ?><td class="col-ku"><?= getKUName($s['tanggal_lahir'], $eventYear, $ageGroups) ?></td><?php endif; ?>
                                    <?php if($cc['tim']): ?><td class="col-tim"><?= htmlspecialchars(getTeamName($s, $partType)) ?></td><?php endif; ?>
                                    <?php if($cc['waktu']): ?><td class="col-waktu"><?= (!$s['entry_time'] || $s['entry_time']=='99.99.99') ? 'NT' : $s['entry_time'] ?></td><?php endif; ?>
                                    <?php if($cc['hasil']): ?><td class="col-hasil">[.......]</td><?php endif; ?>
                                <?php else: ?>
                                    <td colspan="<?= ($activeColumnsCount - 1) ?>" style="color:#aaa; font-style:italic; padding-left:10px;">&lt;Kosong&gt;</td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>

            <?php endif; ?>
        </div>

        <div class="footer-fixed">
            <?php if(!empty($sponsors)): foreach($sponsors as $img): ?>
                <img src="../../../public/<?= $img ?>">
            <?php endforeach; endif; ?>
        </div>

    </div>

</body>
</html>