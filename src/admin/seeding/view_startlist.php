<?php
// src/admin/seeding/view_startlist.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// 1. CEK KEAMANAN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}
$uid = $_SESSION['user_id']; 

// Menerima 'event_id' (sesuai link dari index.php) atau 'category_id' (legacy)
$cat_id = $_GET['event_id'] ?? ($_GET['category_id'] ?? null);

if (!$cat_id) { 
    // Jika tidak ada ID, kembalikan ke index
    header("Location: index.php"); 
    exit; 
}

// 2. AMBIL DATA EVENT & CONFIG (Header Buku Acara)
$stmtProfile = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtProfile->execute([$uid]);
$profile = $stmtProfile->fetch();

$total_lintasan = !empty($profile['lane_count']) ? (int)$profile['lane_count'] : 8;

// Data Header
$header_title    = $profile['nama_lengkap'] ?? 'KEJUARAAN RENANG';
$raw_date        = strtotime($profile['event_start_date']);
$event_year      = date('Y', $raw_date);
$display_date    = date('d F Y', $raw_date);

// Tanggal Rentang untuk KOP SURAT
if(strtotime($profile['event_start_date']) != strtotime($profile['event_end_date'])) {
    $header_date_range = date('d', $raw_date) . ' - ' . date('d F Y', strtotime($profile['event_end_date']));
} else {
    $header_date_range = $display_date;
}

$logo_left       = !empty($profile['logo_left']) ? '../../../public/' . $profile['logo_left'] : null;
$logo_right      = !empty($profile['logo_right']) ? '../../../public/' . $profile['logo_right'] : null;

// 3. AMBIL INFO NOMOR LOMBA
$stmtEvent = $pdo->prepare("SELECT * FROM event_numbers WHERE id = ?");
$stmtEvent->execute([$cat_id]);
$eventData = $stmtEvent->fetch();

if (!$eventData) die("Nomor lomba tidak ditemukan.");

$nomor_lomba = $eventData['event_number'];
$gender_label = ($eventData['jenis_kelamin'] == 'L' || $eventData['jenis_kelamin'] == 'Male') ? 'PUTRA' : 'PUTRI';
$jarak_gaya  = $eventData['distance'] . " M " . strtoupper($eventData['stroke']) . " " . $gender_label;

// 4. AMBIL DATA START LIST (DENGAN FILTER LUNAS)
try {
    $sql = "SELECT 
                ee.heat as heat_no, 
                ee.lane as lane_no, 
                ee.entry_time,
                s.nama_atlet,
                s.tanggal_lahir, 
                s.jenis_kelamin, 
                u.nama_lengkap as club_name, 
                u.location as city_name, 
                s.asal_sekolah
            FROM event_entries ee
            JOIN swimmers s ON ee.swimmer_id = s.id
            LEFT JOIN users u ON ee.club_id = u.id 
            LEFT JOIN payments p ON p.user_id = ee.club_id
            WHERE ee.category_id = ? 
            AND ee.heat IS NOT NULL 
            AND (p.status = 'Paid' OR p.status = 'Verified') -- Filter Lunas
            ORDER BY ee.heat ASC, ee.lane ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cat_id]);
    $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error Database: " . $e->getMessage());
}

// --- HELPER FUNCTIONS ---

// 1. Format Umur
function formatLahir($tanggal_lahir, $event_year) {
    if(!$tanggal_lahir || $tanggal_lahir == '0000-00-00') return '-';
    $born_year = date('Y', strtotime($tanggal_lahir));
    $age = $event_year - $born_year;
    return $born_year . " (" . $age . ")";
}

// 2. Singkat Nama (Smart Shorten)
function shortenName($name) {
    $name = trim(preg_replace('/\s+/', ' ', $name));
    $parts = explode(' ', $name);
    
    if (count($parts) <= 3) return $name;
    
    $final_name = [];
    foreach ($parts as $index => $word) {
        if ($index < 3) {
            $final_name[] = $word;
        } else {
            $final_name[] = substr($word, 0, 1) . '.';
        }
    }
    return implode(' ', $final_name);
}

