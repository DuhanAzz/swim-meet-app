<?php
// FILE: src/user/kompetisi/detail.php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    die("Akses Ditolak.");
}

// 1. Tangkap ID Event dari URL (explore.php mengirimkan event_id)
$event_id = $_GET['event_id'] ?? ($_GET['id'] ?? 0);

// 2. Ambil Info Event Utama dari tabel events
$stmt = $pdo->prepare("
    SELECT e.*, u.nama_lengkap as penyelenggara 
    FROM events e 
    LEFT JOIN users u ON e.user_id = u.id 
    WHERE e.id = ?
");
$stmt->execute([$event_id]);
$eventInfo = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$eventInfo) { 
    die("Data Event tidak ditemukan atau sudah dihapus."); 
}

// 3. Ambil Daftar Lomba dari tabel event_numbers
$stmtRace = $pdo->prepare("
    SELECT * FROM event_numbers 
    WHERE event_id = ? 
    ORDER BY CAST(event_number AS UNSIGNED) ASC
");
$stmtRace->execute([$event_id]);
$raceList = $stmtRace->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 mb-8 relative overflow-hidden">
        <div class="absolute top-0 right-0 w-64 h-64 bg-blue-50 rounded-full -mr-20 -mt-20 opacity-50 pointer-events-none"></div>
        
        <a href="explore.php" class="text-slate-400 hover:text-blue-600 font-bold text-xs uppercase tracking-widest mb-6 inline-block transition relative z-10 bg-slate-50 px-4 py-2 rounded-lg border border-slate-200">
            &larr; Kembali ke Jadwal
        </a>
        
        <div class="relative z-10 flex flex-col md:flex-row gap-6 items-start">
            <?php if(!empty($eventInfo['logo_left'])): ?>
                <img src="../../../public/<?= htmlspecialchars($eventInfo['logo_left']) ?>" alt="Logo Event" class="w-32 h-32 object-contain bg-white p-2 rounded-2xl border border-slate-100 shadow-sm shrink-0">
            <?php else: ?>
                <div class="w-32 h-32 bg-slate-800 rounded-2xl flex items-center justify-center text-5xl shadow-sm border border-slate-200 shrink-0">🏊</div>
            <?php endif; ?>
            
            <div>
                <h1 class="text-4xl font-black text-slate-800 uppercase italic tracking-tight leading-none"><?= htmlspecialchars($eventInfo['event_name']) ?></h1>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-2">Penyelenggara: <span class="text-slate-600"><?= htmlspecialchars($eventInfo['penyelenggara'] ?? 'Kepanitiaan') ?></span></p>
                
                <div class="mt-5 flex flex-wrap gap-3">
                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-50 text-xs font-black text-slate-700 border border-slate-200 uppercase tracking-wide">
                        <span class="text-lg">📅</span> <?= !empty($eventInfo['event_date_start']) && $eventInfo['event_date_start'] != '0000-00-00' ? date('d F Y', strtotime($eventInfo['event_date_start'])) : 'TBA' ?>
                    </span>
                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-50 text-xs font-black text-slate-700 border border-slate-200 uppercase tracking-wide">
                        <span class="text-lg">📍</span> <?= htmlspecialchars($eventInfo['event_location'] ?? 'Lokasi Belum Ditentukan') ?>
                    </span>
                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-50 text-xs font-black text-emerald-600 border border-emerald-200 uppercase tracking-widest shadow-sm">
                        STATUS: <?= htmlspecialchars($eventInfo['event_status'] ?? 'Active') ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="mt-8 flex gap-4 relative z-10 border-t border-slate-100 pt-8 flex-wrap">
            <a href="registration.php?event_id=<?= $event_id ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-black uppercase text-sm px-10 py-4 rounded-xl shadow-xl shadow-blue-200 hover:shadow-blue-300 hover:-translate-y-1 transition duration-300">
                Mulai Pendaftaran Tim 🚀
            </a>
            
            <?php 
            $hasRelay = false;
            foreach($raceList as $r) { if(isset($r['is_relay']) && $r['is_relay'] == 1) { $hasRelay = true; break; } }
            if($hasRelay):
            ?>
            <a href="relay_registration.php?event_id=<?= $event_id ?>" class="bg-pink-600 hover:bg-pink-700 text-white font-black uppercase text-sm px-10 py-4 rounded-xl shadow-xl shadow-pink-200 hover:shadow-pink-300 hover:-translate-y-1 transition duration-300 relative overflow-hidden">
                <div class="absolute -right-4 -top-4 bg-white text-pink-600 text-[8px] font-black uppercase px-6 py-1.5 rotate-45 tracking-widest shadow-lg">NEW</div>
                Daftar Estafet 🏃‍♂️
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-100 bg-slate-50">
            <h3 class="font-black text-slate-800 uppercase italic tracking-wide">Daftar Nomor Perlombaan</h3>
        </div>
        
        <?php if(empty($raceList)): ?>
            <div class="p-16 text-center">
                <div class="text-5xl mb-4 grayscale opacity-40">📋</div>
                <p class="text-slate-500 font-bold">Nomor perlombaan belum ditambahkan oleh panitia.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-white border-b border-slate-200 text-[10px] font-black uppercase tracking-widest text-slate-400">
                        <tr>
                            <th class="px-6 py-5 text-center w-24">Nomor</th>
                            <th class="px-6 py-5">Nama Lomba / Gaya</th>
                            <th class="px-6 py-5 text-center">Kategori Umur</th>
                            <th class="px-6 py-5 text-center">Gender</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach($raceList as $race): 
                            $isMale = in_array(strtoupper($race['jenis_kelamin'] ?? ''), ['L', 'MALE', 'PUTRA']);
                        ?>
                        <tr class="hover:bg-slate-50 transition group">
                            <td class="px-6 py-4 text-center">
                                <span class="font-black text-slate-300 text-xl group-hover:text-blue-500 transition">#<?= htmlspecialchars($race['event_number']) ?></span>
                            </td>
                            <td class="px-6 py-4 font-black text-slate-700 uppercase tracking-tight text-base">
                                <?= htmlspecialchars($race['distance']) ?>M <?= htmlspecialchars($race['stroke']) ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-3 py-1.5 bg-slate-100 border border-slate-200 text-slate-600 rounded-lg text-[10px] font-black uppercase tracking-widest">
                                    <?= htmlspecialchars($race['age_group'] ?? 'OPEN') ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-4 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest border <?= $isMale ? 'bg-blue-50 text-blue-600 border-blue-100' : 'bg-pink-50 text-pink-600 border-pink-100' ?>">
                                    <?= $isMale ? 'PUTRA' : 'PUTRI' ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>