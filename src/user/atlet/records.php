<?php
// FILE: src/user/atlet/records.php
session_start();
require_once __DIR__ . '/../../config/database.php';

// 1. CEK LOGIN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../../public/login.php"); exit;
}
$uid = $_SESSION['user_id'];

// 2. VALIDASI ID ATLET
$atlet_id = $_GET['id'] ?? 0;
$stmtCek = $pdo->prepare("SELECT * FROM swimmers WHERE id = ? AND user_id = ?");
$stmtCek->execute([$atlet_id, $uid]);
$atlet = $stmtCek->fetch();

if (!$atlet) {
    die("Atlet tidak ditemukan atau Anda tidak memiliki akses.");
}

// 3. HANDLE TAMBAH RECORD (POST) - [DISESUAIKAN DENGAN TABLE athlete_records]
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_record'])) {
    // Gabungkan Jarak dan Gaya untuk kolom 'nomor_lomba'
    // Contoh hasil: "50m Gaya Bebas"
    $nomor_lomba = $_POST['distance'] . 'm ' . $_POST['stroke'];
    
    $waktu = $_POST['time_record'];
    $date  = $_POST['record_date'];

    // Simpan ke tabel 'athlete_records'
    // Catatan: Kolom 'meet_name' dihapus karena tidak ada di database Anda
    $stmtIns = $pdo->prepare("INSERT INTO athlete_records (swimmer_id, nomor_lomba, waktu_terbaik, tanggal_dicapai, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmtIns->execute([$atlet_id, $nomor_lomba, $waktu, $date]);
    
    header("Location: records.php?id=" . $atlet_id . "&msg=added");
    exit;
}

// 4. HANDLE HAPUS RECORD (GET) - [DISESUAIKAN]
if (isset($_GET['delete_id'])) {
    $delId = $_GET['delete_id'];
    $stmtDel = $pdo->prepare("DELETE FROM athlete_records WHERE id = ? AND swimmer_id = ?");
    $stmtDel->execute([$delId, $atlet_id]);
    
    header("Location: records.php?id=" . $atlet_id . "&msg=deleted");
    exit;
}

// 5. AMBIL DATA RECORD - [DISESUAIKAN]
// Kita ambil data dari athlete_records
$stmtRec = $pdo->prepare("SELECT * FROM athlete_records WHERE swimmer_id = ? ORDER BY created_at DESC");
$stmtRec->execute([$atlet_id]);
$records = $stmtRec->fetchAll();

