<?php
// FILE: src/admin/results/export_all_results.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { die("Akses Ditolak"); }

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$host = $_SERVER['HTTP_HOST'];

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

if (!$raceInfo) { die("Data Event tidak ditemukan."); }

$eventName  = strtoupper($raceInfo['event_name'] ?? 'EVENT NAME');
$venueName  = strtoupper($raceInfo['event_location'] ?? '-');
$eventDate  = $raceInfo['event_date_start'] ?? date('Y-m-d');
$logoLeft   = !empty($raceInfo['logo_left']) ? '../../../public/' . $raceInfo['logo_left'] : null;
$logoRight  = !empty($raceInfo['logo_right']) ? '../../../public/' . $raceInfo['logo_right'] : null;
$partType   = $raceInfo['participation_type'] ?? 'club';
$rawPool    = $raceInfo['pool_type'] ?? '50m'; 
$poolLabel  = ($rawPool === '25m' || $rawPool === 'SCM') ? 'SCM' : 'LCM';

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
        $club = trim($row['club_name'] ?? ''); $school = trim($row['asal_sekolah'] ?? '');
        if (stripos($type, 'sekolah') !== false || stripos($type, 'school') !== false) return $school ?: ($club ?: '-');
        return $club ?: ($school ?: '-');
    }
}

// === AMBIL DATA HASIL LOMBA KESELURUHAN ===
// PERBAIKAN: Gunakan event_id dan panggil kolom rank_final, time_final secara akurat
$sqlAll = "SELECT 
            en.id as cat_id, en.event_number, en.distance, en.stroke, en.age_group, en.jenis_kelamin, 
            es.rank_final, es.time_prelim as entry_time, es.time_final, es.is_dq_final, es.dq_reason_final,
            s.uid, s.nama_atlet, s.tanggal_lahir, u.nama_lengkap as club_name, s.asal_sekolah
           FROM event_numbers en
           JOIN event_entries ee ON ee.category_id = en.id
           JOIN event_seeding es ON ee.id = es.entry_id
           JOIN swimmers s ON ee.swimmer_id = s.id
           LEFT JOIN users u ON (ee.club_id = u.id OR ee.user_id = u.id)
        WHERE (es.time_final IS NOT NULL OR es.is_dq_final = 1)
           AND en.event_id = ?
           ORDER BY CAST(en.event_number AS UNSIGNED) ASC, 
                    CASE WHEN es.rank_final IS NULL THEN 9999 ELSE es.rank_final END ASC";

$stmtAll = $pdo->prepare($sqlAll);
// PERBAIKAN: Lempar $eventId, BUKAN user_id
$stmtAll->execute([$eventId]);
$rawData = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