// Grouping Data per Heat
$heats = [];
foreach ($raw_data as $row) {
    $heats[$row['heat_no']][$row['lane_no']] = $row;
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&family=Courier+Prime:wght@400;700&display=swap');
    
    .font-condensed { font-family: 'Roboto Condensed', sans-serif; }
    .font-mono { font-family: 'Courier Prime', monospace; }

    /* LAYOUT UTAMA SAMA DENGAN PRINT FULL BOOK */
    .paper-sheet {
        width: 210mm;
        min-height: 297mm;
        background: white;
        margin: 0 auto;
        padding: 10mm; /* Margin disamakan */
        color: #000;
        position: relative;
        font-family: 'Roboto Condensed', sans-serif;
    }

    /* KOP SURAT / HEADER HALAMAN */
    .page-header {
        padding: 10px 0 20px 0;
        border-bottom: 3px double #000;
        margin-bottom: 20px;
        display: flex; justify-content: space-between; align-items: center;
        width: 100%;
    }
    .logo-box { width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; }

    /* HEADER NOMOR LOMBA (Grid Style - Kiri/Tengah/Kanan) */
    .event-header-grid {
        display: grid;
        grid-template-columns: 100px 1fr 100px; /* Struktur kolom sama persis */
        align-items: center;
        border-bottom: 2px solid #000;
        margin-bottom: 15px;
        padding-bottom: 5px;
    }
    .event-num-box { text-align: left; }
    .event-number { font-size: 18pt; font-weight: 900; line-height: 1; }
    .event-date { font-size: 9pt; font-weight: bold; color: #444; margin-top: 2px; text-transform: uppercase;}
    .event-title-box { text-align: center; }
    .event-title { font-size: 14pt; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
    .event-round-box { text-align: right; font-size: 10pt; font-weight: bold; background: #eee; padding: 2px 8px; border-radius: 4px; }

    /* WRAPPER TABLE */
    .heat-wrapper { margin-bottom: 15px; page-break-inside: avoid; }
    .heat-header {
        text-align: right; font-weight: bold; font-size: 10pt; 
        border-bottom: 1px solid #000; margin-bottom: 2px; padding-right: 5px;
    }

    /* TABEL DATA */
    .heat-table { 
        width: 100%; 
        border-collapse: collapse; 
        font-size: 8pt; /* Ukuran Font 8pt */
        table-layout: fixed; 
    }
    
    /* STYLE BORDER HEADER TABEL (SAMA PERSIS) */
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

    /* Alignment & Utilities */
    .col-center { text-align: center; }
    .col-left { text-align: left; }
    .col-right { text-align: right; }
    .font-mono { font-family: 'Courier Prime', monospace; }
    .result-dots { color: #ccc; letter-spacing: 2px; }

    @media print {
        @page { size: A4; margin: 10mm; }
        body { background: white; margin: 0; padding: 0; }
        nav, aside, .no-print { display: none !important; }
        .p-4, .sm\:ml-64 { padding: 0 !important; margin: 0 !important; width: 100% !important; }
        .paper-sheet { width: 100%; box-shadow: none; margin: 0; padding: 0; }
        .page-break-avoid { page-break-inside: avoid; }
    }
</style>

<div class="p-4 sm:ml-64 pt-24 min-h-screen bg-slate-100 text-slate-900 font-sans">

    <div class="max-w-[210mm] mx-auto mb-6 flex justify-between items-center no-print">
        <div>
            <h2 class="text-lg font-bold text-slate-700">PREVIEW ACARA</h2>
            <p class="text-xs text-slate-500">Tampilan disamakan dengan Full Book (Garis & Header).</p>
        </div>
        <div class="flex gap-2">
            <a href="index.php" class="bg-white border border-slate-300 px-4 py-2 rounded text-xs font-bold uppercase hover:bg-slate-50">Kembali</a>
            <button onclick="window.print()" class="bg-slate-900 text-white px-6 py-2 rounded text-xs font-bold uppercase hover:bg-slate-800 flex items-center gap-2">🖨️ Cetak</button>
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
                    <p class="text-xl font-black uppercase tracking-[0.2em] leading-none">BUKU ACARA</p>
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
            <div class="event-round-box">FINAL</div>
        </div>

        <?php if(empty($heats)): ?>
            <div class="text-center py-12 border-y border-dashed border-gray-400 mt-10">
                <p class="italic text-gray-500 font-bold">Data Seeding Belum Tersedia.</p>
                <p class="text-xs text-gray-400 mt-2">Pastikan sudah klik tombol "GENERATE" di halaman sebelumnya.</p>
            </div>
        <?php else: ?>

            <?php foreach($heats as $heatNo => $lanesData): ?>
            <div class="heat-wrapper">
                
                <div class="heat-header">SERI <?= str_pad($heatNo, 2, '0', STR_PAD_LEFT) ?></div>

                <table class="heat-table">
                    <colgroup>
                        <col style="width: 4%;">  <col style="width: 32%;"> <col style="width: 13%;"> <col style="width: 21%;"> <col style="width: 15%;"> <col style="width: 15%;"> </colgroup>
                    <thead>
                        <tr>
                            <th class="col-center">LN</th>
                            <th class="col-left">NAMA ATLET</th>
                            <th class="col-center">LAHIR</th>
                            <th class="col-left">TIM / SEKOLAH</th>
                            <th class="col-right">WAKTU</th>
                            <th class="col-right">HASIL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        for($ln = 1; $ln <= $total_lintasan; $ln++): 
                            $s = isset($lanesData[$ln]) ? $lanesData[$ln] : null;
                        ?>
                        <tr>
                            <td class="col-center font-bold font-mono"><?= $ln ?></td>

                            <?php if($s): ?>
                                <td class="col-left font-bold text-black" title="<?= $s['nama_atlet'] ?>">
                                    <?= shortenName($s['nama_atlet']) ?>
                                </td>
                                
                                <td class="col-center text-gray-700 font-mono">
                                    <?= formatLahir($s['tanggal_lahir'], $event_year) ?>
                                </td>
                                
                                <td class="col-left text-gray-800" title="<?= $s['asal_sekolah'] ?>">
                                    <?= !empty($s['asal_sekolah']) ? $s['asal_sekolah'] : $s['club_name'] ?>
                                </td>
                                
                                <td class="col-right font-mono font-bold">
                                    <?= ($s['entry_time'] == '99:99.99' || !$s['entry_time']) ? 'NT' : $s['entry_time'] ?>
                                </td>
                                
                                <td class="col-right font-mono result-dots">.......</td>

                            <?php else: ?>
                                <td colspan="5" class="text-gray-300 italic font-light pl-2">&lt; KOSONG &gt;</td>
                            <?php endif; ?>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>

        <?php endif; ?>

        <div class="fixed bottom-0 left-0 right-0 p-6 text-[9px] uppercase font-condensed text-gray-400 flex justify-between no-print">
            <span>Dicetak Otomatis oleh Sistem</span>
        </div>

    </div>
    <div class="h-24"></div> 
</div>