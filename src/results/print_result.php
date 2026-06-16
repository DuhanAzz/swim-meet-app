<?php
// src/results/print_result.php
require_once __DIR__ . '/../config/database.php';

$event_id = $_GET['event_id'] ?? 0;

// 1. AMBIL INFO EVENT
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event) die("Event tidak ditemukan.");

// 2. AMBIL HASIL - YANG SAH (Ranked)
// Kita urutkan berdasarkan Rank yang sudah dihitung sebelumnya
$stmtOk = $pdo->prepare("
    SELECT he.*, s.nama_atlet, c.nama_klub 
    FROM heat_entries he
    JOIN swimmers s ON he.swimmer_id = s.id
    LEFT JOIN clubs c ON s.club_id = c.id
    JOIN heats h ON he.heat_id = h.id
    WHERE h.event_id = ? AND he.status = 'OK' AND he.rank IS NOT NULL
    ORDER BY he.rank ASC
");
$stmtOk->execute([$event_id]);
$resultsOK = $stmtOk->fetchAll();

// 3. AMBIL HASIL - YANG GAGAL (DQ, DNS, DNF)
$stmtFail = $pdo->prepare("
    SELECT he.*, s.nama_atlet, c.nama_klub 
    FROM heat_entries he
    JOIN swimmers s ON he.swimmer_id = s.id
    LEFT JOIN clubs c ON s.club_id = c.id
    JOIN heats h ON he.heat_id = h.id
    WHERE h.event_id = ? AND he.status != 'OK'
    ORDER BY he.status ASC
");
$stmtFail->execute([$event_id]);
$resultsFail = $stmtFail->fetchAll();

// Format Tanggal Indonesia
$tanggal = date('d F Y', strtotime($event['jadwal'] ?? date('Y-m-d')));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Hasil - <?= htmlspecialchars($event['nama_event']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;700&family=Inter:wght@400;700;900&display=swap');
        body { font-family: 'Inter', sans-serif; background: #fff; color: #000; }
        .font-mono { font-family: 'Roboto Mono', monospace; }
        
        /* CSS KHUSUS PRINT A4 */
        @media print {
            @page { size: A4; margin: 1cm; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
            table { font-size: 12px; width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #000; padding: 4px 8px; }
            th { background-color: #f0f0f0 !important; }
            .page-break { page-break-before: always; }
        }
    </style>
</head>
<body class="p-8 max-w-[21cm] mx-auto">

    <div class="fixed top-5 right-5 no-print flex gap-2">
        <a href="input_result.php?event_id=<?= $event_id ?>" class="bg-gray-500 text-white px-4 py-2 rounded font-bold hover:bg-gray-600">Kembali</a>
        <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded font-bold hover:bg-blue-700 shadow-lg">🖨️ Cetak Hasil</button>
    </div>

    <div class="text-center border-b-2 border-black pb-4 mb-6">
        <h1 class="text-2xl font-black uppercase tracking-widest"><?= htmlspecialchars($event['nama_event']) ?></h1>
        <p class="text-sm font-bold uppercase mt-1">Official Result</p>
    </div>

    <div class="flex justify-between items-end mb-4 text-sm font-bold border p-2 bg-gray-50">
        <div>
            <div class="text-gray-500 text-xs uppercase">Nomor Lomba</div>
            <div class="text-lg"><?= $event['jarak'] ?>m <?= $event['gaya'] ?></div>
        </div>
        <div class="text-right">
            <div class="text-gray-500 text-xs uppercase">Tanggal</div>
            <div><?= $tanggal ?></div>
        </div>
    </div>

    <table class="w-full text-left border border-black mb-6">
        <thead>
            <tr class="bg-gray-200 uppercase text-xs tracking-wider">
                <th class="border border-black px-3 py-2 text-center w-12">Rank</th>
                <th class="border border-black px-3 py-2">Nama Atlet</th>
                <th class="border border-black px-3 py-2">Klub / Sekolah</th>
                <th class="border border-black px-3 py-2 text-right w-32">Waktu</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($resultsOK)): ?>
                <tr><td colspan="4" class="text-center py-4 italic">Belum ada hasil sah.</td></tr>
            <?php else: ?>
                <?php foreach($resultsOK as $row): ?>
                <tr>
                    <td class="border border-black px-3 py-1 text-center font-bold"><?= $row['rank'] ?></td>
                    <td class="border border-black px-3 py-1 font-bold"><?= htmlspecialchars($row['nama_atlet']) ?></td>
                    <td class="border border-black px-3 py-1 text-sm"><?= htmlspecialchars($row['nama_klub'] ?? '-') ?></td>
                    <td class="border border-black px-3 py-1 text-right font-mono font-bold"><?= $row['final_time'] ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if(!empty($resultsFail)): ?>
        <h3 class="text-sm font-bold uppercase mb-2 mt-6 border-b border-black inline-block">Tidak Sah / Absen</h3>
        <table class="w-full text-left border border-black text-gray-600">
             <thead>
                <tr class="bg-gray-100 uppercase text-xs">
                    <th class="border border-black px-3 py-2 w-12 text-center">STS</th>
                    <th class="border border-black px-3 py-2">Nama Atlet</th>
                    <th class="border border-black px-3 py-2">Klub</th>
                    <th class="border border-black px-3 py-2 text-right w-32">Waktu</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($resultsFail as $row): ?>
                <tr>
                    <td class="border border-black px-3 py-1 text-center font-bold text-red-600"><?= $row['status'] ?></td>
                    <td class="border border-black px-3 py-1 italic"><?= htmlspecialchars($row['nama_atlet']) ?></td>
                    <td class="border border-black px-3 py-1 text-sm italic"><?= htmlspecialchars($row['nama_klub'] ?? '-') ?></td>
                    <td class="border border-black px-3 py-1 text-right font-mono text-sm">-</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="mt-12 grid grid-cols-2 gap-20 page-break-inside-avoid">
        <div class="text-center">
            <p class="text-xs uppercase font-bold mb-16">Mengetahui,<br>Referee (Wasit Utama)</p>
            <div class="border-b border-black w-2/3 mx-auto"></div>
        </div>
        <div class="text-center">
            <p class="text-xs uppercase font-bold mb-16">Dicetak Oleh,<br>Chief Recorder</p>
            <div class="border-b border-black w-2/3 mx-auto"></div>
            <p class="text-[10px] mt-1 text-gray-500">Waktu Cetak: <?= date('d/m/Y H:i') ?></p>
        </div>
    </div>

</body>
</html>