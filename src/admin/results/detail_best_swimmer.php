<?php
// FILE: src/admin/results/detail_best_swimmer.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';
require_once __DIR__ . '/calculate_best_swimmer.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'master'])) {
    header("Location: /swim-meet/public/login.php"); exit;
}

$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$swimmer_ids_raw = isset($_GET['swimmers']) ? explode(',', $_GET['swimmers']) : [];
$swimmer_ids = array_filter(array_map('intval', $swimmer_ids_raw));

if (empty($swimmer_ids)) die("Tidak ada atlet yang dipilih.");

$eventName = $pdo->query("SELECT event_name FROM events WHERE id = $event_id")->fetchColumn();
$comparisonData = [];

foreach ($swimmer_ids as $sid) {
    $stmtAt = $pdo->prepare("SELECT id, nama_atlet, jenis_kelamin FROM swimmers WHERE id = ?");
    $stmtAt->execute([$sid]);
    $atlet = $stmtAt->fetch(PDO::FETCH_ASSOC);
    if (!$atlet) continue;

   // Query hanya mengambil nomor lomba yang dapat medali (Rank 1, 2, 3)
    $sqlRaces = "SELECT en.distance, en.stroke, en.jenis_kelamin, en.age_group, es.time_final, es.rank_final
                 FROM event_entries ee
                 JOIN event_numbers en ON ee.category_id = en.id
                 JOIN event_seeding es ON ee.id = es.entry_id
                 WHERE en.event_id = ? 
                   AND ee.swimmer_id = ? 
                   AND es.rank_final IN (1, 2, 3) 
                   AND es.is_dq_final = 0 
                   AND es.time_final IS NOT NULL";
    $stmtRaces = $pdo->prepare($sqlRaces);
    $stmtRaces->execute([$event_id, $sid]);
    $races = $stmtRaces->fetchAll(PDO::FETCH_ASSOC);

    $detailData = [];
    $bestPct = 0; // Variabel untuk menyimpan persentase tertinggi

    foreach ($races as $race) {
        $stmtRec = $pdo->prepare("SELECT record_time, holder_name, age_group FROM master_records 
                                  WHERE record_type = 'rekornas' AND distance = ? 
                                    AND LOWER(REPLACE(stroke, ' ', '')) = LOWER(REPLACE(?, ' ', '')) 
                                    AND jenis_kelamin = ? AND LOWER(REPLACE(age_group, ' ', '')) = LOWER(REPLACE(?, ' ', '')) 
                                  ORDER BY record_time_ms ASC LIMIT 1");
        $stmtRec->execute([$race['distance'], $race['stroke'], $race['jenis_kelamin'], $race['age_group']]);
        $rec = $stmtRec->fetch(PDO::FETCH_ASSOC);

        if (!$rec) {
            $stmtRecFallback = $pdo->prepare("SELECT record_time, holder_name, age_group FROM master_records 
                                      WHERE record_type = 'rekornas' AND distance = ? 
                                        AND LOWER(REPLACE(stroke, ' ', '')) = LOWER(REPLACE(?, ' ', '')) 
                                        AND jenis_kelamin = ? 
                                      ORDER BY record_time_ms ASC LIMIT 1");
            $stmtRecFallback->execute([$race['distance'], $race['stroke'], $race['jenis_kelamin']]);
            $rec = $stmtRecFallback->fetch(PDO::FETCH_ASSOC);
        }

        $recTime = $rec ? $rec['record_time'] : '-';
        $recHolder = $rec ? $rec['holder_name'] : 'TIDAK TERSEDIA';
        $recKU = $rec ? ' ('.$rec['age_group'].')' : '';
        $recSec = $rec ? timeToSeconds($recTime) : 0;
        $athSec = timeToSeconds($race['time_final']);
        
        $pct = 0;
        if ($recSec > 0 && $athSec > 0) {
            $pct = ($recSec / $athSec) * 100;
            // REVISI LOGIKA: Ambil yang tertinggi
            if ($pct > $bestPct) {
                $bestPct = $pct;
            }
        }

        // Simpan semua data termasuk rank_final untuk medali
        $detailData[] = [
            'acara' => $race['distance'] . 'M ' . strtoupper($race['stroke']),
            'rank_final' => $race['rank_final'], // Data ranking medali
            'holder' => $recHolder . $recKU,
            'rec_time' => $recTime,
            'rec_sec' => $recSec > 0 ? number_format($recSec, 2) : '-',
            'ath_time' => $race['time_final'],
            'ath_sec' => number_format($athSec, 2),
            'pct_raw' => $pct, 
            'pct' => $recSec > 0 ? number_format($pct, 2) . '%' : '0%'
        ];
    }

    $comparisonData[] = [
        'atlet' => $atlet,
        'races' => $detailData,
        'best_pct' => $bestPct 
    ];
}

// URUTAN PERBAIKAN: Berdasarkan persentase TERBAIK (Tertinggi)
usort($comparisonData, function($a, $b) {
    return $b['best_pct'] <=> $a['best_pct'];
});

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    <div class="max-w-6xl mx-auto px-4 py-4">
        
        <div class="mb-8">
            <a href="best_swimmer.php?event_id=<?= $event_id ?>" class="text-blue-600 font-bold text-sm hover:underline flex items-center gap-2 mb-2">
                &larr; Kembali ke Leaderboard
            </a>
            <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tighter">⚖️ PERBANDINGAN TIE-BREAKER (DRAW)</h1>
            <p class="text-slate-500 text-sm mt-1">Kejuaraan: <strong><?= htmlspecialchars($eventName) ?></strong></p>
        </div>

        <?php foreach($comparisonData as $index => $data): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-8">
                
                <?php if($index === 0 && count($comparisonData) > 1): ?>
                    <div class="bg-yellow-400 text-yellow-900 px-4 py-2 text-center font-black text-xs uppercase tracking-widest border-b border-yellow-500">
                        🏆 Pemenang Peringkat Tertinggi
                    </div>
                <?php endif; ?>

                <div class="p-4 <?= $index === 0 ? 'bg-blue-900' : 'bg-slate-700' ?> text-white flex justify-between items-center">
                    <div>
                        <h2 class="font-black text-xl uppercase tracking-wide"><?= htmlspecialchars($data['atlet']['nama_atlet']) ?></h2>
                    </div>
                    <div class="text-right">
                        <span class="text-[10px] uppercase font-bold text-slate-300 tracking-widest block">Persentase Ketajaman Terbaik</span>
                        <span class="font-black text-2xl <?= $index === 0 ? 'text-yellow-400' : 'text-slate-200' ?>">
                            <?= number_format($data['best_pct'], 2) ?>%
                        </span>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-slate-600 font-bold text-xs tracking-wider uppercase border-y border-slate-200">
                                <th class="p-4 border-b border-slate-200">Acara</th>
                                <th class="p-4 border-b border-slate-200">Pemegang Rekor Nasional</th>
                                <th class="p-4 border-b border-slate-200 text-center">Waktu Rekor</th>
                                <th class="p-4 border-b border-slate-200 text-center bg-slate-100">Detik</th>
                                <th class="p-4 border-b border-slate-200 text-center text-emerald-600">Waktu Atlet</th>
                                <th class="p-4 border-b border-slate-200 text-center bg-emerald-50 text-emerald-700">Detik</th>
                                <th class="p-4 border-b border-slate-200 text-center text-amber-600">Persentase (%)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium">
                            <?php foreach($data['races'] as $row): 
                                $isBestRace = ($row['pct_raw'] == $data['best_pct'] && $data['best_pct'] > 0);
                                
                                // LOGIKA LENCANA MEDALI
                                $medalBadge = '';
                                if ($row['rank_final'] == 1) {
                                    $medalBadge = '<span class="ml-2 text-[10px] bg-yellow-100 border border-yellow-400 text-yellow-800 px-2 py-0.5 rounded-md uppercase font-bold tracking-wider">Emas 🥇</span>';
                                } elseif ($row['rank_final'] == 2) {
                                    $medalBadge = '<span class="ml-2 text-[10px] bg-slate-100 border border-slate-400 text-slate-700 px-2 py-0.5 rounded-md uppercase font-bold tracking-wider">Perak 🥈</span>';
                                } elseif ($row['rank_final'] == 3) {
                                    $medalBadge = '<span class="ml-2 text-[10px] bg-orange-100 border border-orange-400 text-orange-800 px-2 py-0.5 rounded-md uppercase font-bold tracking-wider">Perunggu 🥉</span>';
                                }
                            ?>
                                <tr class="transition <?= $isBestRace ? 'bg-indigo-50/50' : 'hover:bg-slate-50' ?>">
                                    <td class="p-4 font-bold text-slate-900">
                                        <?= $row['acara'] ?>
                                        <?= $medalBadge ?>
                                        
                                        <?php if($isBestRace): ?>
                                            <span class="ml-1 text-[9px] bg-indigo-600 text-white px-2 py-0.5 rounded-md uppercase font-bold tracking-wider shadow-sm">Penentu 🚀</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 text-slate-600 text-[11px] uppercase"><?= htmlspecialchars($row['holder']) ?></td>
                                    <td class="p-4 text-center font-mono font-bold"><?= $row['rec_time'] ?></td>
                                    <td class="p-4 text-center font-mono bg-slate-50"><?= $row['rec_sec'] ?></td>
                                    <td class="p-4 text-center font-mono font-bold text-emerald-600"><?= $row['ath_time'] ?></td>
                                    <td class="p-4 text-center font-mono bg-emerald-50 text-emerald-700"><?= $row['ath_sec'] ?></td>
                                    <td class="p-4 text-center font-black <?= $isBestRace ? 'text-indigo-600 text-lg' : 'text-slate-500 text-base' ?>">
                                        <?= $row['pct'] ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-blue-800 text-sm">
            <strong>ℹ️ Mekanisme Tie-Breaker Rekornas:</strong> Rumus yang digunakan adalah <code>(Waktu Rekor Nasional Detik / Waktu Atlet Detik) x 100</code>. Pada kasus perolehan medali yang sama (Draw), atlet dengan <strong>Persentase Tunggal TERTINGGI (dari salah satu nomor lombanya)</strong> yang paling mendekati Rekornas otomatis dinyatakan sebagai Perenang Terbaik.
        </div>

    </div>
</div>