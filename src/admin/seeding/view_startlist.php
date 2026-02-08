<?php
// FILE: src/admin/seeding/view_startlist.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { die("Akses Ditolak"); }

$target_id = $_GET['event_id'] ?? ($_GET['category_id'] ?? null);
if (!$target_id) die("Error: Parameter ID tidak ditemukan.");

$pc = $_SESSION['print_config'] ?? [
    'show_event_no' => true, 'show_date' => true, 'show_event_name' => true,
    'show_group' => true, 'show_gender' => true, 'show_pool' => true, 'show_round' => true
];

// 1. INFO EVENT
$sqlInfo = "SELECT en.*, e.* FROM event_numbers en
            JOIN events e ON en.organizer_id = e.id 
            WHERE en.id = ?";
$stmtRace = $pdo->prepare($sqlInfo);
$stmtRace->execute([$target_id]);
$raceInfo = $stmtRace->fetch(PDO::FETCH_ASSOC);

if (!$raceInfo) die("Data lomba tidak ditemukan.");

// 2. AMBIL DAFTAR KU (AGE GROUPS) UNTUK LOGIKA MAPPING
// Kita butuh ini untuk menentukan "Si Atlet A masuk KU apa?"
$ageGroups = [];
try {
    // Ambil event_id asli dari tabel events (karena en.event_id NULL)
    $realEventId = $raceInfo['organizer_id']; // Sesuai data bapak (organizer_id = event.id)
    
    // Tapi tunggu, organizer_id itu ID User atau ID Event? 
    // Di tabel events, kolom id adalah Primary Key.
    // Di tabel event_numbers, organizer_id merefer ke events.id (berdasarkan query join kita di atas).
    // Jadi ID Eventnya adalah $raceInfo['id'] (kolom id dari tabel events, hati2 bentrok nama kolom).
    // Karena kita select *, kolom 'id' akan tertimpa. 
    // Mari kita pakai $raceInfo['event_id'] jika ada, atau kita ambil ulang ID eventnya.
    
    // PERBAIKAN: Ambil ID Event yang benar untuk cari Age Group
    // Di tabel events: id. Di tabel event_numbers: organizer_id.
    $eventIdForAge = $raceInfo['organizer_id']; 
    
    $stmtAge = $pdo->prepare("SELECT group_name, min_age, max_age FROM event_age_groups WHERE event_id = ?");
    $stmtAge->execute([$eventIdForAge]);
    $ageGroups = $stmtAge->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { }

// VARIABEL HEADER
$eventName  = strtoupper($raceInfo['event_name'] ?? 'EVENT NAME ERROR');
$venueName  = strtoupper($raceInfo['event_location'] ?? '-');
$eventDate  = $raceInfo['event_date_start'];
$totalLane  = (int)($raceInfo['lane_count'] ?? 8);
$logoLeft   = !empty($raceInfo['logo_left']) ? '../../../public/' . $raceInfo['logo_left'] : null;
$logoRight  = !empty($raceInfo['logo_right']) ? '../../../public/' . $raceInfo['logo_right'] : null;
$displayDate = strtoupper(date('d F Y', strtotime($eventDate)));
$eventYear   = date('Y', strtotime($eventDate)); 

if(!empty($raceInfo['event_date_end']) && $raceInfo['event_date_end'] != '0000-00-00' && $raceInfo['event_date_end'] != $eventDate) {
    $dateRange = date('d', strtotime($eventDate)) . ' - ' . date('d F Y', strtotime($raceInfo['event_date_end']));
} else {
    $dateRange = $displayDate;
}

// Judul Tengah
$judulParts = [];
if($pc['show_event_name']) {
    $cleanStroke = trim(str_ireplace(['Gaya', 'GAYA'], '', $raceInfo['stroke'] ?? ''));
    $judulParts[] = ($raceInfo['distance'] ?? '') . " M GAYA " . strtoupper($cleanStroke);
}
if($pc['show_group'])  $judulParts[] = ($raceInfo['age_group'] ?? '-'); 
$genderRaw = $raceInfo['jenis_kelamin'] ?? '';
$genderLabel = ($genderRaw == 'L' || $genderRaw == 'Male') ? 'PUTRA' : (($genderRaw == 'P' || $genderRaw == 'Female') ? 'PUTRI' : $genderRaw);
if($pc['show_gender']) $judulParts[] = strtoupper($genderLabel); 
if($pc['show_pool'])   $judulParts[] = strtoupper($raceInfo['pool_type'] ?? 'LCM');

$judulTengah = implode(" - ", array_filter($judulParts));
$nomorAcara  = $pc['show_event_no'] ? "#" . ($raceInfo['event_number'] ?? '?') : "";
$babakBadge  = $pc['show_round'] ? "FINAL" : "";

// 3. AMBIL DATA PESERTA (Termasuk kolom UID)
$heats = [];
try {
    $sql = "SELECT es.heat_prelim as heat_no, es.lane_prelim as lane_no, es.time_prelim as entry_time,
            s.uid, s.nama_atlet, s.tanggal_lahir, u.nama_lengkap as club_name, s.asal_sekolah
            FROM event_seeding es
            JOIN event_entries ee ON es.entry_id = ee.id
            JOIN swimmers s ON ee.swimmer_id = s.id
            LEFT JOIN users u ON ee.club_id = u.id
            WHERE ee.category_id = ? 
            ORDER BY es.heat_prelim ASC, es.lane_prelim ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$target_id]);
    $rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rawData as $row) {
        $heats[$row['heat_no']][$row['lane_no']] = $row;
    }
} catch (Exception $e) { die($e->getMessage()); }

