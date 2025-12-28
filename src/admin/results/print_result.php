<?php
// src/admin/results/print_result.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// 1. CEK KEAMANAN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$cat_id = $_GET['category_id'] ?? null;
if (!$cat_id) { header("Location: index.php"); exit; }

$uid = $_SESSION['user_id'];

// 2. AMBIL DATA EVENT & PROFIL
// Note: Kolom separate_result_by_ku diambil dari sini (tabel users)
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

// Cek Mode Pisah KU
$is_separate_ku = $profile['separate_result_by_ku'] ?? 0;

// 3. AMBIL SPONSOR FOOTER
$stmtFooter = $pdo->prepare("SELECT * FROM event_footer_logos WHERE user_id = ? ORDER BY id ASC");
$stmtFooter->execute([$uid]);
$footerSponsors = $stmtFooter->fetchAll(PDO::FETCH_ASSOC);

// 4. AMBIL INFO NOMOR LOMBA
$stmtEvent = $pdo->prepare("SELECT * FROM event_numbers WHERE id = ?");
$stmtEvent->execute([$cat_id]);
$eventData = $stmtEvent->fetch();

$nomor_lomba  = $eventData['event_number'];
$gender_label = ($eventData['jenis_kelamin'] == 'L' || $eventData['jenis_kelamin'] == 'Male') ? 'PUTRA' : 'PUTRI';
$jarak_gaya   = $eventData['distance'] . " M " . strtoupper($eventData['stroke']) . " " . $gender_label;

// 5. AMBIL HASIL LOMBA
$sql = "SELECT ee.*, s.nama_atlet, s.tanggal_lahir, u.nama_lengkap as club_name, s.asal_sekolah
        FROM event_entries ee
        JOIN swimmers s ON ee.swimmer_id = s.id
        LEFT JOIN users u ON ee.user_id = u.id
        WHERE ee.category_id = ? 
        AND (ee.final_time IS NOT NULL OR ee.is_dq = 1) 
        ORDER BY 
            CASE 
                WHEN ee.final_rank > 0 THEN 1
                WHEN ee.is_dq = 1 AND ee.dq_reason = 'DQ' THEN 2
                WHEN ee.is_dq = 1 AND (ee.dq_reason = 'DNF' OR ee.dq_reason = 'NF') THEN 3
                WHEN ee.is_dq = 1 AND (ee.dq_reason = 'DNS' OR ee.dq_reason = 'NS') THEN 4
                ELSE 5 
            END ASC,
            ee.final_time ASC"; // Urutkan by Time agar saat di-grouping rankingnya valid

