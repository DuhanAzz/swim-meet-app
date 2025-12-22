<?php
// src/admin/results/medal_tally.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// 1. CEK KEAMANAN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

// 2. PARAMETER & FILTER
$uid = $_SESSION['user_id'];
$mode = $_GET['mode'] ?? 'team'; // 'team' atau 'athlete'
$filter_gender = $_GET['gender'] ?? 'all';
$filter_ku = $_GET['ku'] ?? 'all';

// 3. AMBIL DATA PROFIL & EVENT
$stmtProfile = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtProfile->execute([$uid]);
$profile = $stmtProfile->fetch();

$header_title    = $profile['nama_lengkap'] ?? 'KEJUARAAN RENANG';
$raw_date        = strtotime($profile['event_start_date']);
$event_year      = date('Y', $raw_date);
$display_date    = date('d F Y', $raw_date);

if(strtotime($profile['event_start_date']) != strtotime($profile['event_end_date'])) {
    $header_date_range = date('d', $raw_date) . ' - ' . date('d F Y', strtotime($profile['event_end_date']));
} else {
    $header_date_range = $display_date;
}

$logo_left  = !empty($profile['logo_left']) ? '../../../public/' . $profile['logo_left'] : null;
$logo_right = !empty($profile['logo_right']) ? '../../../public/' . $profile['logo_right'] : null;

// 4. AMBIL SPONSOR FOOTER
$stmtFooter = $pdo->prepare("SELECT * FROM event_footer_logos WHERE user_id = ? ORDER BY id ASC");
$stmtFooter->execute([$uid]);
$footerSponsors = $stmtFooter->fetchAll(PDO::FETCH_ASSOC);

// 5. HELPER: HITUNG KU (Untuk Mode Atlet)
function getKU($tgl_lahir, $event_year) {
    if (!$tgl_lahir || $tgl_lahir == '0000-00-00') return '-';
    $born_year = date('Y', strtotime($tgl_lahir));
    $age = $event_year - $born_year;
    
    if ($age <= 10) return 'KU 4 (≤10)';
    if ($age <= 12) return 'KU 3 (11-12)';
    if ($age <= 14) return 'KU 2 (13-14)';
    if ($age <= 17) return 'KU 1 (15-17)';
    return 'SENIOR (18+)';
}

// 6. LOGIC QUERY DATABASE
$tally = [];
$title_main = "";
$title_sub = "";