// 4. SPONSOR
$stmtSpon = $pdo->prepare("SELECT image_path FROM event_sponsors WHERE event_id = ?");
$stmtSpon->execute([$raceInfo['organizer_id']]); 
$sponsors = $stmtSpon->fetchAll(PDO::FETCH_COLUMN);

// HELPER LOGIKA KU
function getKUName($dob, $evtYear, $groups) {
    if(!$dob || $dob == '0000-00-00') return '-';
    $birthYear = (int)date('Y', strtotime($dob));
    $age = $evtYear - $birthYear;
    
    // Cari grup yang cocok
    foreach($groups as $g) {
        if ($age >= $g['min_age'] && $age <= $g['max_age']) {
            return $g['group_name'];
        }
    }
    return $age . " TH"; // Fallback jika tidak ada KU yang cocok
}

$partType = $raceInfo['participation_type'] ?? 'club';
function getTeamName($row, $type) {
    $club = $row['club_name'] ?? ''; $school = $row['asal_sekolah'] ?? '';
    if (stripos($type, 'sekolah') !== false || stripos($type, 'school') !== false) return $school ?: '-';
    return $club ?: '-';
}
function shorten($str) { return trim($str ?? ''); }
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Startlist - <?= htmlspecialchars($judulTengah) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        * { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        body { margin: 0; padding: 0; background: #525659; font-family: 'Arial Narrow', sans-serif; font-size: 10pt; }

        .sheet {
            width: 210mm; height: 297mm; background: white; margin: 30px auto;
            position: relative; box-shadow: 0 0 15px rgba(0,0,0,0.5); overflow: hidden;
            display: flex; flex-direction: column;
            padding: 10mm 10mm 0 10mm; 
        }

        .page-header {
            width: 100%; border-bottom: 2px double #000; margin-bottom: 5px; padding-bottom: 5px;
            display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;
        }
        .logo-box { width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; }
        .logo-box img { max-height: 100%; max-width: 100%; object-fit: contain; }
        .header-content { text-align: center; flex: 1; padding: 0 10px; }
        .header-content h1 { margin: 0; font-size: 14pt; font-weight: 900; text-transform: uppercase; }
        .header-content p { margin: 2px 0; font-size: 9pt; font-weight: bold; color: #444; text-transform: uppercase; }

        .event-info-bar {
            display: grid; grid-template-columns: 80px 1fr 80px; align-items: center;
            border-bottom: 1px solid #000; margin-bottom: 10px; padding-bottom: 5px; flex-shrink: 0;
        }
        .evt-num { font-size: 16pt; font-weight: 900; }
        .evt-title { font-size: 11pt; font-weight: 800; text-transform: uppercase; text-align: center; }
        .evt-badge { font-size: 8pt; background: #eee; border: 1px solid #ccc; padding: 2px 6px; border-radius: 4px; font-weight: bold; text-align: center; }

        .page-body { width: 100%; flex-grow: 1; display: flex; flex-direction: column; justify-content: flex-start; }

        .heat-block { margin-bottom: 15px; break-inside: avoid; }
        .heat-title { text-align: right; font-weight: bold; font-size: 9pt; border-bottom: 1px solid #000; margin-bottom: 2px; background: #fff; }
        .ht-table { width: 100%; border-collapse: collapse; font-size: 8pt; table-layout: fixed; }
        .ht-table th { background: #f0f0f0; border: 1px solid #000; padding: 3px; text-transform: uppercase; font-size: 7.5pt; }
        .ht-table td { border-bottom: 1px solid #ccc; padding: 3px; vertical-align: middle; line-height: 1.1; }
        .ht-table tr:nth-child(even) { background-color: #fafafa; } 

        .tc { text-align: center; } .tr { text-align: right; } .tl { text-align: left; }
        .font-mono { font-family: 'Courier New', monospace; font-weight: bold; }

        .sheet-footer {
            position: absolute; bottom: 0; left: 0; right: 0; height: 20mm;
            background: white; border-top: 3px double #000;
            display: flex; justify-content: center; align-items: center; gap: 20px;
            z-index: 50; padding: 5px 0;
        }
        .sheet-footer img { height: 45px; width: auto; object-fit: contain; }

        .btn-print {
            position: fixed; top: 20px; right: 20px; z-index: 9999;
            background: #0f172a; color: white; border: none; padding: 12px 24px;
            border-radius: 8px; font-weight: bold; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        .btn-print:hover { transform: scale(1.05); }

        @media print {
            body { background: white; margin: 0; }
            .sheet { margin: 0; box-shadow: none; border: none; page-break-after: always; height: 297mm; }
            .btn-print { display: none; }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body>
    <button onclick="window.print()" class="btn-print"><i class="fas fa-print"></i> CETAK PDF</button>

    <div id="source-data" style="display: none;">
        <div id="tpl-header">
            <div class="page-header">
                <div class="logo-box"><?php if($logoLeft): ?><img src="<?= $logoLeft ?>"><?php endif; ?></div>
                <div class="header-content">
                    <h1><?= htmlspecialchars($eventName) ?></h1>
                    <?php if($venueName): ?><p><?= htmlspecialchars($venueName) ?></p><?php endif; ?>
                    <p><?= htmlspecialchars($dateRange) ?></p>
                </div>
                <div class="logo-box"><?php if($logoRight): ?><img src="<?= $logoRight ?>"><?php endif; ?></div>
            </div>
            <div class="event-info-bar">
                <div class="evt-num"><?= $nomorAcara ?></div>
                <div class="evt-title"><?= $judulTengah ?></div>
                <div><?php if($babakBadge): ?><div class="evt-badge"><?= $babakBadge ?></div><?php endif; ?></div>
            </div>
        </div>

        <div id="tpl-footer">
            <div class="sheet-footer">
                <?php if(!empty($sponsors)): ?>
                    <?php foreach($sponsors as $img): ?>
                        <img src="../../../public/<?= $img ?>">
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div id="tpl-content">
            <?php if(empty($heats)): ?>
                <div class="heat-block" style="text-align:center; padding: 50px; font-style:italic;">Data Kosong / Belum di-Seeding</div>
            <?php else: ?>
                <?php foreach($heats as $heatNo => $lanes): ?>
                <div class="heat-block">
                    <div class="heat-title">SERI <?= str_pad($heatNo, 2, '0', STR_PAD_LEFT) ?></div>
                    <table class="ht-table">
                        <colgroup>
                            <col style="width: 5%">  <col style="width: 12%"> <col style="width: 25%"> <col style="width: 10%"> <col style="width: 25%"> <col style="width: 11%"> <col style="width: 12%"> </colgroup>
                        <thead>
                            <tr>
                                <th>LN</th> <th>UID</th> <th>NAMA ATLET</th> <th>KU</th> <th>TIM</th> <th>PRESTASI</th> <th>HASIL</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for($ln=1; $ln<=$totalLane; $ln++): $s = $lanes[$ln] ?? null; ?>
                            <tr>
                                <td class="tc font-mono"><?= $ln ?></td>
                                <?php if($s): ?>
                                    <td class="tc font-mono text-gray-600" style="font-size:7pt;">
                                        <?= htmlspecialchars($s['uid'] ?? '-') ?>
                                    </td>
                                    <td class="tl"><b><?= shorten($s['nama_atlet']) ?></b></td>
                                    
                                    <td class="tc font-bold"><?= getKUName($s['tanggal_lahir'], $eventYear, $ageGroups) ?></td>
                                    
                                    <td class="tl"><?= shorten(getTeamName($s, $partType)) ?></td>
                                    <td class="tr font-mono">
                                        <?php 
                                            $t = $s['entry_time'];
                                            echo (!$t || $t=='99.99.99' || strpos($t,'99:99')!==false) ? 'NT' : $t;
                                        ?>
                                    </td>
                                    <td class="tr" style="color:#ccc; letter-spacing:1px; font-size:7pt;">[...................]</td>
                                <?php else: ?>
                                    <td colspan="6" style="color:#ccc; font-style:italic; font-size:7pt;"> &lt; KOSONG &gt; </td>
                                <?php endif; ?>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div id="output-area"></div>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const outputArea = document.getElementById('output-area');
            const tplHeader  = document.getElementById('tpl-header').innerHTML;
            const tplFooter  = document.getElementById('tpl-footer').innerHTML;
            const heatBlocks = Array.from(document.querySelectorAll('#tpl-content .heat-block'));
            const PAGE_LIMIT = 1050; 
            
            let currentBody = null;
            let currentHeight = 0;

            function createNewPage() {
                const sheet = document.createElement('div');
                sheet.className = 'sheet';
                const foot = document.createElement('div'); foot.innerHTML = tplFooter; sheet.appendChild(foot);
                const head = document.createElement('div'); head.innerHTML = tplHeader; sheet.appendChild(head);
                const body = document.createElement('div'); body.className = 'page-body'; sheet.appendChild(body);
                outputArea.appendChild(sheet);
                currentBody = body;
                currentHeight = 160; 
            }
            createNewPage();

            if(heatBlocks.length === 0) {
                currentBody.innerHTML = document.querySelector('#tpl-content').innerHTML;
            } else {
                heatBlocks.forEach(block => {
                    const clone = block.cloneNode(true);
                    currentBody.appendChild(clone);
                    const h = clone.offsetHeight + 15; 
                    if (currentHeight + h > PAGE_LIMIT) {
                        currentBody.removeChild(clone);
                        createNewPage();
                        currentBody.appendChild(clone);
                        currentHeight = 160 + h;
                    } else {
                        currentHeight += h;
                    }
                });
            }
        });
    </script>
</body>
</html>