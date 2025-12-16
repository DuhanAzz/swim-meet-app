<?php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// Cek Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

// 1. AMBIL EVENT YANG SUDAH DI-SEEDING SAJA
try {
    // Kita cek dulu apakah kolom 'lane' sudah ada di tabel event_entries
    // Ini untuk mencegah error jika Anda lupa menjalankan SQL di Langkah 1
    $checkCol = $pdo->query("SHOW COLUMNS FROM event_entries LIKE 'lane'");
    
    if($checkCol->rowCount() > 0) {
        // Query Utama:
        // 1. Ambil data Event
        // 2. Hitung jumlah peserta yang sudah punya LANE (is_seeded)
        // 3. Hitung jumlah peserta yang sudah FINISH (total_finished)
        // 4. Filter HAVING is_seeded > 0 (Hanya tampilkan yang sudah seeding)
        
        $sql = "SELECT en.*, 
                (SELECT COUNT(*) FROM event_entries ee WHERE ee.event_id = en.id AND ee.lane IS NOT NULL) as count_seeded,
                (SELECT COUNT(*) FROM race_results rr JOIN event_entries ee ON rr.entry_id = ee.id WHERE ee.event_id = en.id) as total_finished
                FROM event_numbers en 
                HAVING count_seeded > 0
                ORDER BY en.event_number ASC";
        
        $events = $pdo->query($sql)->fetchAll();
    } else {
        // Jika kolom lane belum dibuat, anggap belum ada yang seeding
        $events = [];
        $db_warning = "Kolom 'lane' belum ditemukan. Harap update database.";
    }

} catch (PDOException $e) {
    $events = [];
    $error_msg = "Database Error: " . $e->getMessage();
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">

    <div class="max-w-7xl mx-auto mb-10">
        <h1 class="text-4xl font-black uppercase tracking-tighter italic text-slate-900 leading-none">Input Hasil Lomba</h1>
        <p class="text-sm text-slate-500 font-bold uppercase tracking-widest mt-2">Daftar Nomor Lomba Siap Input (Sudah Seeding)</p>
    </div>

    <?php if(isset($db_warning)): ?>
        <div class="max-w-7xl mx-auto mb-6 bg-amber-100 border border-amber-300 text-amber-800 px-4 py-3 rounded-xl flex items-center gap-3">
            <span class="text-2xl">⚠️</span>
            <div>
                <p class="font-bold">Database Belum Update</p>
                <p class="text-xs">Silakan jalankan perintah SQL: <code>ALTER TABLE event_entries ADD COLUMN lane INT NULL;</code></p>
            </div>
        </div>
    <?php endif; ?>

    <div class="max-w-7xl mx-auto space-y-4 pb-20">
        
        <?php if(empty($events)): ?>
            <div class="bg-white rounded-[2.5rem] p-16 text-center border border-slate-200 shadow-sm flex flex-col items-center">
                <div class="text-6xl mb-4 grayscale opacity-30">⏱️</div>
                <h3 class="font-black text-slate-400 uppercase tracking-widest text-lg">Tidak Ada Event Siap Input</h3>
                <p class="text-xs font-bold text-slate-300 mt-2 max-w-md">
                    Nomor lomba baru akan muncul di sini setelah Anda melakukan proses <span class="text-slate-500">Seeding (Pembagian Lintasan)</span>.
                </p>
                
                <a href="../seeding/index.php" class="mt-6 px-6 py-3 bg-slate-900 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow-lg hover:bg-blue-600 transition">
                    Pergi ke Menu Seeding
                </a>
            </div>

        <?php else: ?>

            <?php foreach($events as $ev): 
                // Styling Gender
                if($ev['jenis_kelamin'] == 'L') { $bg = 'bg-blue-50'; $txt = 'text-blue-600'; $icon='👨'; }
                elseif($ev['jenis_kelamin'] == 'P') { $bg = 'bg-pink-50'; $txt = 'text-pink-600'; $icon='👩'; }
                else { $bg = 'bg-purple-50'; $txt = 'text-purple-600'; $icon='👫'; }
                
                $hasResults = $ev['total_finished'] > 0;
            ?>

            <div class="group bg-white hover:bg-slate-50 rounded-[2rem] p-5 border border-slate-200 hover:border-blue-200 hover:shadow-lg transition flex flex-col md:flex-row items-center gap-6">
                
                <div class="shrink-0 w-16 h-16 rounded-2xl bg-slate-900 text-white flex flex-col items-center justify-center shadow-md">
                    <span class="text-[8px] font-bold text-slate-400 uppercase">Event</span>
                    <span class="text-2xl font-black italic"><?= $ev['event_number'] ?></span>
                </div>

                <div class="flex-1 text-center md:text-left">
                    <div class="inline-flex items-center gap-2 mb-1">
                        <span class="px-2 py-1 rounded-md <?= $bg ?> <?= $txt ?> text-[9px] font-black uppercase tracking-widest">
                            <?= $icon ?> <?= $ev['jenis_kelamin'] == 'L' ? 'PUTRA' : ($ev['jenis_kelamin'] == 'P' ? 'PUTRI' : 'MIXED') ?>
                        </span>
                        <span class="text-[10px] font-bold text-slate-500 bg-slate-100 px-2 py-1 rounded-md">
                            <?= $ev['age_group'] ?>
                        </span>
                        <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 border border-emerald-100 px-2 py-1 rounded-md uppercase">
                             <?= $ev['count_seeded'] ?> Peserta di Lintasan
                        </span>
                    </div>
                    <h3 class="text-lg font-black text-slate-800 uppercase italic">
                        <?= htmlspecialchars($ev['event_name']) ?>
                    </h3>
                </div>

                <div class="hidden md:block text-right px-4 border-r border-slate-100">
                    <span class="block text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Progress Input</span>
                    <?php if($hasResults): ?>
                        <span class="inline-block text-[10px] font-black px-3 py-1 rounded-full bg-blue-100 text-blue-600">
                            DATA MASUK: <?= $ev['total_finished'] ?>
                        </span>
                    <?php else: ?>
                        <span class="inline-block text-[10px] font-black px-3 py-1 rounded-full bg-amber-100 text-amber-600">
                            BELUM DIINPUT
                        </span>
                    <?php endif; ?>
                </div>

                <a href="input_result.php?event_id=<?= $ev['id'] ?>" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow-lg shadow-blue-200 transition flex items-center gap-2">
                    <span>⏱️</span> Input Waktu
                </a>

            </div>
            <?php endforeach; ?>

        <?php endif; ?>
    </div>
</div>