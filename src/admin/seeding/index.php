<?php
// FILE: src/admin/seeding/index.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// 1. Cek Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}
$admin_id = $_SESSION['user_id'];

// 2. Ambil Data
$events = [];
$error_msg = null;
try {
    $sql = "SELECT en.*, 
            (SELECT COUNT(ee.id) FROM event_entries ee WHERE ee.category_id = en.id AND ee.event_id = en.organizer_id) as total_athletes
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
            <p class="text-sm text-slate-500 font-bold uppercase tracking-widest mt-2">Manajemen Lintasan</p>
        </div>
        
        <?php 
            $total_all_entries = !empty($events) ? array_sum(array_column($events, 'total_athletes')) : 0;
            $globalDisabled = $total_all_entries == 0 ? 'opacity-50 cursor-not-allowed grayscale' : 'hover:-translate-y-1 shadow-xl hover:shadow-2xl';
        ?>
        
        <div class="flex flex-wrap items-start gap-3">
            
            <a href="generate_all.php" onclick="return confirm('⚠️ PERINGATAN:\nFitur ini akan mengacak ulang lintasan untuk SEMUA nomor.\nLanjutkan?')" 
               class="bg-indigo-600 text-white pl-6 pr-8 py-4 rounded-[2rem] transition flex items-center gap-4 group hover:-translate-y-1 shadow-xl shadow-indigo-200 hover:bg-indigo-700 h-[72px]">
                <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center group-hover:bg-white group-hover:text-indigo-600 transition">⚡</div>
                <div class="text-left">
                    <span class="block text-[9px] font-bold text-indigo-200 uppercase tracking-widest">System</span>
                    <span class="block font-black text-sm uppercase tracking-wider">Auto Seeding</span>
                </div>
            </a>

            <button onclick="openPrintModal()" class="bg-emerald-600 text-white pl-6 pr-8 py-4 rounded-[2rem] transition flex items-center gap-4 group <?= $globalDisabled ?> h-[72px]">
                <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center group-hover:bg-white group-hover:text-emerald-600 transition">🖨️</div>
                <div class="text-left">
                    <span class="block text-[9px] font-bold text-emerald-200 uppercase tracking-widest">Final Book</span>
                    <span class="block font-black text-sm uppercase tracking-wider">Cetak Buku</span>
                </div>
            </button>

        </div>
    </div>

    <div class="max-w-7xl mx-auto mb-6 relative">
        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none"><span class="text-xl grayscale opacity-40">🔍</span></div>
        <input type="text" id="searchInput" class="w-full pl-12 pr-4 py-4 rounded-2xl border-none ring-1 ring-slate-200 shadow-sm focus:ring-2 focus:ring-blue-500 font-bold text-slate-600 placeholder:text-slate-300 placeholder:font-bold transition" placeholder="Cari Nomor Acara...">
    </div>

    <div class="max-w-7xl mx-auto space-y-4 pb-20" id="eventContainer">
        <?php if(!empty($events)): ?>
            <?php foreach($events as $ev): 
                $isReady = $ev['total_athletes'] > 0;
                $cardOpacity = $isReady ? 'opacity-100' : 'opacity-60 grayscale';
                $genderCode = strtoupper($ev['jenis_kelamin'] ?? 'L'); 
                if(in_array($genderCode, ['L', 'MALE', 'PUTRA'])) { $bg='bg-blue-50'; $txt='text-blue-600'; $icon='👨'; $lbl='PUTRA'; } 
                elseif(in_array($genderCode, ['P', 'FEMALE', 'PUTRI'])) { $bg='bg-pink-50'; $txt='text-pink-600'; $icon='👩'; $lbl='PUTRI'; } 
                else { $bg='bg-purple-50'; $txt='text-purple-600'; $icon='👫'; $lbl='MIXED'; }
                $searchString = strtolower($ev['event_number']." ".$ev['event_name']);
            ?>
            <div class="event-item group relative rounded-[2rem] p-5 border border-slate-200 bg-white transition flex flex-col md:flex-row items-center gap-6 <?= $cardOpacity ?>" data-search="<?= $searchString ?>">
                <div class="shrink-0 w-20 h-20 rounded-3xl bg-slate-900 text-white flex flex-col items-center justify-center shadow-lg">
                    <span class="text-[9px] font-bold text-slate-400 uppercase">Event</span>
                    <span class="text-3xl font-black italic"><?= htmlspecialchars($ev['event_number']) ?></span>
                </div>
                <div class="flex-1 text-center md:text-left">
                    <div class="inline-flex items-center gap-2 mb-1">
                        <span class="px-2 py-1 rounded-md <?= $bg ?> <?= $txt ?> text-[9px] font-black uppercase tracking-widest border border-slate-100"><?= $icon ?> <?= $lbl ?></span>
                        <span class="text-[10px] font-bold text-slate-500 bg-slate-100 px-2 py-1 rounded-md uppercase border border-slate-200"><?= htmlspecialchars($ev['distance']) ?>M <?= htmlspecialchars($ev['stroke']) ?></span>
                    </div>
                    <h3 class="text-xl font-black text-slate-800 uppercase italic tracking-tight"><?= htmlspecialchars($ev['event_name']) ?></h3>
                </div>
                <div class="flex gap-2 w-full md:w-auto">
                    <?php if($isReady): ?>
                        <a href="view_startlist.php?event_id=<?= $ev['id'] ?>" class="px-6 py-3 bg-white hover:bg-slate-50 text-slate-600 rounded-xl font-bold text-xs uppercase border border-slate-200">View</a>
                        <a href="logic.php?category_id=<?= $ev['id'] ?>" class="px-6 py-3 bg-slate-900 hover:bg-blue-600 text-white rounded-xl font-bold text-xs uppercase" onclick="return confirm('Seeding ulang?')">Generate</a>
                    <?php else: ?>
                        <button disabled class="px-6 py-3 bg-slate-100 text-slate-400 rounded-xl font-bold text-xs uppercase border cursor-not-allowed">Empty</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div id="printModal" class="fixed inset-0 z-[100] hidden">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closePrintModal()"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-lg bg-white rounded-[2rem] shadow-2xl p-8 border border-slate-200 max-h-[90vh] overflow-y-auto">
        <h3 class="text-2xl font-black text-slate-800 uppercase italic mb-6">🖨️ Konfigurasi Cetak</h3>
        
        <form action="print_full_book.php" method="POST" enctype="multipart/form-data" target="_blank">
            <div class="mb-4">
                <label class="block text-xs font-black text-slate-500 uppercase mb-2">1. Upload Cover (Opsional)</label>
                <input type="file" name="cover_image" accept="image/*" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 border border-slate-200 rounded-xl p-2">
            </div>
            <hr class="border-slate-100 my-4">
            
            <div class="mb-4">
                <label class="block text-xs font-black text-slate-500 uppercase mb-2">2. Susunan Acara</label>
                <div class="bg-yellow-50 p-4 rounded-xl border border-yellow-100 mb-2">
                    <p class="text-[10px] font-bold text-yellow-800 mb-2 uppercase">A. Upload Gambar Jadwal (Excel/Canva)</p>
                    <input type="file" name="schedule_image" accept="image/*" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-yellow-100 file:text-yellow-700 hover:file:bg-yellow-200 border border-slate-200 rounded-xl p-2 bg-white">
                </div>
                <div class="text-center text-[10px] font-bold text-slate-300 my-2">- ATAU -</div>
                <div class="bg-indigo-50 p-4 rounded-xl border border-indigo-100">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="show_schedule_auto" class="w-5 h-5 rounded text-indigo-600 focus:ring-0" checked>
                        <div>
                            <span class="block text-xs font-black text-indigo-900 uppercase">B. Generate Otomatis DB</span>
                            <span class="block text-[9px] text-indigo-400 font-bold">List standar tanpa istirahat</span>
                        </div>
                    </label>
                </div>
            </div>
            <hr class="border-slate-100 my-4">

            <div class="mb-6">
                <p class="text-[10px] font-black text-slate-400 uppercase mb-3">3. Komponen Judul:</p>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer"><input type="checkbox" name="cfg_event_no" class="rounded text-blue-600" checked> Nomor Acara</label>
                    <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer"><input type="checkbox" name="cfg_date" class="rounded text-blue-600" checked> Tanggal & Jam</label>
                    <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer"><input type="checkbox" name="cfg_event_name" class="rounded text-blue-600" checked> Nama Acara</label>
                    <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer"><input type="checkbox" name="cfg_group" class="rounded text-blue-600" checked> Kelompok Umur</label>
                    <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer"><input type="checkbox" name="cfg_gender" class="rounded text-blue-600" checked> Gender</label>
                    <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer"><input type="checkbox" name="cfg_pool" class="rounded text-blue-600" checked> Tipe Kolam</label>
                    <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer"><input type="checkbox" name="cfg_round" class="rounded text-blue-600" checked> Babak</label>
                </div>
            </div>

            <div class="flex gap-3 sticky bottom-0 bg-white pt-2">
                <button type="button" onclick="closePrintModal()" class="flex-1 py-3 bg-slate-100 text-slate-500 rounded-xl font-bold text-xs uppercase hover:bg-slate-200">Batal</button>
                <button type="submit" onclick="closePrintModal()" class="flex-[2] py-3 bg-emerald-600 text-white rounded-xl font-black text-xs uppercase shadow-lg shadow-emerald-200 hover:bg-emerald-700 hover:-translate-y-1 transition">🖨️ PRINT SEKARANG</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openPrintModal() { document.getElementById('printModal').classList.remove('hidden'); }
    function closePrintModal() { document.getElementById('printModal').classList.add('hidden'); }
    
    // Search Script
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const items = document.querySelectorAll('.event-item');
        if(searchInput) {
            searchInput.addEventListener('keyup', function(e) {
                const term = e.target.value.toLowerCase();
                items.forEach(item => {
                    const searchData = item.getAttribute('data-search');
                    item.style.display = (searchData && searchData.includes(term)) ? "" : "none"; 
                });
            });
        }
    });
</script>