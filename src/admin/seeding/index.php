<?php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// Cek Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$admin_id = $_SESSION['user_id'];

// ============================================================
// AMBIL DATA NOMOR LOMBA + JUMLAH ATLET VALID (LUNAS)
// ============================================================
try {
    // Query ini menghitung jumlah atlet yang Statusnya PAID di tabel Payments
    $sql = "SELECT en.*, 
            (
                SELECT COUNT(DISTINCT ee.id) 
                FROM event_entries ee 
                WHERE ee.category_id = en.id 
                AND EXISTS (
                    SELECT 1 FROM payments p 
                    WHERE p.user_id = ee.user_id 
                    AND p.event_id = ee.event_id 
                    AND p.status = 'Paid'
                )
            ) as total_athletes
            FROM event_numbers en 
            WHERE en.organizer_id = ? 
            ORDER BY CAST(en.event_number AS UNSIGNED) ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$admin_id]);
    $events = $stmt->fetchAll();

} catch (PDOException $e) {
    $events = [];
    $error_msg = "Database Error: " . $e->getMessage();
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">

    <div class="max-w-7xl mx-auto mb-10 flex flex-col lg:flex-row justify-between items-end gap-6">
        <div>
            <h1 class="text-4xl font-black uppercase tracking-tighter italic text-slate-900 leading-none">Seeding & Start List</h1>
            <p class="text-sm text-slate-500 font-bold uppercase tracking-widest mt-2">Penyusunan Lintasan (Hanya Klub Lunas)</p>
        </div>
        
        <?php 
            // Hitung total semua atlet untuk tombol Print All
            $total_all_entries = 0;
            if(!empty($events)) {
                $total_all_entries = array_sum(array_column($events, 'total_athletes'));
            }
            
            $globalDisabled = $total_all_entries == 0 ? 'opacity-50 cursor-not-allowed grayscale' : 'hover:-translate-y-1 shadow-xl shadow-blue-200 hover:bg-blue-700';
            $globalLink = $total_all_entries == 0 ? '#' : 'print_full_book.php';
        ?>
        <div class="flex gap-3">
            <a href="<?= $globalLink ?>" target="_blank" class="bg-blue-600 text-white pl-6 pr-8 py-4 rounded-[2rem] transition flex items-center gap-4 group <?= $globalDisabled ?>">
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

    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'empty_data'): ?>
        <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4 shadow-sm">
            <strong class="font-bold">Peringatan:</strong> Tidak ada data atlet (Lunas) untuk diproses.
        </div>
    <?php endif; ?>

    <?php if(isset($error_msg)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 shadow-sm">
            <strong class="font-bold">Error Query:</strong> <?= $error_msg ?>
        </div>
    <?php endif; ?>

    <div class="max-w-7xl mx-auto space-y-4 pb-20">
        
        <?php if(empty($events)): ?>
            <div class="bg-white rounded-[2.5rem] p-10 text-center border border-slate-200 shadow-sm">
                <div class="text-5xl mb-4 grayscale opacity-30">🏊</div>
                <p class="font-bold text-slate-400 text-lg">Belum ada nomor lomba.</p>
                <p class="text-xs text-slate-400 mb-4">Pastikan Anda sudah membuat nomor lomba di menu Events.</p>
                <a href="../events/create.php" class="inline-block bg-blue-600 text-white px-6 py-2 rounded-full font-bold text-xs hover:bg-blue-700 transition">
                    + Buat Nomor Lomba
                </a>
            </div>
        <?php else: ?>

            <?php foreach($events as $ev): 
                // Data Logic
                $count = $ev['total_athletes'];
                $isReady = $count > 0; 

                // Styles
                $cardOpacity = $isReady ? 'opacity-100' : 'opacity-60 grayscale';
                $cardBorder = $isReady ? 'border-slate-200 hover:shadow-lg bg-white' : 'border-slate-100 bg-slate-50';
                
                // Logic Gender
                $genderCode = strtoupper($ev['jenis_kelamin'] ?? 'L'); 
                
                if(in_array($genderCode, ['L', 'MALE', 'PUTRA', 'LAKI-LAKI'])) { 
                    $bg = 'bg-blue-50'; $txt = 'text-blue-600'; $icon='👨'; $label='PUTRA'; 
                } elseif(in_array($genderCode, ['P', 'FEMALE', 'PUTRI', 'PEREMPUAN'])) { 
                    $bg = 'bg-pink-50'; $txt = 'text-pink-600'; $icon='👩'; $label='PUTRI'; 
                } else { 
                    $bg = 'bg-purple-50'; $txt = 'text-purple-600'; $icon='👫'; $label='MIXED'; 
                }

                // Status Badge
                if ($isReady) {
                    $badgeClass = "bg-emerald-50 text-emerald-600 border-emerald-100";
                    $badgeText = "✅ SIAP ($count ATLET)";
                } else {
                    $badgeClass = "bg-slate-100 text-slate-400 border-slate-200";
                    $badgeText = "🚫 MENUNGGU DATA";
                }
            ?>

            <div class="group relative rounded-[2rem] p-5 border transition flex flex-col md:flex-row items-center gap-6 <?= $cardOpacity ?> <?= $cardBorder ?>">
                
                <div class="shrink-0 w-20 h-20 rounded-3xl bg-slate-900 text-white flex flex-col items-center justify-center shadow-lg shadow-slate-200 group-hover:scale-105 transition">
                    <span class="text-[9px] font-bold text-slate-400 uppercase">Event</span>
                    <span class="text-3xl font-black italic"><?= $ev['event_number'] ?></span>
                </div>

                <div class="flex-1 text-center md:text-left">
                    <div class="inline-flex items-center gap-2 mb-1">
                        <span class="px-2 py-1 rounded-md <?= $bg ?> <?= $txt ?> text-[9px] font-black uppercase tracking-widest border border-slate-100">
                            <?= $icon ?> <?= $label ?>
                        </span>
                        <span class="text-[10px] font-bold text-slate-500 bg-slate-100 px-2 py-1 rounded-md uppercase border border-slate-200">
                            <?= $ev['distance'] ?>M <?= $ev['stroke'] ?>
                        </span>
                        <span class="text-[10px] font-bold text-slate-400 px-1 truncate max-w-[200px]">
                            KU: <?= htmlspecialchars($ev['age_group']) ?>
                        </span>
                    </div>
                    <h3 class="text-xl font-black text-slate-800 uppercase italic tracking-tight">
                        <?= $ev['distance'] ?>m <?= $ev['stroke'] ?>
                    </h3>
                </div>

                <div class="hidden md:block text-right px-4 border-r border-slate-100 min-w-[180px]">
                    <span class="block text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Ketersediaan Data</span>
                    <span class="inline-block text-[10px] font-black px-3 py-1 rounded-full border <?= $badgeClass ?>">
                        <?= $badgeText ?>
                    </span>
                </div>

                <div class="flex gap-2 w-full md:w-auto">
                    <?php if($isReady): ?>
                        <a href="view_startlist.php?category_id=<?= $ev['id'] ?>" class="flex-1 md:flex-none px-6 py-3 bg-white hover:bg-slate-50 text-slate-600 rounded-xl font-bold text-xs uppercase tracking-wider border border-slate-200 transition shadow-sm flex items-center justify-center gap-2">
                            <span>👁️</span> View
                        </a>
                        <a href="logic.php?category_id=<?= $ev['id'] ?>" class="flex-1 md:flex-none px-6 py-3 bg-slate-900 hover:bg-blue-600 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow-lg hover:shadow-blue-200 transition flex items-center justify-center gap-2" onclick="return confirm('Apakah Anda yakin ingin melakukan seeding ulang? Data heat/lintasan sebelumnya akan ditimpa.')">
                            <span>⚙️</span> Generate
                        </a>
                    <?php else: ?>
                        <button disabled class="flex-1 md:flex-none px-6 py-3 bg-slate-100 text-slate-400 rounded-xl font-bold text-xs uppercase tracking-wider border border-slate-200 cursor-not-allowed">
                            View
                        </button>
                        <button disabled class="flex-1 md:flex-none px-6 py-3 bg-slate-100 text-slate-400 rounded-xl font-bold text-xs uppercase tracking-wider border border-slate-200 cursor-not-allowed opacity-50">
                            Generate
                        </button>
                    <?php endif; ?>
                </div>

            </div>
            <?php endforeach; ?>

        <?php endif; ?>
    </div>
</div>