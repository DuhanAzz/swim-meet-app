<?php
// FILE: src/admin/seeding/print_full_book.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// 1. CEK KEAMANAN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Akses Ditolak");
}

// 2. ID EVENT
$eventId = $_GET['event_id'] ?? 0;
if ($eventId == 0) {
    $uid = $_SESSION['user_id'];
    $stmtLast = $pdo->prepare("SELECT id FROM events WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmtLast->execute([$uid]);
    $lastEvent = $stmtLast->fetch();
    $eventId = $lastEvent['id'] ?? 0;
}
if ($eventId == 0) { die("Event ID tidak ditemukan."); }

// 3. INFO UTAMA
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

// Format Tanggal
$displayDate = strtoupper(date('d F Y', strtotime($eventDate)));
$eventYear   = date('Y', strtotime($eventDate)); 
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

// --- HELPER FUNCTIONS ---
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
if (!function_exists('shorten')) {
    function shorten($str) { return trim($str ?? ''); }
}

// 4. AMBIL DATA
$sqlAll = "SELECT 
            en.id as cat_id, en.event_number, en.distance, en.stroke, en.age_group, en.jenis_kelamin, 
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
foreach($rawData as $row) {
    $cid = $row['cat_id'];
    if(!isset($fullBook[$cid])) {
        $cleanStroke = trim(str_ireplace(['Gaya', 'GAYA'], '', $row['stroke']));
        $genderRaw = $row['jenis_kelamin'];
        $genderLabel = ($genderRaw == 'L' || $genderRaw == 'Male') ? 'PUTRA' : (($genderRaw == 'P' || $genderRaw == 'Female') ? 'PUTRI' : $genderRaw);
        
        $judulParts = [];
        $judulParts[] = $row['distance'] . "M " . strtoupper($cleanStroke);
        $judulParts[] = $row['age_group'];
        $judulParts[] = strtoupper($genderLabel);
        
        $fullBook[$cid] = [
            'meta' => [
                'nomor' => "#" . $row['event_number'],
                'judul' => implode(" - ", $judulParts),
                'badge' => 'FINAL'
            ],
            'heats' => []
        ];
    }
    $fullBook[$cid]['heats'][$row['heat_no']][$row['lane_no']] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Full Startlist Book</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* --- STYLE DASAR --- */
        * { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        body { margin: 0; padding: 0; background: #525659; font-family: 'Arial Narrow', sans-serif; font-size: 10pt; }

        .sheet {
            width: 210mm; height: 297mm; background: white; margin: 30px auto;
            position: relative; box-shadow: 0 0 15px rgba(0,0,0,0.5); overflow: hidden;
            display: flex; flex-direction: column;
            padding: 5mm 10mm 0 10mm; 
        }

        /* HEADER BESAR */
        .page-header {
            width: 100%; border-bottom: 2px double #000; margin-bottom: 2px; padding-bottom: 2px;
            display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;
            height: 25mm; 
        }
        .logo-box { width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; }
        .logo-box img { max-height: 100%; max-width: 100%; object-fit: contain; }
        .header-content { text-align: center; flex: 1; padding: 0 10px; }
        .header-content h1 { margin: 0; font-size: 16pt; font-weight: 900; text-transform: uppercase; line-height: 1.1; }
        .header-content p { margin: 2px 0; font-size: 10pt; font-weight: bold; color: #444; text-transform: uppercase; }

        .page-body { width: 100%; flex-grow: 1; display: flex; flex-direction: column; justify-content: flex-start; }
        .print-item { break-inside: avoid; }

        /* JUDUL EVENT */
        .event-info-bar {
            display: grid; grid-template-columns: 60px 1fr 60px; align-items: center;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000; 
            margin-bottom: 2px; padding: 2px 0; flex-shrink: 0;
            background: #fff;
        }
        .evt-num { font-size: 14pt; font-weight: 900; text-align: center; border-right: 1px solid #ccc; }
        .evt-title { font-size: 10pt; font-weight: 800; text-transform: uppercase; text-align: center; }
        .evt-badge { font-size: 8pt; background: #eee; border: 1px solid #ccc; padding: 2px 6px; border-radius: 4px; font-weight: bold; text-align: center; }

        /* BLOK SERI (EDITED: NO BORDER BOTTOM) */
        .heat-block { margin-bottom: 4px; break-inside: avoid; }
        
        .heat-title { 
            text-align: right; 
            font-weight: bold; 
            font-size: 9pt; 
            /* HAPUS BORDER BOTTOM DI SINI */
            margin-bottom: 2px; 
            padding-right: 2px; 
            text-transform: uppercase; 
        }
        
        /* TABEL LEBIH LONGGAR */
        .ht-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; table-layout: fixed; }
        
        .ht-table th { 
            background: #e0e0e0; 
            border-bottom: 2px solid #000; 
            padding: 4px 3px; /* Padding Header */
            text-transform: uppercase; 
            font-size: 8pt; text-align: center;
            height: 20px;
        }
        
        .ht-table td { 
            /* EDIT: PADDING LEBIH BESAR AGAR LONGGAR */
            padding: 6px 4px; 
            vertical-align: middle; 
            border-bottom: 1px solid #ccc; 
            line-height: 1.2;
            height: 26px; /* Tinggi baris minimal */
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            font-size: 9pt; 
            font-weight: bold;
        }
        
        .ht-table tr:nth-child(even) { background-color: #f5f5f5; } 

        .tc { text-align: center; } .tr { text-align: right; } .tl { text-align: left; }
        .font-mono { font-family: 'Courier New', monospace; font-weight: bold; }

        /* FOOTER */
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

        @media print {
            body { background: white; margin: 0; }
            .sheet { margin: 0; box-shadow: none; border: none; page-break-after: always; height: 297mm; }
            .btn-print { display: none; }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body>
    <button onclick="window.print()" class="btn-print"><i class="fas fa-print"></i> CETAK BUKU</button>

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
            <?php if(empty($fullBook)): ?>
                <div style="text-align:center; padding: 50px;">Data Kosong</div>
            <?php else: ?>
                <?php foreach($fullBook as $catId => $data): ?>
                    
                    <?php 
                        $heats = $data['heats'];
                        $isFirst = true; 
                    ?>
                    
                    <?php foreach($heats as $heatNo => $lanes): ?>
                        <?php if($isFirst): ?>
                            <div class="print-item">
                                <div class="event-info-bar">
                                    <div class="evt-num"><?= $data['meta']['nomor'] ?></div>
                                    <div class="evt-title"><?= $data['meta']['judul'] ?></div>
                                    <div class="tc"><div class="evt-badge">FINAL</div></div>
                                </div>
                                <div class="heat-block">
                                    <div class="heat-title">SERI <?= str_pad($heatNo, 2, '0', STR_PAD_LEFT) ?></div>
                                    <?php renderTable($lanes, $totalLane, $eventYear, $ageGroups, $partType); ?>
                                </div>
                            </div>
                            <?php $isFirst = false; ?>
                        <?php else: ?>
                            <div class="print-item heat-block">
                                <div class="heat-title">SERI <?= str_pad($heatNo, 2, '0', STR_PAD_LEFT) ?></div>
                                <?php renderTable($lanes, $totalLane, $eventYear, $ageGroups, $partType); ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <div class="print-item" style="height:5px;"></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php
    function renderTable($lanes, $totalLane, $eventYear, $ageGroups, $partType) {
    ?>
        <table class="ht-table">
            <colgroup>
                <col style="width: 5%">  <col style="width: 10%"> <col style="width: 25%"> <col style="width: 10%"> <col style="width: 8%">  <col style="width: 22%"> <col style="width: 10%"> <col style="width: 10%"> </colgroup>
            <thead>
                <tr>
                    <th class="tc">LN</th> 
                    <th class="tc">UID</th> 
                    <th class="tl" style="padding-left:5px;">NAMA ATLET</th> 
                    <th class="tc">LAHIR</th>
                    <th class="tc">KU</th> 
                    <th class="tl" style="padding-left:5px;">TIM</th> 
                    <th class="tr" style="padding-right:5px;">WAKTU</th> 
                    <th class="tc">HASIL</th>
                </tr>
            </thead>
            <tbody>
                <?php for($ln=1; $ln<=$totalLane; $ln++): $s = $lanes[$ln] ?? null; ?>
                <tr>
                    <td class="tc font-mono bg-slate-50" style="border-right:1px solid #ccc;"><b><?= $ln ?></b></td>
                    <?php if($s): ?>
                        <td class="tc font-mono" style="font-size:7.5pt;"><?= htmlspecialchars($s['uid'] ?? '-') ?></td>
                        <td class="tl font-bold text-black" style="padding-left:5px;"><?= shorten($s['nama_atlet']) ?></td>
                        
                        <?php 
                            $thn = ($s['tanggal_lahir'] && $s['tanggal_lahir']!='0000-00-00') ? date('Y', strtotime($s['tanggal_lahir'])) : '-';
                            $umr = ($thn != '-') ? ($eventYear - $thn) : 0;
                            $lahirInfo = ($thn != '-') ? $thn." (".$umr.")" : "-";
                        ?>
                        <td class="tc"><?= $lahirInfo ?></td>
                        
                        <td class="tc font-bold"><?= getKUName($s['tanggal_lahir'], $eventYear, $ageGroups) ?></td>
                        <td class="tl" style="font-size:7.5pt; padding-left:5px;"><?= shorten(getTeamName($s, $partType)) ?></td>
                        <td class="tr font-mono font-bold" style="padding-right:5px;">
                            <?php $t = $s['entry_time']; echo (!$t || $t=='99.99.99' || strpos($t,'99:99')!==false) ? 'NT' : $t; ?>
                        </td>
                        <td class="tr" style="color:#aaa; font-size:7pt; padding-right:5px; letter-spacing:1px;">[...............]</td>
                    <?php else: ?>
                        <td colspan="7" class="tl" style="color:#888; font-style:italic; padding-left:5px;">&lt;Kosong&gt;</td>
                    <?php endif; ?>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    <?php } ?>

    <div id="output-area"></div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const outputArea = document.getElementById('output-area');
            const tplHeader  = document.getElementById('tpl-header').innerHTML;
            const tplFooter  = document.getElementById('tpl-footer').innerHTML;
            const printItems = Array.from(document.querySelectorAll('#tpl-content .print-item'));
            
            // PAGE LIMIT LOGIC
            // Karena tabel sekarang lebih tinggi (spasi longgar), limit per halaman
            // harus disesuaikan sedikit agar tidak terpotong jelek.
            const PAGE_LIMIT = 1000; 
            
            let currentBody = null;
            let currentHeight = 0;

            function createNewPage() {
                const sheet = document.createElement('div');
                sheet.className = 'sheet';
                
                const head = document.createElement('div'); head.innerHTML = tplHeader; sheet.appendChild(head);
                const body = document.createElement('div'); body.className = 'page-body'; sheet.appendChild(body);
                const foot = document.createElement('div'); foot.innerHTML = tplFooter; sheet.appendChild(foot);
                
                outputArea.appendChild(sheet);
                currentBody = body;
                currentHeight = 135; 
            }
            createNewPage();

            printItems.forEach(item => {
                const clone = item.cloneNode(true);
                currentBody.appendChild(clone);
                
                const h = clone.offsetHeight;
                
                // Jika melebihi batas, pindah halaman
                if (currentHeight + h > PAGE_LIMIT) {
                    currentBody.removeChild(clone);
                    createNewPage();
                    currentBody.appendChild(clone);
                    currentHeight = 135 + h;
                } else {
                    currentHeight += h;
                }
            });
        });
    </script>
</body>
</html>