if ($mode == 'team') {
    // --- MODE JUARA UMUM (TIM) ---
    $title_main = "KLASEMEN JUARA UMUM";
    $title_sub = "PEROLEHAN MEDALI TIM / KLUB";
    
    $sql = "SELECT 
                COALESCE(NULLIF(s.asal_sekolah, ''), u.nama_lengkap, 'Unattached') as name,
                SUM(CASE WHEN ee.final_rank = 1 THEN 1 ELSE 0 END) as gold,
                SUM(CASE WHEN ee.final_rank = 2 THEN 1 ELSE 0 END) as silver,
                SUM(CASE WHEN ee.final_rank = 3 THEN 1 ELSE 0 END) as bronze,
                COUNT(ee.id) as total_medals
            FROM event_entries ee
            JOIN swimmers s ON ee.swimmer_id = s.id
            LEFT JOIN users u ON ee.user_id = u.id
            WHERE ee.final_rank IN (1, 2, 3)
            GROUP BY name
            ORDER BY gold DESC, silver DESC, bronze DESC";
            
    $tally = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

} else {
    // --- MODE PERENANG TERBAIK (ATLET) ---
    $title_main = "PERENANG TERBAIK";
    $label_gender = ($filter_gender == 'all') ? 'PUTRA & PUTRI' : ($filter_gender == 'L' ? 'PUTRA' : 'PUTRI');
    $label_ku = ($filter_ku == 'all') ? 'SEMUA UMUR' : strtoupper($filter_ku);
    $title_sub = "$label_ku - $label_gender";

    // Filter Query
    $whereClause = "WHERE ee.final_rank IN (1, 2, 3)";
    $params = [];

    if ($filter_gender !== 'all') {
        $whereClause .= " AND s.jenis_kelamin = ?";
        $params[] = $filter_gender;
    }

    $sql = "SELECT 
                s.nama_atlet, 
                s.jenis_kelamin, 
                s.tanggal_lahir,
                COALESCE(NULLIF(s.asal_sekolah, ''), u.nama_lengkap) as team,
                SUM(CASE WHEN ee.final_rank = 1 THEN 1 ELSE 0 END) as gold,
                SUM(CASE WHEN ee.final_rank = 2 THEN 1 ELSE 0 END) as silver,
                SUM(CASE WHEN ee.final_rank = 3 THEN 1 ELSE 0 END) as bronze,
                COUNT(ee.id) as total_medals
            FROM event_entries ee
            JOIN swimmers s ON ee.swimmer_id = s.id
            LEFT JOIN users u ON ee.user_id = u.id
            $whereClause
            GROUP BY s.id
            ORDER BY gold DESC, silver DESC, bronze DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Filter KU via PHP
    foreach ($raw_data as $row) {
        $ku = getKU($row['tanggal_lahir'], $event_year);
        if ($filter_ku !== 'all') {
            $pass = false;
            if ($filter_ku == 'ku4' && strpos($ku, 'KU 4') !== false) $pass = true;
            if ($filter_ku == 'ku3' && strpos($ku, 'KU 3') !== false) $pass = true;
            if ($filter_ku == 'ku2' && strpos($ku, 'KU 2') !== false) $pass = true;
            if ($filter_ku == 'ku1' && strpos($ku, 'KU 1') !== false) $pass = true;
            if ($filter_ku == 'senior' && strpos($ku, 'SENIOR') !== false) $pass = true;
            if (!$pass) continue;
        }
        $row['ku_label'] = $ku;
        $tally[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Medal Tally</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&family=Courier+Prime:wght@400;700&display=swap" rel="stylesheet">
    
    <style>
        /* === STYLE SERAGAM (SAMA SEPERTI print_result.php) === */
        .font-condensed { font-family: 'Roboto Condensed', sans-serif; }
        .font-mono { font-family: 'Courier Prime', monospace; }
        
        body { background: #525659; margin: 0; padding: 20px; min-height: 100vh; display: flex; flex-direction: column; align-items: center; }
        
        /* LAYOUT KERTAS A4 */
        .paper-sheet {
            width: 210mm; min-height: 297mm; background: white; 
            padding: 10mm; 
            color: #000; position: relative; font-family: 'Roboto Condensed', sans-serif;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
            display: flex; flex-direction: column;
        }

        /* HEADER KOP SURAT */
        .page-header {
            padding: 10px 0 20px 0; border-bottom: 3px double #000; margin-bottom: 20px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .logo-box { width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; }
        
        /* HEADER ACARA GRID (ADAPTASI UNTUK MEDALI) */
        .event-header-grid {
            display: grid; grid-template-columns: 120px 1fr 120px; align-items: center;
            border-bottom: 2px solid #000; margin-bottom: 15px; padding-bottom: 5px;
        }
        .event-info-box { text-align: left; } 
        .event-info-title { font-size: 8pt; font-weight: bold; color: #444; text-transform: uppercase; }
        .event-info-val { font-size: 11pt; font-weight: 900; line-height: 1.2; text-transform: uppercase; }
        
        .event-title-box { text-align: center; } 
        .event-title { font-size: 16pt; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
        
        .event-round-box { text-align: right; }

        /* TABEL DATA (STYLE SAMA) */
        .result-table { width: 100%; border-collapse: collapse; font-size: 9pt; table-layout: fixed; }
        
        .result-table th { 
            background: #f0f0f0; border-bottom: 1px solid #000; border-top: 1px solid #000;
            padding: 6px 8px; font-weight: bold; font-size: 9pt; vertical-align: middle;
            text-transform: uppercase;
        }
        .result-table td { 
            border-bottom: 1px solid #ddd; padding: 4px 8px; 
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; vertical-align: middle; 
        }

        /* Highlight untuk kolom medali */
        .bg-gold { background-color: #fffbe6; }
        .bg-silver { background-color: #f4f4f5; }
        .bg-bronze { background-color: #fff1e6; }
        .bg-total { background-color: #fafafa; font-weight: 900; }

        /* Alignment */
        .col-center { text-align: center; } 
        .col-left { text-align: left; } 
        .col-right { text-align: right; }
        .font-black { font-weight: 900; }
        
        /* Footer Sponsor */
        .page-footer {
            margin-top: auto; padding-top: 10px; border-top: 2px solid #000;
            height: 70px; display: flex; align-items: center; justify-content: center; gap: 30px;
        }
        .page-footer img { height: 100%; width: auto; max-width: 150px; object-fit: contain; }

        /* Styling Form Filter di No-Print */
        .filter-select {
            background: white; border: 1px solid #cbd5e1; padding: 4px 8px; border-radius: 4px;
            font-size: 0.8rem; font-weight: bold; color: #334155;
        }

        @media print {
            body { background: white; padding: 0; display: block; }
            .no-print { display: none !important; }
            .paper-sheet { width: 100%; box-shadow: none; margin: 0; padding: 0; min-height: 100vh; }
            /* Paksa cetak background color untuk kolom medali */
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style>
</head>
<body>

    <div class="no-print w-[210mm] mb-6 flex flex-col gap-4">
        
        <div class="bg-white p-4 rounded-xl shadow border border-gray-300 flex justify-between items-center">
            <div>
                <h1 class="font-bold text-lg text-slate-800">REKAPITULASI MEDALI</h1>
                <p class="text-xs text-slate-500">Pilih mode tampilan:</p>
            </div>
            <div class="flex gap-2 bg-slate-100 p-1 rounded-lg">
                <a href="?mode=team" class="px-4 py-1.5 rounded-md text-xs font-bold uppercase transition <?= $mode=='team' ? 'bg-white shadow text-slate-800' : 'text-slate-500 hover:text-slate-800' ?>">
                    🏆 Tim / Klub
                </a>
                <a href="?mode=athlete" class="px-4 py-1.5 rounded-md text-xs font-bold uppercase transition <?= $mode=='athlete' ? 'bg-white shadow text-slate-800' : 'text-slate-500 hover:text-slate-800' ?>">
                    🏊 Perenang Terbaik
                </a>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl shadow border border-gray-300 flex justify-between items-center">
            
            <?php if($mode == 'athlete'): ?>
                <form class="flex items-center gap-3">
                    <input type="hidden" name="mode" value="athlete">
                    <div class="flex flex-col">
                        <label class="text-[10px] font-bold text-slate-400 uppercase">Kelompok Umur</label>
                        <select name="ku" class="filter-select">
                            <option value="all">SEMUA UMUR</option>
                            <option value="senior" <?= $filter_ku=='senior'?'selected':'' ?>>SENIOR</option>
                            <option value="ku1" <?= $filter_ku=='ku1'?'selected':'' ?>>KU 1</option>
                            <option value="ku2" <?= $filter_ku=='ku2'?'selected':'' ?>>KU 2</option>
                            <option value="ku3" <?= $filter_ku=='ku3'?'selected':'' ?>>KU 3</option>
                            <option value="ku4" <?= $filter_ku=='ku4'?'selected':'' ?>>KU 4</option>
                        </select>
                    </div>
                    <div class="flex flex-col">
                        <label class="text-[10px] font-bold text-slate-400 uppercase">Gender</label>
                        <select name="gender" class="filter-select">
                            <option value="all">SEMUA</option>
                            <option value="L" <?= $filter_gender=='L'?'selected':'' ?>>PUTRA</option>
                            <option value="P" <?= $filter_gender=='P'?'selected':'' ?>>PUTRI</option>
                        </select>
                    </div>
                    <button type="submit" class="mt-4 px-4 py-1 bg-blue-600 text-white text-xs font-bold rounded hover:bg-blue-700">FILTER</button>
                </form>
            <?php else: ?>
                <div class="text-xs text-slate-400 italic">Filter tidak tersedia untuk mode Tim.</div>
            <?php endif; ?>

            <div class="flex gap-2">
                <a href="index.php" class="px-4 py-2 bg-slate-100 text-slate-600 rounded font-bold text-xs uppercase hover:bg-slate-200">Kembali</a>
                <button onclick="window.print()" class="px-6 py-2 bg-slate-900 text-white rounded font-bold text-xs uppercase hover:bg-slate-800 flex items-center gap-2">🖨️ Cetak</button>
            </div>
        </div>
    </div>

    <div class="paper-sheet">
        
        <div class="page-header">
            <div class="logo-box">
                <?php if($logo_left): ?><img src="<?= $logo_left ?>" class="max-h-full max-w-full object-contain"><?php endif; ?>
            </div>
            <div class="text-center flex-1 px-4">
                <h1 class="text-xl font-black uppercase leading-tight tracking-wide"><?= htmlspecialchars($header_title) ?></h1>
                <p class="text-sm font-bold uppercase text-gray-600 mt-1"><?= htmlspecialchars($header_date_range) ?></p>
                <div class="inline-block border-2 border-black px-6 py-1 mt-2">
                    <p class="text-xl font-black uppercase tracking-[0.2em] leading-none">MEDAL TALLY</p>
                </div>
            </div>
            <div class="logo-box">
                <?php if($logo_right): ?><img src="<?= $logo_right ?>" class="max-h-full max-w-full object-contain"><?php endif; ?>
            </div>
        </div>

        <div class="event-header-grid">
            <div class="event-info-box">
                <div class="event-info-title">LAST UPDATE</div>
                <div class="event-info-val"><?= date('d/m H:i') ?></div>
            </div>
            <div class="event-title-box">
                <div class="event-title"><?= $title_main ?></div>
            </div>
            <div class="event-round-box">
                <div class="event-info-title">KATEGORI</div>
                <div class="event-info-val" style="font-size: 9pt;"><?= $title_sub ?></div>
            </div>
        </div>

        <?php if(empty($tally)): ?>
            <div class="text-center py-20 italic text-gray-400">
                Belum ada data medali untuk kategori ini.
            </div>
        <?php else: ?>
            <table class="result-table">
                <colgroup>
                    <col style="width: 8%;">   <?php if($mode == 'team'): ?>
                        <col style="width: 52%;">  <?php else: ?>
                        <col style="width: 32%;">  <col style="width: 20%;">  <?php endif; ?>
                    
                    <col style="width: 10%;">  <col style="width: 10%;">  <col style="width: 10%;">  <col style="width: 10%;">  </colgroup>
                <thead>
                    <tr>
                        <th class="col-center">RANK</th>
                        
                        <?php if($mode == 'team'): ?>
                            <th class="col-left">TIM / KONTINGEN</th>
                        <?php else: ?>
                            <th class="col-left">NAMA ATLET</th>
                            <th class="col-left">TIM / KONTINGEN</th>
                        <?php endif; ?>

                        <th class="col-center bg-gold">EMAS</th>
                        <th class="col-center bg-silver">PERAK</th>
                        <th class="col-center bg-bronze">PRG</th>
                        <th class="col-center bg-total">TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rank = 1;
                    foreach($tally as $row): 
                    ?>
                    <tr>
                        <td class="col-center font-black text-sm"><?= $rank++ ?></td>
                        
                        <?php if($mode == 'team'): ?>
                            <td class="col-left font-bold text-black"><?= htmlspecialchars($row['name']) ?></td>
                        <?php else: ?>
                            <td class="col-left font-bold text-black">
                                <?= htmlspecialchars($row['nama_atlet']) ?>
                                <span class="text-[8px] text-gray-500 block font-normal">
                                    <?= $row['jenis_kelamin'] ?> | <?= $row['ku_label'] ?>
                                </span>
                            </td>
                            <td class="col-left text-xs text-gray-700"><?= htmlspecialchars($row['team']) ?></td>
                        <?php endif; ?>

                        <td class="col-center font-mono font-bold bg-gold"><?= $row['gold'] ?></td>
                        <td class="col-center font-mono font-bold bg-silver"><?= $row['silver'] ?></td>
                        <td class="col-center font-mono font-bold bg-bronze"><?= $row['bronze'] ?></td>
                        <td class="col-center font-mono font-black bg-total"><?= $row['total_medals'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="mt-12 flex justify-between px-10">
            <div class="text-center w-40">
                </div>
            <div class="text-center w-40">
                <p class="text-[10px] font-bold uppercase text-gray-500">Ketua Panitia</p>
                <div class="border-bottom border-black mt-12 border-b"></div>
            </div>
        </div>

        <div class="page-footer">
            <?php if(!empty($footerSponsors)): ?>
                <?php foreach($footerSponsors as $fs): ?>
                    <img src="../../../public/<?= $fs['image_path'] ?>" alt="Sponsor">
                <?php endforeach; ?>
            <?php else: ?>
                <span class="text-xs text-gray-300 italic">Supported by Swim Event System</span>
            <?php endif; ?>
        </div>
        
        <div class="absolute bottom-2 left-10 text-[8px] text-gray-400 uppercase">
            Printed: <?= date('d/m/Y H:i') ?>
        </div>

    </div>

</body>
</html>