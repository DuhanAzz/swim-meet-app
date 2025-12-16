<?php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// Cek Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

// Cek ID
if (!isset($_GET['id'])) {
    header("Location: index.php"); exit;
}

$club_id = $_GET['id'];

// 1. AMBIL DATA KLUB
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'user'");
$stmt->execute([$club_id]);
$club = $stmt->fetch();

if (!$club) {
    echo "Data klub tidak ditemukan."; exit;
}

// 2. AMBIL DATA ENTRIES (Matriks Atlet)
// Asumsi: Ada tabel 'event_entries' yang menyimpan pendaftaran
// Kita join dengan event_numbers untuk dapat nama event
try {
    $sqlEntries = "SELECT ent.*, en.event_number, en.event_name, en.distance, en.age_group 
                   FROM event_entries ent
                   JOIN event_numbers en ON ent.event_id = en.id
                   WHERE ent.user_id = ? 
                   ORDER BY ent.athlete_name ASC";
    $stmtEntries = $pdo->prepare($sqlEntries);
    $stmtEntries->execute([$club_id]);
    $entries = $stmtEntries->fetchAll();
} catch (PDOException $e) {
    // Fallback jika tabel belum ada, agar tidak error fatal
    $entries = [];
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">

    <div class="max-w-7xl mx-auto mb-8 flex items-center gap-4">
        <a href="index.php" class="w-12 h-12 rounded-2xl bg-white border border-slate-200 flex items-center justify-center text-slate-400 hover:bg-slate-900 hover:text-white transition shadow-sm">
            ←
        </a>
        <div>
            <h1 class="text-3xl font-black uppercase tracking-tighter italic text-slate-900 leading-none">Detail Klub</h1>
            <p class="text-xs text-slate-500 font-bold uppercase tracking-widest mt-1">Verifikasi Data & Matriks Atlet</p>
        </div>
    </div>

    <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-8 pb-20">
        
        <div class="space-y-6">
            
            <div class="bg-white rounded-[2.5rem] p-8 border border-slate-200 shadow-sm relative overflow-hidden">
                <div class="absolute top-0 right-0 p-6 opacity-10 text-9xl grayscale">🏛️</div>
                
                <div class="relative z-10">
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-2">Nama Klub</span>
                    <h2 class="text-2xl font-black text-slate-800 uppercase italic leading-none mb-6">
                        <?= htmlspecialchars($club['nama_lengkap']) ?>
                    </h2>

                    <div class="space-y-4">
                        <div>
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block">Email Penanggung Jawab</span>
                            <span class="text-sm font-bold text-slate-600"><?= htmlspecialchars($club['email']) ?></span>
                        </div>
                        <div>
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block">Tanggal Daftar</span>
                            <span class="text-sm font-bold text-slate-600"><?= date('d M Y', strtotime($club['created_at'])) ?></span>
                        </div>
                        <div>
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block">Status Akun</span>
                            <?php if(($club['account_status']??'') == 'verified'): ?>
                                <span class="inline-block mt-1 px-3 py-1 bg-emerald-100 text-emerald-700 rounded-lg text-[10px] font-black uppercase">✅ Verified</span>
                            <?php else: ?>
                                <span class="inline-block mt-1 px-3 py-1 bg-amber-100 text-amber-700 rounded-lg text-[10px] font-black uppercase">⏳ Menunggu Validasi</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-[2.5rem] p-8 border border-slate-200 shadow-sm">
                <h3 class="text-lg font-black text-slate-800 uppercase italic mb-4">Bukti Pembayaran</h3>
                
                <?php if(!empty($club['payment_proof'])): ?>
                    <div class="group relative rounded-2xl overflow-hidden border border-slate-100 bg-slate-50 aspect-video flex items-center justify-center cursor-pointer">
                        <img src="../../../public/<?= htmlspecialchars($club['payment_proof']) ?>" alt="Bukti Bayar" class="object-cover w-full h-full group-hover:scale-110 transition duration-500">
                        
                        <a href="../../../public/<?= htmlspecialchars($club['payment_proof']) ?>" target="_blank" class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white font-bold uppercase text-xs tracking-widest">
                            🔍 Perbesar
                        </a>
                    </div>
                    <p class="text-[10px] text-slate-400 font-bold mt-3 text-center uppercase">Klik gambar untuk melihat detail</p>
                <?php else: ?>
                    <div class="w-full h-40 bg-slate-50 rounded-2xl border border-dashed border-slate-300 flex flex-col items-center justify-center text-slate-400">
                        <span class="text-2xl mb-2">🚫</span>
                        <span class="text-[10px] font-bold uppercase">Belum Upload Bukti</span>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <div class="lg:col-span-2">
            <div class="bg-white rounded-[2.5rem] border border-slate-200 shadow-sm overflow-hidden min-h-[500px]">
                
                <div class="p-8 border-b border-slate-100 flex justify-between items-center">
                    <h3 class="text-xl font-black text-slate-800 uppercase italic">Matriks Atlet</h3>
                    <span class="bg-blue-50 text-blue-600 px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest">
                        Total: <?= count($entries) ?> Entries
                    </span>
                </div>

                <?php if(empty($entries)): ?>
                    <div class="flex flex-col items-center justify-center py-20 text-center opacity-50">
                        <div class="text-5xl mb-4 grayscale">🏊</div>
                        <h4 class="font-black text-slate-400 uppercase tracking-widest text-lg">Data Kosong</h4>
                        <p class="text-xs font-bold text-slate-300 mt-1">Klub ini belum mendaftarkan atlet ke nomor lomba manapun.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-slate-50 border-b border-slate-100">
                                <tr>
                                    <th class="py-4 px-6 text-[9px] font-black text-slate-400 uppercase tracking-widest">Nama Atlet</th>
                                    <th class="py-4 px-6 text-[9px] font-black text-slate-400 uppercase tracking-widest">Gender</th>
                                    <th class="py-4 px-6 text-[9px] font-black text-slate-400 uppercase tracking-widest">Nomor Lomba</th>
                                    <th class="py-4 px-6 text-[9px] font-black text-slate-400 uppercase tracking-widest">Waktu (Seed)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach($entries as $ent): ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="py-4 px-6 font-bold text-slate-700 text-xs uppercase">
                                        <?= htmlspecialchars($ent['athlete_name']) ?>
                                    </td>
                                    <td class="py-4 px-6">
                                        <?php if($ent['gender'] == 'L'): ?>
                                            <span class="text-xs">👨 Putra</span>
                                        <?php else: ?>
                                            <span class="text-xs">👩 Putri</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-6">
                                        <div class="flex flex-col">
                                            <span class="text-[10px] font-black text-blue-600">EVENT <?= $ent['event_number'] ?></span>
                                            <span class="text-[10px] font-bold text-slate-400"><?= $ent['event_name'] ?> (<?= $ent['distance'] ?>m)</span>
                                        </div>
                                    </td>
                                    <td class="py-4 px-6 font-mono text-xs font-bold text-slate-600">
                                        <?= htmlspecialchars($ent['seed_time'] ?? 'NT') ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>