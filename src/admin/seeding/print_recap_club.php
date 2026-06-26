<?php
// FILE: src/admin/seeding/print_recap_club.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// Cek Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Akses ditolak. Silakan login sebagai Admin.");
}

$event_id = $_GET['event_id'] ?? 0;
$club_id = $_GET['club_id'] ?? 0; // Ini adalah user_id dari klub

if (!$event_id || !$club_id) {
    die("Data tidak lengkap.");
}

// Ambil info nama event
$stmtEvt = $pdo->prepare("SELECT event_name, event_location FROM events WHERE id = ?");
$stmtEvt->execute([$event_id]);
$event = $stmtEvt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    die("Event tidak ditemukan.");
}

// Ambil info klub
$stmtClub = $pdo->prepare("SELECT nama_lengkap as nama_klub FROM users WHERE id = ?");
$stmtClub->execute([$club_id]);
$club = $stmtClub->fetch(PDO::FETCH_ASSOC);

if (!$club) {
    die("Klub tidak ditemukan.");
}

// SQL AKURAT: Menggunakan heat_prelim dan lane_prelim
$sql = "SELECT s.nama_atlet, 
               en.event_number, en.distance, en.stroke, en.jenis_kelamin, en.age_group,
               es.heat_prelim, 
               es.lane_prelim
        FROM swimmers s
        JOIN event_entries ee ON s.id = ee.swimmer_id
        JOIN event_numbers en ON ee.category_id = en.id
        JOIN event_seeding es ON ee.id = es.entry_id
        WHERE s.user_id = ? AND en.event_id = ?
        ORDER BY s.nama_atlet ASC, CAST(en.event_number AS UNSIGNED) ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$club_id, $event_id]);
