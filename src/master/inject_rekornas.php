<?php
// FILE: src/master/inject_rekornas.php
session_start();
require_once __DIR__ . '/../../src/config/database.php';

// Proteksi akses hanya untuk role 'master'
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'master') {
    header("Location: ../../public/login.php"); exit;
}

$msg = '';

// === DATA MENTAH SPECTRA YANG SUDAH DIRAPIKAN & DIKONVERSI ===
$rekornas_data = [
    // GAYA BEBAS
    ['distance' => 50,  'stroke' => 'Gaya Bebas', 'jk' => 'L', 'holder' => 'TRIADY FAUZI SIDIQ', 'time' => '00:22.66', 'ms' => 22660, 'loc' => 'KUALA LUMPUR', 'year' => 2017],
    ['distance' => 50,  'stroke' => 'Gaya Bebas', 'jk' => 'P', 'holder' => 'NADIA AISHA NURAZMI', 'time' => '00:25.68', 'ms' => 25680, 'loc' => 'BANGKOK', 'year' => 2025],
    ['distance' => 100, 'stroke' => 'Gaya Bebas', 'jk' => 'L', 'holder' => 'TRIADY FAUZI SIDIQ', 'time' => '00:49.99', 'ms' => 49990, 'loc' => 'NAY PYI TAW', 'year' => 2013],
    ['distance' => 100, 'stroke' => 'Gaya Bebas', 'jk' => 'P', 'holder' => 'ADELIA CHANTIKA AULIA', 'time' => '00:56.49', 'ms' => 56490, 'loc' => 'JAKARTA', 'year' => 2025],
    ['distance' => 200, 'stroke' => 'Gaya Bebas', 'jk' => 'L', 'holder' => 'JOE ADITYA WIJAYA KURNIAWAN', 'time' => '01:50.35', 'ms' => 110350, 'loc' => 'MEDAN', 'year' => 2024],
    ['distance' => 200, 'stroke' => 'Gaya Bebas', 'jk' => 'P', 'holder' => 'RESSA KANIA DEWI', 'time' => '02:02.74', 'ms' => 122740, 'loc' => 'SINGAPORE', 'year' => 2017],
    ['distance' => 400, 'stroke' => 'Gaya Bebas', 'jk' => 'L', 'holder' => 'AFLAH FADLAN PRAWIRA', 'time' => '03:52.16', 'ms' => 232160, 'loc' => 'SINGAPORE', 'year' => 2019],
    ['distance' => 400, 'stroke' => 'Gaya Bebas', 'jk' => 'P', 'holder' => 'ADINDA LARASATI DEWI KIRANA', 'time' => '04:16.84', 'ms' => 256840, 'loc' => 'SURABAYA', 'year' => 2018],
    ['distance' => 800, 'stroke' => 'Gaya Bebas', 'jk' => 'L', 'holder' => 'AFLAH FADLAN PRAWIRA', 'time' => '08:03.87', 'ms' => 483870, 'loc' => 'JAKARTA', 'year' => 2018],
    ['distance' => 800, 'stroke' => 'Gaya Bebas', 'jk' => 'P', 'holder' => 'ADINDA LARASATI DEWI KIRANA', 'time' => '08:52.80', 'ms' => 532800, 'loc' => 'SURABAYA', 'year' => 2018],
    ['distance' => 1500,'stroke' => 'Gaya Bebas', 'jk' => 'L', 'holder' => 'AFLAH FADLAN PRAWIRA', 'time' => '15:15.77', 'ms' => 915770, 'loc' => 'NEW CLARK CITY', 'year' => 2019],
    ['distance' => 1500,'stroke' => 'Gaya Bebas', 'jk' => 'P', 'holder' => 'MAGDALENA SUTANTO', 'time' => '17:05.38', 'ms' => 1025380, 'loc' => 'SEATTLE', 'year' => 2005],

    // GAYA KUPU-KUPU
    ['distance' => 50,  'stroke' => 'Gaya Kupu-kupu', 'jk' => 'L', 'holder' => 'GLENN VICTOR SUTANTO', 'time' => '00:23.84', 'ms' => 23840, 'loc' => 'NEW CLARK CITY', 'year' => 2019],
    ['distance' => 50,  'stroke' => 'Gaya Kupu-kupu', 'jk' => 'P', 'holder' => 'ANGEL GABRIELLA YUS', 'time' => '00:27.40', 'ms' => 27400, 'loc' => 'KAB JAYAPURA', 'year' => 2021],
    ['distance' => 100, 'stroke' => 'Gaya Kupu-kupu', 'jk' => 'L', 'holder' => 'JOE ADITYA WIJAYA KURNIAWAN', 'time' => '00:52.75', 'ms' => 52750, 'loc' => 'JAKARTA', 'year' => 2023],
    ['distance' => 100, 'stroke' => 'Gaya Kupu-kupu', 'jk' => 'P', 'holder' => 'ADINDA LARASATI DEWI KIRANA', 'time' => '01:00.55', 'ms' => 60550, 'loc' => 'JAKARTA', 'year' => 2019],
    ['distance' => 200, 'stroke' => 'Gaya Kupu-kupu', 'jk' => 'L', 'holder' => 'TRIADY FAUZI SIDIQ', 'time' => '01:59.66', 'ms' => 119660, 'loc' => 'PALEMBANG', 'year' => 2013],
    ['distance' => 200, 'stroke' => 'Gaya Kupu-kupu', 'jk' => 'P', 'holder' => 'ADINDA LARASATI DEWI KIRANA', 'time' => '02:12.84', 'ms' => 132840, 'loc' => 'JAKARTA', 'year' => 2019],

    // GAYA GANTI
    ['distance' => 200, 'stroke' => 'Gaya Ganti Perorangan', 'jk' => 'L', 'holder' => 'TRIADY FAUZI SIDIQ', 'time' => '02:01.72', 'ms' => 121720, 'loc' => 'KUALA LUMPUR', 'year' => 2017],
    ['distance' => 200, 'stroke' => 'Gaya Ganti Perorangan', 'jk' => 'P', 'holder' => 'AZZAHRA PERMATAHANI', 'time' => '02:16.43', 'ms' => 136430, 'loc' => 'JAKARTA', 'year' => 2019],
    ['distance' => 400, 'stroke' => 'Gaya Ganti Perorangan', 'jk' => 'L', 'holder' => 'AFLAH FADLAN PRAWIRA', 'time' => '04:21.30', 'ms' => 261300, 'loc' => 'NEW CLARK CITY', 'year' => 2019],
    ['distance' => 400, 'stroke' => 'Gaya Ganti Perorangan', 'jk' => 'P', 'holder' => 'AZZAHRA PERMATAHANI', 'time' => '04:48.51', 'ms' => 288510, 'loc' => 'SINGAPORE', 'year' => 2019],

    // GAYA PUNGGUNG
    ['distance' => 50,  'stroke' => 'Gaya Punggung', 'jk' => 'L', 'holder' => 'I GEDE SIMAN SUDARTAWA', 'time' => '00:25.01', 'ms' => 25010, 'loc' => 'JAKARTA', 'year' => 2018],
    ['distance' => 50,  'stroke' => 'Gaya Punggung', 'jk' => 'P', 'holder' => 'MASNIARI WOLF', 'time' => '00:28.80', 'ms' => 28800, 'loc' => 'BANGKOK', 'year' => 2025],
    ['distance' => 100, 'stroke' => 'Gaya Punggung', 'jk' => 'L', 'holder' => 'I GEDE SIMAN SUDARTAWA', 'time' => '00:54.94', 'ms' => 54940, 'loc' => 'KUALA LUMPUR', 'year' => 2017],
    ['distance' => 100, 'stroke' => 'Gaya Punggung', 'jk' => 'P', 'holder' => 'FLAIRENE CANDREA W', 'time' => '01:02.25', 'ms' => 62250, 'loc' => 'JAKARTA', 'year' => 2025],
    ['distance' => 200, 'stroke' => 'Gaya Punggung', 'jk' => 'L', 'holder' => 'FARREL ARMANDIO TANGKAS', 'time' => '02:01.16', 'ms' => 121160, 'loc' => 'JAKARTA', 'year' => 2019],
    ['distance' => 200, 'stroke' => 'Gaya Punggung', 'jk' => 'P', 'holder' => 'YESSY VENISIA YOSAPUTRA', 'time' => '02:15.73', 'ms' => 135730, 'loc' => 'PALEMBANG', 'year' => 2011],

    // GAYA DADA
    ['distance' => 50,  'stroke' => 'Gaya Dada', 'jk' => 'L', 'holder' => 'FELIX VIKTOR IBERLE', 'time' => '00:26.98', 'ms' => 26980, 'loc' => 'NETANYA', 'year' => 2023],
    ['distance' => 50,  'stroke' => 'Gaya Dada', 'jk' => 'P', 'holder' => 'ANANDIA TRECIEL VANESSAE EVATO', 'time' => '00:32.13', 'ms' => 32130, 'loc' => 'SINGAPORE', 'year' => 2017],
    ['distance' => 100, 'stroke' => 'Gaya Dada', 'jk' => 'L', 'holder' => 'ARYA ANDREAN PUTRA HARYONO', 'time' => '01:01.75', 'ms' => 61750, 'loc' => 'JAKARTA', 'year' => 2026],
    ['distance' => 100, 'stroke' => 'Gaya Dada', 'jk' => 'P', 'holder' => 'ANANDIA TRECIEL VANESSAE EVATO', 'time' => '01:09.78', 'ms' => 69780, 'loc' => 'JAKARTA', 'year' => 2018],
    ['distance' => 200, 'stroke' => 'Gaya Dada', 'jk' => 'L', 'holder' => 'GAGARIN NATHANIEL YUS', 'time' => '02:15.36', 'ms' => 135360, 'loc' => 'PALEMBANG', 'year' => 2017],
    ['distance' => 200, 'stroke' => 'Gaya Dada', 'jk' => 'P', 'holder' => 'ADELLIA', 'time' => '02:32.09', 'ms' => 152090, 'loc' => 'SINGAPORE', 'year' => 2025]
];

