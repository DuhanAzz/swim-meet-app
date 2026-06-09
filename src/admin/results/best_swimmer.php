<?php
// FILE: src/admin/results/best_swimmer.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';
require_once __DIR__ . '/calculate_best_swimmer.php';

// Proteksi akses hanya untuk admin/master
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'master'])) {
    header("Location: /swim-meet/public/login.php"); exit;
}

// 1. Ambil Parameter Filter
$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$filter_gender = $_GET['gender'] ?? 'all';
$filter_ku = $_GET['ku'] ?? 'all';

// Jika event_id belum dipilih, ambil event terakhir
if ($event_id === 0) {
    $stmtEvent = $pdo->query("SELECT id, event_name FROM events ORDER BY event_date_start DESC LIMIT 1");
    $activeEvent = $stmtEvent->fetch(PDO::FETCH_ASSOC);
    if ($activeEvent) {
        $event_id = $activeEvent['id'];
        $eventName = $activeEvent['event_name'];
    } else {
        $eventName = "Belum Ada Event";
    }
} else {
    $stmtEvent = $pdo->prepare("SELECT event_name FROM events WHERE id = ?");
    $stmtEvent->execute([$event_id]);
    $eventName = $stmtEvent->fetchColumn();
}

// Ambil Daftar KU untuk Dropdown
$available_kus = [];
if ($event_id > 0) {
    $stmtKU = $pdo->prepare("SELECT id, group_name FROM event_age_groups WHERE event_id = ? ORDER BY min_age ASC");
    $stmtKU->execute([$event_id]);
    $available_kus = $stmtKU->fetchAll(PDO::FETCH_ASSOC);
}

