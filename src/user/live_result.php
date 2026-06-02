<?php
// FILE: src/user/live_result.php
session_start();
require_once __DIR__ . '/../../src/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../public/login.php"); exit;
}
$user_id = $_SESSION['user_id'];
$event_id = $_GET['event_id'] ?? 0;

// 🚀 PERBAIKAN 1: AUTO-DETEKSI EVENT (Untuk akses dari Sidebar)
// Jika tidak ada ID yang dikirim, cari event terbaru yang sudah punya Live Result
if ($event_id == 0) {
    $stmtFind = $pdo->query("SELECT e.id FROM events e JOIN event_numbers en ON e.id = en.event_id WHERE en.is_published = 1 ORDER BY e.event_date_start DESC LIMIT 1");
    $event_id = $stmtFind->fetchColumn() ?: 0;
}

// 🚀 PERBAIKAN 2: BYPASS GEMBOK UTAMA
// Hapus syarat "is_result_published = 1" karena keamanan sudah diatur per-nomor lomba di bawah.
$stmtEvt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmtEvt->execute([$event_id]);
$event = $stmtEvt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    echo "<script>alert('Belum ada Live Result yang tersedia untuk saat ini.'); window.location.href='pengumuman.php';</script>";
    exit;
}

// Cek Tipe Partisipasi (Sekolah vs Klub)
$partType = strtolower($event['participation_type'] ?? 'club');
$isSchoolEvent = (strpos($partType, 'school') !== false || strpos($partType, 'sekolah') !== false);
$teamHeaderLabel = $isSchoolEvent ? 'SEKOLAH' : 'KLUB / TIM';

// 3. Mengambil Hasil Perlombaan
// Di sini keamanannya dijaga: HANYA menampilkan nomor lomba yang is_published = 1
$sql = "SELECT en.event_number, en.distance, en.stroke, en.jenis_kelamin, en.age_group,
               s.nama_atlet, c.nama_klub, s.asal_sekolah, s.user_id as swimmer_owner_id,
               ee.entry_time, 
               es.time_final, es.rank_final, es.is_dq_final, es.dq_reason_final
        FROM event_numbers en
        JOIN event_entries ee ON en.id = ee.category_id
        JOIN event_seeding es ON ee.id = es.entry_id
        JOIN swimmers s ON ee.swimmer_id = s.id
        LEFT JOIN clubs c ON s.club_id = c.id
        WHERE en.event_id = ? 
          AND en.is_published = 1  
          AND (es.time_final IS NOT NULL OR es.is_dq_final = 1)
        ORDER BY 
            CAST(en.event_number AS UNSIGNED) ASC,
            es.is_dq_final ASC,
            es.rank_final ASC";

$stmtRes = $pdo->prepare($sql);
$stmtRes->execute([$event_id]);
$results = $stmtRes->fetchAll(PDO::FETCH_ASSOC);

