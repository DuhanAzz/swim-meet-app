<?php
// src/admin/results/medal_tally.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// 1. CEK KEAMANAN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}
$uid = $_SESSION['user_id'];

// 2. AMBIL EVENT TERAKHIR (Untuk Kop Surat & Logo)
$stmtLast = $pdo->prepare("SELECT id FROM events WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmtLast->execute([$uid]);
$lastEvent = $stmtLast->fetch();
$eventId = $lastEvent['id'] ?? 0;

$stmtProfile = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmtProfile->execute([$eventId]);
$eventProfile = $stmtProfile->fetch(PDO::FETCH_ASSOC);

// Sponsor
$stmtFooter = $pdo->prepare("SELECT image_path FROM event_sponsors WHERE event_id = ?");
$stmtFooter->execute([$eventId]);
$footerSponsors = $stmtFooter->fetchAll(PDO::FETCH_COLUMN);

// Setup Variabel Header
$header_title = strtoupper($eventProfile['nama_event'] ?? 'KEJUARAAN RENANG');
$venue_name   = strtoupper($eventProfile['venue_name'] ?? $eventProfile['lokasi'] ?? '');
$event_date   = $eventProfile['event_start_date'] ?? date('Y-m-d');
$display_date = strtoupper(date('d F Y', strtotime($event_date)));

// Logo
$logo_left  = !empty($eventProfile['logo_left']) ? '../../../public/' . $eventProfile['logo_left'] : null;
$logo_right = !empty($eventProfile['logo_right']) ? '../../../public/' . $eventProfile['logo_right'] : null;

// 3. AMBIL PARAMETER FILTER
$mode = $_GET['mode'] ?? 'team'; // 'team' atau 'athlete'
$filter_gender = $_GET['gender'] ?? 'all';
$filter_year   = $_GET['year'] ?? 'all'; 

// 4. LOGIC QUERY UTAMA
$tally = [];
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
            JOIN event_numbers en ON ee.category_id = en.id 
            LEFT JOIN users u ON ee.user_id = u.id
            WHERE ee.final_rank IN (1, 2, 3) 
            AND en.organizer_id = ? 
            GROUP BY name
            ORDER BY gold DESC, silver DESC, bronze DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$uid]);
    $tally = $stmt->fetchAll(PDO::FETCH_ASSOC);

} else {
    // --- MODE PERENANG TERBAIK ---
    $title_main = "PERENANG TERBAIK";
    
    $lbl_gender = ($filter_gender == 'all') ? 'PUTRA & PUTRI' : ($filter_gender == 'L' ? 'PUTRA' : 'PUTRI');
    $lbl_year   = ($filter_year == 'all') ? 'SEMUA UMUR' : "KELAHIRAN $filter_year";
    $title_sub  = "$lbl_year - $lbl_gender";

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
            JOIN event_numbers en ON ee.category_id = en.id
            LEFT JOIN users u ON ee.user_id = u.id
            WHERE ee.final_rank IN (1, 2, 3)
            AND en.organizer_id = ?";
    
    $params = [$uid];

    if ($filter_gender !== 'all') {
        $sql .= " AND s.jenis_kelamin = ?";
        $params[] = $filter_gender;
    }
    if ($filter_year !== 'all') {
        $sql .= " AND YEAR(s.tanggal_lahir) = ?";
        $params[] = $filter_year;
    }

    $sql .= " GROUP BY s.id ORDER BY gold DESC, silver DESC, bronze DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tally = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// INCLUDES LAYOUT
include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700;900&family=Courier+Prime:wght@400;700&display=swap');
    
    .font-condensed { font-family: 'Roboto Condensed', sans-serif; }
    .font-mono { font-family: 'Courier Prime', monospace; }

    /* LAYOUT SCREEN */
    .paper-sheet {
        width: 210mm; 
        min-height: 297mm; 
        background: white; 
        margin: 0 auto;
        padding: 5mm 10mm 35mm 10mm; 
        color: #000; 
        font-family: 'Roboto Condensed', sans-serif;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        position: relative; 
    }

    /* HEADER */
    .page-header {
        padding: 5px 0 10px 0; border-bottom: 3px double #000; margin-bottom: 20px;
        display: flex; justify-content: space-between; align-items: center; width: 100%;
    }
    .logo-box { width: 70px; height: 70px; display: flex; align-items: center; justify-content: center; }
    .logo-box img { max-height: 100%; max-width: 100%; object-fit: contain; }

    /* TABEL HASIL */
    .result-table { width: 100%; border-collapse: collapse; font-size: 9pt; margin-top: 10px; }
    .result-table th { 
        background: #f0f0f0; border: 1px solid #000; 
        padding: 6px; text-transform: uppercase; font-weight: bold;
    }
    .result-table td { 
        border: 1px solid #000; padding: 5px 8px; vertical-align: middle; 
    }
    
    /* Warna Medali */
    .bg-gold { background-color: #fff9c4 !important; }
    .bg-silver { background-color: #f5f5f5 !important; }
    .bg-bronze { background-color: #ffccbc !important; }
    .bg-total { background-color: #e0f2f1 !important; }

    /* FOOTER SPONSOR */
    .footer-sponsor {
        position: absolute; bottom: 0; left: 0; width: 100%; 
        padding-bottom: 5mm; display: flex; flex-direction: column; align-items: center; justify-content: flex-end;
        z-index: 10; pointer-events: none;
    }
    .sponsor-line-separator { width: 90%; border-top: 3px double #000; margin-bottom: 5px; margin-left: auto; margin-right: auto; }
    .sponsor-logo-container { display: flex; justify-content: center; align-items: center; gap: 15px; width: 100%; padding: 0 10px; }
    .sponsor-logo-container img { height: 40px; width: auto; object-fit: contain; filter: grayscale(100%); opacity: 0.9; }

    /* === KHUSUS PRINT === */
    @media print {
        @page { size: A4; margin: 5mm 5mm 10mm 5mm; }
        
        nav, aside, header, .sidebar, .no-print, .fixed, .navbar, .topbar, .sticky, #sidebar { display: none !important; }
        body, html { margin: 0 !important; padding: 0 !important; background: white !important; width: 100%; height: 100%; }

        #print-wrapper {
            margin: 0 !important; padding: 0 !important; width: 100% !important; max-width: 100% !important;
            position: static !important; background: white !important; border: none !important; display: block !important;
        }

        .paper-sheet { 
            width: 100% !important; margin: 0 !important; padding: 0 0 25mm 0 !important; 
            box-shadow: none !important; min-height: auto;
        }

        .footer-sponsor { 
            position: fixed; bottom: 0; left: 0; right: 0;
            padding-bottom: 2mm; background: white; 
        }
        
        /* Pastikan background warna tabel tercetak */
        .result-table th, .result-table td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>

<div id="print-wrapper" class="p-4 sm:ml-64 pt-24 min-h-screen bg-slate-100 text-slate-900 font-sans">
    
    <div class="max-w-[210mm] mx-auto mb-6 space-y-4 no-print">
        <div class="bg-white p-4 rounded-xl shadow border border-slate-200 flex flex-col md:flex-row justify-between items-center gap-4">
            <div>
                <h1 class="font-bold text-lg text-slate-800">REKAPITULASI MEDALI</h1>
                <p class="text-xs text-slate-500">Pilih mode tampilan:</p>
            </div>
            <div class="flex gap-2 bg-slate-100 p-1 rounded-lg">
                <a href="?mode=team" class="<?= $mode=='team'?'bg-white shadow text-blue-700':'text-gray-500 hover:text-gray-700' ?> px-4 py-1.5 rounded text-xs font-bold uppercase transition">
                    🏆 Juara Umum (Tim)
                </a>
                <a href="?mode=athlete" class="<?= $mode=='athlete'?'bg-white shadow text-blue-700':'text-gray-500 hover:text-gray-700' ?> px-4 py-1.5 rounded text-xs font-bold uppercase transition">
                    🏊 Perenang Terbaik
                </a>
            </div>
        </div>

        <?php if($mode == 'athlete'): ?>
        <div class="bg-white p-4 rounded-xl shadow border border-blue-100">
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <input type="hidden" name="mode" value="athlete">
                
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-bold text-slate-400 uppercase">Tahun Lahir</label>
                    <select name="year" class="border border-slate-300 rounded px-3 py-2 text-xs font-bold bg-slate-50">
                        <option value="all">SEMUA TAHUN</option>
                        <?php 
                            $thn_skrg = date('Y');
                            for($y = $thn_skrg; $y >= $thn_skrg - 20; $y--): 
                        ?>
                            <option value="<?= $y ?>" <?= $filter_year == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-bold text-slate-400 uppercase">Gender</label>
                    <select name="gender" class="border border-slate-300 rounded px-3 py-2 text-xs font-bold bg-slate-50">
                        <option value="all">SEMUA</option>
                        <option value="L" <?= $filter_gender=='L'?'selected':'' ?>>PUTRA</option>
                        <option value="P" <?= $filter_gender=='P'?'selected':'' ?>>PUTRI</option>
                    </select>
                </div>

                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded text-xs font-bold uppercase shadow">
                    🔍 Filter
                </button>
            </form>
        </div>
        <?php endif; ?>

        <div class="flex justify-end">
            <button onclick="window.print()" class="bg-slate-900 hover:bg-slate-800 text-white px-6 py-2 rounded text-xs font-bold uppercase shadow flex items-center gap-2">
                🖨️ Cetak Halaman Ini
            </button>
        </div>
    </div>

    <div class="paper-sheet">
        
        <div class="page-header">
            <div class="logo-box"><?php if($logo_left): ?><img src="<?= $logo_left ?>"><?php endif; ?></div>
            <div class="text-center flex-1 px-4">
                <h1 class="text-xl font-black uppercase leading-tight"><?= htmlspecialchars($header_title) ?></h1>
                <?php if($venue_name): ?><p class="text-[9pt] font-bold uppercase text-gray-800 mt-1"><?= htmlspecialchars($venue_name) ?></p><?php endif; ?>
                <p class="text-[8pt] font-bold uppercase text-gray-500 mt-1"><?= htmlspecialchars($display_date) ?></p>
                
                <div class="inline-block border-2 border-black px-6 py-1 mt-2">
                    <p class="text-xl font-black uppercase tracking-[0.1em] leading-none"><?= $title_main ?></p>
                </div>
            </div>
            <div class="logo-box"><?php if($logo_right): ?><img src="<?= $logo_right ?>"><?php endif; ?></div>
        </div>

        <div class="mb-4 text-center">
             <span class="text-sm font-bold bg-black text-white px-4 py-1 rounded uppercase shadow-sm">
                <?= $title_sub ?>
             </span>
        </div>

        <?php if(empty($tally)): ?>
            <div class="py-12 text-center border-2 border-dashed border-gray-300 rounded-lg mt-6">
                <p class="text-gray-400 font-bold italic">Belum ada data perolehan medali.</p>
            </div>
        <?php else: ?>
            <table class="result-table">
                <thead>
                    <tr>
                        <th class="w-12 text-center">RANK</th>
                        <th class="text-center">NAMA / IDENTITAS</th>
                        <?php if($mode=='athlete'): ?><th class="text-left">DETAIL</th><?php endif; ?>
                        <th class="text-center">TIM / SEKOLAH</th>
                        <th class="w-18 bg-gold text-center">EMAS</th>
                        <th class="w-18 bg-silver text-center">PERAK</th>
                        <th class="w-18 bg-bronze text-center">PRG</th>
                        <th class="w-18 bg-total text-center">TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rank=1; 
                    foreach($tally as $row): 
                    ?>
                    <tr>
                        <td class="text-center font-bold text-lg"><?= $rank++ ?></td>
                        
                        <td class="font-bold">
                            <?= strtoupper($row['name'] ?? $row['nama_atlet']) ?>
                        </td>

                        <?php if($mode=='athlete'): ?>
                        <td class="text-xs text-gray-600 font-mono">
                            <?= $row['jenis_kelamin'] ?> | <?= date('Y', strtotime($row['tanggal_lahir'])) ?>
                        </td>
                        <?php endif; ?>

                        <td class="text-[9pt] font-bold text-gray-700">
                            <?= strtoupper($row['team'] ?? '-') ?>
                        </td>

                        <td class="text-center font-mono font-bold text-lg bg-gold"><?= $row['gold'] ?></td>
                        <td class="text-center font-mono font-bold text-lg bg-silver"><?= $row['silver'] ?></td>
                        <td class="text-center font-mono font-bold text-lg bg-bronze"><?= $row['bronze'] ?></td>
                        <td class="text-center font-mono font-black text-lg bg-total"><?= $row['total_medals'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if(!empty($footerSponsors)): ?>
        <div class="footer-sponsor">
            <div class="sponsor-line-separator"></div>
            <div class="sponsor-logo-container">
                <?php foreach($footerSponsors as $img): ?>
                    <img src="../../../public/<?= $img ?>">
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>