// === PROSES INJEKSI JIKA TOMBOL DITEKAN ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'inject_now') {
    try {
        // Mulai transaksi database
        $pdo->beginTransaction();

        // 1. Hapus seluruh data rekornas lama agar tidak double
        $pdo->exec("DELETE FROM master_records WHERE record_type = 'rekornas'");

        // 2. Siapkan query insert
        $sql = "INSERT INTO master_records (record_type, record_name, distance, stroke, jenis_kelamin, age_group, holder_name, location, record_year, record_time, record_time_ms) 
                VALUES ('rekornas', 'REKOR NASIONAL', ?, ?, ?, 'SENIOR', ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);

        $count = 0;
        foreach ($rekornas_data as $r) {
            $stmt->execute([
                $r['distance'], 
                $r['stroke'], 
                $r['jk'], 
                $r['holder'], 
                $r['loc'], 
                $r['year'], 
                $r['time'], 
                $r['ms']
            ]);
            $count++;
        }

        // Simpan perubahan
        $pdo->commit();
        $msg = "<div class='p-4 mb-6 text-sm text-green-700 bg-green-100 rounded-xl border border-green-200'>
                    <strong>✅ Berhasil!</strong> Injeksi <strong>$count</strong> data Rekor Nasional dari Spectra telah berhasil dimasukkan ke dalam database.
                </div>";
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = "<div class='p-4 mb-6 text-sm text-red-700 bg-red-100 rounded-xl border border-red-200'>
                    <strong>❌ Gagal:</strong> " . $e->getMessage() . "
                </div>";
    }
}

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    <div class="max-w-5xl mx-auto px-4 py-4">
        
        <div class="flex justify-between items-center mb-6">
            <div>
                <a href="manage_records.php?tab=rekornas" class="text-blue-600 hover:text-blue-800 text-sm font-bold flex items-center gap-1 mb-2">
                    &larr; Kembali ke Kelola Rekor
                </a>
                <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tighter">INJEKSI REKORNAS (SPECTRA)</h1>
                <p class="text-slate-500 text-sm mt-1">Halaman khusus untuk mereset dan menyuntikkan data rekor nasional standar secara otomatis.</p>
            </div>
        </div>

        <?= $msg ?>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-6">
            <div class="p-6 bg-blue-50 border-b border-blue-100 flex flex-col sm:flex-row justify-between items-center gap-4">
                <div>
                    <h2 class="font-bold text-blue-900 text-lg uppercase tracking-wide">Sinkronisasi Database Rekornas</h2>
                    <p class="text-blue-700 text-xs mt-1">Sistem akan menghapus data Rekornas lama Anda dan menggantinya dengan <strong><?= count($rekornas_data) ?> data</strong> di bawah ini.</p>
                </div>
                
                <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin MENGHAPUS data rekornas lama dan MENGINJEKSI ulang data ini?');">
                    <input type="hidden" name="action" value="inject_now">
                    <button type="submit" class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-black text-sm rounded-xl uppercase tracking-wider shadow-lg shadow-red-200 transition">
                        ⚡ Eksekusi Injeksi Sekarang
                    </button>
                </form>
            </div>
            
            <div class="overflow-x-auto max-h-[600px]">
                <table class="w-full text-left border-collapse">
                    <thead class="sticky top-0 z-10">
                        <tr class="bg-slate-800 text-slate-200 font-bold text-xs tracking-wider uppercase">
                            <th class="p-3">No</th>
                            <th class="p-3">Nomor Lomba</th>
                            <th class="p-3 text-center">JK</th>
                            <th class="p-3">Nama Pemegang</th>
                            <th class="p-3 text-center">Waktu</th>
                            <th class="p-3">Lokasi (Tahun)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs font-medium">
                        <?php foreach($rekornas_data as $index => $r): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="p-3 text-slate-400 text-center"><?= $index + 1 ?></td>
                                <td class="p-3 font-bold text-slate-900"><?= $r['distance'] ?>M <?= $r['stroke'] ?></td>
                                <td class="p-3 text-center">
                                    <span class="px-2 py-0.5 rounded <?= $r['jk']=='L'?'bg-sky-100 text-sky-700':'bg-rose-100 text-rose-700' ?>"><?= $r['jk'] ?></span>
                                </td>
                                <td class="p-3 text-slate-700 uppercase font-bold"><?= $r['holder'] ?></td>
                                <td class="p-3 text-center font-mono font-black text-emerald-600"><?= $r['time'] ?></td>
                                <td class="p-3 text-slate-500"><?= $r['loc'] ?> (<?= $r['year'] ?>)</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>