// 4. Kelompokkan berdasarkan Nomor Acara
$groupedResults = [];
foreach ($results as $r) {
    $judulAcara = "ACARA #" . $r['event_number'] . " - " . $r['distance'] . "M " . strtoupper($r['stroke']) . " " . strtoupper($r['jenis_kelamin']) . " (" . $r['age_group'] . ")";
    $groupedResults[$judulAcara][] = $r;
}

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="max-w-6xl mx-auto">
        <a href="pengumuman.php" class="inline-flex items-center text-[10px] font-black text-slate-400 uppercase tracking-widest hover:text-blue-600 transition mb-6">
            &larr; Kembali ke Pusat Informasi
        </a>

        <div class="bg-slate-900 text-white p-8 rounded-3xl shadow-xl mb-8 relative overflow-hidden">
            <div class="absolute -right-10 -top-10 text-9xl opacity-10">🏆</div>
            <div class="relative z-10">
                <span class="inline-block px-3 py-1 bg-blue-600 text-white text-[9px] font-black uppercase tracking-widest rounded-lg mb-3">Live Result Digital</span>
                <h1 class="text-3xl font-black uppercase italic leading-tight mb-2"><?= htmlspecialchars($event['event_name']) ?></h1>
                <p class="text-xs text-slate-300 font-bold uppercase tracking-widest">
                    📍 <?= htmlspecialchars($event['event_location']) ?> | 📅 <?= date('d F Y', strtotime($event['event_date_start'])) ?>
                </p>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 mb-8 flex items-center gap-4">
            <span class="text-2xl ml-2">🔍</span>
            <input type="text" id="searchInput" placeholder="Cari nama atlet atau tim di sini..." class="w-full bg-transparent border-none focus:ring-0 text-sm font-bold text-slate-700 uppercase placeholder:text-slate-300 placeholder:normal-case">
        </div>

        <?php if (empty($groupedResults)): ?>
            <div class="bg-white p-12 text-center rounded-3xl border border-slate-200 border-dashed">
                <span class="text-4xl block mb-3 opacity-30">📭</span>
                <p class="text-sm font-bold text-slate-400 uppercase tracking-widest">Belum ada hasil perlombaan yang diterbitkan.</p>
                <p class="text-[10px] text-slate-400 mt-2">Silakan tunggu panitia memperbarui data atau mengaktifkan saklar Live Result.</p>
            </div>
        <?php else: ?>
            <div id="resultContainer" class="space-y-6 pb-12">
                <?php foreach ($groupedResults as $judul => $atletList): ?>
                    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden result-card">
                        <div class="bg-slate-100 px-6 py-4 border-b border-slate-200 flex justify-between items-center">
                            <h2 class="text-sm font-black text-slate-800 uppercase italic"><?= $judul ?></h2>
                            <span class="px-2 py-1 bg-emerald-100 text-emerald-700 rounded text-[9px] font-black uppercase tracking-widest animate-pulse">🔴 LIVE</span>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-50 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-200">
                                        <th class="py-3 px-4 w-12 text-center">Rank</th>
                                        <th class="py-3 px-4">Nama Atlet</th>
                                        <th class="py-3 px-4 text-center w-20">KU</th>
                                        <th class="py-3 px-4"><?= $teamHeaderLabel ?></th>
                                        <th class="py-3 px-4 text-center w-28">Wkt. Prestasi</th>
                                        <th class="py-3 px-4 text-right w-28">Wkt. Final</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    foreach ($atletList as $atlet): 
                                        $isMyTeam = ($atlet['swimmer_owner_id'] == $user_id);
                                        $isDQ = ($atlet['is_dq_final'] == 1);
                                        
                                        $rowClass = 'border-b border-slate-100 hover:bg-slate-50 transition-colors';
                                        
                                        if ($isSchoolEvent) {
                                            $displayTeam = !empty($atlet['asal_sekolah']) ? $atlet['asal_sekolah'] : '-';
                                        } else {
                                            $displayTeam = !empty($atlet['nama_klub']) ? $atlet['nama_klub'] : 'UNATTACHED';
                                        }

                                        $rankBadge = '-';
                                        if (!$isDQ && !empty($atlet['rank_final'])) {
                                            $rankBadge = $atlet['rank_final'];
                                            if($rankBadge == 1) $rankBadge = '🥇 1';
                                            if($rankBadge == 2) $rankBadge = '🥈 2';
                                            if($rankBadge == 3) $rankBadge = '🥉 3';
                                        }

                                        $waktuDaftar = $atlet['entry_time'];
                                        if (empty($waktuDaftar) || $waktuDaftar === '00:00.00' || $waktuDaftar === '00:00:00') {
                                            $waktuDaftar = 'NT';
                                        }
                                    ?>
                                    <tr class="searchable-row <?= $rowClass ?>">
                                        
                                        <td class="py-3 px-4 text-center font-black <?= ($atlet['rank_final'] <= 3 && !$isDQ) ? 'text-amber-600' : 'text-slate-400' ?>">
                                            <?= $rankBadge ?>
                                        </td>
                                        
                                        <td class="py-3 px-4">
                                            <span class="text-xs font-black uppercase athlete-name <?= $isMyTeam ? 'bg-yellow-300 text-slate-900 px-2 py-0.5 rounded shadow-sm' : 'text-slate-700' ?>">
                                                <?= htmlspecialchars($atlet['nama_atlet']) ?>
                                            </span>
                                        </td>
                                        
                                        <td class="py-3 px-4 text-center text-[10px] font-black uppercase tracking-widest text-slate-500">
                                            <?= htmlspecialchars($atlet['age_group']) ?>
                                        </td>
                                        
                                        <td class="py-3 px-4 text-[10px] font-bold uppercase tracking-widest team-name text-slate-500">
                                            <?= htmlspecialchars($displayTeam) ?>
                                        </td>
                                        
                                        <td class="py-3 px-4 text-center font-mono text-xs text-slate-400 font-bold">
                                            <?= htmlspecialchars($waktuDaftar) ?>
                                        </td>
                                        
                                        <td class="py-3 px-4 text-right font-mono text-sm font-black text-slate-800">
                                            <?php if($isDQ): ?>
                                                <span class="text-red-500 text-xs font-black"><?= htmlspecialchars($atlet['dq_reason_final'] ?? 'DQ') ?></span>
                                            <?php else: ?>
                                                <?= htmlspecialchars($atlet['time_final']) ?>
                                            <?php endif; ?>
                                        </td>
                                        
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('searchInput').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let cards = document.querySelectorAll('.result-card');

    cards.forEach(card => {
        let rows = card.querySelectorAll('.searchable-row');
        let cardHasVisibleRow = false;

        rows.forEach(row => {
            let athleteName = row.querySelector('.athlete-name').textContent.toLowerCase();
            let teamName = row.querySelector('.team-name').textContent.toLowerCase();

            if (athleteName.includes(filter) || teamName.includes(filter)) {
                row.style.display = '';
                cardHasVisibleRow = true;
            } else {
                row.style.display = 'none';
            }
        });
        card.style.display = cardHasVisibleRow ? '' : 'none';
    });
});
</script>