<?php
// FILE: src/admin/seeding/print_full_book.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { die("Akses Ditolak"); }

// === LOGIKA CONFIG ===
$usePost = ($_SERVER['REQUEST_METHOD'] === 'POST');
$isSubmitted = $usePost || isset($_REQUEST['print_trigger']) || isset($_REQUEST['cfg_event_name']) || isset($_REQUEST['cfg_event_no']) || isset($_REQUEST['col_uid']);

$pc = [
    'show_event_no'   => $isSubmitted ? isset($_REQUEST['cfg_event_no']) : true,
    'show_date'       => $isSubmitted ? isset($_REQUEST['cfg_date']) : true,
    'show_event_name' => $isSubmitted ? isset($_REQUEST['cfg_event_name']) : true,
    'show_group'      => $isSubmitted ? isset($_REQUEST['cfg_group']) : true,
    'show_gender'     => $isSubmitted ? isset($_REQUEST['cfg_gender']) : true,
    'show_pool'       => $isSubmitted ? isset($_REQUEST['cfg_pool']) : true,
    'show_round'      => $isSubmitted ? isset($_REQUEST['cfg_round']) : true,
    'show_records'    => $isSubmitted ? isset($_REQUEST['cfg_show_records']) : true
];

$cc = [
    'uid'   => $isSubmitted ? isset($_REQUEST['col_uid']) : true,
    'lahir' => $isSubmitted ? isset($_REQUEST['col_lahir']) : true,
    'ku'    => $isSubmitted ? isset($_REQUEST['col_ku']) : true,
    'tim'   => $isSubmitted ? isset($_REQUEST['col_tim']) : true,
    'waktu' => $isSubmitted ? isset($_REQUEST['col_waktu']) : true,
    'hasil' => $isSubmitted ? isset($_REQUEST['col_hasil']) : true,
];

$activeColumnsCount = 2; 
if ($cc['uid'])   $activeColumnsCount++;
if ($cc['lahir']) $activeColumnsCount++;
if ($cc['ku'])    $activeColumnsCount++;
if ($cc['tim'])   $activeColumnsCount++;
if ($cc['waktu']) $activeColumnsCount++;
if ($cc['hasil']) $activeColumnsCount++;

// Handle Images
$scheduleImage = null;
if ($usePost && !empty($_FILES['schedule_image']['tmp_name'])) {
    $imgData = file_get_contents($_FILES['schedule_image']['tmp_name']);
    $scheduleImage = 'data:' . $_FILES['schedule_image']['type'] . ';base64,' . base64_encode($imgData);
}

$showScheduleAuto = ($isSubmitted ? isset($_REQUEST['show_schedule_auto']) : false) && empty($scheduleImage);

$coverImage = null;
if ($usePost && !empty($_FILES['cover_image']['tmp_name'])) {
    $imgData = file_get_contents($_FILES['cover_image']['tmp_name']);
    $coverImage = 'data:' . $_FILES['cover_image']['type'] . ';base64,' . base64_encode($imgData);
}

