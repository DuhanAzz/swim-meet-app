<?php
// FILE: src/admin/seeding/print_full_book.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { die("Akses Ditolak"); }

// === LOGIKA CONFIG ===
$usePost = ($_SERVER['REQUEST_METHOD'] === 'POST');

$pc = [
    'show_event_no'   => $usePost ? isset($_POST['cfg_event_no']) : true,
    'show_date'       => $usePost ? isset($_POST['cfg_date']) : true,
    'show_event_name' => $usePost ? isset($_POST['cfg_event_name']) : true,
    'show_group'      => $usePost ? isset($_POST['cfg_group']) : true,
    'show_gender'     => $usePost ? isset($_POST['cfg_gender']) : true,
    'show_pool'       => $usePost ? isset($_POST['cfg_pool']) : true,
    'show_round'      => $usePost ? isset($_POST['cfg_round']) : true
];

// Handle Images
$scheduleImage = null;
if ($usePost && !empty($_FILES['schedule_image']['tmp_name'])) {
    $imgData = file_get_contents($_FILES['schedule_image']['tmp_name']);
    $scheduleImage = 'data:' . $_FILES['schedule_image']['type'] . ';base64,' . base64_encode($imgData);
}
$showScheduleAuto = ($usePost ? isset($_POST['show_schedule_auto']) : false) && empty($scheduleImage);

$coverImage = null;
if ($usePost && !empty($_FILES['cover_image']['tmp_name'])) {
    $imgData = file_get_contents($_FILES['cover_image']['tmp_name']);
    $coverImage = 'data:' . $_FILES['cover_image']['type'] . ';base64,' . base64_encode($imgData);
}

