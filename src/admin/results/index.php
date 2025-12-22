<?php
// src/admin/results/index.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// Cek Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$db_warning = null;

// 1. AMBIL EVENT YANG SUDAH DI-SEEDING SAJA
try {
    // Cek apakah kolom 'final_time' sudah dibuat (agar tidak error fatal)
    $checkCol = $pdo->query("SHOW COLUMNS FROM event_entries LIKE 'final_time'");
    
    if($checkCol->rowCount() > 0) {
        // Query Utama:
        // 1. Ambil data Event (Nomor Lomba)
        // 2. count_seeded: Jumlah peserta yang sudah dapat Lintasan (Heat/Lane)
        // 3. total_finished: Jumlah peserta yang SUDAH punya Waktu Finish (final_time)
        
        $sql = "SELECT en.*, 
                (SELECT COUNT(*) FROM event_entries ee 
                 WHERE ee.category_id = en.id AND ee.heat IS NOT NULL) as count_seeded,
                 
                (SELECT COUNT(*) FROM event_entries ee 
                 WHERE ee.category_id = en.id AND (ee.final_time IS NOT NULL OR ee.is_dq = 1)) as total_finished
                 
                FROM event_numbers en 
                HAVING count_seeded > 0
                ORDER BY en.event_number ASC";
        
        $events = $pdo->query($sql)->fetchAll();
    } else {
        $events = [];
        $db_warning = "Kolom 'final_time' belum ditemukan. Harap jalankan SQL Update.";
    }

} catch (PDOException $e) {
    $events = [];
    $db_warning = "Database Error: " . $e->getMessage();
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">

    <div class="max-w-7xl mx-auto mb-10">
        <h1 class="text-4xl font-black uppercase tracking-tighter italic text-slate-900 leading-none">Input Hasil Lomba</h1>
        <p class="text-sm text-slate-500 font-bold uppercase tracking-widest mt-2">Daftar Nomor Lomba Siap Input (Sudah Seeding)</p>
    </div>

    <?php if($db_warning): ?>
        <div class="max-w-7xl mx-auto mb-6 bg-red-100 border border-red-300 text-red-800 px-6 py-4 rounded-xl flex items-center gap-4 shadow-sm">
            <span class="text-3xl">⚠️</span>
            <div>
                <p class="font-black uppercase">Database Belum Update</p>
                <p class="text-xs mb-2">Sistem tidak bisa menyimpan hasil lomba. Jalankan perintah ini di Database:</p>
                <code class="bg-black/10 px-2 py-1 rounded text-[10px] font-mono select-all">ALTER TABLE event_entries ADD COLUMN final_time VARCHAR(20) NULL, ADD COLUMN final_rank INT NULL, ADD COLUMN is_dq TINYINT(1) DEFAULT 0, ADD COLUMN dq_reason TEXT NULL;</code>
            </div>
        </div>
    <?php endif; ?>

    <div class="max-w-7xl mx-auto space-y-4 pb-20">
        
        <?php if(empty($events) && !$db_warning): ?>
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

        <?php elseif(!empty($events)): ?>

            <div class="grid grid-cols-1 gap-4">
            <?php foreach($events as $ev): 
                // Styling Gender
                if($ev['jenis_kelamin'] == 'L' || $ev['jenis_kelamin'] == 'Male') { 
                    $bg = 'bg-blue-50'; $txt = 'text-blue-600'; $icon='👨'; $brd='hover:border-blue-300';
                } elseif($ev['jenis_kelamin'] == 'P' || $ev['jenis_kelamin'] == 'Female') { 
                    $bg = 'bg-pink-50'; $txt = 'text-pink-600'; $icon='👩'; $brd='hover:border-pink-300';
                } else { 
                    $bg = 'bg-purple-50'; $txt = 'text-purple-600'; $icon='👫'; $brd='hover:border-purple-300';
                }
                
                // Status Selesai
                $percent = ($ev['count_seeded'] > 0) ? round(($ev['total_finished'] / $ev['count_seeded']) * 100) : 0;
                $is_completed = ($percent >= 100);
            ?>

            <div class="group bg-white hover:bg-slate-50 rounded-[2rem] p-5 border border-slate-200 <?= $brd ?> shadow-sm hover:shadow-lg transition-all duration-300 flex flex-col md:flex-row items-center gap-6 relative overflow-hidden">
                
                <div class="absolute bottom-0 left-0 h-1 bg-emerald-500 transition-all duration-1000" style="width: <?= $percent ?>%"></div>

                <div class="shrink-0 w-20 h-20 rounded-2xl bg-slate-900 text-white flex flex-col items-center justify-center shadow-md relative z-10">
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Event</span>
                    <span class="text-3xl font-black italic"><?= $ev['event_number'] ?></span>
                </div>

                <div class="flex-1 text-center md:text-left relative z-10">
                    <div class="inline-flex items-center gap-2 mb-2">
                        <span class="px-3 py-1 rounded-lg <?= $bg ?> <?= $txt ?> text-[10px] font-black uppercase tracking-widest shadow-sm">
                            <?= $icon ?> <?= ($ev['jenis_kelamin'] == 'L' || $ev['jenis_kelamin'] == 'Male') ? 'PUTRA' : 'PUTRI' ?>
                        </span>
                        <span class="text-[10px] font-bold text-slate-500 bg-slate-100 px-3 py-1 rounded-lg border border-slate-200">
                            <?= $ev['age_group'] ?>
                        </span>
                        <?php if($is_completed): ?>
                            <span class="text-[10px] font-bold text-emerald-600 bg-emerald-100 px-2 py-1 rounded-lg flex items-center gap-1">
                                ✅ SELESAI
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <h3 class="text-xl font-black text-slate-800 uppercase italic leading-tight">
                        <?= htmlspecialchars($ev['event_name']) ?>
                    </h3>
                    <p class="text-xs font-bold text-slate-400 mt-1">
                        <?= $ev['distance'] ?>M <?= strtoupper($ev['stroke']) ?> • <span class="text-slate-600"><?= $ev['count_seeded'] ?> Peserta</span>
                    </p>
                </div>

                <div class="w-full md:w-auto flex flex-col items-center md:items-end gap-3 px-4 relative z-10">
                    <div class="text-right">
                        <span class="block text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Data Masuk</span>
                        <div class="flex items-center gap-2 justify-end">
                            <span class="text-2xl font-black <?= $is_completed ? 'text-emerald-500' : 'text-slate-700' ?>">
                                <?= $ev['total_finished'] ?>
                            </span>
                            <span class="text-xs font-bold text-slate-400">/ <?= $ev['count_seeded'] ?></span>
                        </div>
                    </div>

                    <a href="input_result.php?category_id=<?= $ev['id'] ?>" class="w-full md:w-auto px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow-lg shadow-blue-200 hover:shadow-blue-300 transition flex items-center justify-center gap-2 transform active:scale-95">
                        <span>⏱️</span> <?= $is_completed ? 'Edit Hasil' : 'Input Waktu' ?>
                    </a>
                </div>

            </div>
            <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </div>
</div>