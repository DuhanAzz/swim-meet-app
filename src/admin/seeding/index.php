<?php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// Cek Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

// AMBIL DATA NOMOR LOMBA + JUMLAH ATLET VALID
// Kita menggunakan Subquery untuk menghitung jumlah peserta yang valid (klub sudah verified)
try {
    $sql = "SELECT en.*, 
            (
                SELECT COUNT(*) 
                FROM event_entries ee 
                JOIN users u ON ee.user_id = u.id 
                WHERE ee.event_id = en.id 
                AND u.account_status = 'verified'
            ) as total_athletes
            FROM event_numbers en 
            ORDER BY en.event_number ASC";
            
    $events = $pdo->query($sql)->fetchAll();
} catch (PDOException $e) {
    $events = [];
    $error_msg = "Tabel belum siap: " . $e->getMessage();
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">

    <div class="max-w-7xl mx-auto mb-10 flex flex-col lg:flex-row justify-between items-end gap-6">
        <div>
            <h1 class="text-4xl font-black uppercase tracking-tighter italic text-slate-900 leading-none">Seeding & Start List</h1>
            <p class="text-sm text-slate-500 font-bold uppercase tracking-widest mt-2">Penyusunan Lintasan (Hanya Peserta Valid)</p>
        </div>
        
        <?php 
            $total_all_entries = array_sum(array_column($events, 'total_athletes'));
            $globalDisabled = $total_all_entries == 0 ? 'opacity-50 cursor-not-allowed grayscale' : 'hover:-translate-y-1 shadow-xl shadow-blue-200 hover:bg-blue-700';
            $globalLink = $total_all_entries == 0 ? '#' : 'print_all.php';
        ?>
        <div class="flex gap-3">
            <a href="<?= $globalLink ?>" class="bg-blue-600 text-white pl-6 pr-8 py-4 rounded-[2rem] transition flex items-center gap-4 group <?= $globalDisabled ?>">
                <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center group-hover:bg-white group-hover:text-blue-600 transition">
                    📄
                </div>
                <div class="text-left">
                    <span class="block text-[9px] font-bold text-blue-200 uppercase tracking-widest">Download Full</span>
                    <span class="block font-black text-sm uppercase tracking-wider">Cetak Buku Acara</span>
                </div>
            </a>
        </div>
    </div>

    <?php if(isset($error_msg)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Error:</strong> <?= $error_msg ?>
        </div>
    <?php endif; ?>

    <div class="max-w-7xl mx-auto space-y-4 pb-20">
        
        <?php if(empty($events)): ?>
            <div class="bg-white rounded-[2.5rem] p-10 text-center border border-slate-200">
                <p class="font-bold text-slate-400">Belum ada nomor lomba yang dibuat.</p>
            </div>
        <?php else: ?>

            <?php foreach($events as $ev): 
                // Data Logic
                $count = $ev['total_athletes'];
                $isReady = $count > 0; // Siap jika ada minimal 1 atlet valid

                // Styles
                $cardOpacity = $isReady ? 'opacity-100' : 'opacity-70';
                $cardBorder = $isReady ? 'border-slate-200 hover:shadow-lg' : 'border-slate-100 bg-slate-50';
                
                // Gender Styles
                if($ev['jenis_kelamin'] == 'L') { $bg = 'bg-blue-50'; $txt = 'text-blue-600'; $icon='👨'; }
                elseif($ev['jenis_kelamin'] == 'P') { $bg = 'bg-pink-50'; $txt = 'text-pink-600'; $icon='👩'; }
                else { $bg = 'bg-purple-50'; $txt = 'text-purple-600'; $icon='👫'; }

                // Status Badge Logic
                if ($isReady) {
                    $badgeClass = "bg-emerald-50 text-emerald-600 border-emerald-100";
                    $badgeText = "✅ SIAP SEEDING ($count ATLET)";
                } else {
                    $badgeClass = "bg-slate-100 text-slate-400 border-slate-200";
                    $badgeText = "⏳ MENUNGGU VALIDASI";
                }
            ?>

            <div class="group relative rounded-[2rem] p-5 border transition flex flex-col md:flex-row items-center gap-6 <?= $cardOpacity ?> <?= $cardBorder ?>">
                
                <div class="shrink-0 w-20 h-20 rounded-3xl bg-slate-900 text-white flex flex-col items-center justify-center shadow-lg shadow-slate-200">
                    <span class="text-[9px] font-bold text-slate-400 uppercase">Event</span>
                    <span class="text-3xl font-black italic"><?= $ev['event_number'] ?></span>
                </div>

                <div class="flex-1 text-center md:text-left">
                    <div class="inline-flex items-center gap-2 mb-1">
                        <span class="px-2 py-1 rounded-md <?= $bg ?> <?= $txt ?> text-[9px] font-black uppercase tracking-widest border border-slate-100">
                            <?= $icon ?> <?= $ev['jenis_kelamin'] == 'L' ? 'PUTRA' : ($ev['jenis_kelamin'] == 'P' ? 'PUTRI' : 'MIXED') ?>
                        </span>
                        <span class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-1 rounded-md">
                            <?= $ev['age_group'] ?>
                        </span>
                    </div>
                    <h3 class="text-xl font-black text-slate-800 uppercase italic tracking-tight">
                        <?= htmlspecialchars($ev['event_name']) ?>
                    </h3>
                </div>

                <div class="hidden md:block text-right px-4 border-r border-slate-100 min-w-[180px]">
                    <span class="block text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Status Data</span>
                    <span class="inline-block text-[10px] font-black px-3 py-1 rounded-full border <?= $badgeClass ?>">
                        <?= $badgeText ?>
                    </span>
                </div>

                <div class="flex gap-2 w-full md:w-auto">
                    <?php if($isReady): ?>
                        <a href="view_startlist.php?event_id=<?= $ev['id'] ?>" class="flex-1 md:flex-none px-6 py-3 bg-white hover:bg-slate-50 text-slate-600 rounded-xl font-bold text-xs uppercase tracking-wider border border-slate-200 transition shadow-sm" title="Lihat Start List">
                            👁️ View
                        </a>
                        <a href="process_seeding.php?event_id=<?= $ev['id'] ?>" class="flex-1 md:flex-none px-6 py-3 bg-slate-900 hover:bg-blue-600 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow-lg hover:shadow-blue-200 transition" title="Lakukan Seeding">
                            ⚙️ Seed
                        </a>
                    <?php else: ?>
                        <button disabled class="flex-1 md:flex-none px-6 py-3 bg-slate-100 text-slate-400 rounded-xl font-bold text-xs uppercase tracking-wider border border-slate-200 cursor-not-allowed">
                            👁️ View
                        </button>
                        <button disabled class="flex-1 md:flex-none px-6 py-3 bg-slate-100 text-slate-400 rounded-xl font-bold text-xs uppercase tracking-wider border border-slate-200 cursor-not-allowed">
                            🚫 Empty
                        </button>
                    <?php endif; ?>
                </div>

            </div>
            <?php endforeach; ?>

        <?php endif; ?>
    </div>
</div>