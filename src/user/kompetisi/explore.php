<?php
// FILE: src/user/kompetisi/explore.php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Cek Login User
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../../public/login.php"); exit;
}

// 1. AMBIL DAFTAR KOMPETISI (ACTIVE EVENTS)
// PERBAIKAN: Menghapus fallback "Admin = Event" yang berbahaya
// dan menyesuaikan filter status menggunakan 'Active' (sesuai standar dashboard admin)
try {
    $sql = "SELECT 
                e.id as event_id,
                e.event_name as nama_event,
                e.logo_left as banner_image,
                e.event_location as lokasi,
                e.event_date_start as tanggal_pelaksanaan,
                e.event_status as status,
                u.nama_lengkap as penyelenggara
            FROM events e
            LEFT JOIN users u ON e.user_id = u.id
            WHERE e.event_status IN ('Active', 'Open', 'Upcoming')
            ORDER BY e.event_date_start ASC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $competitions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error mengambil data event: " . $e->getMessage());
}

include __DIR__ . '/../../../views/layout/topbar.php';
include __DIR__ . '/../../../views/layout/sidebar.php';
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen">
    <div class="mb-8">
        <h1 class="text-4xl font-black italic uppercase tracking-tighter text-slate-900">Jadwal Lomba</h1>
        <p class="text-sm font-bold text-slate-500 uppercase tracking-widest mt-1">Temukan dan ikuti kejuaraan renang</p>
    </div>

    <?php if(empty($competitions)): ?>
        <div class="bg-white border-2 border-dashed border-slate-200 rounded-3xl p-12 text-center max-w-2xl mx-auto mt-12">
            <div class="text-6xl mb-4 grayscale opacity-50">🏊‍♂️</div>
            <h3 class="text-xl font-black text-slate-800 uppercase italic mb-2">Belum Ada Event Lomba</h3>
            <p class="text-sm text-slate-500 font-bold">Saat ini belum ada kejuaraan renang yang membuka pendaftaran.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            <?php foreach($competitions as $comp): 
                $imgSrc = !empty($comp['banner_image']) ? '../../../public/' . $comp['banner_image'] : 'https://images.unsplash.com/photo-1530549387789-4c1017266635?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80';
                
                $tgl = '-';
                if (!empty($comp['tanggal_pelaksanaan']) && $comp['tanggal_pelaksanaan'] != '0000-00-00') {
                    $tgl = date('d M Y', strtotime($comp['tanggal_pelaksanaan']));
                }
            ?>
            <a href="detail.php?event_id=<?= $comp['event_id'] ?>" class="group bg-white rounded-3xl overflow-hidden shadow-sm hover:shadow-2xl transition-all duration-300 border border-slate-200 flex flex-col hover:-translate-y-1 relative">
                
                <div class="absolute top-4 right-4 z-10 bg-emerald-500 text-white px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest shadow-lg">
                    <?= htmlspecialchars($comp['status']) ?>
                </div>

                <div class="relative h-48 bg-slate-800 overflow-hidden shrink-0 p-4 flex items-center justify-center">
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-900 via-transparent to-transparent opacity-60 z-10"></div>
                    <img src="<?= $imgSrc ?>" alt="Banner" class="absolute inset-0 w-full h-full object-cover opacity-80 group-hover:scale-105 transition duration-500">
                    
                    <div class="relative z-20 text-center mt-auto">
                        <h3 class="text-xl font-black text-white uppercase italic tracking-wide text-shadow-sm line-clamp-2">
                            <?= htmlspecialchars($comp['nama_event']) ?>
                        </h3>
                        <p class="text-[10px] text-slate-300 font-bold uppercase tracking-widest mt-1">
                            By <?= htmlspecialchars($comp['penyelenggara'] ?? 'Penyelenggara') ?>
                        </p>
                    </div>
                </div>

                <div class="p-6 flex-1 flex flex-col justify-between gap-4">
                    <div class="space-y-3">
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-sm">📅</div>
                            <div>
                                <p class="text-[10px] text-slate-400 font-bold uppercase">Tanggal</p>
                                <p class="text-sm font-bold text-slate-800"><?= $tgl ?></p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-sm">📍</div>
                            <div>
                                <p class="text-[10px] text-slate-400 font-bold uppercase">Lokasi</p>
                                <p class="text-sm font-bold text-slate-800 line-clamp-1"><?= htmlspecialchars($comp['lokasi'] ?? 'TBA') ?></p>
                            </div>
                        </div>
                    </div>

                    <button class="w-full py-3 rounded-xl bg-slate-900 text-white font-black uppercase text-xs tracking-widest hover:bg-blue-600 transition shadow-lg mt-2">
                        Lihat & Daftar →
                    </button>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>