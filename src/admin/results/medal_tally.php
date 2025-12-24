<?php
// src/admin/results/medal_tally.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// 1. CEK KEAMANAN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

// 2. AMBIL PARAMETER FILTER
$uid = $_SESSION['user_id'];
$mode = $_GET['mode'] ?? 'team'; // 'team' atau 'athlete'
$filter_gender = $_GET['gender'] ?? 'all';
$filter_year   = $_GET['year'] ?? 'all'; // GANTI KU JADI YEAR

// 3. PROFIL EVENT (Untuk Kop Surat)
$stmtProfile = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtProfile->execute([$uid]);
$profile = $stmtProfile->fetch();

$header_title = $profile['nama_lengkap'] ?? 'KEJUARAAN RENANG';
$tgl_event    = date('d F Y', strtotime($profile['event_start_date']));

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
            LEFT JOIN users u ON ee.user_id = u.id
            WHERE ee.final_rank IN (1, 2, 3)
            GROUP BY name
            ORDER BY gold DESC, silver DESC, bronze DESC";
            
    $tally = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

} else {
    // --- MODE PERENANG TERBAIK (FILTER TAHUN & GENDER) ---
    $title_main = "PERENANG TERBAIK";
    
    // Label Sub-Judul Laporan
    $lbl_gender = ($filter_gender == 'all') ? 'PUTRA & PUTRI' : ($filter_gender == 'L' ? 'PUTRA' : 'PUTRI');
    $lbl_year   = ($filter_year == 'all') ? 'SEMUA UMUR' : "KELAHIRAN TAHUN $filter_year";
    $title_sub  = "$lbl_year - $lbl_gender";

    // Query Builder
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
            WHERE ee.final_rank IN (1, 2, 3)";
    
    $params = [];

    // Filter 1: Gender
    if ($filter_gender !== 'all') {
        $sql .= " AND s.jenis_kelamin = ?";
        $params[] = $filter_gender;
    }

    // Filter 2: TAHUN KELAHIRAN (Pengganti KU)
    if ($filter_year !== 'all') {
        // Fungsi YEAR() mengambil tahun dari kolom tanggal_lahir (contoh: '2015-05-20' -> '2015')
        $sql .= " AND YEAR(s.tanggal_lahir) = ?";
        $params[] = $filter_year;
    }

    $sql .= " GROUP BY s.id ORDER BY gold DESC, silver DESC, bronze DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tally = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Medal Tally - <?= $title_main ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&family=Courier+Prime:wght@400;700&display=swap" rel="stylesheet">
    <style>
        /* CSS KHUSUS CETAK */
        @media print {
            body { background: white; padding: 0; }
            .no-print { display: none !important; }
            .paper-sheet { box-shadow: none; margin: 0; width: 100%; border: none; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
        
        body { background: #525659; font-family: 'Roboto Condensed', sans-serif; display: flex; flex-direction: column; align-items: center; padding: 20px; }
        .paper-sheet { width: 210mm; min-height: 297mm; background: white; padding: 10mm; box-shadow: 0 0 10px rgba(0,0,0,0.5); }
        
        /* TABEL STYLE */
        .result-table { width: 100%; border-collapse: collapse; font-size: 10pt; margin-top: 10px; }
        .result-table th { background: #f0f0f0; border: 1px solid #000; padding: 5px; text-transform: uppercase; }
        .result-table td { border: 1px solid #ddd; padding: 4px 8px; vertical-align: middle; }
        
        .bg-gold { background-color: #fffbe6; }
        .bg-silver { background-color: #f4f4f5; }
        .bg-bronze { background-color: #fff1e6; }
        .font-mono { font-family: 'Courier Prime', monospace; }
    </style>
</head>
<body>

    <div class="no-print w-[210mm] mb-6 space-y-4">
        
        <div class="bg-white p-4 rounded-xl shadow flex justify-between items-center">
            <h1 class="font-bold text-lg text-slate-800">REKAP MEDALI</h1>
            <div class="flex gap-2 bg-slate-100 p-1 rounded-lg">
                <a href="?mode=team" class="<?= $mode=='team'?'bg-white shadow text-black':'text-gray-500' ?> px-4 py-1 rounded text-xs font-bold uppercase">Tim / Klub</a>
                <a href="?mode=athlete" class="<?= $mode=='athlete'?'bg-white shadow text-black':'text-gray-500' ?> px-4 py-1 rounded text-xs font-bold uppercase">Perenang Terbaik</a>
            </div>
        </div>

        <?php if($mode == 'athlete'): ?>
        <div class="bg-white p-4 rounded-xl shadow border border-blue-200">
            <form method="GET" class="flex items-end gap-4">
                <input type="hidden" name="mode" value="athlete">
                
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-bold text-slate-400 uppercase">Tahun Lahir</label>
                    <select name="year" class="border border-slate-300 rounded px-3 py-2 text-sm font-bold">
                        <option value="all">SEMUA TAHUN</option>
                        <?php 
                            $thn_skrg = date('Y');
                            // Loop dari tahun sekarang mundur 20 tahun
                            for($y = $thn_skrg; $y >= $thn_skrg - 20; $y--): 
                        ?>
                            <option value="<?= $y ?>" <?= $filter_year == $y ? 'selected' : '' ?>>
                                <?= $y ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-bold text-slate-400 uppercase">Jenis Kelamin</label>
                    <select name="gender" class="border border-slate-300 rounded px-3 py-2 text-sm font-bold">
                        <option value="all">SEMUA</option>
                        <option value="L" <?= $filter_gender=='L'?'selected':'' ?>>PUTRA</option>
                        <option value="P" <?= $filter_gender=='P'?'selected':'' ?>>PUTRI</option>
                    </select>
                </div>

                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded text-xs font-bold uppercase mb-[1px]">
                    Terapkan Filter
                </button>
                
                <button type="button" onclick="window.print()" class="ml-auto bg-slate-800 text-white px-6 py-2 rounded text-xs font-bold uppercase mb-[1px]">
                    Cetak PDF
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <div class="paper-sheet">
        
        <div class="text-center border-b-4 border-double border-black pb-4 mb-6">
            <h1 class="text-2xl font-black uppercase"><?= $header_title ?></h1>
            <p class="text-sm font-bold uppercase text-gray-500"><?= $tgl_event ?></p>
        </div>

        <div class="flex justify-between items-end mb-2 border-b-2 border-black pb-2">
            <h2 class="text-xl font-black uppercase tracking-tight"><?= $title_main ?></h2>
            <span class="text-sm font-bold bg-black text-white px-3 py-1 rounded uppercase"><?= $title_sub ?></span>
        </div>

        <?php if(empty($tally)): ?>
            <div class="py-10 text-center text-gray-400 italic font-bold">
                Tidak ada data medali untuk filter Tahun/Gender ini.
            </div>
        <?php else: ?>
            <table class="result-table">
                <thead>
                    <tr>
                        <th class="w-12 text-center">RANK</th>
                        <th class="text-left">NAMA</th>
                        <?php if($mode=='athlete'): ?><th class="text-left">DETAIL</th><?php endif; ?>
                        <th class="text-left">TIM / SEKOLAH</th>
                        <th class="w-16 bg-gold text-center">EMAS</th>
                        <th class="w-16 bg-silver text-center">PERAK</th>
                        <th class="w-16 bg-bronze text-center">PRG</th>
                        <th class="w-16 bg-gray-100 text-center">TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $rank=1; foreach($tally as $row): ?>
                    <tr>
                        <td class="text-center font-bold"><?= $rank++ ?></td>
                        
                        <td class="font-bold">
                            <?= strtoupper($row['name'] ?? $row['nama_atlet']) ?>
                        </td>

                        <?php if($mode=='athlete'): ?>
                        <td class="text-xs text-gray-600">
                            <?= $row['jenis_kelamin'] ?> | Lahir: <?= date('Y', strtotime($row['tanggal_lahir'])) ?>
                        </td>
                        <?php endif; ?>

                        <td class="text-sm text-gray-700">
                            <?= strtoupper($row['team'] ?? '-') ?>
                        </td>

                        <td class="text-center font-mono font-bold bg-gold"><?= $row['gold'] ?></td>
                        <td class="text-center font-mono font-bold bg-silver"><?= $row['silver'] ?></td>
                        <td class="text-center font-mono font-bold bg-bronze"><?= $row['bronze'] ?></td>
                        <td class="text-center font-mono font-black bg-gray-100"><?= $row['total_medals'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="mt-12 flex justify-end px-10">
            <div class="text-center w-48">
                <p class="text-[10px] font-bold uppercase text-gray-500 mb-16">Ketua Panitia</p>
                <div class="border-b border-black"></div>
            </div>
        </div>

    </div>

</body>
</html>