// === AMBIL DATA ===
$eventId = $_GET['event_id'] ?? 0;
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
$logoLeft   = !empty($raceInfo['logo_left']) ? '../../../public/' . $raceInfo['logo_left'] : null;
$logoRight  = !empty($raceInfo['logo_right']) ? '../../../public/' . $raceInfo['logo_right'] : null;
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
            s.uid, s.nama_atlet, s.tanggal_lahir, u.nama_lengkap as club_name, s.asal_sekolah
           FROM event_numbers en
           JOIN event_entries ee ON ee.category_id = en.id
           JOIN event_seeding es ON es.entry_id = ee.id
           JOIN swimmers s ON ee.swimmer_id = s.id
           LEFT JOIN users u ON ee.club_id = u.id
           WHERE en.organizer_id = ? 
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
                'jadwal' => $tglMain . " | " . $jamMain 
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
        /* RESET */
        * { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        body { margin: 0; padding: 0; font-family: 'Arial', sans-serif; background: #ccc; }
        
        /* CONTAINER HALAMAN BIASA */
        .page-wrapper { background: white; width: 210mm; margin: 20px auto; padding: 0 10mm; min-height: 297mm; position: relative; }
        
        /* PERBAIKAN CSS FULL PAGE (Cover & Jadwal) 
           Menggunakan position: relative agar tidak menumpuk (stacking),
           tetapi Z-index tinggi dan Background Putih agar menutupi Header Fixed.
        */
        .full-page { 
            position: relative; /* Jangan Absolute */
            width: 210mm; 
            height: 297mm; /* Ukuran A4 */
            margin: 0 auto;
            z-index: 99999; 
            background: white; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            overflow: hidden;
            /* Trik menutupi header */
            margin-bottom: -35mm; /* Tarik halaman berikutnya ke atas sedikit jika perlu, atau biarkan normal */
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
        .schedule-title { text-align:center; font-size:14pt; font-weight:900; margin-bottom:15px; text-transform:uppercase; font-family: 'Arial', sans-serif; text-decoration: underline; }
        .schedule-table { width: 100%; border-collapse: collapse; border: none; font-family: 'Courier New', Courier, monospace; font-size: 8pt; }
        .schedule-table th { border: none; border-bottom: 1px solid #000; text-align: left; padding: 2px 4px; text-transform: uppercase; font-weight: bold; }
        .schedule-table td { border: none; padding: 1px 4px; vertical-align: top; }
        .schedule-date-header { font-weight: 900; padding-top: 15px; padding-bottom: 5px; font-size: 9pt; text-decoration: underline; }
        
        .event-header { position: relative; display: flex; justify-content: space-between; align-items: flex-end; border-top: none; border-bottom: 2px solid #000; padding: 2px 0; margin-top: 5px; margin-bottom: 2px; background: #fff; font-family: 'Arial', sans-serif; min-height: 35px; }
        .eh-left-group { display: flex; flex-direction: column; justify-content: center; width: 180px; line-height: 1.1; z-index: 2; position: relative; background: white; }
        .eh-number { font-size: 14pt; font-weight: 900; margin-bottom: 2px; }
        .eh-date { font-size: 8pt; font-weight: bold; font-style: normal; }
        .eh-center { position: absolute; left: 50%; bottom: 3px; transform: translateX(-50%); text-align: center; width: 60%; z-index: 1; }
        .eh-title  { font-size: 11pt; font-weight: 800; text-transform: uppercase; }
        .eh-right  { font-size: 10pt; font-weight: 900; width: 80px; text-align: right; z-index: 2; position: relative; background: white; }
        .heat-title { text-align: right; font-size: 9pt; font-weight: bold; font-family: 'Arial', sans-serif; text-transform: uppercase; margin-top: 12px; margin-bottom: 2px; }
        .event-header + .heat-title { margin-top: 2px !important; }
        .data-table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 2px; font-family: 'Courier New', Courier, monospace; font-size: 8pt; }
        .data-table th { background-color: #e5e7eb; color: #000; font-family: 'Arial Narrow', sans-serif; font-weight: bold; font-size: 8pt; text-transform: uppercase; padding: 2px 2px; border-top: 1px solid #000; border-bottom: 2px solid #000; text-align: center; }
        .data-table td { padding: 4px 4px; border-bottom: 1px solid #ccc; vertical-align: middle; }
        .col-ln { width: 4%; text-align: center; background: #f8f9fa; border-right: 1px solid #eee; font-weight: bold; white-space: nowrap; }
        .col-uid { width: 11%; text-align: center; white-space: nowrap; }
        .col-nama { width: 27%; text-align: left; padding-left: 5px; white-space: normal; line-height: 1.1; }
        .col-lahir { width: 8%; text-align: center; white-space: nowrap; }
        .col-ku { width: 10%; text-align: center; white-space: nowrap; }
        .col-tim { width: 20%; text-align: left; padding-left: 5px; white-space: normal; line-height: 1.1; }
        .col-waktu { width: 9%; text-align: right; padding-right: 5px; white-space: nowrap; }
        .col-hasil { width: 11%; text-align: right; color: #000; letter-spacing: 0px; white-space: nowrap; }
        .data-table tr:nth-child(even) { background-color: #f9fafb; }
        .data-table tr { break-inside: avoid; }

        @media print {
            @page { size: A4; margin: 0; }
            body { background: white; margin: 0; }
            
            /* Full Page di Print mode */
            .full-page { 
                position: relative; /* Tetap relative agar tidak tumpuk */
                width: 100%; 
                height: 100vh; /* Full viewport height */
                margin: 0; 
                page-break-after: always; 
                break-after: always;
                z-index: 99999;
                background: white;
            }
            
            .schedule-section { break-after: always; page-break-after: always; }
            .page-wrapper { margin: 0; width: 100%; box-shadow: none; padding: 0 10mm; min-height: auto; position: relative; }
            .layout-table > thead { display: table-header-group !important; }
            .data-table > thead { display: table-row-group !important; }
            tfoot { display: table-footer-group; }
        }
    </style>
    <script>
        window.onload = function() {
            setTimeout(function() { window.print(); }, 800);
        };
    </script>
</head>
<body>
    
    <?php if ($coverImage): ?>
        <div class="full-page">
            <img src="<?= $coverImage ?>" class="full-page-img">
        </div>
    <?php endif; ?>

    <?php if ($scheduleImage): ?>
        <div class="full-page">
            <img src="<?= $scheduleImage ?>" class="full-page-img">
        </div>
    <?php endif; ?>

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
        <?php if(!empty($sponsors)): ?>
            <?php foreach($sponsors as $img): ?>
                <img src="../../../public/<?= $img ?>" style="height:45px; margin:0 10px;">
            <?php endforeach; ?>
        <?php endif; ?>
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
                            <div style="text-align:center; padding: 50px;">DATA KOSONG</div>
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
                                <?php foreach($data['heats'] as $heatNo => $lanes): ?>
                                    <div class="heat-title">SERI <?= $heatNo ?></div>
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th class="col-ln">LN</th> <th class="col-uid">UID</th> <th class="col-nama">NAMA ATLET</th> <th class="col-lahir">LAHIR</th> <th class="col-ku">KU</th> <th class="col-tim">TIM</th> <th class="col-waktu">WAKTU</th> <th class="col-hasil">HASIL</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php for($ln=1; $ln<=$totalLane; $ln++): $s = $lanes[$ln] ?? null; ?>
                                            <tr>
                                                <td class="col-ln"><?= $ln ?></td>
                                                <?php if($s): ?>
                                                    <td class="col-uid"><?= htmlspecialchars($s['uid'] ?? '-') ?></td>
                                                    <td class="col-nama"><?= $s['nama_atlet'] ?></td>
                                                    <td class="col-lahir"><?= ($s['tanggal_lahir'] && $s['tanggal_lahir']!='0000-00-00') ? date('Y', strtotime($s['tanggal_lahir'])) : '-' ?></td>
                                                    <td class="col-ku"><?= getKUName($s['tanggal_lahir'], $eventYear, $ageGroups) ?></td>
                                                    <td class="col-tim"><?= getTeamName($s, $partType) ?></td>
                                                    <td class="col-waktu"><?= (!$s['entry_time'] || $s['entry_time']=='99.99.99') ? 'NT' : $s['entry_time'] ?></td>
                                                    <td class="col-hasil">[.......]</td>
                                                <?php else: ?>
                                                    <td colspan="7" style="color:#aaa; font-style:italic; padding-left:10px;">&lt;Kosong&gt;</td>
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