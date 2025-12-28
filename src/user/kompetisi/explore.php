<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Cek Login User
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../../public/login.php"); exit;
}

// 1. AMBIL DAFTAR KOMPETISI (ACTIVE EVENTS)
// Kita mengambil dari tabel 'events' yang di-join dengan 'users' (penyelenggara)
try {
    $sql = "SELECT 
                e.id as event_id,
                e.nama_event,
                e.banner_image,
                e.lokasi,
                e.tanggal_pelaksanaan,
                e.status,
                u.nama_lengkap as penyelenggara
            FROM events e
            JOIN users u ON e.organizer_id = u.id
            WHERE e.status = 'open' OR e.status = 'upcoming'
            ORDER BY e.tanggal_pelaksanaan ASC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $competitions = $stmt->fetchAll();
    
    // FALLBACK: Jika tabel events kosong/belum migrasi, pakai logika lama (ambil dari users admin)
    if(empty($competitions) && count($competitions) == 0) {
        $sqlBackup = "SELECT id as event_id, nama_lengkap as nama_event, profile_image as banner_image, location as lokasi, event_start_date as tanggal_pelaksanaan, 'open' as status, nama_lengkap as penyelenggara FROM users WHERE role = 'admin' ORDER BY created_at DESC";
        $stmt = $pdo->prepare($sqlBackup);
        $stmt->execute();
        $competitions = $stmt->fetchAll();
    }

} catch (PDOException $e) {
    die("Error Database: " . $e->getMessage());
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="mb-8">
        <h1 class="text-4xl font-black text-slate-900 uppercase italic tracking-tighter">Jelajah Event</h1>
        <p class="text-sm text-slate-500 font-medium">Temukan kejuaraan renang resmi dan daftarkan klub Anda.</p>
    </div>

    <?php if(empty($competitions)): ?>
        <div class="flex flex-col items-center justify-center py-20 bg-white rounded-[2.5rem] border border-slate-200 text-center shadow-sm">
            <div class="text-6xl mb-4 grayscale opacity-30">🏊‍♂️</div>
            <h3 class="text-xl font-black text-slate-700 uppercase italic">Belum Ada Kompetisi</h3>
            <p class="text-slate-400 text-sm mt-2">Saat ini belum ada event yang dibuka.</p>
        </div>
    <?php else: ?>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach($competitions as $comp): 
                $img = !empty($comp['banner_image']) ? $comp['banner_image'] : 'https://images.unsplash.com/photo-1519315901367-f34ff9154487?auto=format&fit=crop&w=800&q=80';
                if(strpos($img, 'http') !== 0) $img = "../../../public/" . $img;
                
                $tgl = date('d M Y', strtotime($comp['tanggal_pelaksanaan'] ?? 'now'));
            ?>
            
            <a href="register_event.php?event_id=<?= $comp['event_id'] ?>" class="group bg-white rounded-3xl overflow-hidden shadow-sm border border-slate-200 hover:shadow-2xl hover:-translate-y-1 transition duration-300 flex flex-col h-full relative">
                
                <div class="h-56 relative overflow-hidden bg-slate-900">
                    <img src="<?= htmlspecialchars($img) ?>" class="w-full h-full object-cover opacity-80 group-hover:opacity-100 group-hover:scale-110 transition duration-700">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent"></div>
                    
                    <div class="absolute bottom-4 left-4 right-4">
                        <span class="inline-block px-3 py-1 bg-blue-600 text-white text-[10px] font-black uppercase tracking-wider rounded-md mb-2 shadow-lg shadow-blue-900/50">
                            Open Registration
                        </span>
                        <h3 class="text-xl font-black text-white uppercase italic leading-none text-shadow">
                            <?= htmlspecialchars($comp['nama_event']) ?>
                        </h3>
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

                    <button class="w-full py-3 rounded-xl bg-slate-900 text-white font-black uppercase text-xs tracking-widest hover:bg-blue-600 transition shadow-lg">
                        Lihat & Daftar →
                    </button>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>