// LAYOUT
include __DIR__ . '/../../../views/layout/topbar.php';
include __DIR__ . '/../../../views/layout/sidebar.php';
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <a href="index.php" class="text-slate-400 text-xs font-bold uppercase tracking-widest hover:text-blue-600 transition mb-1 block">
                &larr; Kembali ke Daftar Atlet
            </a>
            <h1 class="text-3xl font-black uppercase italic tracking-tighter text-slate-900">
                <?= htmlspecialchars($atlet['nama_atlet']) ?>
            </h1>
            <div class="flex items-center gap-2 mt-1">
                <span class="px-2 py-0.5 rounded bg-blue-100 text-blue-700 text-[10px] font-black uppercase">
                    <?= $atlet['jenis_kelamin'] == 'L' ? 'PUTRA' : 'PUTRI' ?>
                </span>
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wide">
                    Lahir: <?= date('d M Y', strtotime($atlet['tanggal_lahir'])) ?>
                </span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-1">
            <div class="bg-white p-6 rounded-[1.5rem] shadow-sm border border-slate-200 sticky top-24">
                <h3 class="font-black text-lg text-slate-800 mb-4 uppercase italic">Tambah Waktu</h3>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="add_record" value="1">
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Jarak</label>
                            <select name="distance" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-bold focus:border-blue-500 outline-none">
                                <option value="25">25</option>
                                <option value="50" selected>50</option>
                                <option value="100">100</option>
                                <option value="200">200</option>
                                <option value="400">400</option>
                                <option value="800">800</option>
                                <option value="1500">1500</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Gaya</label>
                            <select name="stroke" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-bold focus:border-blue-500 outline-none">
                                <option value="Gaya Bebas">Gaya Bebas</option>
                                <option value="Gaya Dada">Gaya Dada</option>
                                <option value="Gaya Punggung">Gaya Punggung</option>
                                <option value="Gaya Kupu-kupu">Gaya Kupu-kupu</option>
                                <option value="Gaya Ganti">Gaya Ganti</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Waktu (MM.SS.ms)</label>
                        <input type="text" name="time_record" placeholder="00.30.50" required
                            class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono font-bold focus:border-blue-500 outline-none">
                        <p class="text-[9px] text-slate-400 mt-1">Gunakan titik sebagai pemisah (Contoh: 01.05.50)</p>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Tanggal Record</label>
                        <input type="date" name="record_date" value="<?= date('Y-m-d') ?>" required
                            class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-bold focus:border-blue-500 outline-none">
                    </div>

                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl shadow-lg shadow-blue-200 transition text-xs uppercase tracking-widest mt-2">
                        Simpan Record
                    </button>
                </form>
            </div>
        </div>

        <div class="lg:col-span-2">
            <div class="bg-white rounded-[1.5rem] shadow-sm border border-slate-200 overflow-hidden">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 border-b border-slate-100 text-[10px] font-black uppercase tracking-widest text-slate-400">
                        <tr>
                            <th class="p-4">Nomor / Gaya</th>
                            <th class="p-4 text-center">Waktu Terbaik</th>
                            <th class="p-4">Tanggal Dicapai</th>
                            <th class="p-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if(empty($records)): ?>
                            <tr>
                                <td colspan="4" class="p-8 text-center text-slate-400 italic font-bold">
                                    Belum ada record waktu untuk atlet ini.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($records as $r): 
                                // PARSING DATA: "50m Gaya Bebas" -> Menjadi Jarak: 50, Gaya: Gaya Bebas
                                // Agar layout badge tetap bisa dipakai
                                $dist = ''; 
                                $strokeName = $r['nomor_lomba']; 
                                if (preg_match('/^(\d+)m\s+(.+)$/i', $r['nomor_lomba'], $matches)) {
                                    $dist = $matches[1];
                                    $strokeName = $matches[2];
                                }

                                // Styling Badge Gaya
                                $strokeClass = 'bg-slate-100 text-slate-600';
                                $s = strtoupper($strokeName);
                                if(strpos($s, 'BEBAS')!==false || strpos($s, 'FREE')!==false) $strokeClass = 'bg-blue-50 text-blue-600 border-blue-100';
                                elseif(strpos($s, 'DADA')!==false || strpos($s, 'BREAST')!==false) $strokeClass = 'bg-emerald-50 text-emerald-600 border-emerald-100';
                                elseif(strpos($s, 'PUNGGUNG')!==false || strpos($s, 'BACK')!==false) $strokeClass = 'bg-amber-50 text-amber-600 border-amber-100';
                                elseif(strpos($s, 'KUPU')!==false || strpos($s, 'FLY')!==false) $strokeClass = 'bg-pink-50 text-pink-600 border-pink-100';
                            ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="p-4 align-middle">
                                    <div class="font-black text-slate-700 text-lg"><?= $dist ? $dist.'m' : '-' ?></div>
                                    <span class="inline-block px-2 py-0.5 rounded text-[9px] font-bold border uppercase mt-1 <?= $strokeClass ?>">
                                        <?= $strokeName ?>
                                    </span>
                                </td>
                                <td class="p-4 align-middle text-center">
                                    <div class="font-mono font-black text-xl text-slate-800 tracking-tight">
                                        <?= htmlspecialchars($r['waktu_terbaik']) ?>
                                    </div>
                                </td>
                                <td class="p-4 align-middle">
                                    <div class="font-bold text-slate-600 text-xs">Best Time Record</div>
                                    <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                        📅 <?= date('d/m/Y', strtotime($r['tanggal_dicapai'])) ?>
                                    </div>
                                </td>
                                <td class="p-4 align-middle text-center">
                                    <a href="?id=<?= $atlet_id ?>&delete_id=<?= $r['id'] ?>" onclick="return confirm('Hapus record ini?')" 
                                       class="text-red-400 hover:text-red-600 bg-red-50 hover:bg-red-100 p-2 rounded-lg transition" title="Hapus">
                                        🗑️
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>