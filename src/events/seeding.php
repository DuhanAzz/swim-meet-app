<?php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: ../../../public/login.php"); exit; }

// AMBIL DAFTAR NOMOR UNTUK DROPDOWN
// Kita ambil dari tabel yang sama: event_numbers
$events = $pdo->query("SELECT * FROM event_numbers ORDER BY event_number ASC")->fetchAll();

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="mb-8">
        <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tight">Atur Seeding & Lintasan</h1>
        <p class="text-sm text-slate-500">Pilih nomor lomba untuk mengatur posisi lintasan perenang.</p>
    </div>

    <div class="bg-white p-8 rounded-2xl shadow-lg border border-slate-200 max-w-xl">
        <form action="process.php" method="GET"> <div class="mb-6">
                <label class="block text-slate-700 font-bold mb-3 text-xs uppercase tracking-widest">Pilih Nomor Lomba</label>
                <div class="relative">
                    <select name="event_id" class="w-full pl-4 pr-10 py-4 border-2 border-slate-200 rounded-xl font-bold text-slate-700 focus:border-blue-600 focus:ring-0 outline-none appearance-none bg-slate-50 transition cursor-pointer">
                        <option value="" disabled selected>-- Klik untuk memilih --</option>
                        
                        <?php foreach($events as $ev): 
                            // Label Gender
                            $gender = $ev['jenis_kelamin'] == 'L' ? '(PUTRA)' : ($ev['jenis_kelamin'] == 'P' ? '(PUTRI)' : '(MIXED)');
                        ?>
                            <option value="<?= $ev['id'] ?>">
                                #<?= $ev['event_number'] ?> &mdash; <?= $ev['event_name'] ?> <?= $gender ?> <?= $ev['age_group'] ?>
                            </option>
                        <?php endforeach; ?>
                        
                    </select>
                    <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-slate-500">▼</div>
                </div>
                <?php if(empty($events)): ?>
                    <p class="text-red-500 text-xs mt-2 font-bold italic">* Belum ada nomor lomba dibuat. <a href="../../events/create.php" class="underline">Buat disini.</a></p>
                <?php endif; ?>
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-4 rounded-xl shadow-xl shadow-blue-200 transition transform hover:-translate-y-1 uppercase tracking-widest text-sm flex items-center justify-center gap-2">
                <span>⚙️</span> Mulai Seeding
            </button>
        </form>
    </div>
</div>