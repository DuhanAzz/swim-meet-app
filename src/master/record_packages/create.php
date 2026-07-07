<?php
// FILE: src/master/record_packages/create.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// Proteksi akses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'master') {
    header("Location: ../../../public/login.php"); exit;
}

// Filter dilonggarkan agar memunculkan semua event untuk kemudahan testing.
// Kita bisa menampilkan semua event, lalu mengurutkannya dari yang terbaru.
$sqlEvents = "
    SELECT id, event_name, YEAR(event_date_start) as event_year, event_city 
    FROM events 
    ORDER BY event_date_start DESC
";
$events = $pdo->query($sqlEvents)->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    <div class="max-w-4xl mx-auto px-4 py-4">
        
        <div class="mb-8">
            <a href="index.php" class="text-blue-600 hover:underline font-bold text-sm mb-2 inline-block">← Kembali ke Daftar Paket</a>
            <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tighter">BUAT PAKET REKOR BARU</h1>
            <p class="text-slate-500 text-sm mt-1">Pilih event-event historis untuk diagregasi dan dijadikan paket rekor acuan.</p>
        </div>

        <form action="process_aggregate.php" method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            
            <div class="p-6 border-b border-slate-100 bg-slate-50">
                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Nama Paket Rekor</label>
                <input type="text" name="package_name" required placeholder="Contoh: GRUP REKOR O2SN PROVINSI" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-3 font-black text-lg text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none uppercase">
                <p class="text-xs text-slate-400 mt-2">Nama paket ini akan muncul di dropdown Admin Event saat mereka melakukan konfigurasi acara.</p>
            </div>

            <div class="p-6 border-b border-slate-100">
                <label class="block text-xs font-bold text-slate-600 uppercase mb-3">Metode Pengisian Rekor</label>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <label class="flex items-center gap-3 p-4 bg-white border border-slate-200 rounded-xl cursor-pointer hover:bg-blue-50 hover:border-blue-200 transition">
                        <input type="radio" name="creation_method" value="aggregate" checked onchange="toggleMethod()" class="w-5 h-5 text-blue-600 focus:ring-blue-500">
                        <span class="font-bold text-slate-700 text-sm">Agregasi Event Historis</span>
                    </label>
                    <label class="flex items-center gap-3 p-4 bg-white border border-slate-200 rounded-xl cursor-pointer hover:bg-blue-50 hover:border-blue-200 transition">
                        <input type="radio" name="creation_method" value="manual" onchange="toggleMethod()" class="w-5 h-5 text-blue-600 focus:ring-blue-500">
                        <span class="font-bold text-slate-700 text-sm">Input Manual (Satu per Satu)</span>
                    </label>
                    <label class="flex items-center gap-3 p-4 bg-white border border-slate-200 rounded-xl cursor-pointer hover:bg-blue-50 hover:border-blue-200 transition">
                        <input type="radio" name="creation_method" value="csv" onchange="toggleMethod()" class="w-5 h-5 text-blue-600 focus:ring-blue-500">
                        <span class="font-bold text-slate-700 text-sm">Upload File CSV</span>
                    </label>
                </div>
            </div>

            <!-- SECTION: AGREGASI HISTORIS -->
            <div id="section-aggregate" class="p-6">
                <label class="block text-xs font-bold text-slate-600 uppercase mb-3">Pilih Event Historis Sumber Rekor</label>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 h-80 overflow-y-auto">
                    <?php if(empty($events)): ?>
                        <div class="text-center text-slate-400 italic py-8">Belum ada event yang tersimpan di database.</div>
                    <?php else: ?>
                        <div class="space-y-2">
                            <?php foreach($events as $ev): ?>
                                <label class="flex items-center gap-3 p-3 bg-white border border-slate-200 rounded-lg cursor-pointer hover:bg-blue-50 transition">
                                    <input type="checkbox" name="source_event_ids[]" value="<?= $ev['id'] ?>" class="w-5 h-5 text-blue-600 rounded border-gray-300">
                                    <div class="flex-1">
                                        <div class="font-bold text-slate-800 uppercase text-sm"><?= htmlspecialchars($ev['event_name']) ?></div>
                                        <div class="text-[11px] text-slate-500 font-medium">Tahun: <?= $ev['event_year'] ?> | Lokasi: <?= htmlspecialchars($ev['event_city']) ?></div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <p class="text-xs text-slate-500 mt-3 font-medium bg-blue-50 p-3 rounded-lg border border-blue-100">ℹ️ <strong class="text-blue-800">Cara Kerja:</strong> Sistem akan mencari perenang tercepat (MIN time) dari seluruh event yang Anda centang di atas untuk masing-masing kategori umur, jarak, gaya, dan jenis kelamin.</p>
            </div>

            <!-- SECTION: INPUT MANUAL -->
            <div id="section-manual" class="p-6 hidden">
                <div class="text-center py-10 bg-slate-50 border border-slate-200 rounded-xl border-dashed">
                    <p class="text-slate-500 font-medium">Anda akan diarahkan ke halaman input manual setelah paket ini dibuat.</p>
                </div>
            </div>

            <!-- SECTION: UPLOAD CSV -->
            <div id="section-csv" class="p-6 hidden">
                <label class="block text-xs font-bold text-slate-600 uppercase mb-3">Upload File Laporan (.CSV)</label>
                <div class="flex items-center justify-center w-full">
                    <label id="dropzone-container" for="dropzone-file" class="flex flex-col items-center justify-center w-full h-64 border-2 border-slate-300 border-dashed rounded-2xl cursor-pointer bg-slate-50 hover:bg-slate-100 hover:border-blue-400 transition">
                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                            <svg class="w-10 h-10 mb-4 text-slate-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 16">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 13h3a3 3 0 0 0 0-6h-.025A5.56 5.56 0 0 0 16 6.5 5.5 5.5 0 0 0 5.207 5.021C5.137 5.017 5.071 5 5 5a4 4 0 0 0 0 8h2.167M10 15V6m0 0L8 8m2-2 2 2"/>
                            </svg>
                            <p id="dropzone-text" class="mb-2 text-sm text-slate-500 font-bold"><span class="font-black text-blue-600">Klik untuk upload</span> atau drag and drop</p>
                            <p class="text-xs text-slate-400 uppercase tracking-widest">HANYA FILE .CSV (BISA PILIH LEBIH DARI 1)</p>
                        </div>
                        <input id="dropzone-file" type="file" name="csv_file[]" accept=".csv" multiple class="hidden" />
                    </label>
                </div>
                <p class="text-xs text-slate-500 mt-3 font-medium bg-amber-50 p-3 rounded-lg border border-amber-100">ℹ️ <strong class="text-amber-800">Format Laporan:</strong> Pastikan format laporan bertingkat standar Meet Manager. Baris 'Acara' sebagai header blok, lalu tabel rank di bawahnya.</p>
            </div>

            <div class="p-6 bg-slate-50 border-t border-slate-100 flex justify-end">
                <button type="submit" id="btn-submit" class="px-8 py-3.5 bg-blue-600 text-white font-bold text-sm rounded-xl uppercase tracking-wider hover:bg-blue-700 shadow-xl shadow-blue-200 transition">
                    Agregasi Waktu & Buat Paket
                </button>
            </div>

        </form>

    </div>