$stmt = $pdo->prepare($sql);
$stmt->execute([$cat_id]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- LOGIC UTAMA: GROUPING DATA ---
$final_data_groups = [];

if ($is_separate_ku == 1) {
    // A. LOGIKA PISAH KU
    foreach ($results as $row) {
        // Ambil Tahun Lahir sebagai Key Group
        $year = date('Y', strtotime($row['tanggal_lahir']));
        $final_data_groups[$year][] = $row;
    }
    // Urutkan Group Tahun (Muda ke Tua atau sebaliknya, di sini Ascending Tahun)
    ksort($final_data_groups);
} else {
    // B. LOGIKA NORMAL (GABUNG)
    // Masukkan semua ke dalam satu grup "OVERALL"
    $final_data_groups['OVERALL'] = $results;
}

// --- HELPER FUNCTIONS ---
function shortenName($name) {
    $name = trim(preg_replace('/\s+/', ' ', $name ?? ''));
    $parts = explode(' ', $name);
    if (count($parts) <= 3) return $name;
    $final = [];
    foreach ($parts as $i => $w) {
        if ($i < 3) $final[] = $w; else $final[] = substr($w, 0, 1) . '.';
    }
    return implode(' ', $final);
}
function formatLahir($tgl, $year) {
    if(!$tgl || $tgl == '0000-00-00') return '-';
    $by = date('Y', strtotime($tgl));
    return $by . " (" . ($year - $by) . ")";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Result #<?= $nomor_lomba ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&family=Courier+Prime:wght@400;700&display=swap" rel="stylesheet">
    <style>
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
        
        /* HEADER ACARA GRID */
        .event-header-grid {
            display: grid; grid-template-columns: 100px 1fr 100px; align-items: center;
            border-bottom: 2px solid #000; margin-bottom: 15px; padding-bottom: 5px;
        }
        .event-num-box { text-align: left; } .event-number { font-size: 18pt; font-weight: 900; line-height: 1; }
        .event-date { font-size: 9pt; font-weight: bold; color: #444; margin-top: 2px; text-transform: uppercase;}
        .event-title-box { text-align: center; } .event-title { font-size: 14pt; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
        .event-round-box { text-align: right; font-size: 10pt; font-weight: bold; background: #eee; padding: 2px 8px; border-radius: 4px; }

        /* TABEL DATA */
        .result-table { width: 100%; border-collapse: collapse; font-size: 8pt; table-layout: fixed; margin-bottom: 20px; }
        
        .result-table th { 
            background: #f0f0f0; border-bottom: 1px solid #000; border-top: 1px solid #000;
            padding: 6px 8px; font-weight: bold; font-size: 8pt; vertical-align: middle;
            text-transform: uppercase;
        }
        .result-table td { 
            border-bottom: 1px solid #ddd; padding: 4px 8px; 
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; vertical-align: middle; 
            text-transform: uppercase;
        }

        /* Alignment */
        .col-center { text-align: center; } 
        .col-left { text-align: left; } 
        .col-right { text-align: right; }
        .font-black { font-weight: 900; }
        
        /* Footer Sponsor */
        .page-footer {
            margin-top: auto;
            padding-top: 10px;
            border-top: 2px solid #000;
            height: 70px;
            display: flex; align-items: center; justify-content: center; gap: 30px;
        }
        .page-footer img { height: 100%; width: auto; max-width: 150px; object-fit: contain; }
        
        /* Agar tabel tidak terpotong jelek saat print */
        .group-container { page-break-inside: avoid; margin-bottom: 25px; }

        @media print {
            body { background: white; padding: 0; display: block; }
            .no-print { display: none !important; }
            .paper-sheet { width: 100%; box-shadow: none; margin: 0; padding: 0; min-height: 100vh; }
        }
    </style>
</head>
<body>

    <div class="no-print w-[210mm] flex justify-between items-center mb-6 bg-white p-4 rounded-xl shadow border border-gray-300">
        <div>
            <h1 class="font-bold text-lg text-slate-800">PREVIEW CETAK HASIL</h1>
            <p class="text-xs text-slate-500">
                Mode: <?= $is_separate_ku ? '<span class="text-blue-600 font-bold">Terpisah per KU (Juknis)</span>' : '<span class="text-gray-600 font-bold">Gabungan (Normal)</span>' ?>
            </p>
        </div>
        <div class="flex gap-2">
            <a href="input_result.php?category_id=<?= $cat_id ?>" class="px-4 py-2 bg-slate-100 text-slate-600 rounded font-bold text-xs uppercase hover:bg-slate-200">Kembali</a>
            <button onclick="window.print()" class="px-6 py-2 bg-slate-900 text-white rounded font-bold text-xs uppercase hover:bg-slate-800 flex items-center gap-2">🖨️ Cetak</button>
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
                    <p class="text-xl font-black uppercase tracking-[0.2em] leading-none">HASIL LOMBA</p>
                </div>
            </div>
            <div class="logo-box">
                <?php if($logo_right): ?><img src="<?= $logo_right ?>" class="max-h-full max-w-full object-contain"><?php endif; ?>
            </div>
        </div>

        <div class="event-header-grid">
            <div class="event-num-box">
                <div class="event-number">#<?= $nomor_lomba ?></div>
                <div class="event-date"><?= strtoupper($display_date) ?></div>
            </div>
            <div class="event-title-box">
                <div class="event-title"><?= $jarak_gaya ?></div>
            </div>
            <div class="event-round-box">
                FINAL
            </div>
        </div>

        <?php if(empty($results)): ?>
            <div class="text-center py-20 italic text-gray-400">
                Belum ada data hasil yang diinput / disimpan.
            </div>
        <?php else: ?>
            
            <?php foreach($final_data_groups as $group_key => $group_items): ?>
                
                <div class="group-container">
                    
                    <?php if($is_separate_ku && count($final_data_groups) > 0): ?>
                        <div class="bg-gray-100 px-2 py-1 mb-2 border-l-4 border-black">
                            <h4 class="font-black text-sm uppercase">KELOMPOK UMUR / LAHIR TAHUN : <?= $group_key ?></h4>
                        </div>
                    <?php endif; ?>

                    <table class="result-table">
                        <colgroup>
                            <col style="width: 6%;">  
                            <col style="width: 32%;"> 
                            <col style="width: 12%;"> 
                            <col style="width: 24%;"> 
                            <col style="width: 13%;"> 
                            <col style="width: 13%;"> 
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="col-center">RANK</th>
                                <th class="col-left">NAMA ATLET</th>
                                <th class="col-center">LAHIR</th>
                                <th class="col-left">TIM / SEKOLAH</th>
                                <th class="col-right">PRESTASI</th>
                                <th class="col-right">WAKTU</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // RESET RANKING COUNTER UNTUK SETIAP GRUP
                            $local_rank = 1;

                            foreach($group_items as $r): 
                                $is_dq = ($r['is_dq'] == 1);
                                $status_reason = strtoupper($r['dq_reason'] ?? '');
                                
                                $is_ns = ($status_reason === 'DNS' || $status_reason === 'NS');
                                $is_nf = ($status_reason === 'DNF' || $status_reason === 'NF');
                                $is_real_dq = (!$is_ns && !$is_nf && $is_dq);

                                // LOGIKA RANKING LOKAL
                                // Jika DQ/NS/NF, rank kosong. Jika valid, pakai counter lokal.
                                if($is_dq) {
                                    $rank_display = '';
                                } else {
                                    $rank_display = $local_rank++;
                                }
                                
                                // Style Rank
                                $rank_class = "";
                                if(!$is_dq) {
                                    if($rank_display == 1) $rank_class = "text-yellow-600 font-black";
                                    elseif($rank_display == 2) $rank_class = "text-slate-500 font-black";
                                    elseif($rank_display == 3) $rank_class = "text-orange-700 font-black";
                                }

                                // Display Waktu
                                if ($is_real_dq) $time_display = '<span style="color:red; font-weight:bold;">DQ</span>';
                                elseif ($is_nf) $time_display = '<span style="color:red; font-weight:bold;">NF</span>';
                                elseif ($is_ns) $time_display = '<span style="color:red; font-weight:bold;">NS</span>';
                                else $time_display = htmlspecialchars($r['final_time']);

                                $prestasi = ($r['entry_time'] == '99:99.99' || !$r['entry_time']) ? 'NT' : $r['entry_time'];
                            ?>
                            <tr>
                                <td class="col-center font-black text-sm <?= $rank_class ?>">
                                    <?= $rank_display ?>
                                </td>
                                <td class="col-left font-bold text-black" title="<?= $r['nama_atlet'] ?>">
                                    <?= shortenName($r['nama_atlet']) ?>
                                </td>
                                <td class="col-center font-mono text-gray-600">
                                    <?= formatLahir($r['tanggal_lahir'], $event_year) ?>
                                </td>
                                <td class="col-left text-xs text-gray-800">
                                    <?= !empty($r['asal_sekolah']) ? $r['asal_sekolah'] : $r['club_name'] ?>
                                </td>
                                <td class="col-right font-mono text-xs text-gray-600 font-bold">
                                    <?= $prestasi ?>
                                </td>
                                <td class="col-right font-mono font-bold text-black text-sm">
                                    <?= $time_display ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endforeach; ?>
            <?php endif; ?>

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
            Dicetak: <?= date('d/m/Y H:i') ?>
        </div>

    </div>

</body>
</html>