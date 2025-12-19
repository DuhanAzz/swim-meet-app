<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Cek Login User
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../../public/login.php"); exit;
}

$today = date('Y-m-d');

// --- 1. AMBIL DAFTAR KOMPETISI ---
// Logika: Mengambil data user dengan role 'admin' (sebagai Penyelenggara/EO)
// Disini kita asumsikan 1 Admin = 1 Event.
try {
    $sql = "SELECT id, nama_lengkap, profile_image, location, event_start_date 
            FROM users 
            WHERE role = 'admin' 
            ORDER BY event_start_date DESC"; // Tampilkan yang terbaru
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $competitions = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error Database: " . $e->getMessage());
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="flex flex-col md:flex-row justify-between items-end mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tight italic">Cari Kompetisi</h1>
            <p class="text-sm text-slate-500">Pilih kejuaraan renang yang ingin diikuti oleh klub Anda.</p>
        </div>
    </div>

    <?php if(empty($competitions)): ?>
        <div class="flex flex-col items-center justify-center h-96 bg-white rounded-2xl border-2 border-dashed border-slate-300 text-center p-8">
            <span class="text-6xl mb-4 grayscale opacity-30">🏆</span>
            <h3 class="text-xl font-bold text-slate-700">Belum Ada Kompetisi</h3>
            <p class="text-slate-500 mt-2 text-xs">Saat ini belum ada event organizer yang membuka pendaftaran.</p>
        </div>
    <?php else: ?>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach($competitions as $comp): 
                // Data Fallback jika kosong
                $namaEvent = !empty($comp['nama_lengkap']) ? $comp['nama_lengkap'] : 'Nama Event Belum Diisi';
                $lokasi    = !empty($comp['location']) ? $comp['location'] : 'Lokasi Belum Diisi';
                $tgl       = !empty($comp['event_start_date']) ? date('d F Y', strtotime($comp['event_start_date'])) : 'Segera';
                
                // Gambar Profil Event
                $img = (!empty($comp['profile_image'])) ? $comp['profile_image'] : 'https://images.unsplash.com/photo-1530549387789-4c1017266635?auto=format&fit=crop&w=500&q=80';
                // Cek path gambar lokal
                if(strpos($img, 'http') !== 0) $img = "../../../public/" . $img;
            ?>
            
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden hover:shadow-xl transition group duration-300 flex flex-col h-full hover:-translate-y-1">
                
                <div class="h-48 bg-slate-200 relative overflow-hidden">
                    <img src="<?= htmlspecialchars($img) ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-700" onerror="this.src='https://via.placeholder.com/500x300?text=Event+Image'">
                    
                    <div class="absolute top-4 right-4 bg-white/90 backdrop-blur text-blue-800 text-[10px] font-black px-3 py-1 rounded-full uppercase shadow-sm">
                        Open Registration
                    </div>
                </div>

                <div class="p-6 flex-1 flex flex-col">
                    <h3 class="font-black text-xl text-slate-800 mb-3 line-clamp-2 uppercase leading-tight">
                        <?= htmlspecialchars($namaEvent) ?>
                    </h3>
                    
                    <div class="space-y-3 text-sm text-slate-600 mb-6">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center text-blue-600">📅</div>
                            <div>
                                <div class="text-[10px] text-slate-400 font-bold uppercase">Pelaksanaan</div>
                                <div class="font-bold text-slate-800"><?= $tgl ?></div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-red-50 flex items-center justify-center text-red-600">📍</div>
                            <div>
                                <div class="text-[10px] text-slate-400 font-bold uppercase">Lokasi</div>
                                <div class="font-bold text-slate-800"><?= htmlspecialchars($lokasi) ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-auto">
                        <a href="register_event.php?event_id=<?= $comp['id'] ?>" class="block w-full text-center bg-slate-900 text-white font-bold py-3 rounded-xl hover:bg-blue-600 transition shadow-lg flex items-center justify-center gap-2">
                            <span>📝</span> LIHAT LOMBA
                        </a>
                    </div>
                </div>

            </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

</div>