</div>

<script>
function toggleMethod() {
    const val = document.querySelector('input[name="creation_method"]:checked').value;
    
    document.getElementById('section-aggregate').classList.add('hidden');
    document.getElementById('section-manual').classList.add('hidden');
    document.getElementById('section-csv').classList.add('hidden');
    
    let btnText = "Buat Paket";
    if (val === 'aggregate') {
        document.getElementById('section-aggregate').classList.remove('hidden');
        btnText = "Agregasi Waktu & Buat Paket";
    } else if (val === 'manual') {
        document.getElementById('section-manual').classList.remove('hidden');
        btnText = "Buat Paket & Input Manual";
    } else if (val === 'csv') {
        document.getElementById('section-csv').classList.remove('hidden');
        btnText = "Upload CSV & Buat Paket";
    }
    
    document.getElementById('btn-submit').innerText = btnText;
}

// Menampilkan nama file CSV yang dipilih
document.getElementById('dropzone-file').addEventListener('change', function(e) {
    const files = e.target.files;
    if (files.length > 0) {
        let fileNames = [];
        for (let i = 0; i < files.length; i++) {
            fileNames.push(files[i].name);
        }
        let displayText = fileNames.join(', ');
        if (displayText.length > 60) displayText = displayText.substring(0, 60) + '...';
        
        document.getElementById('dropzone-text').innerHTML = `<span class="font-black text-emerald-600">${files.length} File terpilih:</span> ${displayText}`;
        document.getElementById('dropzone-container').classList.add('border-emerald-400', 'bg-emerald-50');
        document.getElementById('dropzone-container').classList.remove('border-slate-300', 'bg-slate-50');
    }
});

// Inisialisasi awal
document.addEventListener('DOMContentLoaded', toggleMethod);
</script>
