<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];
$search = $_GET['q'] ?? '';

// --- 1. AMBIL DATA (REVISI: MENGHITUNG SEMUA STATUS) ---
// Perbaikan: Menghapus "AND ee.status = 'Approved'" agar semua data yang masuk terbaca
$sql = "SELECT ec.*, 
        (SELECT COUNT(*) FROM race_heats rh WHERE rh.category_id = ec.id) as total_heats,
        (SELECT COUNT(*) FROM event_entries ee WHERE ee.category_id = ec.id) as total_entries
        FROM event_categories ec 
        WHERE ec.user_id = ?";

$params = [$uid];

// Logika Pencarian
if ($search) {
    $sql .= " AND (ec.event_no LIKE ? OR ec.style LIKE ? OR ec.age_group LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY ec.event_no ASC";
$stmt = $pdo->prepare($sql); 
$stmt->execute($params);
$events = $stmt->fetchAll();

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">
    
    <div class="mb-10 flex flex-col xl:flex-row justify-between items-center gap-6">
        <div>
            <h1 class="text-3xl font-black uppercase italic text-slate-900 leading-none tracking-tighter">Start List Manager</h1>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-2">Penyusunan Lintasan & Manajemen Acara</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="print_full_book.php" target="_blank" class="bg-blue-600 text-white font-black px-10 py-4 rounded-2xl text-[10px] uppercase shadow-xl shadow-blue-100 hover:bg-blue-700 transition flex items-center gap-3">
                <span>📚</span> Cetak Buku Acara Lengkap
            </a>
        </div>
    </div>

    <div class="mb-10">
        <form method="GET" action="" class="relative group max-w-2xl">
            <div class="absolute inset-y-0 left-0 pl-6 flex items-center pointer-events-none">
                <span class="text-xl text-slate-400 group-focus-within:text-blue-500 transition-colors">🔍</span>
            </div>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" 
                   placeholder="Cari Nomor Acara (misal: 101), Gaya Renang, atau Kelompok Umur..." 
                   class="w-full pl-16 pr-6 py-5 bg-white border border-slate-200 rounded-[2rem] font-bold text-sm shadow-sm outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all">
            
            <?php if($search): ?>
                <a href="index.php" class="absolute inset-y-0 right-6 flex items-center text-[10px] font-black text-red-500 uppercase hover:text-red-700 transition">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="bg-white rounded-[2.5rem] border border-slate-200 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-900 text-white text-[10px] font-black uppercase tracking-[0.2em]">
                    <tr>
                        <th class="px-8 py-6 text-center w-24">#</th>
                        <th class="px-8 py-6">Event Description</th>
                        <th class="px-8 py-6 text-center">Entries (All)</th>
                        <th class="px-8 py-6 text-center">Status</th>
                        <th class="px-8 py-6 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if(empty($events)): ?>
                        <tr>
                            <td colspan="5" class="px-8 py-20 text-center">
                                <p class="text-slate-400 font-bold italic">Tidak ada acara yang ditemukan.</p>
                            </td>
                        </tr>
                    <?php else: foreach($events as $e): 
                        $hasHeats = $e['total_heats'] > 0;
                        $hasEntries = $e['total_entries'] > 0;
                    ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-8 py-6 text-center font-black text-2xl text-slate-300 italic">#<?= $e['event_no'] ?></td>
                            
                            <td class="px-8 py-6">
                                <div class="font-black text-slate-800 uppercase italic tracking-tighter text-lg">
                                    <?= $e['distance'] ?>m <?= $e['style'] ?> (<?= $e['gender'] == 'Male' ? 'Putra' : 'Putri' ?>)
                                </div>
                                <div class="text-[10px] font-bold text-slate-400 mt-1 uppercase tracking-widest">
                                    <?= $e['age_group'] ?>
                                </div>
                            </td>
                            
                            <td class="px-8 py-6 text-center">
                                <div class="flex flex-col items-center">
                                    <span class="font-black text-xl <?= $hasEntries ? 'text-blue-600' : 'text-slate-200' ?>">
                                        <?= $e['total_entries'] ?>
                                    </span>
                                    <span class="text-[9px] font-bold uppercase text-slate-400">Swimmers</span>
                                </div>
                            </td>

                            <td class="px-8 py-6 text-center">
                                <?php if (!$hasEntries): ?>
                                    <span class="px-4 py-2 rounded-full text-[9px] font-black uppercase tracking-tighter bg-slate-100 text-slate-400 border border-slate-200">
                                        ⛔ Kosong
                                    </span>
                                <?php elseif ($hasHeats): ?>
                                    <span class="px-4 py-2 rounded-full text-[9px] font-black uppercase tracking-tighter bg-emerald-100 text-emerald-700 border border-emerald-200">
                                        ✅ Siap Lomba
                                    </span>
                                <?php else: ?>
                                    <span class="px-4 py-2 rounded-full text-[9px] font-black uppercase tracking-tighter bg-blue-100 text-blue-700 border border-blue-200 animate-pulse">
                                        ⚠️ Butuh Seeding
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="px-8 py-6 text-right">
                                <div class="flex justify-end gap-2">
                                    <?php if ($hasEntries): ?>
                                        <form action="logic.php" method="POST">
                                            <input type="hidden" name="category_id" value="<?= $e['id'] ?>">
                                            <button name="generate_startlist" class="bg-slate-900 text-white font-black px-6 py-3 rounded-2xl text-[9px] uppercase tracking-widest hover:bg-blue-600 transition shadow-lg shadow-slate-200">
                                                ⚡ <?= $hasHeats ? 'RE-SEED' : 'SEED' ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button disabled class="bg-slate-50 text-slate-300 font-black px-6 py-3 rounded-2xl text-[9px] uppercase tracking-widest cursor-not-allowed">
                                            Empty
                                        </button>
                                    <?php endif; ?>

                                    <?php if($hasHeats): ?>
                                        <a href="view_startlist.php?category_id=<?= $e['id'] ?>" class="bg-white border-2 border-slate-100 text-slate-400 font-black px-6 py-3 rounded-2xl text-[9px] uppercase tracking-widest hover:bg-slate-900 hover:text-white transition">👁️ View</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="mt-8 text-center text-[10px] text-slate-400">
        Jika jumlah peserta muncul tapi status belum Approved, Seeding tetap bisa dilakukan dengan kode ini.
    </div>

</div>