// 2. PANGGIL MESIN KALKULATOR DENGAN FILTER!
$bestSwimmers = [];
if ($event_id > 0) {
    $bestSwimmers = getBestSwimmerRanking($pdo, $event_id, $filter_gender, $filter_ku);
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    <div class="max-w-6xl mx-auto px-4 py-4">
        
        <!-- Header & Filter Section -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 mb-8">
            <div class="mb-6">
                <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tighter">🏆 KALKULATOR PERENANG TERBAIK</h1>
                <p class="text-slate-500 text-sm mt-1">Alat bantu admin untuk memecahkan *tie-breaker* medali menggunakan algoritma skor ketajaman rekor.</p>
            </div>
            
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Pilih Event Lomba</label>
                    <select name="event_id" class="w-full bg-slate-50 border border-slate-300 text-slate-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2.5 font-bold outline-none" onchange="this.form.submit()">
                        <?php
                        $events = $pdo->query("SELECT id, event_name FROM events ORDER BY event_date_start DESC")->fetchAll(PDO::FETCH_ASSOC);
                        foreach($events as $ev): 
                            $selected = ($ev['id'] == $event_id) ? 'selected' : '';
                        ?>
                            <option value="<?= $ev['id'] ?>" <?= $selected ?>><?= htmlspecialchars($ev['event_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Filter Gender</label>
                    <select name="gender" class="w-full bg-slate-50 border border-slate-300 text-slate-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2.5 font-bold outline-none">
                        <option value="all" <?= $filter_gender == 'all' ? 'selected' : '' ?>>Semua Gender</option>
                        <option value="L" <?= $filter_gender == 'L' ? 'selected' : '' ?>>Putra (L)</option>
                        <option value="P" <?= $filter_gender == 'P' ? 'selected' : '' ?>>Putri (P)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Kelompok Umur (KU)</label>
                    <select name="ku" class="w-full bg-slate-50 border border-slate-300 text-slate-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2.5 font-bold outline-none">
                        <option value="all" <?= $filter_ku == 'all' ? 'selected' : '' ?>>Semua KU (Gabungan)</option>
                        <?php foreach($available_kus as $ku): ?>
                            <option value="<?= $ku['id'] ?>" <?= $filter_ku == (string)$ku['id'] ? 'selected' : '' ?>>KU: <?= htmlspecialchars($ku['group_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <button type="submit" class="w-full px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-bold text-sm shadow-md transition flex items-center justify-center gap-2">
                        <span>🔍 Terapkan Filter</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Leaderboard Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-6 bg-gradient-to-r from-blue-900 to-indigo-900 text-white flex justify-between items-center">
                <div>
                    <h2 class="font-black text-xl uppercase tracking-wide">LEADERBOARD</h2>
                    <p class="text-blue-200 text-xs mt-1 font-medium">Event: <?= htmlspecialchars($eventName) ?></p>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr class="text-slate-600 font-bold text-xs tracking-wider uppercase">
                            <th class="p-4 text-center w-16">Rank</th>
                            <th class="p-4">Nama Atlet</th>
                            <th class="p-4 text-center">Gender</th>
                            <th class="p-4 text-center">🥇 Emas</th>
                            <th class="p-4 text-center">🥈 Perak</th>
                            <th class="p-4 text-center">🥉 Perunggu</th>
                            <th class="p-4 text-center">⚡ Skor Ketajaman</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm font-medium">
                        <?php if (empty($bestSwimmers)): ?>
                            <tr>
                                <td colspan="7" class="p-8 text-center text-slate-400 font-bold">Belum ada data perenang yang sesuai dengan filter ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            // 1. KELOMPOKKAN ATLET YANG DRAW (Medali Sama Persis)
                            $groupedByMedal = [];
                            foreach ($bestSwimmers as $atlet) {
                                $medalKey = $atlet['emas'] . '-' . $atlet['perak'] . '-' . $atlet['perunggu'];
                                $groupedByMedal[$medalKey][] = $atlet;
                            }

                            // UBAH: Gunakan currentRank untuk sistem peringkat lompat
                            $currentRank = 1;
                            
                            // 2. TAMPILKAN BERDASARKAN KELOMPOK
                            foreach ($groupedByMedal as $medalKey => $group):
                                $isDraw = count($group) > 1; // Jika lebih dari 1 orang di kelompok ini = DRAW
                                $swimmerIdsDraw = implode(',', array_column($group, 'swimmer_id'));

                                foreach ($group as $index => $atlet):
                                    $bgClass = '';
                                    $rankBadge = $currentRank; // Terapkan rank yang sama untuk satu kelompok
                                    
                                    // Beri warna khusus untuk rank 1, 2, dan 3
                                    if ($currentRank == 1) { $bgClass = 'bg-yellow-50/50'; $rankBadge = '👑 1'; }
                                    elseif ($currentRank == 2) { $bgClass = 'bg-slate-50'; $rankBadge = '🥈 2'; }
                                    elseif ($currentRank == 3) { $bgClass = 'bg-orange-50/30'; $rankBadge = '🥉 3'; }
                            ?>
                                <tr class="hover:bg-blue-50/50 transition <?= $bgClass ?> <?= $isDraw ? 'border-l-4 border-l-amber-400' : '' ?>">
                                    <td class="p-4 text-center font-black text-slate-700 text-lg"><?= $rankBadge ?></td>
                                    <td class="p-4 font-bold text-slate-900 uppercase">
                                        <?= htmlspecialchars($atlet['nama_atlet']) ?>
                                        <?php if($isDraw): ?>
                                            <span class="ml-2 text-[10px] bg-amber-100 text-amber-700 px-2 py-0.5 rounded uppercase font-black tracking-widest">Draw</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 text-center">
                                        <span class="px-2 py-1 bg-slate-100 text-slate-600 rounded text-xs font-bold"><?= $atlet['jenis_kelamin'] == 'L' ? 'Putra' : 'Putri' ?></span>
                                    </td>
                                    <td class="p-4 text-center font-bold text-yellow-600 text-lg"><?= $atlet['emas'] ?></td>
                                    <td class="p-4 text-center font-bold text-slate-500 text-lg"><?= $atlet['perak'] ?></td>
                                    <td class="p-4 text-center font-bold text-orange-600 text-lg"><?= $atlet['perunggu'] ?></td>
                                    <td class="p-4 text-center">
                                        <?php if($isDraw && $index === 0): // Tampilkan tombol Bandingkan Draw HANYA di baris pertama kelompok draw ?>
                                            <a href="detail_best_swimmer.php?event_id=<?= $event_id ?>&swimmers=<?= $swimmerIdsDraw ?>" 
                                               class="inline-flex items-center gap-1 px-4 py-1.5 bg-amber-500 hover:bg-amber-600 text-white rounded-lg font-black text-xs transition shadow-md cursor-pointer" title="Bandingkan Rincian Atlet Draw">
                                                ⚖️ Bandingkan Draw &rarr;
                                            </a>
                                        <?php elseif(!$isDraw): ?>
                                            <a href="detail_best_swimmer.php?event_id=<?= $event_id ?>&swimmers=<?= $atlet['swimmer_id'] ?>" 
                                               class="inline-flex items-center gap-1 px-4 py-1.5 bg-indigo-50 hover:bg-indigo-600 text-indigo-700 hover:text-white rounded-lg font-black text-xs border border-indigo-200 transition shadow-sm cursor-pointer" title="Lihat Rincian Kalkulasi">
                                                ⚡ <?= number_format($atlet['total_sharpness'] ?? 0, 2) ?> % &rarr;
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php 
                                endforeach; 
                                
                                // UBAH: Tambahkan rank saat ini dengan jumlah atlet yang ada di kelompok draw tersebut
                                // Misal rank saat ini 1, dan ada 2 atlet draw, maka rank berikutnya = 1 + 2 = 3
                                $currentRank += count($group);
                                
                            endforeach; 
                            ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>