$recapData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Kelompokkan data berdasarkan Nama Atlet
$groupedData = [];
foreach ($recapData as $row) {
    $groupedData[$row['nama_atlet']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recap Starting List - <?= htmlspecialchars($club['nama_klub']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        
        /* ========================================================
           PENGATURAN CETAK: MODE SUPER RAPAT & HEMAT KERTAS
           ======================================================== */
        @media print {
            @page { size: A4 portrait; margin: 0.8cm; } /* Margin kertas diperkecil */
            .no-print { display: none !important; }
            body { background: white !important; color: black !important; }
            
            /* Reset container agar full width */
            .print-card { 
                border: none !important; 
                box-shadow: none !important; 
                padding: 0 !important; 
                max-width: 100% !important; 
                margin: 0 !important; 
            }
            
            /* Header Cetak Dirapatkan */
            .header-container { border-bottom: 1px solid #000 !important; padding-bottom: 4px !important; margin-bottom: 6px !important; }
            h1 { font-size: 12pt !important; font-weight: 900 !important; color: #000 !important; margin-bottom: 2px !important; line-height: 1.1 !important; }
            .header-loc { font-size: 9pt !important; color: #333 !important; font-weight: bold !important; margin-top: 0 !important; }
            .header-label { display: none !important; } /* Sembunyikan label biru kecil saat print untuk hemat tempat */
            
            /* Tabel Cetak */
            table { width: 100% !important; border-collapse: collapse !important; margin-top: 0 !important; }
            thead tr { background-color: #000 !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            thead th { font-size: 9pt !important; padding: 4px 2px !important; font-weight: 900 !important; border: 1px solid #000 !important; text-transform: uppercase; }
            
            /* Baris Header Atlet Cetak - Rapat */
            .print-athlete-header {
                background-color: #e2e8f0 !important;
                -webkit-print-color-adjust: exact; print-color-adjust: exact;
                border: 1px solid #000 !important;
                border-top: 2px solid #000 !important; /* Pemisah tegas antar atlet */
            }
            .print-athlete-header td {
                font-size: 10pt !important;
                font-weight: 900 !important;
                color: #000 !important;
                padding: 3px 6px !important; /* Padding tipis */
            }

            /* Isi Tabel Cetak - Padat tapi tebal */
            tbody tr { page-break-inside: avoid; }
            tbody td { 
                font-size: 9pt !important; 
                font-weight: 800 !important; 
                color: #000 !important; 
                padding: 2px 4px !important; /* PADDING SUPER TIPIS */
                border: 1px solid #666 !important;
                vertical-align: middle !important;
            }
            .sub-text { font-size: 7.5pt !important; font-weight: 700 !important; color: #444 !important; display: block; margin-top: 0 !important; }
            
            /* Badge Nomor Cetak - Dirampingkan */
            .print-badge { 
                border: 1.5px solid #000 !important; 
                font-size: 9pt !important; 
                font-weight: 900 !important; 
                padding: 1px 4px !important; /* Padding kotak dikecilkan */
                border-radius: 4px !important;
                background: transparent !important;
                color: #000 !important;
                display: inline-block;
                white-space: nowrap !important;
                margin: 0 !important;
            }
            
            /* Sembunyikan Footer di Print jika kepanjangan */
            .print-footer { display: none !important; }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen p-2 sm:p-6 text-sm">

    <div class="max-w-4xl mx-auto bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-8 print-card">
        
        <div class="flex justify-between items-start border-b-2 border-slate-200 pb-4 mb-4 header-container">
            <div class="pr-2">
                <span class="header-label text-[10px] font-black tracking-widest text-fuchsia-600 uppercase block mb-1">Recap Starting List - <?= htmlspecialchars($club['nama_klub']) ?></span>
                <h1 class="text-lg sm:text-xl font-black uppercase text-slate-900 leading-tight"><?= htmlspecialchars($event['event_name']) ?></h1>
                <p class="header-loc text-xs font-bold text-slate-500 uppercase tracking-wider mt-1">
                    📍 <?= htmlspecialchars($event['event_location']) ?>
                </p>
            </div>
            <div class="no-print shrink-0">
                <button onclick="window.print()" class="bg-fuchsia-600 hover:bg-fuchsia-700 text-white text-xs font-black uppercase tracking-widest px-4 py-2 rounded-lg transition shadow-sm flex items-center gap-2 cursor-pointer">
                    🖨️ Cetak / PDF
                </button>
            </div>
        </div>

        <?php if (empty($groupedData)): ?>
            <div class="text-center py-10 border-2 border-dashed border-slate-200 rounded-2xl">
                <span class="text-4xl block mb-2">🏊‍♂️</span>
                <p class="text-sm font-black text-slate-400 uppercase tracking-widest">Belum Ada Jadwal Lintasan</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-900 text-white text-[10px] sm:text-xs font-black uppercase tracking-widest">
                            <th class="py-2 px-3 rounded-tl-lg">Detail Acara (Lomba)</th>
                            <th class="py-2 px-3 text-center w-20">No. Acara</th>
                            <th class="py-2 px-3 text-center w-20">No. Seri</th>
                            <th class="py-2 px-3 text-center w-24 rounded-tr-lg">No. Lintasan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 border-b-2 border-slate-200">
                        <?php foreach ($groupedData as $athleteName => $events): ?>
                            
                            <tr class="bg-slate-100 print-athlete-header">
                                <td colspan="4" class="py-2 px-3 text-sm font-black uppercase text-slate-900 tracking-wide">
                                    👤 <?= htmlspecialchars($athleteName) ?>
                                </td>
                            </tr>

                            <?php foreach ($events as $row): ?>
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="py-2 px-3">
                                        <div class="text-xs sm:text-sm font-extrabold text-slate-800 uppercase leading-tight">
                                            <?= htmlspecialchars($row['distance']) ?>M <?= htmlspecialchars($row['stroke']) ?>
                                        </div>
                                        <span class="sub-text text-[9px] font-bold text-slate-500 uppercase tracking-wider">
                                            <?= htmlspecialchars($row['jenis_kelamin']) ?> (<?= htmlspecialchars($row['age_group']) ?>)
                                        </span>
                                    </td>
                                    <td class="py-2 px-3 text-center font-mono text-sm font-black text-slate-700">
                                        #<?= htmlspecialchars($row['event_number']) ?>
                                    </td>
                                    <td class="py-2 px-3 text-center">
                                        <span class="print-badge bg-blue-50 text-blue-700 border-2 border-blue-200 px-2 py-0.5 rounded text-xs font-mono font-black shadow-sm">
                                            <?= htmlspecialchars($row['heat_prelim'] ?? '-') ?>
                                        </span>
                                    </td>
                                    <td class="py-2 px-3 text-center">
                                        <span class="print-badge bg-emerald-50 text-emerald-700 border-2 border-emerald-200 px-2 py-0.5 rounded text-xs font-mono font-black shadow-sm whitespace-nowrap">
                                            Lnt. <?= htmlspecialchars($row['lane_prelim'] ?? '-') ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="print-footer mt-6 pt-3 border-t border-slate-200 text-[10px] font-bold text-slate-400 text-center uppercase tracking-widest no-print">
            SET System - Admin Panel
        </div>

    </div>

</body>
</html>
