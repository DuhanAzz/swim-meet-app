<?php
// src/user/atlet/records.php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Cek Login
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../../public/login.php"); exit;
}

$swimmer_id = $_GET['id'] ?? null;
if (!$swimmer_id) { header("Location: index.php"); exit; }

// 1. AMBIL DATA ATLET
$stmt = $pdo->prepare("SELECT * FROM swimmers WHERE id = ? AND user_id = ?");
$stmt->execute([$swimmer_id, $_SESSION['user_id']]);
$atlet = $stmt->fetch();

if (!$atlet) { echo "Atlet tidak ditemukan."; exit; }

// 2. PROSES TAMBAH RECORD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_record'])) {
    $dist   = $_POST['distance'];
    $stroke = $_POST['stroke'];
    $time   = $_POST['time_record']; // Format: MM:SS.ms atau SS.ms
    $event  = $_POST['meet_name'];
    $date   = $_POST['record_date'];

    try {
        $sql = "INSERT INTO swimmer_records (swimmer_id, distance, stroke, time_record, meet_name, record_date) VALUES (?, ?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$swimmer_id, $dist, $stroke, $time, $event, $date]);
        header("Location: records.php?id=" . $swimmer_id . "&msg=added"); exit;
    } catch (PDOException $e) { $error = "Error: " . $e->getMessage(); }
}

// 3. PROSES HAPUS RECORD
if (isset($_GET['delete_id'])) {
    $delId = $_GET['delete_id'];
    $pdo->prepare("DELETE FROM swimmer_records WHERE id = ? AND swimmer_id = ?")->execute([$delId, $swimmer_id]);
    header("Location: records.php?id=" . $swimmer_id . "&msg=deleted"); exit;
}

// 4. AMBIL LIST RECORD
$records = $pdo->prepare("SELECT * FROM swimmer_records WHERE swimmer_id = ? ORDER BY distance ASC, stroke ASC");
$records->execute([$swimmer_id]);
$list = $records->fetchAll();

// Include Layout
include __DIR__ . '/../../../views/layout/topbar.php';
include __DIR__ . '/../../../views/layout/sidebar.php';
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
        <div>
            <div class="flex items-center gap-2 text-slate-400 text-xs font-bold uppercase tracking-widest mb-1">
                <a href="index.php" class="hover:text-blue-600 transition">Database Atlet</a>
                <span>/</span>
                <span>Best Time</span>
            </div>
            <h1 class="text-2xl font-black uppercase italic text-slate-800">
                <?= htmlspecialchars($atlet['nama_atlet']) ?>
            </h1>
        </div>
        <a href="index.php" class="bg-white border border-slate-200 text-slate-600 hover:bg-slate-100 px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition">
            Kembali
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <div class="lg:col-span-1">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 sticky top-24">
                <h3 class="font-bold text-slate-700 mb-4 flex items-center gap-2">
                    <span class="bg-blue-100 text-blue-600 w-6 h-6 flex items-center justify-center rounded-full text-xs">If</span>
                    Input Waktu Baru
                </h3>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="add_record" value="1">
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label-text">Jarak (m)</label>
                            <select name="distance" class="input-field" required>
                                <option value="50">50m</option>
                                <option value="100">100m</option>
                                <option value="200">200m</option>
                                <option value="400">400m</option>
                                <option value="800">800m</option>
                                <option value="1500">1500m</option>
                            </select>
                        </div>
                        <div>
                            <label class="label-text">Gaya</label>
                            <select name="stroke" class="input-field" required>
                                <option value="FREE">Freestyle</option>
                                <option value="BACK">Backstroke</option>
                                <option value="BREAST">Breaststroke</option>
                                <option value="BUTTERFLY">Butterfly</option>
                                <option value="IM">Ind. Medley</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="label-text">Waktu (Contoh: 00:28.45)</label>
                        <input type="text" name="time_record" placeholder="MM:SS.ms" class="input-field font-mono" required>
                    </div>

                    <div>
                        <label class="label-text">Event / Kejuaraan (Opsional)</label>
                        <input type="text" name="meet_name" placeholder="Latihan / Kejurda..." class="input-field uppercase">
                    </div>

                    <div>
                        <label class="label-text">Tanggal</label>
                        <input type="date" name="record_date" value="<?= date('Y-m-d') ?>" class="input-field">
                    </div>

                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl shadow-lg transition text-xs uppercase tracking-widest mt-2">
                        + Simpan Record
                    </button>
                </form>
            </div>
        </div>

        <div class="lg:col-span-2">
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 border-b border-slate-100 text-[10px] font-black uppercase tracking-widest text-slate-400">
                        <tr>
                            <th class="p-4">Nomor</th>
                            <th class="p-4">Waktu</th>
                            <th class="p-4">Event & Tanggal</th>
                            <th class="p-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if(empty($list)): ?>
                            <tr><td colspan="4" class="p-8 text-center text-slate-400 font-bold italic">Belum ada catatan waktu.</td></tr>
                        <?php else: ?>
                            <?php foreach($list as $r): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="p-4 font-bold text-slate-700">
                                    <?= $r['distance'] ?>m <span class="text-slate-400 mx-1">|</span> <?= $r['stroke'] ?>
                                </td>
                                <td class="p-4">
                                    <span class="font-mono font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded">
                                        <?= htmlspecialchars($r['time_record']) ?>
                                    </span>
                                </td>
                                <td class="p-4">
                                    <div class="text-xs font-bold text-slate-700 uppercase"><?= htmlspecialchars($r['meet_name'] ?: '-') ?></div>
                                    <div class="text-[10px] text-slate-400"><?= $r['record_date'] ?></div>
                                </td>
                                <td class="p-4 text-center">
                                    <a href="records.php?id=<?= $swimmer_id ?>&delete_id=<?= $r['id'] ?>" onclick="return confirm('Hapus waktu ini?')" class="text-red-400 hover:text-red-600 hover:bg-red-50 p-2 rounded-lg transition">
                                        🗑
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

<style>
    .label-text { display: block; font-size: 0.65rem; font-weight: 900; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem; }
    .input-field { width: 100%; padding: 0.5rem 0.75rem; border-radius: 0.5rem; background-color: #f8fafc; border: 1px solid #e2e8f0; font-size: 0.875rem; font-weight: 700; color: #1e293b; outline: none; transition: all; }
    .input-field:focus { background-color: #ffffff; border-color: #3b82f6; ring: 2px solid #3b82f6; }
</style>