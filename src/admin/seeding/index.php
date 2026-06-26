<?php
// FILE: src/admin/seeding/index.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// 1. Cek Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}
$admin_id = $_SESSION['user_id'];

// --- 2. AMBIL ID EVENT TERAKHIR MILIK ADMIN ---
$targetEventId = $_GET['event_id'] ?? 0;
if ($targetEventId == 0) {
    $stmtLastEvt = $pdo->prepare("SELECT id FROM events WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmtLastEvt->execute([$admin_id]);
    $targetEventId = $stmtLastEvt->fetchColumn() ?: 0;
}

// 3. Ambil Data Nomor Lomba
$events = [];
$error_msg = null;
try {
    $sql = "SELECT en.*, 
            (SELECT COUNT(ee.id) FROM event_entries ee WHERE ee.category_id = en.id AND ee.event_id = ?) as total_athletes
            FROM event_numbers en 
            WHERE en.event_id = ? 
            ORDER BY CAST(en.event_number AS UNSIGNED) ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$targetEventId, $targetEventId]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msg = "Database Error: " . $e->getMessage();
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">

    <div class="max-w-7xl mx-auto mb-6 flex flex-col lg:flex-row justify-between items-start gap-6">
        <div>
            <h1 class="text-4xl font-black uppercase tracking-tighter italic text-slate-900 leading-none">Seeding & Start List</h1>
            <p class="text-sm text-slate-500 font-bold uppercase tracking-widest mt-2">Manajemen Lintasan</p>
        </div>
        
        <?php 
            $total_all_entries = !empty($events) ? array_sum(array_column($events, 'total_athletes')) : 0;
            $globalDisabled = $total_all_entries == 0 ? 'opacity-50 cursor-not-allowed grayscale' : 'hover:-translate-y-1 shadow-xl hover:shadow-2xl';
        ?>
        
        <div class="flex flex-wrap items-start gap-3">
            <a href="generate_all.php?event_id=<?= $targetEventId ?>" onclick="return confirm('⚠️ PERINGATAN:\nFitur ini akan mengacak ulang lintasan untuk SEMUA nomor.\nLanjutkan?')" 
               class="bg-indigo-600 text-white pl-6 pr-8 py-4 rounded-[2rem] transition flex items-center gap-4 group hover:-translate-y-1 shadow-xl shadow-indigo-200 hover:bg-indigo-700 h-[72px]">
                <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center group-hover:bg-white group-hover:text-indigo-600 transition">⚡</div>
                <div class="text-left">
                    <span class="block text-[9px] font-bold text-indigo-200 uppercase tracking-widest">System</span>
                    <span class="block font-black text-sm uppercase tracking-wider">Auto Seeding</span>
                </div>
            </a>

            <a href="list_clubs_recap.php?event_id=<?= $targetEventId ?>" 
               class="bg-fuchsia-600 text-white pl-6 pr-8 py-4 rounded-[2rem] transition flex items-center gap-4 group hover:-translate-y-1 shadow-xl shadow-fuchsia-200 hover:bg-fuchsia-700 h-[72px]">
                <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center group-hover:bg-white group-hover:text-fuchsia-600 transition">📋</div>
                <div class="text-left">
                    <span class="block text-[9px] font-bold text-fuchsia-200 uppercase tracking-widest">Starting List</span>
                    <span class="block font-black text-sm uppercase tracking-wider">Recap Klub</span>
                </div>
            </a>

            <button type="button" onclick="if(<?= $total_all_entries ?> > 0) { document.getElementById('configForm').submit(); }" class="bg-emerald-600 text-white pl-6 pr-8 py-4 rounded-[2rem] transition flex items-center gap-4 group <?= $globalDisabled ?> h-[72px]">
                <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center group-hover:bg-white group-hover:text-emerald-600 transition">🖨️</div>
                <div class="text-left">
                    <span class="block text-[9px] font-bold text-emerald-200 uppercase tracking-widest">Final Book</span>
                    <span class="block font-black text-sm uppercase tracking-wider">Cetak Buku</span>
                </div>
            </button>
        </div>
    </div>

    <?php if($targetEventId == 0): ?>
        <div class="max-w-7xl mx-auto flex flex-col items-center justify-center py-20 text-center opacity-50">
            <div class="text-5xl mb-4 grayscale">⚠️</div>
            <h3 class="font-black text-slate-400 uppercase tracking-widest text-lg">Anda Belum Memiliki Event Aktif</h3>
        </div>
    <?php else: ?>

        <form id="configForm" action="print_full_book.php" method="POST" enctype="multipart/form-data" target="_blank" class="max-w-7xl mx-auto mb-6 bg-white rounded-[2rem] border border-slate-200 shadow-sm p-6 sm:p-8">
            <input type="hidden" name="event_id" value="<?= $targetEventId ?>">
            
            <div class="flex items-center gap-2 border-b border-slate-100 pb-3 mb-4">
                <span class="text-xl">⚙️</span>
                <h3 class="text-sm font-black text-slate-800 uppercase tracking-wider">Panel Pengaturan Buku Acara & Kolam Lintasan</h3>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <div class="space-y-3">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">1. Upload Cover (Opsional)</label>
                        <input type="file" name="cover_image" accept="image/*" class="block w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-[10px] file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 border border-slate-200 rounded-xl p-1 bg-slate-50/50">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">2. Gambar Jadwal Acara (Opsional)</label>
                        <input type="file" name="schedule_image" accept="image/*" class="block w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-[10px] file:font-bold file:bg-yellow-50 file:text-yellow-700 hover:file:bg-yellow-100 border border-slate-200 rounded-xl p-1 bg-slate-50/50">
                    </div>
                    <div class="bg-indigo-50/70 px-3 py-2 rounded-xl border border-indigo-100/70 flex items-center">
                        <label class="flex items-center gap-2 cursor-pointer select-none w-full">
                            <input type="checkbox" name="show_schedule_auto" class="w-4 h-4 rounded text-indigo-600 focus:ring-0 border-indigo-300 config-cb" checked>
                            <div>
                                <span class="block text-[10px] font-black text-indigo-900 uppercase leading-tight">Generate Jadwal Otomatis DB</span>
                                <span class="block text-[8px] text-indigo-400 font-bold">List standar tanpa jeda istirahat</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="border-t lg:border-t-0 lg:border-x border-slate-100 lg:px-6">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2.5">3. Komponen Judul Acara:</label>
                    <div class="grid grid-cols-2 gap-x-2 gap-y-1.5 text-xs font-bold text-slate-600">
                        <label class="flex items-center gap-2 cursor-pointer select-none"><input type="checkbox" name="cfg_event_no" value="1" class="rounded border-slate-300 text-blue-600 config-cb" checked> Nomor Acara</label>
                        <label class="flex items-center gap-2 cursor-pointer select-none"><input type="checkbox" name="cfg_date" value="1" class="rounded border-slate-300 text-blue-600 config-cb" checked> Tanggal & Jam</label>
                        <label class="flex items-center gap-2 cursor-pointer select-none"><input type="checkbox" name="cfg_event_name" value="1" class="rounded border-slate-300 text-blue-600 config-cb" checked> Jarak & Gaya</label>
                        <label class="flex items-center gap-2 cursor-pointer select-none"><input type="checkbox" name="cfg_group" value="1" class="rounded border-slate-300 text-blue-600 config-cb" checked> Kelompok Umur</label>
                        <label class="flex items-center gap-2 cursor-pointer select-none"><input type="checkbox" name="cfg_gender" value="1" class="rounded border-slate-300 text-blue-600 config-cb" checked> Jenis Kelamin</label>
                        <label class="flex items-center gap-2 cursor-pointer select-none"><input type="checkbox" name="cfg_pool" value="1" class="rounded border-slate-300 text-blue-600 config-cb" checked> Tipe Kolam</label>
                        <label class="flex items-center gap-2 cursor-pointer select-none"><input type="checkbox" name="cfg_round" value="1" class="rounded border-slate-300 text-blue-600 config-cb" checked> Babak (FINAL)</label>
                        <label class="flex items-center gap-2 cursor-pointer select-none text-amber-700 font-extrabold"><input type="checkbox" name="cfg_show_records" value="1" class="rounded border-amber-300 text-amber-600 config-cb" checked> Tampilkan Rekor</label>
                    </div>
                </div>

                <div class="flex flex-col justify-between">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2.5">4. Kolom Tabel Atlet:</label>
                        <div class="grid grid-cols-2 gap-x-2 gap-y-1.5 text-xs font-bold text-slate-600">
                            <label class="flex items-center gap-2 cursor-pointer select-none"><input type="checkbox" name="col_uid" value="1" class="rounded border-slate-300 text-blue-600 config-cb" checked> Kolom UID</label>
                            <label class="flex items-center gap-2 cursor-pointer select-none"><input type="checkbox" name="col_lahir" value="1" class="rounded border-slate-300 text-blue-600 config-cb" checked> Tahun Lahir</label>
                            <label class="flex items-center gap-2 cursor-pointer select-none"><input type="checkbox" name="col_ku" value="1" class="rounded border-slate-300 text-blue-600 config-cb" checked> Kolom KU</label>
                            <label class="flex items-center gap-2 cursor-pointer select-none"><input type="checkbox" name="col_tim" value="1" class="rounded border-slate-300 text-blue-600 config-cb" checked> TIM / Klub</label>
                            <label class="flex items-center gap-2 cursor-pointer select-none"><input type="checkbox" name="col_waktu" value="1" class="rounded border-slate-300 text-blue-600 config-cb" checked> Waktu Entry</label>
                            <label class="flex items-center gap-2 cursor-pointer select-none"><input type="checkbox" name="col_hasil" value="1" class="rounded border-slate-300 text-blue-600 config-cb" checked> Lembar Titik</label>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-3 border-t border-slate-100">
                        <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs uppercase tracking-wider rounded-xl transition shadow-md hover:-translate-y-0.5 flex items-center justify-center gap-2">
                            🖨️ PRINT FULL MEET BOOK
                        </button>
                    </div>
                </div>

            </div>
            
            <div class="mt-4 text-[10px] text-slate-400 font-bold bg-slate-50 px-3 py-1.5 rounded-xl border border-slate-100">
                💡 <b>Tips Multi-Fungsi:</b> Setiap kali Anda mencentang/melepas pilihan di atas, tombol <b>"View"</b> pada list lomba di bawah akan otomatis menyesuaikan konfigurasinya secara langsung.
            </div>
        </form>

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
                        <span class="text-3xl font-black italic"><?= htmlspecialchars($ev['event_number'] ?? '-') ?></span>
                    </div>
                    <div class="flex-1 text-center md:text-left">
                        <div class="inline-flex items-center gap-2 mb-1">
                            <span class="px-2 py-1 rounded-md <?= $bg ?> <?= $txt ?> text-[9px] font-black uppercase tracking-widest border border-slate-100"><?= $icon ?> <?= $lbl ?></span>
                            <span class="text-[10px] font-bold text-slate-500 bg-slate-100 px-2 py-1 rounded-md uppercase border border-slate-200"><?= htmlspecialchars($ev['distance'] ?? '0') ?>M <?= htmlspecialchars($ev['stroke'] ?? '-') ?></span>
                            <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded-md uppercase border border-emerald-200">👥 <?= $ev['total_athletes'] ?> Atlet</span>
                        </div>
                        <h3 class="text-xl font-black text-slate-800 uppercase italic tracking-tight"><?= htmlspecialchars($ev['event_name'] ?? 'Nomor Lomba') ?></h3>
                    </div>
                    <div class="flex gap-2 w-full md:w-auto">
                        <?php if($isReady): ?>
                            <a href="view_startlist.php?category_id=<?= $ev['id'] ?>" data-base-url="view_startlist.php?category_id=<?= $ev['id'] ?>" class="view-startlist-btn px-6 py-3 bg-white hover:bg-slate-50 text-slate-600 rounded-xl font-bold text-xs uppercase border border-slate-200 text-center">View</a>
                            
                            <a href="logic.php?category_id=<?= $ev['id'] ?>" class="px-6 py-3 bg-slate-900 hover:bg-blue-600 text-white rounded-xl font-bold text-xs uppercase text-center" onclick="return confirm('Seeding ulang nomor ini?')">Generate</a>
                        <?php else: ?>
                            <button disabled class="px-6 py-3 bg-slate-100 text-slate-400 rounded-xl font-bold text-xs uppercase border cursor-not-allowed">Empty</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-20">
                    <p class="text-slate-400 font-bold italic">Belum ada nomor lomba untuk event ini.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    /**
     * 🧠 SCRIPT PINTAR INJEKSI PARAMETER
     * Mengambil seluruh status checkbox di panel atas, lalu menyuntikkannya ke URL tombol "View" secara real-time.
     */
    function updateDynamicViewLinks() {
        const form = document.getElementById('configForm');
        if (!form) return;
        
        const checkboxes = form.querySelectorAll('.config-cb');
        let queryParts = [];
        
        checkboxes.forEach(cb => {
            if (cb.checked) {
                queryParts.push(encodeURIComponent(cb.name) + '=1');
            }
        });
        
        const queryString = queryParts.join('&');
        const viewButtons = document.querySelectorAll('.view-startlist-btn');
        
        viewButtons.forEach(btn => {
            const baseUrl = btn.getAttribute('data-base-url');
            btn.href = baseUrl + (queryString ? '&' + queryString : '');
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        // 1. Logika Live Search Nomor Acara
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

        // 2. Pasang Event Listener ke seluruh checkbox di Panel Konfigurasi
        const form = document.getElementById('configForm');
        if (form) {
            form.querySelectorAll('.config-cb').forEach(cb => {
                cb.addEventListener('change', updateDynamicViewLinks);
            });
            // Jalankan sekali di awal load agar link langsung siap bawaan default checked
            updateDynamicViewLinks();
        }
    });
</script>