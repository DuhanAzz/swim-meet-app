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

        <form action="process_aggregate.php" method="POST" class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            
            <div class="p-6 border-b border-slate-100 bg-slate-50">
                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Nama Paket Rekor</label>
                <input type="text" name="package_name" required placeholder="Contoh: GRUP REKOR O2SN PROVINSI" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-3 font-black text-lg text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none uppercase">
                <p class="text-xs text-slate-400 mt-2">Nama paket ini akan muncul di dropdown Admin Event saat mereka melakukan konfigurasi acara.</p>
            </div>

            <div class="p-6">
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

            <div class="p-6 bg-slate-50 border-t border-slate-100 flex justify-end">
                <button type="submit" class="px-8 py-3.5 bg-blue-600 text-white font-bold text-sm rounded-xl uppercase tracking-wider hover:bg-blue-700 shadow-xl shadow-blue-200 transition">
                    Agregasi Waktu & Buat Paket
                </button>
            </div>

        </form>

    </div>
</div>
