<?php
// src/admin/seeding/print_full_book.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}
$uid = $_SESSION['user_id']; 

// --- 1. AMBIL PROFIL & LOGO ---
$stmtProfile = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtProfile->execute([$uid]);
$profile = $stmtProfile->fetch();

// --- 2. AMBIL SPONSOR FOOTER ---
$stmtFooter = $pdo->prepare("SELECT * FROM event_footer_logos WHERE user_id = ? ORDER BY id ASC");
$stmtFooter->execute([$uid]);
$footerSponsors = $stmtFooter->fetchAll(PDO::FETCH_ASSOC);

// --- 3. VARIABEL HEADER & TANGGAL ---
$total_lintasan = !empty($profile['lane_count']) ? (int)$profile['lane_count'] : 8;
$header_title    = $profile['nama_lengkap'] ?? 'KEJUARAAN RENANG';

$raw_date        = strtotime($profile['event_start_date']);
$display_date    = date('d F Y', $raw_date); 
$event_year      = date('Y', $raw_date);

if(strtotime($profile['event_start_date']) != strtotime($profile['event_end_date'])) {
    $header_date_range = date('d', $raw_date) . ' - ' . date('d F Y', strtotime($profile['event_end_date']));
} else {
    $header_date_range = $display_date;
}

$logo_left  = !empty($profile['logo_left']) ? '../../../public/' . $profile['logo_left'] : null;
$logo_right = !empty($profile['logo_right']) ? '../../../public/' . $profile['logo_right'] : null;

// --- LOGIKA BARU: MENENTUKAN LCM / SCM DARI PROFIL (GLOBAL UNTUK SEMUA NOMOR) ---
$poolSuffix = ""; 
$pType = "";

if (!empty($profile['pool_type'])) {
    $pType = $profile['pool_type'];
} elseif (!empty($profile['pool_length'])) {
    $pType = $profile['pool_length'];
}

$pType = strtolower(trim($pType));
if ($pType === '50m' || $pType === 'lcm' || $pType === 'long course') {
    $poolSuffix = " - LCM";
} elseif ($pType === '25m' || $pType === 'scm' || $pType === 'short course') {
    $poolSuffix = " - SCM";
}
// --------------------------------------------------------------------------------

// --- 4. AMBIL DATA LOMBA ---
$stmtEvents = $pdo->query("SELECT * FROM event_numbers ORDER BY event_number ASC");
$all_events = $stmtEvents->fetchAll(PDO::FETCH_ASSOC);

// --- HELPER FUNCTIONS ---

// 1. Format Umur
function formatLahir($tgl, $year) {
    if(!$tgl || $tgl == '0000-00-00') return '-';
    $by = date('Y', strtotime($tgl));
    return $by . " (" . ($year - $by) . ")";
}

