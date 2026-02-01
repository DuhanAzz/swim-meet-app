<?php
// FILE: src/admin/verifikasi.php
session_start();
require_once __DIR__ . '/../../src/config/database.php';

// 1. CEK OTORITAS
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Akses Ditolak.");
}
$uid = $_SESSION['user_id'];

// 2. DAPATKAN ID EVENT MILIK ADMIN INI
$stmtEv = $pdo->prepare("SELECT id FROM events WHERE created_by = ? ORDER BY id DESC LIMIT 1");
$stmtEv->execute([$uid]);
$activeEvent = $stmtEv->fetch();
$eventId = $activeEvent['id'] ?? 0;

if ($eventId == 0) {
    die("Anda belum membuat event. Silakan buat event terlebih dahulu di dashboard.");
}

// 3. HANDLE AKSI VALIDASI (Saat tombol diklik)
if (isset($_GET['action']) && isset($_GET['sid'])) {
    $swimmerId = $_GET['sid'];
    
    if ($_GET['action'] == 'verify') {
        // Update status swimmer jadi verified
        $sqlUpd = "UPDATE swimmers SET status_verifikasi = 'verified' WHERE id = ?";
        $pdo->prepare($sqlUpd)->execute([$swimmerId]);
        $msg = "Atlet berhasil diverifikasi!";
    } 
    elseif ($_GET['action'] == 'reject') {
        // Kembalikan ke unverified / tolak
        $sqlUpd = "UPDATE swimmers SET status_verifikasi = 'unverified' WHERE id = ?";
        $pdo->prepare($sqlUpd)->execute([$swimmerId]);
        $msg = "Status verifikasi dibatalkan.";
    }
    
    // Redirect agar bersih url
    echo "<script>alert('$msg'); window.location.href='verifikasi.php';</script>";
    exit;
}

// 4. AMBIL DATA ATLET YANG SUDAH MENDAFTAR (Logic Baru)
// Kita ambil data dari tabel 'event_entries' yang terhubung ke event ini
// JOIN ke swimmers dan clubs untuk ambil nama
$sqlList = "
    SELECT DISTINCT 
        s.id as swimmer_id, 
        s.nama_atlet, 
        s.jenis_kelamin, 
        s.status_verifikasi,
        c.nama_klub,
        (SELECT COUNT(*) FROM event_entries ee2 WHERE ee2.swimmer_id = s.id AND ee2.event_id = ?) as total_nomor
    FROM event_entries ee
    JOIN swimmers s ON ee.swimmer_id = s.id
    JOIN clubs c ON s.club_id = c.id
    WHERE ee.event_id = ?
    ORDER BY c.nama_klub ASC, s.nama_atlet ASC
";

$stmtList = $pdo->prepare($sqlList);
$stmtList->execute([$eventId, $eventId]);
$swimmers = $stmtList->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">

    <div class="mb-8">
        <h1 class="text-3xl font-black text-slate-800 uppercase italic tracking-tighter">Verifikasi Atlet</h1>
        <p class="text-sm text-slate-500 font-bold uppercase tracking-widest mt-1">
            Daftar atlet yang sudah masuk entry event ini.
        </p>
    </div>

    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-800 text-white font-black uppercase text-[10px] tracking-widest">
                    <tr>
                        <th class="px-6 py-4">Nama Atlet</th>
                        <th class="px-6 py-4">Klub / Sekolah</th>
                        <th class="px-6 py-4 text-center">Gender</th>
                        <th class="px-6 py-4 text-center">Jml Nomor</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if(empty($swimmers)): ?>
                        <tr><td colspan="6" class="px-6 py-8 text-center text-slate-400 italic">Belum ada entry masuk.</td></tr>
                    <?php else: ?>
                        <?php foreach($swimmers as $s): ?>
                        <tr class="hover:bg-blue-50/50 transition duration-200">
                            <td class="px-6 py-4 font-bold text-slate-700"><?= htmlspecialchars($s['nama_atlet']) ?></td>
                            <td class="px-6 py-4 text-slate-500 font-medium"><?= htmlspecialchars($s['nama_klub']) ?></td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-2 py-1 rounded text-[10px] font-black uppercase <?= $s['jenis_kelamin']=='L'?'bg-blue-100 text-blue-600':'bg-pink-100 text-pink-600' ?>">
                                    <?= $s['jenis_kelamin'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center font-mono font-bold"><?= $s['total_nomor'] ?></td>
                            <td class="px-6 py-4 text-center">
                                <?php if($s['status_verifikasi'] == 'verified'): ?>
                                    <span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wide">
                                        Verified
                                    </span>
                                <?php else: ?>
                                    <span class="bg-orange-100 text-orange-700 px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wide animate-pulse">
                                        Pending
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <?php if($s['status_verifikasi'] != 'verified'): ?>
                                    <a href="?action=verify&sid=<?= $s['swimmer_id'] ?>" class="inline-block bg-blue-600 text-white px-4 py-2 rounded-lg text-[10px] font-black uppercase hover:bg-blue-700 transition shadow-lg shadow-blue-200">
                                        ✅ Validasi
                                    </a>
                                <?php else: ?>
                                    <a href="?action=reject&sid=<?= $s['swimmer_id'] ?>" class="text-red-400 text-[10px] font-bold uppercase hover:text-red-600 hover:underline" onclick="return confirm('Batalkan verifikasi atlet ini?')">
                                        Batal
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>