<?php
// src/admin/seeding/index.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// 1. Cek Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$admin_id = $_SESSION['user_id'];

// 2. Default Config
if (!isset($_SESSION['print_config'])) {
    $_SESSION['print_config'] = [
        'show_event_no' => true,
        'show_date' => true,
        'show_event_name' => true,
        'show_group' => true,
        'show_gender' => true,
        'show_pool' => true,
        'show_round' => true
    ];
}
$pc = $_SESSION['print_config'];

// 3. AMBIL DATA (Query diperbaiki agar tidak error "Table age_groups doesn't exist")
$events = [];
$error_msg = null;

try {
    $sql = "SELECT en.*, 
            (
                SELECT COUNT(ee.id) 
                FROM event_entries ee 
                WHERE ee.category_id = en.id 
                AND ee.event_id = en.organizer_id 
            ) as total_athletes
            FROM event_numbers en 
            WHERE en.organizer_id = ? 
            ORDER BY CAST(en.event_number AS UNSIGNED) ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$admin_id]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_msg = "Database Error: " . $e->getMessage();
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">

    <div class="max-w-7xl mx-auto mb-8 flex flex-col lg:flex-row justify-between items-start gap-6">
        <div>
            <h1 class="text-4xl font-black uppercase tracking-tighter italic text-slate-900 leading-none">Seeding & Start List</h1>
            <p class="text-sm text-slate-500 font-bold uppercase tracking-widest mt-2">Penyusunan Lintasan</p>
        </div>
        
        <?php 
            $total_all_entries = !empty($events) ? array_sum(array_column($events, 'total_athletes')) : 0;
            $globalDisabled = $total_all_entries == 0 ? 'opacity-50 cursor-not-allowed grayscale' : 'hover:-translate-y-1 shadow-xl shadow-blue-200 hover:bg-blue-700';
            $globalLink = $total_all_entries == 0 ? '#' : 'print_full_book.php';
        ?>
        
        <div class="flex flex-wrap items-start gap-3">
            
            <details class="relative group z-50">
                <summary class="list-none bg-white text-slate-600 px-4 py-4 rounded-[2rem] border border-slate-200 font-bold text-xs uppercase cursor-pointer hover:bg-slate-50 shadow-sm flex items-center gap-2 h-[72px]">
                    <span>⚙️ ATUR JUDUL</span>
                </summary>
                <div class="absolute right-0 top-20 w-64 bg-white border border-slate-200 p-4 rounded-2xl shadow-xl z-50">
                    <form action="set_print_config.php" method="POST" class="space-y-2">
                        <p class="text-[10px] font-black uppercase text-slate-400 mb-2">Pilih Komponen Judul:</p>
                        <label class="flex items-center gap-2 text-xs font-bold cursor-pointer hover:text-blue-600">
                            <input type="checkbox" name="show_event_no" <?= $pc['show_event_no']?'checked':'' ?> class="rounded text-blue-600 focus:ring-0"> Nomor Acara
                        </label>
                        <label class="flex items-center gap-2 text-xs font-bold cursor-pointer hover:text-blue-600">
                            <input type="checkbox" name="show_date" <?= $pc['show_date']?'checked':'' ?> class="rounded text-blue-600 focus:ring-0"> Tanggal
                        </label>
                        <label class="flex items-center gap-2 text-xs font-bold cursor-pointer hover:text-blue-600">
                            <input type="checkbox" name="show_event_name" <?= $pc['show_event_name']?'checked':'' ?> class="rounded text-blue-600 focus:ring-0"> Nama Nomor
                        </label>
                        <label class="flex items-center gap-2 text-xs font-bold cursor-pointer hover:text-blue-600">
                            <input type="checkbox" name="show_group" <?= $pc['show_group']?'checked':'' ?> class="rounded text-blue-600 focus:ring-0"> Kelompok Umur
                        </label>
                        <label class="flex items-center gap-2 text-xs font-bold cursor-pointer hover:text-blue-600">
                            <input type="checkbox" name="show_gender" <?= $pc['show_gender']?'checked':'' ?> class="rounded text-blue-600 focus:ring-0"> Gender
                        </label>
                        <label class="flex items-center gap-2 text-xs font-bold cursor-pointer hover:text-blue-600">
                            <input type="checkbox" name="show_pool" <?= $pc['show_pool']?'checked':'' ?> class="rounded text-blue-600 focus:ring-0"> Pool
                        </label>
                        <label class="flex items-center gap-2 text-xs font-bold cursor-pointer hover:text-blue-600">
                            <input type="checkbox" name="show_round" <?= $pc['show_round']?'checked':'' ?> class="rounded text-blue-600 focus:ring-0"> Babak
                        </label>
                        <button type="submit" class="w-full bg-slate-900 text-white py-2 rounded-lg text-[10px] font-black uppercase mt-3 hover:bg-blue-600">Simpan</button>
                    </form>
                </div>
            </details>

            <a href="generate_all.php" onclick="return confirm('⚠️ PERINGATAN:\nFitur ini akan mengacak ulang lintasan untuk SEMUA nomor.\nLanjutkan?')" 
               class="bg-indigo-600 text-white pl-6 pr-8 py-4 rounded-[2rem] transition flex items-center gap-4 group hover:-translate-y-1 shadow-xl shadow-indigo-200 hover:bg-indigo-700 h-[72px]">
                <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center group-hover:bg-white group-hover:text-indigo-600 transition">⚡</div>
                <div class="text-left">
                    <span class="block text-[9px] font-bold text-indigo-200 uppercase tracking-widest">Auto Seeding</span>
                    <span class="block font-black text-sm uppercase tracking-wider">Generate All</span>
                </div>
            </a>

            <a href="<?= $globalLink ?>" target="_blank" class="bg-blue-600 text-white pl-6 pr-8 py-4 rounded-[2rem] transition flex items-center gap-4 group <?= $globalDisabled ?> h-[72px]">
                <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center group-hover:bg-white group-hover:text-blue-600 transition">📄</div>
                <div class="text-left">
                    <span class="block text-[9px] font-bold text-blue-200 uppercase tracking-widest">Download Full</span>
                    <span class="block font-black text-sm uppercase tracking-wider">Cetak Buku</span>
                </div>
            </a>

        </div>
    </div>

    <div class="max-w-7xl mx-auto mb-6 relative">
        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
            <span class="text-xl grayscale opacity-40">🔍</span>
        </div>
        <input type="text" id="searchInput" 
            class="w-full pl-12 pr-4 py-4 rounded-2xl border-none ring-1 ring-slate-200 shadow-sm focus:ring-2 focus:ring-blue-500 font-bold text-slate-600 placeholder:text-slate-300 placeholder:font-bold transition" 
            placeholder="Cari Nomor Acara, Gaya, atau Jarak... (Contoh: 101, Bebas, 50m)">
    </div>

    <?php if ($error_msg): ?>
    <div class="max-w-7xl mx-auto mb-6 p-4 bg-red-50 border border-red-200 rounded-2xl text-red-700 flex items-center gap-3">
        <span class="text-2xl">⚠️</span>
        <div>
            <p class="font-bold text-sm uppercase">Terjadi Kesalahan Database</p>
            <p class="text-xs font-mono mt-1"><?= htmlspecialchars($error_msg) ?></p>
        </div>
    </div>
    <?php endif; ?>

    <div class="max-w-7xl mx-auto space-y-4 pb-20" id="eventContainer">
        <?php if(empty($events) && empty($error_msg)): ?>
            <div class="bg-white rounded-[2.5rem] p-10 text-center border border-slate-200 shadow-sm">
                <p class="font-bold text-slate-400">Belum ada nomor lomba.</p>
            </div>
        <?php elseif(!empty($events)): ?>
            <?php foreach($events as $ev): 
                $count = $ev['total_athletes']; $isReady = $count > 0; $cat_id = $ev['id'];
                $cardOpacity = $isReady ? 'opacity-100' : 'opacity-60 grayscale';
                $cardBorder = $isReady ? 'border-slate-200 hover:shadow-lg bg-white' : 'border-slate-100 bg-slate-50';
                
                $genderCode = strtoupper($ev['jenis_kelamin'] ?? 'L'); 
                if(in_array($genderCode, ['L', 'MALE', 'PUTRA', 'LAKI-LAKI'])) { $bg='bg-blue-50'; $txt='text-blue-600'; $icon='👨'; $lbl='PUTRA'; } 
                elseif(in_array($genderCode, ['P', 'FEMALE', 'PUTRI', 'PEREMPUAN'])) { $bg='bg-pink-50'; $txt='text-pink-600'; $icon='👩'; $lbl='PUTRI'; } 
                else { $bg='bg-purple-50'; $txt='text-purple-600'; $icon='👫'; $lbl='MIXED'; }

                $ageGroup = isset($ev['age_group']) ? $ev['age_group'] : '-';
                $searchString = strtolower($ev['event_number'] . " " . $ev['event_name'] . " " . $ev['distance'] . " " . $ev['stroke'] . " " . $ageGroup);
            ?>

            <div class="event-item group relative rounded-[2rem] p-5 border transition flex flex-col md:flex-row items-center gap-6 <?= $cardOpacity ?> <?= $cardBorder ?>" 
                 data-search="<?= $searchString ?>">
                
                <div class="shrink-0 w-20 h-20 rounded-3xl bg-slate-900 text-white flex flex-col items-center justify-center shadow-lg shadow-slate-200 group-hover:scale-105 transition">
                    <span class="text-[9px] font-bold text-slate-400 uppercase">Event</span>
                    <span class="text-3xl font-black italic"><?= htmlspecialchars($ev['event_number']) ?></span>
                </div>

                <div class="flex-1 text-center md:text-left">
                    <div class="inline-flex items-center gap-2 mb-1">
                        <span class="px-2 py-1 rounded-md <?= $bg ?> <?= $txt ?> text-[9px] font-black uppercase tracking-widest border border-slate-100"><?= $icon ?> <?= $lbl ?></span>
                        <span class="text-[10px] font-bold text-slate-500 bg-slate-100 px-2 py-1 rounded-md uppercase border border-slate-200"><?= htmlspecialchars($ev['distance']) ?>M <?= htmlspecialchars($ev['stroke']) ?></span>
                        <span class="text-[10px] font-bold text-slate-400 px-1">KU: <?= htmlspecialchars($ageGroup) ?></span>
                    </div>
                    <h3 class="text-xl font-black text-slate-800 uppercase italic tracking-tight"><?= htmlspecialchars($ev['event_name']) ?></h3>
                </div>

                <div class="flex gap-2 w-full md:w-auto">
                    <?php if($isReady): ?>
                        <a href="view_startlist.php?event_id=<?= $cat_id ?>" class="flex-1 md:flex-none px-6 py-3 bg-white hover:bg-slate-50 text-slate-600 rounded-xl font-bold text-xs uppercase tracking-wider border border-slate-200 transition shadow-sm flex items-center justify-center gap-2"><span>👁️</span> View</a>
                        <a href="logic.php?category_id=<?= $cat_id ?>" class="flex-1 md:flex-none px-6 py-3 bg-slate-900 hover:bg-blue-600 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow-lg hover:shadow-blue-200 transition flex items-center justify-center gap-2" onclick="return confirm('Seeding ulang?')"><span>⚙️</span> Generate</a>
                    <?php else: ?>
                        <button disabled class="flex-1 md:flex-none px-6 py-3 bg-slate-100 text-slate-400 rounded-xl font-bold text-xs uppercase border cursor-not-allowed">View</button>
                        <button disabled class="flex-1 md:flex-none px-6 py-3 bg-slate-100 text-slate-400 rounded-xl font-bold text-xs uppercase border cursor-not-allowed opacity-50">Generate</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <div id="noResults" class="hidden text-center py-10">
                <p class="text-slate-400 font-bold italic">Nomor lomba tidak ditemukan.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const items = document.querySelectorAll('.event-item');
    const noResults = document.getElementById('noResults');

    if(searchInput) {
        searchInput.addEventListener('keyup', function(e) {
            const term = e.target.value.toLowerCase();
            let hasVisible = false;

            items.forEach(item => {
                const searchData = item.getAttribute('data-search');
                if(searchData && searchData.includes(term)) {
                    item.style.display = ""; 
                    hasVisible = true;
                } else {
                    item.style.display = "none"; 
                }
            });

            if(noResults) {
                noResults.style.display = hasVisible ? "none" : "block";
            }
        });
    }
});
</script>