// 2. Singkat Nama (Fitur Baru)
function shortenName($name) {
    // Bersihkan spasi ganda
    $name = trim(preg_replace('/\s+/', ' ', $name));
    $parts = explode(' ', $name);
    
    // Jika 3 kata atau kurang, biarkan utuh
    if (count($parts) <= 3) {
        return $name;
    }
    
    // Jika lebih, singkat kata ke-4 dst
    $final_name = [];
    foreach ($parts as $index => $word) {
        if ($index < 3) {
            $final_name[] = $word;
        } else {
            // Ambil huruf depan + titik
            $final_name[] = substr($word, 0, 1) . '.';
        }
    }
    return implode(' ', $final_name);
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&family=Courier+Prime:wght@400;700&display=swap');
    
    .font-condensed { font-family: 'Roboto Condensed', sans-serif; }
    .font-mono { font-family: 'Courier Prime', monospace; }
    
    /* STRUKTUR UTAMA */
    .main-table {
        width: 100%;
        max-width: 210mm; 
        margin: 0 auto;
        border-collapse: collapse;
        background: white;
        font-family: 'Roboto Condensed', sans-serif;
    }

    /* KOP SURAT (HEADER) */
    .page-header {
        padding: 10px 0 15px 0;
        border-bottom: 3px double #000;
        margin-bottom: 20px;
        display: flex; justify-content: space-between; align-items: center;
        width: 100%;
    }
    .logo-box { width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; }
    
    /* FOOTER SPONSOR */
    .page-footer {
        padding-top: 5px;
        border-top: 2px solid #000;
        margin-top: 15px;
        height: 70px;
        display: flex; align-items: center; justify-content: center; gap: 30px;
    }
    .page-footer img { height: 100%; width: auto; max-width: 150px; object-fit: contain; }

    /* HEADER ACARA (#101 JUDUL) */
    .event-block { margin-bottom: 35px; page-break-inside: avoid; }
    
    .event-header-grid {
        display: grid;
        grid-template-columns: 100px 1fr 100px;
        align-items: center;
        border-bottom: 2px solid #000;
        margin-bottom: 10px;
        padding-bottom: 4px;
    }
    .event-num-box { text-align: left; }
    .event-number { font-size: 18pt; font-weight: 900; line-height: 1; }
    .event-date { font-size: 9pt; font-weight: bold; color: #444; margin-top: 2px; text-transform: uppercase;}
    .event-title-box { text-align: center; }
    .event-title { font-size: 14pt; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
    .event-round-box { text-align: right; font-size: 10pt; font-weight: bold; background: #eee; padding: 2px 8px; border-radius: 4px; }

    /* --- TABEL DATA --- */
    .heat-wrapper { margin-bottom: 12px; page-break-inside: avoid; }
    .heat-header {
        text-align: right; font-weight: bold; font-size: 9pt; 
        border-bottom: 1px solid #000; margin-bottom: 2px; padding-right: 5px;
    }

    /* PENGATURAN KOLOM AGAR SEJAJAR */
    .heat-table { 
        width: 100%; 
        border-collapse: collapse; 
        font-size: 8pt; /* FONT DIPERKECIL SESUAI REQUEST */
        table-layout: fixed; 
    }
    
    .heat-table th { 
        background: #f0f0f0; 
        border-bottom: 1px solid #000; 
        border-top: 1px solid #000;
        padding: 4px 6px; 
        font-weight: bold;
        text-transform: uppercase;
        font-size: 8pt; 
        vertical-align: middle;
    }
    
    .heat-table td { 
        border-bottom: 1px solid #ddd; 
        padding: 3px 6px; 
        white-space: nowrap; 
        overflow: hidden; 
        text-overflow: ellipsis;
        text-transform: uppercase;
        vertical-align: middle;
    }

    /* KELAS KHUSUS ALIGNMENT */
    .col-center { text-align: center; }
    .col-left   { text-align: left; }
    .col-right  { text-align: right; } 
    
    .font-mono { font-family: 'Courier Prime', monospace; }
    .result-dots { color: #ccc; letter-spacing: 2px; }

    @media print {
        @page { size: A4; margin: 10mm; }
        body { margin: 0; padding: 0; background: white; }
        .no-print, nav, header, aside { display: none !important; }
        .p-4, .sm\:ml-64 { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        thead { display: table-header-group; }
        tfoot { display: table-footer-group; }
        tr { page-break-inside: avoid; }
    }
</style>

<div class="p-4 sm:ml-64 pt-24 min-h-screen bg-slate-100 text-slate-900 font-sans">
    
    <div class="max-w-[210mm] mx-auto mb-6 flex justify-between items-center no-print">
        <div>
            <h2 class="text-lg font-bold text-slate-700">FULL BOOK LAYOUT</h2>
            <p class="text-xs text-slate-500">Font 8pt, Nama max 3 kata, Lebar Kolom Umur diperbaiki.</p>
        </div>
        <button onclick="window.print()" class="bg-slate-900 text-white px-6 py-2 rounded text-sm font-bold shadow hover:bg-slate-800">
            🖨️ CETAK
        </button>
    </div>

    <table class="main-table">
        
        <thead>
            <tr>
                <td>
                    <div class="page-header">
                        <div class="logo-box">
                            <?php if($logo_left): ?><img src="<?= $logo_left ?>" class="max-h-full max-w-full object-contain"><?php endif; ?>
                        </div>
                        <div class="text-center flex-1 px-4">
                            <h1 class="text-xl font-black uppercase leading-tight tracking-wide"><?= htmlspecialchars($header_title) ?></h1>
                            <p class="text-sm font-bold uppercase text-gray-600 mt-1"><?= htmlspecialchars($header_date_range) ?></p>
                            <div class="inline-block border-2 border-black px-6 py-1 mt-2">
                                <p class="text-xl font-black uppercase tracking-[0.2em] leading-none">BUKU ACARA</p>
                            </div>
                        </div>
                        <div class="logo-box">
                            <?php if($logo_right): ?><img src="<?= $logo_right ?>" class="max-h-full max-w-full object-contain"><?php endif; ?>
                        </div>
                    </div>
                </td>
            </tr>
        </thead>

        <tfoot>
            <tr>
                <td>
                    <div class="page-footer">
                        <?php if(!empty($footerSponsors)): ?>
                            <?php foreach($footerSponsors as $fs): ?>
                                <img src="../../../public/<?= $fs['image_path'] ?>" alt="Sponsor">
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="text-xs text-gray-300 italic">Supported by Swim Event System</span>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        </tfoot>

        <tbody>
            <tr>
                <td class="align-top py-4">
                    <?php 
                    $has_data = false;
                    foreach($all_events as $event): 
                        $cat_id = $event['id'];
                        $gender_label = ($event['jenis_kelamin'] == 'L' || $event['jenis_kelamin'] == 'Male') ? 'PUTRA' : 'PUTRI';
                        
                        // --- UPDATE JUDUL: Tambahkan $poolSuffix ---
                        $jarak_gaya  = $event['distance'] . " M " . strtoupper($event['stroke']) . " " . $gender_label . $poolSuffix;
                        // -------------------------------------------
                        
                        $sql = "SELECT ee.heat, ee.lane, ee.entry_time, s.nama_atlet, s.tanggal_lahir, 
                                       u.nama_lengkap as club_name, s.asal_sekolah
                                FROM event_entries ee
                                JOIN swimmers s ON ee.swimmer_id = s.id
                                LEFT JOIN users u ON ee.user_id = u.id 
                                WHERE ee.category_id = ? AND ee.heat IS NOT NULL 
                                ORDER BY ee.heat ASC, ee.lane ASC";
                        $stmt = $pdo->prepare($sql); $stmt->execute([$cat_id]); 
                        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        if(empty($entries)) continue; 
                        $has_data = true;
                        $heats = []; foreach($entries as $r) { $heats[$r['heat']][$r['lane']] = $r; }
                    ?>

                    <div class="event-block">
                        <div class="event-header-grid">
                            <div class="event-num-box">
                                <div class="event-number">#<?= $event['event_number'] ?></div>
                                <div class="event-date"><?= strtoupper($display_date) ?></div>
                            </div>
                            <div class="event-title-box">
                                <div class="event-title"><?= $jarak_gaya ?></div>
                            </div>
                            <div class="event-round-box">FINAL</div>
                        </div>

                        <?php foreach($heats as $heatNo => $lanesData): ?>
                            <div class="heat-wrapper">
                                <div class="heat-header">SERI <?= str_pad($heatNo, 2, '0', STR_PAD_LEFT) ?></div>
                                
                                <table class="heat-table">
                                    <colgroup>
                                        <col style="width: 4%;">  
                                        <col style="width: 32%;"> 
                                        <col style="width: 13%;"> 
                                        <col style="width: 21%;"> 
                                        <col style="width: 15%;"> 
                                        <col style="width: 15%;"> 
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th class="col-center">LN</th> 
                                            <th class="col-left">NAMA ATLET</th> 
                                            <th class="col-center">LAHIR</th>
                                            <th class="col-left">TIM / SEKOLAH</th> 
                                            <th class="col-right">PRESTASI</th> 
                                            <th class="col-right">HASIL</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php for($ln=1; $ln<=$total_lintasan; $ln++): $s = $lanesData[$ln]??null; ?>
                                        <tr>
                                            <td class="col-center font-mono font-bold"><?= $ln ?></td>
                                            <?php if($s): ?>
                                                <td class="col-left font-bold text-black" title="<?= $s['nama_atlet'] ?>">
                                                    <?= shortenName($s['nama_atlet']) ?>
                                                </td>
                                                <td class="col-center font-mono text-xs text-gray-700">
                                                    <?= formatLahir($s['tanggal_lahir'], $event_year) ?>
                                                </td>
                                                <td class="col-left text-xs text-gray-800" title="<?= $s['asal_sekolah'] ?>">
                                                    <?= $s['asal_sekolah'] ?: $s['club_name'] ?>
                                                </td>
                                                
                                                <td class="col-right font-mono font-bold">
                                                    <?= ($s['entry_time']=='99:99.99' || !$s['entry_time']) ? 'NT' : $s['entry_time'] ?>
                                                </td>
                                                <td class="col-right font-mono result-dots">.......</td>
                                            <?php else: ?>
                                                <td colspan="5" class="text-gray-300 italic text-xs pl-2">&lt; KOSONG &gt;</td>
                                            <?php endif; ?>
                                        </tr>
                                        <?php endfor; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php endforeach; ?>
                    
                    <?php if(!$has_data): ?>
                        <div class="text-center py-20 text-gray-400">Belum ada data seeding yang tersedia.</div>
                    <?php endif; ?>
                </td>
            </tr>
        </tbody>
    </table>
    <div class="h-20"></div>
</div>