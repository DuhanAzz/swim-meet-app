<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
if (!isset($_SESSION['role'])) die("Silakan login dulu.");

$event_id = $_GET['event_id'];

// Info Event
$stmtEv = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmtEv->execute([$event_id]);
$ev = $stmtEv->fetch();

// AMBIL SEMUA DATA (Termasuk yang DQ/DNS, tapi urutkan Rank dulu, baru status)
// Logic Order: Rank ASC (Juara dulu), kemudian Status (Biar DQ/DNS ngumpul di bawah)
$sql = "SELECT heat_entries.rank, 
               heat_entries.final_time, 
               heat_entries.status,
               swimmers.nama_atlet, 
               clubs.nama_klub,
               heats.heat_number,
               heat_entries.lane_number
        FROM heat_entries
        JOIN heats ON heat_entries.heat_id = heats.id
        JOIN swimmers ON heat_entries.swimmer_id = swimmers.id
        JOIN clubs ON swimmers.club_id = clubs.id
        WHERE heats.event_id = ?
        ORDER BY 
            CASE WHEN heat_entries.rank IS NULL THEN 1 ELSE 0 END, -- Yang ada ranking di atas
            heat_entries.rank ASC, 
            heat_entries.status ASC"; -- DQ/DNS diurutkan abjad di bawah

$stmt = $pdo->prepare($sql);
$stmt->execute([$event_id]);
$results = $stmt->fetchAll();

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 mt-16">
    <div class="flex justify-between items-start mb-8">
        <div>
            <a href="javascript:history.back()" class="text-blue-600 hover:underline mb-2 block">&larr; Kembali</a>
            <h1 class="text-3xl font-extrabold text-gray-900">Hasil Resmi (Official Results)</h1>
            <div class="mt-2 text-gray-600">
                <span class="font-bold text-black"><?= htmlspecialchars($ev['nama_event']) ?></span> • 
                <?= $ev['jarak'] ?>m <?= $ev['gaya'] ?> • <?= $ev['jenis_kelamin'] ?>
            </div>
        </div>
        <button onclick="window.print()" class="bg-gray-800 text-white px-5 py-2.5 rounded-lg flex items-center gap-2 hover:bg-gray-900 shadow-lg transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2-2v4h10z"></path></svg>
            Cetak PDF
        </button>
    </div>

    <?php if(empty($results)): ?>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded shadow-sm">Hasil belum tersedia.</div>
    <?php else: ?>
        <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200">
            <table class="w-full text-sm text-left">
                <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b">
                    <tr>
                        <th class="px-6 py-4 w-16 text-center">Rank</th>
                        <th class="px-6 py-4">Atlet</th>
                        <th class="px-6 py-4">Klub</th>
                        <th class="px-6 py-4 text-center">Seri / Lint</th>
                        <th class="px-6 py-4 text-right">Waktu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach($results as $row): ?>
                        <?php 
                            $rank = $row['rank'];
                            $status = $row['status'];
                            
                            // Logika Tampilan
                            $rankDisplay = "<span class='font-bold text-gray-400'>-</span>";
                            $timeDisplay = $row['final_time'];
                            $bgClass = 'hover:bg-gray-50';
                            $textClass = 'text-gray-900';

                            if ($status == 'OK') {
                                if ($rank == 1) { $rankDisplay = '🥇'; $bgClass = 'bg-yellow-50'; $textClass = 'text-yellow-800 font-bold'; }
                                elseif ($rank == 2) { $rankDisplay = '🥈'; $bgClass = 'bg-gray-50'; }
                                elseif ($rank == 3) { $rankDisplay = '🥉'; $bgClass = 'bg-orange-50'; }
                                else { $rankDisplay = "#$rank"; }
                            } else {
                                // KASUS DQ / DNS
                                $rankDisplay = ""; 
                                $bgClass = 'bg-red-50';
                                $timeDisplay = "<span class='text-red-600 font-bold'>$status</span>";
                            }
                        ?>
                        <tr class="<?= $bgClass ?> transition">
                            <td class="px-6 py-4 text-center text-lg"><?= $rankDisplay ?></td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-base <?= $textClass ?>"><?= htmlspecialchars($row['nama_atlet']) ?></div>
                            </td>
                            <td class="px-6 py-4 text-gray-500"><?= htmlspecialchars($row['nama_klub']) ?></td>
                            <td class="px-6 py-4 text-center text-gray-400 text-xs">S<?= $row['heat_number'] ?> - L<?= $row['lane_number'] ?></td>
                            <td class="px-6 py-4 text-right font-mono text-lg font-bold text-blue-700"><?= $timeDisplay ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
