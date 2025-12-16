<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$cat_id = $_GET['category_id'] ?? null;
if (!$cat_id) {
    header("Location: index.php"); exit;
}

// 1. AMBIL INFO EVENT
$stmt = $pdo->prepare("SELECT * FROM event_categories WHERE id = ?");
$stmt->execute([$cat_id]);
$event = $stmt->fetch();

if (!$event) die("Event tidak ditemukan.");

// 2. AMBIL DATA SERI (HEATS) & LINTASAN (LINES)
try {
    // QUERY FINAL - DISESUAIKAN DENGAN STRUKTUR DB ANDA
    $sql = "SELECT 
                rh.heat_number as heat_no, 
                rh.stage, 
                rl.lane_number as lane_no, 
                rl.entry_time,
                s.nama_atlet as swimmer_name,   /* Dari tabel swimmers */
                s.jenis_kelamin as gender,      /* Dari tabel swimmers */
                s.asal_sekolah,                 /* Opsional: Tampilkan sekolah */
                u.nama_lengkap as club_name     /* Nama Klub dari tabel users */
            FROM race_heats rh
            JOIN race_lines rl ON rl.heat_id = rh.id
            JOIN swimmers s ON rl.swimmer_id = s.id
            /* Hubungkan swimmer ke user (klub) via user_id */
            LEFT JOIN users u ON s.user_id = u.id 
            WHERE rh.category_id = ?
            ORDER BY rh.heat_number ASC, rl.lane_number ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cat_id]);
    $raw_data = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Error Database: " . $e->getMessage());
}

// 3. GROUPING DATA BERDASARKAN HEAT
$heats = [];
foreach ($raw_data as $row) {
    $key = $row['heat_no'];
    $heats[$key]['stage'] = $row['stage'] ?? 'Prelims';
    $heats[$key]['swimmers'][] = $row;
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">

    <div class="mb-8 flex justify-between items-end">
        <div>
            <a href="index.php" class="text-[10px] font-bold text-slate-400 uppercase tracking-widest hover:text-slate-600 transition mb-2 block">← Kembali ke Seeding</a>
            <h1 class="text-3xl font-black uppercase italic text-slate-900 leading-none tracking-tighter">
                Start List
            </h1>
            <div class="flex items-center gap-3 mt-4">
                <span class="bg-slate-900 text-white px-4 py-2 rounded-lg text-lg font-black italic uppercase">
                    #<?= $event['event_no'] ?>
                </span>
                <div class="leading-tight">
                    <div class="text-xl font-black uppercase italic text-slate-800">
                        <?= $event['distance'] ?>m <?= $event['style'] ?> <span class="text-slate-400">/</span> <?= $event['gender'] == 'Male' ? 'Putra' : 'Putri' ?>
                    </div>
                    <div class="text-xs font-bold text-slate-500 uppercase tracking-widest">
                        <?= $event['age_group'] ?> • <?= date('d M Y', strtotime($event['event_date'])) ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="flex gap-2">
             <button onclick="window.print()" class="bg-blue-600 text-white font-black px-6 py-3 rounded-xl text-[10px] uppercase tracking-widest hover:bg-blue-700 transition shadow-lg shadow-blue-100 flex items-center gap-2">
                🖨️ Cetak / PDF
            </button>
        </div>
    </div>

    <div class="space-y-8 print:space-y-8">
        <?php if (empty($heats)): ?>
            <div class="bg-white p-10 rounded-3xl border border-slate-200 text-center">
                <p class="text-slate-400 font-bold italic">Belum ada seeding untuk nomor ini.</p>
                <a href="index.php" class="text-blue-600 font-bold text-sm mt-2 block underline">Lakukan Seeding Sekarang</a>
            </div>
        <?php else: ?>
            
            <?php foreach ($heats as $heat_num => $data): ?>
                <div class="bg-white rounded-[1.5rem] border border-slate-200 overflow-hidden shadow-sm break-inside-avoid">
                    <div class="bg-slate-900 text-white px-6 py-3 flex justify-between items-center">
                        <div class="flex items-center gap-3">
                            <span class="font-black uppercase tracking-widest text-sm">HEAT <?= $heat_num ?></span>
                            <?php if(isset($data['stage']) && $data['stage'] == 'Final'): ?>
                                <span class="bg-yellow-400 text-slate-900 text-[9px] px-2 py-0.5 rounded font-black uppercase">Final</span>
                            <?php endif; ?>
                        </div>
                        <span class="text-[9px] font-bold uppercase text-slate-400 tracking-wider">Start List</span>
                    </div>

                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-500 text-[9px] font-black uppercase tracking-widest border-b border-slate-100">
                            <tr>
                                <th class="px-6 py-3 text-center w-16">LN</th>
                                <th class="px-6 py-3">Nama Atlet</th>
                                <th class="px-6 py-3">Klub / Sekolah</th>
                                <th class="px-6 py-3 text-right">Waktu (Entry)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($data['swimmers'] as $s): ?>
                            <tr class="hover:bg-blue-50/50 transition">
                                <td class="px-6 py-3 text-center">
                                    <span class="font-black text-lg text-slate-800"><?= $s['lane_no'] ?></span>
                                </td>
                                
                                <td class="px-6 py-3">
                                    <div class="font-bold text-slate-800 uppercase text-sm"><?= $s['swimmer_name'] ?></div>
                                    <div class="text-[9px] text-slate-400 font-bold">
                                        <?= ($s['gender'] == 'Male' || $s['gender'] == 'L') ? 'Laki-Laki' : 'Perempuan' ?>
                                    </div>
                                </td>
                                
                                <td class="px-6 py-3">
                                    <div class="text-xs font-semibold text-slate-600 uppercase truncate max-w-[200px]" title="<?= $s['club_name'] ?>">
                                        <?= !empty($s['club_name']) ? $s['club_name'] : $s['asal_sekolah'] ?>
                                    </div>
                                </td>

                                <td class="px-6 py-3 text-right font-mono font-bold text-slate-700">
                                    <?= ($s['entry_time'] == '99:99.99' || $s['entry_time'] == NULL) ? 'NT' : $s['entry_time'] ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>
    </div>

    <div class="mt-10 text-center print:hidden">
        <p class="text-[10px] font-bold text-slate-300 uppercase">SwimMeet System • Generated at <?= date('H:i:s') ?></p>
    </div>
</div>

<style media="print">
    @page { margin: 1cm; size: A4; }
    body { background: white; -webkit-print-color-adjust: exact; }
    .print\:hidden { display: none !important; }
    .bg-slate-50 { background: white !important; }
    .sm\:ml-64 { margin-left: 0 !important; }
    .p-6 { padding: 0 !important; }
    .shadow-sm, .shadow-lg { box-shadow: none !important; }
    .border { border: 1px solid #eee !important; }
    a { text-decoration: none; color: black; }
</style>