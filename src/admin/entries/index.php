<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];

// --- HANDLE EXPORT CSV ---
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="Data_Peserta_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    // Header CSV
    fputcsv($output, ['Nama Atlet', 'Klub', 'Gender', 'Tgl Lahir', 'Jarak', 'Gaya', 'Kelompok Umur', 'Waktu Entry']);
    
    // PERBAIKAN DI SINI: Menggunakan u.nama_lengkap sebagai Klub
    $sql = "SELECT s.nama_atlet, u.nama_lengkap, s.jenis_kelamin, s.tanggal_lahir, 
                   ec.distance, ec.style, ec.age_group, ee.entry_time
            FROM event_entries ee
            JOIN swimmers s ON ee.swimmer_id = s.id
            JOIN users u ON ee.club_id = u.id
            JOIN event_categories ec ON ee.category_id = ec.id
            WHERE ee.event_id = ?
            ORDER BY u.nama_lengkap ASC, s.nama_atlet ASC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$uid]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// --- AMBIL STATISTIK RINGKAS ---
// Total Atlet
$stmt1 = $pdo->prepare("SELECT COUNT(DISTINCT swimmer_id) FROM event_entries WHERE event_id = ?");
$stmt1->execute([$uid]);
$countSwimmers = $stmt1->fetchColumn();

// Total Splash (Nomor yang diikuti)
$stmt2 = $pdo->prepare("SELECT COUNT(*) FROM event_entries WHERE event_id = ?");
$stmt2->execute([$uid]);
$countSplash = $stmt2->fetchColumn();

// Total Klub
$stmt3 = $pdo->prepare("SELECT COUNT(DISTINCT club_id) FROM event_entries WHERE event_id = ?");
$stmt3->execute([$uid]);
$countClubs = $stmt3->fetchColumn();

// --- AMBIL DATA ENTRY (PERBAIKAN SQL DI SINI) ---
// Kita ubah 'u.nama_klub' menjadi 'u.nama_lengkap as nama_klub' agar tidak error
$sqlAll = "SELECT ee.*, s.nama_atlet, s.jenis_kelamin, s.tanggal_lahir, 
                  u.nama_lengkap as nama_klub, 
                  ec.distance, ec.style, ec.age_group
           FROM event_entries ee
           JOIN swimmers s ON ee.swimmer_id = s.id
           JOIN users u ON ee.club_id = u.id
           JOIN event_categories ec ON ee.category_id = ec.id
           WHERE ee.event_id = ?
           ORDER BY ec.id ASC, s.nama_atlet ASC";

$stmtAll = $pdo->prepare($sqlAll);
$stmtAll->execute([$uid]);
$entries = $stmtAll->fetchAll();

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tight">Data Peserta</h1>
            <p class="text-sm text-slate-500">Rekap pendaftaran atlet yang masuk.</p>
        </div>
        <div>
            <a href="index.php?export=csv" class="bg-green-600 hover:bg-green-700 text-white px-5 py-2.5 rounded-lg font-bold text-xs shadow-lg flex items-center gap-2 transition transform hover:-translate-y-0.5">
                <span>📊</span> Download Excel (CSV)
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
            <p class="text-xs font-bold text-slate-400 uppercase">Total Klub</p>
            <h3 class="text-3xl font-black text-slate-800"><?= number_format($countClubs) ?></h3>
        </div>
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
            <p class="text-xs font-bold text-slate-400 uppercase">Total Atlet</p>
            <h3 class="text-3xl font-black text-slate-800"><?= number_format($countSwimmers) ?></h3>
        </div>
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
            <p class="text-xs font-bold text-slate-400 uppercase">Total Entry / Splash</p>
            <h3 class="text-3xl font-black text-blue-600"><?= number_format($countSplash) ?></h3>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex justify-between items-center">
            <h3 class="font-bold text-slate-700 text-sm uppercase">Daftar Peserta</h3>
            <span class="text-xs text-slate-400 italic">Menampilkan semua data</span>
        </div>
        
        <?php if(empty($entries)): ?>
            <div class="p-12 text-center text-slate-400">
                <span class="text-5xl block mb-4 grayscale opacity-30">📭</span>
                <h3 class="text-lg font-bold text-slate-700">Belum ada pendaftaran masuk.</h3>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500 font-bold uppercase text-xs border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-3">Nomor Lomba</th>
                            <th class="px-6 py-3">Nama Atlet</th>
                            <th class="px-6 py-3">Klub</th>
                            <th class="px-6 py-3">Waktu (Entry)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach($entries as $e): 
                            $dob = new DateTime($e['tanggal_lahir']);
                            $now = new DateTime();
                            $age = $now->diff($dob)->y;
                        ?>
                        <tr class="hover:bg-blue-50 transition">
                            <td class="px-6 py-3">
                                <span class="bg-slate-100 text-slate-700 px-2 py-1 rounded text-xs font-bold">
                                    <?= $e['distance'] ?>m <?= $e['style'] ?>
                                </span>
                                <div class="text-[10px] text-slate-400 mt-1 font-mono"><?= $e['age_group'] ?></div>
                            </td>
                            <td class="px-6 py-3">
                                <div class="font-bold text-slate-800"><?= htmlspecialchars($e['nama_atlet']) ?></div>
                                <div class="text-xs text-slate-500">
                                    <?= $e['jenis_kelamin']=='Male' ? 'Putra' : 'Putri' ?> • <?= $age ?> Th
                                </div>
                            </td>
                            <td class="px-6 py-3 font-semibold text-slate-600 text-xs">
                                <?= htmlspecialchars($e['nama_klub']) ?>
                            </td>
                            <td class="px-6 py-3 font-mono font-bold text-blue-600">
                                <?= $e['entry_time'] ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>