$fullResults = [];
foreach($rawData as $row) {
    $cid = $row['cat_id'];
    if(!isset($fullResults[$cid])) {
        $cleanStroke = trim(str_ireplace(['Gaya', 'GAYA'], '', $row['stroke']));
        $genderLabel = (in_array($row['jenis_kelamin'], ['L','Male','Man'])) ? 'PUTRA' : 'PUTRI';
        
        $judulParts = [];
        $judulParts[] = $row['distance']."M ".strtoupper($cleanStroke); 
        $judulParts[] = $row['age_group']; 
        $judulParts[] = strtoupper($genderLabel); 
        $judulParts[] = $poolLabel; 
        
        $fullResults[$cid] = [
            'meta' => [
                'nomor'  => $row['event_number'],
                'judul'  => implode(" - ", $judulParts),
                'jadwal' => $dateRange
            ],
            'data' => []
        ];
    }
    $fullResults[$cid]['data'][] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Meet Results Print</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* RESET */
        * { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        body { margin: 0; padding: 0; font-family: 'Arial', sans-serif; background: #ccc; }
        
        /* CONTAINER HALAMAN BIASA */
        .page-wrapper { background: white; width: 210mm; margin: 20px auto; padding: 0 10mm; min-height: 297mm; position: relative; }
        
        /* HEADER FIXED - IDENTIK PRINT FULL BOOK */
        .header-fixed { position: fixed; top: 0; left: 0; right: 0; height: 35mm; background: white; border-bottom: 3px double #000; display: grid; grid-template-columns: 110px 1fr 110px; align-items: flex-end; padding: 5px 10mm 3px 10mm; z-index: 999; }
        .header-center { display: flex; flex-direction: column; align-items: center; justify-content: flex-end; text-align: center; line-height: 1.2; color: #000; }
        .header-line-1 { font-size: 14pt; font-weight: 900; text-transform: uppercase; margin-bottom: 2px; }
        .header-line-2 { font-size: 9pt; font-weight: bold; text-transform: uppercase; }
        .header-line-3 { font-size: 9pt; font-weight: bold; text-transform: uppercase; }
        .header-line-4 { height: 3px; } 
        .header-line-5 { font-size: 18pt; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; color: #000; margin-top: 2px; margin-bottom: 0px; line-height: 1; }
        .logo-img { max-height: 100px; max-width: 100%; object-fit: contain; margin-bottom: 2px; }
        
        /* FOOTER FIXED - IDENTIK PRINT FULL BOOK + WARNA SPONSOR */
        .footer-fixed { position: fixed; bottom: 0; left: 0; right: 0; height: 20mm; background: white; border-top: 2px double #000; display: flex; justify-content: space-between; align-items: center; padding: 0 10mm; z-index: 999; }
        .footer-sponsors { display: flex; gap: 10px; align-items: center; justify-content: center; flex: 1; }
        .footer-sponsors img { height: 45px; object-fit: contain; } 
        .footer-time { font-family: monospace; font-size: 8pt; color: #666; width: 120px; }

        /* SPACER */
        .layout-table { width: 100%; border-collapse: collapse; border: none; }
        .layout-header-space { height: 40mm; } 
        .layout-footer-space { height: 22mm; }
        
        /* EVENT HEADER - IDENTIK PRINT FULL BOOK */
        .event-header { position: relative; display: flex; justify-content: space-between; align-items: flex-end; border-top: none; border-bottom: 2px solid #000; padding: 2px 0; margin-top: 5px; margin-bottom: 2px; background: #fff; font-family: 'Arial', sans-serif; min-height: 35px; }
        .eh-left-group { display: flex; flex-direction: column; justify-content: center; width: 150px; line-height: 1.1; z-index: 2; position: relative; background: white; }
        .eh-number { font-size: 14pt; font-weight: 900; margin-bottom: 2px; }
        .eh-date { font-size: 8pt; font-weight: bold; font-style: normal; }
        .eh-center { position: absolute; left: 50%; bottom: 3px; transform: translateX(-50%); text-align: center; width: 60%; z-index: 1; }
        .eh-title  { font-size: 11pt; font-weight: 800; text-transform: uppercase; }
        .eh-right  { width: 150px; text-align: right; z-index: 2; position: relative; background: white; display: flex; justify-content: flex-end; }
        .qr-header { width: 45px; height: 45px; object-fit: contain; margin-bottom: 2px; }

        /* TABEL STYLE SINKRONISASI LEBAR */
        .data-table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 2px; font-family: 'Courier New', Courier, monospace; font-size: 8pt; }
        .data-table th { background-color: #e5e7eb; color: #000; font-family: 'Arial Narrow', sans-serif; font-weight: bold; font-size: 8pt; text-transform: uppercase; padding: 2px 4px; border-top: 1px solid #000; border-bottom: 2px solid #000; text-align: center; }
        .data-table td { padding: 4px 4px; border-bottom: 1px solid #ccc; vertical-align: middle; }
        
        .col-rank { width: 4%; text-align: center; background: #f8f9fa; border-right: 1px solid #eee; font-weight: bold; white-space: nowrap; }
        .col-uid { width: 11%; text-align: center; white-space: nowrap; }
        .col-nama { width: 35%; text-align: left; padding-left: 5px; white-space: normal; line-height: 1.1; font-weight: bold;}
        .col-ku { width: 10%; text-align: center; white-space: nowrap; }
        .col-tim { width: 20%; text-align: left; padding-left: 5px; white-space: normal; line-height: 1.1; }
        .col-waktu-awal { width: 9%; text-align: right; padding-right: 5px; white-space: nowrap; color: #666; font-size: 7.5pt; }
        .col-hasil { width: 11%; text-align: right; font-weight: bold; font-size: 9pt; white-space: nowrap; }

        .group-row td { background-color: #f3f4f6; font-family: 'Arial', sans-serif; font-weight: bold; text-transform: uppercase; color: #6b7280; border-top: 1px solid #9ca3af; border-bottom: 1px solid #9ca3af; padding: 6px 8px; text-align: left; font-size: 9pt; }

        .btn-print { position: fixed; top: 20px; right: 20px; z-index: 9999; background: #000; color: #fff; border: none; padding: 12px 20px; font-weight: bold; border-radius: 6px; cursor: pointer; }

        @media print {
            @page { size: A4; margin: 0; }
            body { background: white; margin: 0; }
            .btn-print { display: none; }
            .page-wrapper { margin: 0; width: 100%; box-shadow: none; padding: 0 10mm; min-height: auto; position: relative; }
            .layout-table > thead { display: table-header-group !important; }
            .data-table > thead { display: table-row-group !important; }
            tfoot { display: table-footer-group; }
            .data-table th { background-color: #e5e7eb !important; }
            .col-rank { background: #f8f9fa !important; }
            .group-row td { background-color: #f3f4f6 !important; }
        }
    </style>
</head>
<body>
    <button onclick="window.print()" class="btn-print"><i class="fas fa-print"></i> CETAK KESELURUHAN HASIL</button>

    <div class="header-fixed">
        <div style="text-align: left;"><?php if($logoLeft): ?><img src="<?= $logoLeft ?>" class="logo-img"><?php endif; ?></div>
        <div class="header-center">
            <div class="header-line-1"><?= htmlspecialchars($eventName) ?></div>
            <div class="header-line-2"><?= htmlspecialchars($venueName) ?></div>
            <div class="header-line-3"><?= htmlspecialchars($dateRange) ?></div>
            <div class="header-line-4"></div>
            <div class="header-line-5">BUKU HASIL LOMBA</div>
        </div>
        <div style="text-align: right;"><?php if($logoRight): ?><img src="<?= $logoRight ?>" class="logo-img"><?php endif; ?></div>
    </div>

<div class="footer-fixed" style="justify-content: center;">
        <div class="footer-sponsors">
            <?php if(!empty($sponsors)): ?>
                <?php foreach($sponsors as $img): ?>
                    <img src="../../../public/<?= $img ?>">
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="page-wrapper">
        <table class="layout-table">
            <thead><tr><td><div class="layout-header-space"></div></td></tr></thead>
            <tfoot><tr><td><div class="layout-footer-space"></div></td></tr></tfoot>
            <tbody>
                <tr>
                    <td>
                        <?php if(empty($fullResults)): ?>
                            <div style="text-align:center; padding: 50px; font-weight: bold; color:#888;">BELUM ADA DATA HASIL LOMBA</div>
                        <?php else: ?>
                            <?php foreach($fullResults as $catId => $data): 
                                $qrApi = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&margin=0&data=" . urlencode($protocol . "://" . $host . "/public/result.php?category_id=" . $catId);
                            ?>
                                <div class="event-header">
                                    <div class="eh-left-group">
                                        <div class="eh-number">ACARA #<?= $data['meta']['nomor'] ?></div>
                                        <div class="eh-date"><?= $data['meta']['jadwal'] ?></div>
                                    </div>
                                    <div class="eh-center"><div class="eh-title"><?= $data['meta']['judul'] ?></div></div>
                                    <div class="eh-right">
                                        <img src="<?= $qrApi ?>" class="qr-header" alt="QR">
                                    </div>
                                </div>

                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th class="col-rank">RANK</th> 
                                            <th class="col-uid">UID</th> 
                                            <th class="col-nama">NAMA ATLET</th> 
                                            <th class="col-ku">KU</th> 
                                            <th class="col-tim">TIM / SEKOLAH</th> 
                                            <th class="col-waktu-awal">ENTRY</th> 
                                            <th class="col-hasil">HASIL</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        // Grouping by KU
                                        $groupedData = [];
                                        foreach($data['data'] as $s) {
                                            $kuLabel = getKUName($s['tanggal_lahir'], $eventYear, $ageGroups);
                                            $groupedData[$kuLabel][] = $s;
                                        }

                                        foreach($groupedData as $groupName => $swimmers): 
                                        ?>
                                            <tr class="group-row"><td colspan="7"><?= htmlspecialchars($groupName) ?></td></tr>
                                            <?php foreach($swimmers as $s): ?>
                                            <tr>
                                                <td class="col-rank"><?= $s['rank_final'] ?? '-' ?></td>
                                                <td class="col-uid"><?= htmlspecialchars($s['uid'] ?? '-') ?></td>
                                                <td class="col-nama"><?= $s['nama_atlet'] ?></td>
                                                <td class="col-ku"><?= getKUName($s['tanggal_lahir'], $eventYear, $ageGroups) ?></td>
                                                <td class="col-tim"><?= getTeamName($s, $partType) ?></td>
                                                <td class="col-waktu-awal"><?= (!$s['entry_time'] || $s['entry_time']=='99.99.99') ? 'NT' : $s['entry_time'] ?></td>
                                                <td class="col-hasil">
                                                    <?php 
                                                    if ($s['is_dq_final'] == 1) { 
                                                        $reason = $s['dq_reason_final'] ?? 'DQ';
                                                        // Logika: Jika DNF atau DNS, tetap tampilkan DNF/DNS. Jika pasal, jadikan "DQ"
                                                        $print_text = (in_array($reason, ['DNS', 'DNF'])) ? $reason : 'DQ';
                                                        echo '<span style="color:red;">' . $print_text . '</span>'; 
                                                    } 
                                                    else { echo $s['time_final'] ?? '-'; }
                                                    ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <div style="height: 5px;"></div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <script> window.onload = function() { setTimeout(function() { window.print(); }, 800); }; </script>
</body>
</html>