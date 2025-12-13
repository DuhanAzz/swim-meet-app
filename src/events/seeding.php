<?php
session_start();
// PERBAIKAN: Gunakan '../' (1 langkah mundur) agar pas ke folder src/config
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') die("Akses Ditolak.");

$event_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

// 1. Ambil Data Event & Pastikan Milik Admin Ini
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND user_id = ?");
$stmt->execute([$event_id, $user_id]);
$event = $stmt->fetch();

if (!$event) {
    $_SESSION['toast_type'] = 'error';
    $_SESSION['toast_message'] = 'Event tidak ditemukan atau bukan milik Anda.';
    header("Location: index.php"); exit;
}

// 2. LOGIKA GENERATE SEEDING (AUTO)
if (isset($_POST['generate_seeding'])) {
    try {
        $pdo->beginTransaction();

        // A. Hapus Seeding Lama (Reset)
        $pdo->prepare("DELETE FROM heat_entries WHERE heat_id IN (SELECT id FROM heats WHERE event_id = ?)")->execute([$event_id]);
        $pdo->prepare("DELETE FROM heats WHERE event_id = ?")->execute([$event_id]);

        // B. Ambil Data Atlet (SIMULASI: Ambil Random 10-30 atlet dari database)
        // Di sistem riil, ini diambil dari tabel pendaftaran
        $swimmers = $pdo->query("SELECT id FROM swimmers ORDER BY RAND() LIMIT 24")->fetchAll(); 
        
        if (count($swimmers) > 0) {
            $lanes_per_heat = 8; // Standar kolam 8 lintasan
            $total_swimmers = count($swimmers);
            $total_heats = ceil($total_swimmers / $lanes_per_heat);
            
            $swimmer_idx = 0;
            
            for ($h = 1; $h <= $total_heats; $h++) {
                // Buat Heat Baru
                $stmt = $pdo->prepare("INSERT INTO heats (event_id, heat_number) VALUES (?, ?)");
                $stmt->execute([$event_id, $h]);
                $heat_id = $pdo->lastInsertId();

                // Isi Lintasan
                for ($l = 1; $l <= $lanes_per_heat; $l++) {
                    if ($swimmer_idx < $total_swimmers) {
                        $sid = $swimmers[$swimmer_idx]['id'];
                        $stmtEntry = $pdo->prepare("INSERT INTO heat_entries (heat_id, swimmer_id, lane_number) VALUES (?, ?, ?)");
                        $stmtEntry->execute([$heat_id, $sid, $l]);
                        $swimmer_idx++;
                    }
                }
            }
            $_SESSION['toast_type'] = 'success';
            $_SESSION['toast_message'] = 'Seeding berhasil! ' . $total_heats . ' Seri terbentuk.';
        } else {
            $_SESSION['toast_type'] = 'warning';
            $_SESSION['toast_message'] = 'Belum ada data atlet di database master.';
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['toast_type'] = 'error';
        $_SESSION['toast_message'] = 'Gagal Seeding: ' . $e->getMessage();
    }
    header("Location: seeding.php?id=$event_id"); exit;
}

// 3. AMBIL DATA SEEDING UTK DITAMPILKAN
$stmt = $pdo->prepare("SELECT 
    h.heat_number, 
    he.lane_number, 
    s.nama_atlet, 
    c.nama_klub 
    FROM heat_entries he
    JOIN heats h ON he.heat_id = h.id
    JOIN swimmers s ON he.swimmer_id = s.id
    JOIN clubs c ON s.club_id = c.id
    WHERE h.event_id = ?
    ORDER BY h.heat_number ASC, he.lane_number ASC");
$stmt->execute([$event_id]);
$entries = $stmt->fetchAll(PDO::FETCH_GROUP); // Group by Heat Number

// INCLUDE VIEW (Mundur 2 langkah karena views ada di luar src)
include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 mt-16 bg-slate-50 min-h-screen font-sans">
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="bg-blue-100 text-blue-700 text-xs font-bold px-2 py-1 rounded">Start List Manager</span>
            </div>
            <h1 class="text-2xl font-black text-slate-800 tracking-tight uppercase"><?= htmlspecialchars($event['nama_event']) ?></h1>
            <p class="text-sm text-slate-500">Atur pembagian lintasan (seeding) untuk nomor lomba ini.</p>
        </div>
        <div class="flex gap-2">
            <a href="index.php" class="bg-white border border-slate-300 text-slate-600 px-4 py-2 rounded-lg font-bold text-sm hover:bg-slate-50 transition">Kembali</a>
            <form method="POST" onsubmit="return confirm('Generate ulang akan menghapus data hasil lomba yang sudah ada. Lanjut?');">
                <button type="submit" name="generate_seeding" class="bg-blue-600 text-white px-4 py-2 rounded-lg font-bold text-sm hover:bg-blue-700 shadow-lg shadow-blue-600/30 transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    Auto Generate / Acak
                </button>
            </form>
        </div>
    </div>

    <?php if(empty($entries)): ?>
        <div class="bg-white p-10 rounded-xl border border-slate-200 text-center shadow-sm">
            <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl">📭</div>
            <h3 class="font-bold text-slate-800 text-lg">Belum Ada Seeding</h3>
            <p class="text-slate-500 text-sm mb-6">Klik tombol "Auto Generate" di atas untuk memasukkan atlet secara otomatis.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 gap-6">
            <?php foreach($entries as $heatNo => $swimmers): ?>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-slate-50 p-4 border-b border-slate-200 flex justify-between items-center">
                    <h3 class="font-bold text-slate-800">SERI <?= $heatNo ?> (Heat <?= $heatNo ?>)</h3>
                    <span class="text-xs font-bold bg-blue-100 text-blue-700 px-2 py-1 rounded"><?= count($swimmers) ?> Atlet</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs text-slate-500 uppercase bg-white border-b border-slate-100">
                            <tr>
                                <th class="px-6 py-3 w-20 text-center">Lane</th>
                                <th class="px-6 py-3">Nama Atlet</th>
                                <th class="px-6 py-3">Asal Klub</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php foreach($swimmers as $s): ?>
                            <tr class="hover:bg-blue-50/30 transition">
                                <td class="px-6 py-3 text-center font-bold text-slate-700 bg-slate-50/50"><?= $s['lane_number'] ?></td>
                                <td class="px-6 py-3 font-bold text-slate-800"><?= htmlspecialchars($s['nama_atlet']) ?></td>
                                <td class="px-6 py-3 text-slate-500"><?= htmlspecialchars($s['nama_klub']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>