// === AMBIL DATA ===
$eventId = $_GET['event_id'] ?? ($_POST['event_id'] ?? 0);
if ($eventId == 0) {
    $uid = $_SESSION['user_id'];
    $stmtLast = $pdo->prepare("SELECT id FROM events WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmtLast->execute([$uid]);
    $lastEvent = $stmtLast->fetch();
    $eventId = $lastEvent['id'] ?? 0;
}

$stmtProfile = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmtProfile->execute([$eventId]);
$raceInfo = $stmtProfile->fetch(PDO::FETCH_ASSOC);

$eventName  = strtoupper($raceInfo['event_name'] ?? 'EVENT NAME');
$venueName  = strtoupper($raceInfo['event_location'] ?? '-');
$eventDate  = $raceInfo['event_date_start'];
$logoLeft   = !empty($raceInfo['logo_left']) ? '/' . ltrim($raceInfo['logo_left'], '/') : null;
$logoRight  = !empty($raceInfo['logo_right']) ? '/' . ltrim($raceInfo['logo_right'], '/') : null;
$totalLane  = (int)($raceInfo['lane_count'] ?? 8);
$partType   = $raceInfo['participation_type'] ?? 'club';
$rawPool = $raceInfo['pool_type'] ?? '50m'; 
$poolLabel = ($rawPool === '25m') ? 'SCM' : 'LCM';

$eventYear   = date('Y', strtotime($eventDate)); 
$displayDate = strtoupper(date('d F Y', strtotime($eventDate)));
if(!empty($raceInfo['event_date_end']) && $raceInfo['event_date_end'] != '0000-00-00' && $raceInfo['event_date_end'] != $eventDate) {
    $dateRange = date('d', strtotime($eventDate)) . ' - ' . date('d F Y', strtotime($raceInfo['event_date_end']));
} else {
    $dateRange = $displayDate;
}
$dateRange = strtoupper($dateRange);

$stmtSpon = $pdo->prepare("SELECT image_path FROM event_sponsors WHERE event_id = ?");
$stmtSpon->execute([$eventId]); 
$sponsors = $stmtSpon->fetchAll(PDO::FETCH_COLUMN);

$stmtAge = $pdo->prepare("SELECT group_name, min_age, max_age FROM event_age_groups WHERE event_id = ?");
$stmtAge->execute([$eventId]);
$ageGroups = $stmtAge->fetchAll(PDO::FETCH_ASSOC);

if (!function_exists('getKUName')) {
    function getKUName($dob, $evtYear, $groups) {
        if(!$dob || $dob == '0000-00-00') return '-';
        $age = $evtYear - (int)date('Y', strtotime($dob));
        foreach($groups as $g) {
            if ($age >= $g['min_age'] && $age <= $g['max_age']) return $g['group_name'];
        }
        return $age . " TH";
    }
}
if (!function_exists('getTeamName')) {
    function getTeamName($row, $type) {
        $club = $row['club_name'] ?? ''; $school = $row['asal_sekolah'] ?? '';
        if (stripos($type, 'sekolah') !== false || stripos($type, 'school') !== false) return $school ?: '-';
        return $club ?: '-';
    }
}

$sqlAll = "SELECT en.id as cat_id, en.event_number, en.distance, en.stroke, en.age_group, en.jenis_kelamin, en.schedule_date, en.schedule_time,
            es.heat_prelim as heat_no, es.lane_prelim as lane_no, es.time_prelim as entry_time,
            s.uid, s.nama_atlet, s.tanggal_lahir, c.nama_klub as club_name, s.asal_sekolah
           FROM event_numbers en
           JOIN event_entries ee ON ee.category_id = en.id
           JOIN event_seeding es ON es.entry_id = ee.id
           JOIN swimmers s ON ee.swimmer_id = s.id
           LEFT JOIN clubs c ON ee.club_id = c.id
           WHERE en.event_id = ? 
           ORDER BY CAST(en.event_number AS UNSIGNED) ASC, es.heat_prelim ASC, es.lane_prelim ASC";

$stmtAll = $pdo->prepare($sqlAll);
$stmtAll->execute([$eventId]);
$rawData = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

$fullBook = [];
$scheduleData = []; 

foreach($rawData as $row) {
    $cid = $row['cat_id'];
    if (!isset($scheduleData[$cid])) {
        $tglRaw = !empty($row['schedule_date']) ? $row['schedule_date'] : $eventDate;
        $jamRaw = !empty($row['schedule_time']) ? $row['schedule_time'] : '00:00:00';
        $cleanStroke = trim(str_ireplace(['Gaya', 'GAYA'], '', $row['stroke']));
        $genderLabel = (in_array($row['jenis_kelamin'], ['L','Male'])) ? 'PUTRA' : 'PUTRI';
        $scheduleData[$cid] = [
            'no' => $row['event_number'],
            'jam' => date('H:i', strtotime($jamRaw)),
            'tgl_display' => strtoupper(date('l, d F Y', strtotime($tglRaw))),
            'uraian' => $row['distance']."M ".strtoupper($cleanStroke),
            'kategori' => $row['age_group'] . " - " . strtoupper($genderLabel),
            'babak' => 'FINAL'
        ];
    }
    if(!isset($fullBook[$cid])) {
        $cleanStroke = trim(str_ireplace(['Gaya', 'GAYA'], '', $row['stroke']));
        $genderLabel = (in_array($row['jenis_kelamin'], ['L','Male'])) ? 'PUTRA' : 'PUTRI';
        $tglMain = !empty($row['schedule_date']) ? strtoupper(date('d F Y', strtotime($row['schedule_date']))) : $displayDate;
        $jamMain = !empty($row['schedule_time']) ? date('H:i', strtotime($row['schedule_time'])) . ' WIB' : '08:00 WIB';
        
        $judulParts = [];
        if ($pc['show_event_name']) $judulParts[] = $row['distance']."M ".strtoupper($cleanStroke); 
        if ($pc['show_group'])      $judulParts[] = $row['age_group']; 
        if ($pc['show_gender'])     $judulParts[] = strtoupper($genderLabel); 
        if ($pc['show_pool'])       $judulParts[] = $poolLabel; 
        
        $fullBook[$cid] = [
            'meta' => [
                'nomor'  => $row['event_number'],
                'judul'  => empty($judulParts) ? "EVENT" : implode(" - ", $judulParts),
                'jadwal' => $tglMain . " | " . $jamMain,
                'distance' => $row['distance'],
                'stroke' => $row['stroke'],
                'jenis_kelamin' => $row['jenis_kelamin'],
                'age_group' => $row['age_group']
            ],
            'heats' => []
        ];
    }
    $fullBook[$cid]['heats'][$row['heat_no']][$row['lane_no']] = $row;
}

if ($showScheduleAuto) {
    usort($scheduleData, function($a, $b) { return (int)$a['no'] - (int)$b['no']; });
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Meet Program Print</title>
    <style>
        * { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        body { margin: 0; padding: 0; font-family: 'Arial Narrow', sans-serif; background: #ccc; }
        
        .page-wrapper { background: white; width: 210mm; margin: 20px auto; padding: 0 10mm; min-height: 297mm; position: relative; }
        
        .full-page { 
            position: relative; width: 210mm; height: 297mm; margin: 0 auto;
            z-index: 99999; background: white; display: flex; justify-content: center; align-items: center; overflow: hidden;
            margin-bottom: -35mm; 
        }
        .full-page-img { width: 100%; height: 100%; object-fit: fill; }
        
        /* HEADER FIXED */
        .header-fixed { position: fixed; top: 0; left: 0; right: 0; height: 35mm; background: white; border-bottom: 3px double #000; display: grid; grid-template-columns: 110px 1fr 110px; align-items: flex-end; padding: 5px 10mm 3px 10mm; z-index: 999; }
        .header-center { display: flex; flex-direction: column; align-items: center; justify-content: flex-end; text-align: center; line-height: 1.2; color: #000; }
        .header-line-1 { font-size: 14pt; font-weight: 900; text-transform: uppercase; margin-bottom: 2px; }
        .header-line-2 { font-size: 9pt; font-weight: bold; text-transform: uppercase; }
        .header-line-3 { font-size: 9pt; font-weight: bold; text-transform: uppercase; }
        .header-line-4 { height: 3px; } 
        .header-line-5 { font-size: 18pt; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; color: #000; margin-top: 2px; margin-bottom: 0px; line-height: 1; }
        .logo-img { max-height: 100px; max-width: 100%; object-fit: contain; margin-bottom: 2px; }
        
        .footer-fixed { position: fixed; bottom: 0; left: 0; right: 0; height: 20mm; background: white; border-top: 2px double #000; display: flex; justify-content: center; align-items: center; padding: 0 10mm; z-index: 999; }
        
        /* SPACER */
        .layout-table { width: 100%; border-collapse: collapse; border: none; }
        .layout-header-space { height: 40mm; } 
        .layout-footer-space { height: 22mm; }
        
        /* TABEL STYLE */
        .schedule-title { text-align:center; font-size:14pt; font-weight:900; margin-bottom:15px; text-transform:uppercase; font-family: 'Arial Narrow', sans-serif; text-decoration: underline; }
        .schedule-table { width: 100%; border-collapse: collapse; border: none; font-family: 'Courier New', Courier, monospace; font-size: 8pt; }
        .schedule-table th { border: none; border-bottom: 1px solid #000; text-align: left; padding: 2px 4px; text-transform: uppercase; font-weight: bold; }
        .schedule-table td { border: none; padding: 1px 4px; vertical-align: top; font-weight: bold !important; }
        .schedule-date-header { font-weight: 900; padding-top: 15px; padding-bottom: 5px; font-size: 9pt; text-decoration: underline; }
        
        .event-header { position: relative; display: flex; justify-content: space-between; align-items: flex-end; border-top: none; border-bottom: 2px solid #000; padding: 2px 0; margin-top: 5px; margin-bottom: 0px; background: #fff; min-height: 35px; }
        .eh-left-group { display: flex; flex-direction: column; justify-content: center; width: 180px; line-height: 1.1; z-index: 2; position: relative; background: white; }
        .eh-number { font-size: 14pt; font-weight: 900; margin-bottom: 2px; }
        .eh-date { font-size: 8pt; font-weight: bold; font-style: normal; }
        .eh-center { position: absolute; left: 50%; bottom: 3px; transform: translateX(-50%); text-align: center; width: 60%; z-index: 1; }
        .eh-title  { font-size: 11pt; font-weight: 800; text-transform: uppercase; }
        .eh-right  { font-size: 10pt; font-weight: 900; width: 80px; text-align: right; z-index: 2; position: relative; background: white; }
        
        /* REKOR CONTAINER DI ANTARA 2 LINES */
        .event-records-container { border-bottom: 1px solid #000; padding: 4px 0; margin-bottom: 10px; font-size: 8pt; font-weight: bold; line-height: 1.3; text-align: left; }
        .rec-row { display: flex; justify-content: flex-start; text-transform: uppercase; }
        .rec-label { width: 140px; font-weight: 900; }
        .rec-details { flex: 1; }

        .heat-title { text-align: right; font-size: 9pt; font-weight: bold; text-transform: uppercase; margin-top: 12px; margin-bottom: 2px; }
        .event-header + .heat-title { margin-top: 2px !important; }
        
        /* DATA TABLE DINAMIS - BOLD */
        .data-table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 2px; font-size: 8pt; }
        .data-table th { background-color: #e5e7eb; color: #000; font-weight: bold; font-size: 8pt; text-transform: uppercase; padding: 2px 2px; border-top: 1px solid #000; border-bottom: 2px solid #000; text-align: center; }
        .data-table td { padding: 4px 4px; border-bottom: 1px solid #ccc; vertical-align: middle; font-weight: bold !important; } 
        
        .col-ln { width: 5%; text-align: center; background: #f8f9fa; border-right: 1px solid #eee; font-weight: bold; white-space: nowrap; }
        .col-uid { width: 12%; text-align: center; white-space: nowrap; font-family: 'Courier New', monospace; }
        .col-nama { text-align: left; padding-left: 5px; white-space: normal; line-height: 1.1; }
        .col-lahir { width: 8%; text-align: center; white-space: nowrap; }
        .col-ku { width: 10%; text-align: center; white-space: nowrap; }
        .col-tim { width: 22%; text-align: left; padding-left: 5px; white-space: normal; line-height: 1.1; }
        .col-waktu { width: 10%; text-align: right; padding-right: 5px; white-space: nowrap; font-family: 'Courier New', monospace; }
        .col-hasil { width: 12%; text-align: right; color: #000; letter-spacing: 0px; white-space: nowrap; }
        
        .data-table tr:nth-child(even) { background-color: #f9fafb; }
        .data-table tr { break-inside: avoid; }

        @media print {
            @page { size: A4; margin: 0; }
            body { background: white; margin: 0; }
            .full-page { position: relative; width: 100%; height: 100vh; margin: 0; page-break-after: always; break-after: always; z-index: 99999; background: white; }
            .schedule-section { break-after: always; page-break-after: always; }
            .page-wrapper { margin: 0; width: 100%; box-shadow: none; padding: 0 10mm; min-height: auto; position: relative; }
            .layout-table > thead { display: table-header-group !important; }
            .data-table > thead { display: table-row-group !important; }
            tfoot { display: table-footer-group; }
        }
    </style>
    <script>
        window.onload = function() { setTimeout(function() { window.print(); }, 800); };
    </script>
</head>
<body>
    
    <?php if ($coverImage): ?><div class="full-page"><img src="<?= $coverImage ?>" class="full-page-img"></div><?php endif; ?>
    <?php if ($scheduleImage): ?><div class="full-page"><img src="<?= $scheduleImage ?>" class="full-page-img"></div><?php endif; ?>

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

    <div class="footer-fixed">
        <?php if(!empty($sponsors)): foreach($sponsors as $img): ?>
            <img src="<?= '/' . ltrim($img, '/') ?>" style="height:45px; margin:0 10px;">
        <?php endforeach; endif; ?>
    </div>

    <?php if ($showScheduleAuto && empty($scheduleImage) && !empty($scheduleData)): ?>
        <div class="page-wrapper schedule-section">
            <table class="layout-table">
                <thead><tr><td><div class="layout-header-space"></div></td></tr></thead>
                <tfoot><tr><td><div class="layout-footer-space"></div></td></tr></tfoot>
                <tbody>
                    <tr>
                        <td>
                            <div class="schedule-title">SUSUNAN ACARA (ORDER OF EVENTS)</div>
                            <table class="schedule-table">
                                <thead>
                                    <tr>
                                        <th style="width:8%">JAM</th>
                                        <th style="width:6%; text-align:center">NO</th>
                                        <th style="width:35%">URAIAN ACARA</th>
                                        <th style="width:40%">KATEGORI</th>
                                        <th style="width:11%; text-align:right">BABAK</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $lastDate = ''; foreach($scheduleData as $sch): if ($sch['tgl_display'] !== $lastDate): ?>
                                        <tr><td colspan="5" class="schedule-date-header"><?= $sch['tgl_display'] ?></td></tr>
                                    <?php $lastDate = $sch['tgl_display']; endif; ?>
                                    <tr>
                                        <td><?= $sch['jam'] ?></td>
                                        <td style="text-align:center;">#<?= $sch['no'] ?></td>
                                        <td><?= $sch['uraian'] ?></td>
                                        <td><?= $sch['kategori'] ?></td>
                                        <td style="text-align:right;"><?= $sch['babak'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="page-wrapper">
        <table class="layout-table">
            <thead><tr><td><div class="layout-header-space"></div></td></tr></thead>
            <tfoot><tr><td><div class="layout-footer-space"></div></td></tr></tfoot>
            <tbody>
                <tr>
                    <td>
                        <?php if(empty($fullBook)): ?>
                            <div style="text-align:center; padding: 50px; font-weight:bold;">DATA KOSONG</div>
                        <?php else: ?>
                            <?php foreach($fullBook as $catId => $data): ?>
                                <div class="event-header">
                                    <div class="eh-left-group">
                                        <?php if($pc['show_event_no']): ?><div class="eh-number">ACARA #<?= $data['meta']['nomor'] ?></div><?php endif; ?>
                                        <?php if($pc['show_date']): ?><div class="eh-date"><?= $data['meta']['jadwal'] ?></div><?php endif; ?>
                                    </div>
                                    <div class="eh-center"><div class="eh-title"><?= $data['meta']['judul'] ?></div></div>
                                    <div class="eh-right"><?= $pc['show_round'] ? 'FINAL' : '' ?></div>
                                </div>

                                <div class="event-records-container">
                                    <?php 
                                    if($pc['show_records']): 
                                        $stmtRec = $pdo->prepare("SELECT record_type, holder_name, record_time, location, record_year FROM master_records WHERE distance = ? AND stroke = ? AND jenis_kelamin = ? AND age_group = ? ORDER BY id ASC");
                                        $stmtRec->execute([$data['meta']['distance'], $data['meta']['stroke'], $data['meta']['jenis_kelamin'], $data['meta']['age_group']]);
                                        $records = $stmtRec->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        if(!empty($records)):
                                            foreach($records as $rec):
                                                $tipeLabel = strtoupper(str_replace('_', ' ', $rec['record_type']));
                                                if($tipeLabel === 'REKORNAS') $tipeLabel = 'REKOR NAS';
                                                
                                                $lokasiDisplay = !empty($rec['location']) ? strtoupper($rec['location']) : '-';
                                                $tahunDisplay = !empty($rec['record_year']) ? $rec['record_year'] : '-';
                                                ?>
                                                <div class="rec-row">
                                                    <span class="rec-label"><?= $tipeLabel ?></span>
                                                    <span class="rec-details"><?= strtoupper($rec['holder_name']) ?>, <?= $lokasiDisplay ?>, <?= $tahunDisplay ?>, <?= $rec['record_time'] ?></span>
                                                </div>
                                                <?php 
                                            endforeach;
                                        else:
                                            echo "<div style='color:#aaa; font-style:italic;'>NO MASTER RECORD DATA FOUND</div>";
                                        endif;
                                    else:
                                        echo "<div style='height:2px;'></div>";
                                    endif; 
                                    ?>
                                </div>

                                <?php foreach($data['heats'] as $heatNo => $lanes): ?>
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
                                            <?php for($ln=1; $ln<=$totalLane; $ln++): $s = $lanes[$ln] ?? null; ?>
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
                                            <?php endfor; ?>
                                        </tbody>
                                    </table>
                                <?php endforeach; ?>
                                <div style="height: 5px;"></div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>