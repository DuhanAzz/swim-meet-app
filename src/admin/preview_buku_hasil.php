<?php
session_start();
require_once __DIR__ . '/../../src/config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') die("Akses Ditolak.");
$user_id = $_SESSION['user_id'];

// 1. AMBIL SEMUA EVENT
$stmt = $pdo->prepare("SELECT * FROM events WHERE user_id = ? ORDER BY tanggal_lomba ASC, jam_lomba ASC");
$stmt->execute([$user_id]);
$events = $stmt->fetchAll();

// Fungsi Helper Ambil Hasil per Event
function getResults($pdo, $event_id) {
    $stmt = $pdo->prepare("SELECT 
        he.lane_number, he.final_time, he.rank, he.status,
        s.nama_atlet, c.nama_klub 
        FROM heat_entries he
        JOIN heats h ON he.heat_id = h.id
        JOIN swimmers s ON he.swimmer_id = s.id
        JOIN clubs c ON s.club_id = c.id
        WHERE h.event_id = ? AND (he.final_time IS NOT NULL OR he.status != 'OK') -- Hanya yg sudah ada hasil/status
        ORDER BY 
            CASE WHEN he.rank IS NULL THEN 1 ELSE 0 END, -- Utamakan yang punya ranking
            he.rank ASC, 
            he.final_time ASC");
    $stmt->execute([$event_id]);
    return $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Preview Buku Hasil</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: #525659; font-family: 'Arial', sans-serif; }
        
        /* Kertas A4 */
        .page {
            background: white;
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            padding: 15mm;
            box-shadow: 0 0 15px rgba(0,0,0,0.5);
            position: relative;
        }

        /* Styling Tabel */
        .event-header { 
            border-bottom: 2px solid #000; 
            margin-bottom: 10px; 
            padding-bottom: 5px; 
            margin-top: 25px;
            display: flex; justify-content: space-between; align-items: flex-end;
        }
        table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 5px; }
        th, td { border-bottom: 1px solid #ddd; padding: 4px 6px; text-align: left; }
        th { border-top: 1px solid #000; border-bottom: 2px solid #000; font-weight: bold; background: #f9f9f9; }
        
        .rank-col { text-align: center; font-weight: bold; width: 40px; }
        .time-col { text-align: right; font-family: 'Courier New', monospace; font-weight: bold; width: 100px; }
        .status-dq { color: red; font-style: italic; font-weight: bold; }

        /* Toolbar */
        .toolbar {
            position: fixed; top: 0; left: 0; width: 100%;
            background: #2d2d2d; color: white; padding: 12px;
            display: flex; justify-content: center; gap: 15px;
            z-index: 1000; box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        /* Mode Cetak */
        @media print {
            body { background: white; margin: 0; }
            .toolbar { display: none; }
            .page { width: 100%; margin: 0; box-shadow: none; padding: 0; min-height: auto; }
            .break-avoid { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

    <div class="toolbar">
        <div class="flex items-center gap-4">
            <span class="font-bold text-sm text-gray-400 tracking-wider">PREVIEW RESULT BOOK</span>
            <button onclick="window.close()" class="bg-gray-600 hover:bg-gray-500 px-4 py-2 rounded text-xs font-bold transition">
                Tutup
            </button>
            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-500 px-6 py-2 rounded text-xs font-bold shadow-lg flex items-center gap-2 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Cetak Hasil Lengkap (PDF)
            </button>
        </div>
    </div>

    <div class="page">
        
        <div class="text-center mb-10 pb-5 border-b-4 border-black">
            <h1 class="text-3xl font-black uppercase tracking-tight">HASIL LENGKAP LOMBA</h1>
            <h2 class="text-xl mt-2 font-bold text-gray-700"><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></h2>
            <p class="text-xs text-gray-500 mt-1">Generated: <?= date('d F Y, H:i') ?> WIB</p>
        </div>

        <?php 
        $count = 0;
        foreach($events as $e): 
            $results = getResults($pdo, $e['id']);
            if(empty($results)) continue; // Lewati event yang belum ada hasilnya
            $count++;
        ?>
            <div class="break-avoid mb-8">
                <div class="event-header">
                    <div>
                        <span class="font-bold text-lg">#<?= $count ?></span> 
                        <span class="font-black uppercase text-lg ml-2"><?= $e['nama_event'] ?></span>
                    </div>
                    <div class="text-right text-xs text-gray-600 font-bold">
                        <?= $e['jarak'] ?>M <?= $e['gaya'] ?> • <?= $e['jenis_kelamin']=='L'?'PUTRA':'PUTRI' ?>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th class="rank-col">Rank</th>
                            <th>Nama Atlet</th>
                            <th>Klub / Sekolah</th>
                            <th class="time-col">Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($results as $r): ?>
                        <tr>
                            <td class="rank-col">
                                <?php 
                                    if ($r['status'] !== 'OK') echo '-';
                                    elseif ($r['rank']) echo $r['rank'];
                                    else echo '';
                                ?>
                            </td>
                            <td>
                                <b><?= htmlspecialchars($r['nama_atlet']) ?></b>
                            </td>
                            <td><?= htmlspecialchars($r['nama_klub']) ?></td>
                            <td class="time-col">
                                <?php 
                                    if ($r['status'] == 'OK') echo $r['final_time'];
                                    else echo "<span class='status-dq'>".$r['status']."</span>";
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>

        <?php if($count == 0): ?>
            <div class="text-center py-20 text-gray-400">
                <p class="text-xl font-bold">Belum ada hasil lomba yang diinput.</p>
                <p class="text-sm">Silakan input waktu di menu "Input Result" terlebih dahulu.</p>
            </div>
        <?php endif; ?>

        <div class="absolute bottom-5 left-0 w-full text-center text-[9px] text-gray-400 border-t pt-2 mx-10">
            © Sports Entry Tech System - Official Result Book
        </div>

    </div>

</body>
</html>
