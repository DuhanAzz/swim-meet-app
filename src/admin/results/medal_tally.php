<?php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// Cek Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

// HITUNG PEROLEHAN MEDALI
// Logika:
// 1. Ambil data User (Klub)
// 2. Join ke event_entries (Atlet)
// 3. Join ke race_results (Hasil)
// 4. Hitung jumlah Rank 1 (Emas), Rank 2 (Perak), Rank 3 (Perunggu)
// 5. Urutkan berdasarkan Emas terbanyak, lalu Perak, lalu Perunggu.

try {
    $sql = "SELECT 
                u.nama_lengkap as nama_klub,
                COALESCE(SUM(CASE WHEN rr.rank = 1 THEN 1 ELSE 0 END), 0) as emas,
                COALESCE(SUM(CASE WHEN rr.rank = 2 THEN 1 ELSE 0 END), 0) as perak,
                COALESCE(SUM(CASE WHEN rr.rank = 3 THEN 1 ELSE 0 END), 0) as perunggu
            FROM users u
            JOIN event_entries ee ON u.id = ee.user_id
            JOIN race_results rr ON ee.id = rr.entry_id
            WHERE u.role = 'user' 
            AND rr.disqualified = 0
            GROUP BY u.id
            HAVING (emas + perak + perunggu) > 0
            ORDER BY emas DESC, perak DESC, perunggu DESC";

    $tally = $pdo->query($sql)->fetchAll();

} catch (PDOException $e) {
    $tally = [];
    $error_msg = "Error Database: " . $e->getMessage();
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">

    <div class="max-w-5xl mx-auto mb-10 flex flex-col md:flex-row justify-between items-end gap-6">
        <div>
            <h1 class="text-4xl font-black uppercase tracking-tighter italic text-slate-900 leading-none">Klasemen Medali</h1>
            <p class="text-sm text-slate-500 font-bold uppercase tracking-widest mt-2">Perolehan Juara Antar Klub</p>
        </div>
        
        <button onclick="window.print()" class="px-6 py-3 bg-white border border-slate-200 text-slate-600 rounded-xl font-bold text-xs uppercase tracking-wider shadow-sm hover:bg-slate-50 transition flex items-center gap-2">
            🖨️ Cetak Klasemen
        </button>
    </div>

    <?php if(isset($error_msg)): ?>
        <div class="max-w-5xl mx-auto mb-6 bg-red-100 border border-red-200 text-red-700 px-4 py-3 rounded-xl">
            <strong class="font-bold">Terjadi Kesalahan:</strong> <?= $error_msg ?>
        </div>
    <?php endif; ?>

    <div class="max-w-5xl mx-auto">
        
        <?php if(empty($tally)): ?>
            <div class="bg-white rounded-[2.5rem] p-16 text-center border border-slate-200 shadow-sm">
                <div class="text-6xl mb-4 grayscale opacity-30">🏆</div>
                <h3 class="font-black text-slate-400 uppercase tracking-widest text-lg">Belum Ada Juara</h3>
                <p class="text-xs font-bold text-slate-300 mt-2">
                    Klasemen akan muncul otomatis setelah Anda menginput hasil lomba (Juara 1, 2, 3).
                </p>
                <a href="index.php" class="inline-block mt-6 px-6 py-3 bg-blue-600 text-white rounded-xl font-bold text-xs uppercase tracking-wider hover:bg-blue-700 transition">
                    Input Hasil Lomba
                </a>
            </div>

        <?php else: ?>
            
            <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-900 text-white">
                            <tr>
                                <th class="py-5 px-6 text-[10px] font-black uppercase tracking-widest w-16 text-center">Rank</th>
                                <th class="py-5 px-6 text-[10px] font-black uppercase tracking-widest">Nama Klub</th>
                                <th class="py-5 px-6 text-[10px] font-black uppercase tracking-widest text-center w-24 bg-yellow-500 text-yellow-900">🥇 Emas</th>
                                <th class="py-5 px-6 text-[10px] font-black uppercase tracking-widest text-center w-24 bg-slate-400 text-slate-900">🥈 Perak</th>
                                <th class="py-5 px-6 text-[10px] font-black uppercase tracking-widest text-center w-24 bg-orange-400 text-orange-900">🥉 Perunggu</th>
                                <th class="py-5 px-6 text-[10px] font-black uppercase tracking-widest text-center w-24 bg-slate-800 text-slate-400">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach($tally as $i => $row): 
                                $rank = $i + 1;
                                $total = $row['emas'] + $row['perak'] + $row['perunggu'];
                                
                                // Highlight Juara 1, 2, 3
                                $rankClass = "text-slate-400";
                                if($rank == 1) $rankClass = "text-yellow-500 text-2xl";
                                if($rank == 2) $rankClass = "text-slate-400 text-xl";
                                if($rank == 3) $rankClass = "text-orange-400 text-lg";
                            ?>
                            <tr class="hover:bg-slate-50 transition group">
                                <td class="py-4 px-6 text-center font-black italic <?= $rankClass ?>">
                                    <?= $rank ?>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="block font-black text-slate-800 uppercase italic text-sm group-hover:text-blue-600 transition">
                                        <?= htmlspecialchars($row['nama_klub']) ?>
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-center font-black text-slate-700 bg-yellow-50 group-hover:bg-yellow-100 transition">
                                    <?= $row['emas'] ?>
                                </td>
                                <td class="py-4 px-6 text-center font-black text-slate-700 bg-slate-50 group-hover:bg-slate-100 transition">
                                    <?= $row['perak'] ?>
                                </td>
                                <td class="py-4 px-6 text-center font-black text-slate-700 bg-orange-50 group-hover:bg-orange-100 transition">
                                    <?= $row['perunggu'] ?>
                                </td>
                                <td class="py-4 px-6 text-center font-black text-white bg-slate-900">
                                    <?= $total ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6 text-center">
                 <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                    * Klasemen dihitung otomatis berdasarkan hasil input lomba
                </p>
            </div>

        <?php endif; ?>
    </div>
</div>

<style>
    @media print {
        .sm\:ml-64 { margin-left: 0 !important; }
        button, a { display: none !important; }
        body { background: white !important; }
        .shadow-sm { box-shadow: none !important; border: 1px solid #ccc !important; }
    }
</style>