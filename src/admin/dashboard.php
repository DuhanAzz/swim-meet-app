<?php
// FILE: src/admin/dashboard.php
session_start();
require_once __DIR__ . '/../../src/config/database.php';

// 1. CEK KEAMANAN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}
$uid = $_SESSION['user_id'];

// 2. AMBIL EVENT AKTIF 
$stmtEvent = $pdo->prepare("SELECT * FROM events WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmtEvent->execute([$uid]);
$event = $stmtEvent->fetch(PDO::FETCH_ASSOC);

// Variable Default
$eventId   = $event['id'] ?? 0;
$eventName = $event['event_name'] ?? 'Belum Ada Event Aktif';
$eventDate = $event['event_date_start'] ?? date('Y-m-d'); 
$eventLoc  = $event['event_location'] ?? '-';
$eventStatus = $event['event_status'] ?? 'Draft';

// 3. HITUNG STATISTIK 
$stats = ['atlet' => 0, 'entries' => 0, 'clubs' => 0, 'revenue' => 0];

if ($eventId > 0) {
    try {
        // A. Total Entries (Nomor Lomba yang diikuti)
        $stmtEntry = $pdo->prepare("SELECT COUNT(*) FROM event_entries WHERE event_id = ?");
        $stmtEntry->execute([$eventId]);
        $stats['entries'] = $stmtEntry->fetchColumn();

        // B. Total Atlet (Unik)
        $stmtAtlet = $pdo->prepare("SELECT COUNT(DISTINCT swimmer_id) FROM event_entries WHERE event_id = ?");
        $stmtAtlet->execute([$eventId]);
        $stats['atlet'] = $stmtAtlet->fetchColumn();

        // C. Total Klub/Sekolah (Unik)
        $stmtClub = $pdo->prepare("SELECT COUNT(DISTINCT club_id) FROM event_entries WHERE event_id = ?");
        $stmtClub->execute([$eventId]);
        $stats['clubs'] = $stmtClub->fetchColumn();

        // D. PERBAIKAN: Total Pemasukan (Revenue) dari tabel payments yang sudah Lunas
        $stmtRev = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE event_id = ? AND status IN ('Paid', 'completed')");
        $stmtRev->execute([$eventId]);
        $stats['revenue'] = $stmtRev->fetchColumn() ?: 0;

    } catch (Exception $e) { /* Silent Error */ }
}

// 4. DATA CHART (Top 5 Klub / Sekolah)
$chartLabels = [];
$chartValues = [];

if ($eventId > 0) {
    $sqlChart = "
        SELECT u.nama_lengkap as nama_klub, COUNT(DISTINCT ee.swimmer_id) as jumlah_atlet
        FROM event_entries ee
        JOIN users u ON ee.club_id = u.id
        WHERE ee.event_id = ?
        GROUP BY u.id
        ORDER BY jumlah_atlet DESC
        LIMIT 5
    ";
    try {
        $stmtChart = $pdo->prepare($sqlChart);
        $stmtChart->execute([$eventId]);
        $dataChart = $stmtChart->fetchAll(PDO::FETCH_ASSOC);
        foreach ($dataChart as $d) {
            $chartLabels[] = $d['nama_klub'];
            $chartValues[] = $d['jumlah_atlet'];
        }
    } catch(Exception $e) {}
}

$jsLabels = json_encode($chartLabels);
$jsValues = json_encode($chartValues);

// INCLUDE LAYOUT 
include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center mb-8 gap-6">
        <div>
            <h1 class="text-3xl font-black text-slate-800 uppercase italic tracking-tighter">
                <?= htmlspecialchars($eventName) ?>
            </h1>
            <p class="text-sm text-slate-500 font-bold uppercase tracking-widest mt-1">
                📍 <?= htmlspecialchars($eventLoc) ?> • 📅 <?= date('d M Y', strtotime($eventDate)) ?>
            </p>
        </div>
        
        <?php if($eventId == 0): ?>
            <a href="settings/event_profile.php" class="bg-blue-600 text-white px-6 py-3 rounded-xl font-bold text-xs uppercase hover:bg-blue-700 transition shadow-lg animate-bounce">
                + Buat Event Baru
            </a>
        <?php else: ?>
            <div class="flex gap-2">
                <span class="px-4 py-2 bg-emerald-100 text-emerald-700 rounded-lg text-xs font-black uppercase tracking-wide border border-emerald-200">
                    Status: <?= htmlspecialchars($eventStatus) ?>
                </span>
                <a href="settings/event_profile.php?event_id=<?= $eventId ?>" class="px-4 py-2 bg-slate-800 text-white rounded-lg text-xs font-bold uppercase hover:bg-slate-700 transition">
                    ⚙️ Edit Event
                </a>
            </div>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-slate-900 rounded-2xl p-6 text-white shadow-xl relative overflow-hidden group">
            <div class="relative z-10">
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">Total Pemasukan</p>
                <h2 class="text-2xl font-black">Rp <?= number_format($stats['revenue'], 0, ',', '.') ?></h2>
            </div>
            <div class="absolute right-[-10px] bottom-[-10px] opacity-10 group-hover:scale-110 transition text-white text-6xl">💰</div>
        </div>

        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:border-blue-500 transition group">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">Total Entries</p>
                    <h2 class="text-3xl font-black text-slate-800"><?= number_format($stats['entries']) ?></h2>
                    <p class="text-[10px] text-slate-400 mt-1">Nomor Lomba</p>
                </div>
                <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center text-xl group-hover:bg-blue-600 group-hover:text-white transition">🏊</div>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:border-purple-500 transition group">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">Total Atlet</p>
                    <h2 class="text-3xl font-black text-slate-800"><?= number_format($stats['atlet']) ?></h2>
                    <p class="text-[10px] text-slate-400 mt-1">Orang</p>
                </div>
                <div class="w-10 h-10 bg-purple-50 text-purple-600 rounded-lg flex items-center justify-center text-xl group-hover:bg-purple-600 group-hover:text-white transition">👤</div>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:border-orange-500 transition group">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">Klub/Sekolah</p>
                    <h2 class="text-3xl font-black text-slate-800"><?= number_format($stats['clubs']) ?></h2>
                    <p class="text-[10px] text-slate-400 mt-1">Partisipan</p>
                </div>
                <div class="w-10 h-10 bg-orange-50 text-orange-600 rounded-lg flex items-center justify-center text-xl group-hover:bg-orange-600 group-hover:text-white transition">🏢</div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-2 bg-white rounded-[2rem] shadow-sm border border-slate-200 p-8">
            <div class="flex justify-between items-center mb-6">
                <h3 class="font-black text-slate-800 uppercase italic text-sm tracking-widest">🏆 Top 5 Tim/Klub Teraktif</h3>
            </div>
            <div class="relative h-64 w-full">
                <?php if(empty($chartLabels)): ?>
                    <div class="flex items-center justify-center h-full text-slate-400 text-xs italic bg-slate-50 rounded-xl border-2 border-dashed border-slate-200">
                        Belum ada data pendaftaran.
                    </div>
                <?php else: ?>
                    <canvas id="clubChart"></canvas>
                <?php endif; ?>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-[2rem] p-6 border border-slate-200 shadow-sm">
                <h3 class="font-black text-slate-800 uppercase italic text-xs tracking-widest mb-4">⚡ Menu Cepat</h3>
                <div class="grid grid-cols-1 gap-3">
                    
                    <a href="entries/index.php" class="flex items-center gap-3 p-3 bg-slate-50 hover:bg-blue-50 rounded-xl transition group border border-slate-100">
                        <span class="w-8 h-8 flex items-center justify-center bg-white rounded-full shadow-sm text-xs border border-slate-100 group-hover:scale-110 transition">✅</span>
                        <div>
                            <p class="text-xs font-black text-slate-700 uppercase">Verifikasi Atlet</p>
                            <p class="text-[10px] text-slate-400">Cek status pembayaran</p>
                        </div>
                    </a>

                    <a href="seeding/index.php" class="flex items-center gap-3 p-3 bg-slate-50 hover:bg-purple-50 rounded-xl transition group border border-slate-100">
                        <span class="w-8 h-8 flex items-center justify-center bg-white rounded-full shadow-sm text-xs border border-slate-100 group-hover:scale-110 transition">🎲</span>
                        <div>
                            <p class="text-xs font-black text-slate-700 uppercase">Seeding / Undian</p>
                            <p class="text-[10px] text-slate-400">Atur lintasan lomba</p>
                        </div>
                    </a>

                    <a href="results/index.php" class="flex items-center gap-3 p-3 bg-slate-50 hover:bg-emerald-50 rounded-xl transition group border border-slate-100">
                        <span class="w-8 h-8 flex items-center justify-center bg-white rounded-full shadow-sm text-xs border border-slate-100 group-hover:scale-110 transition">⏱️</span>
                        <div>
                            <p class="text-xs font-black text-slate-700 uppercase">Input Hasil</p>
                            <p class="text-[10px] text-slate-400">Masukkan waktu finish</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // 1. Chart Config
    const ctx = document.getElementById('clubChart');
    if(ctx && <?= count($chartLabels) ?> > 0) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= $jsLabels ?>,
                datasets: [{
                    label: 'Jumlah Atlet',
                    data: <?= $jsValues ?>,
                    backgroundColor: [
                        '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899'
                    ],
                    borderRadius: 6,
                    barPercentage: 0.6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { display: false } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // 2. SweetAlert Toast Notification
    <?php if(isset($_SESSION['swal_type'])): ?>
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',    
            showConfirmButton: false, 
            timer: 3000,            
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        Toast.fire({
            icon: '<?= $_SESSION['swal_type'] ?>',
            title: '<?= $_SESSION['swal_msg'] ?>'
        });

        // Hapus session
        <?php unset($_SESSION['swal_type']); unset($_SESSION['swal_msg']); ?>
    <?php endif; ?>
</script>