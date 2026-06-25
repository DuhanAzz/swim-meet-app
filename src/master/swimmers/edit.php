<?php
// FILE: src/master/swimmers/edit.php
session_start();
require_once __DIR__ . '/../../config/database.php';

// 1. CEK AKSES
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'master') {
    header("Location: ../../../public/login.php"); exit;
}

// 2. AMBIL DATA LAMA
$id = $_GET['id'] ?? null;
if (!$id) { header("Location: index.php"); exit; }

$stmt = $pdo->prepare("SELECT * FROM swimmers WHERE id = ?");
$stmt->execute([$id]);
$swimmer = $stmt->fetch();

if (!$swimmer) { die("Data atlet tidak ditemukan."); }

// 3. AMBIL LIST KLUB
$clubs = $pdo->query("SELECT id, nama_klub, kota FROM clubs ORDER BY nama_klub ASC")->fetchAll();

// 4. PROSES SIMPAN DATA (POST)
$message = "";
$error   = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Tangkap Input
    $uid        = $_POST['uid'];
    $nama       = $_POST['nama_atlet'];
    $gender     = $_POST['jenis_kelamin'];
    $tgl_lahir  = $_POST['tanggal_lahir'];
    $sekolah    = $_POST['asal_sekolah'];
    $club_id    = !empty($_POST['club_id']) ? $_POST['club_id'] : NULL;

    try {
        $pdo->beginTransaction(); // Mulai Transaksi

        // ============================================================
        // A. LOGIKA MUTASI (UPDATE: Tambah Log ke System Health)
        // ============================================================
        
        // 1. Ambil Club ID Lama
        $stmtOld = $pdo->prepare("SELECT club_id FROM swimmers WHERE id = ?");
        $stmtOld->execute([$id]);
        $currentData = $stmtOld->fetch();
        $old_club_id = $currentData['club_id'];

        // 2. Cek apakah Klub Berubah?
        if ($old_club_id != $club_id) {
            
            // a. Catat di tabel swimmer_transfers (Untuk Menu Riwayat Mutasi)
            $sqlTransfer = "INSERT INTO swimmer_transfers (swimmer_id, old_club_id, new_club_id, processed_by, notes, transfer_date) 
                            VALUES (?, ?, ?, ?, ?, NOW())";
            $pdo->prepare($sqlTransfer)->execute([
                $id,                    
                $old_club_id,           
                $club_id,               
                $_SESSION['user_id'],   
                "Mutasi via Edit Data (Manual)" 
            ]);

            // b. Catat di tabel system_logs (BARU: Agar muncul di System Health)
            $logDesc = "Mutasi Klub atlet: $nama (UID: $uid)";
            $sqlLog = "INSERT INTO system_logs (user_id, action_type, target_id, description, ip_address) 
                       VALUES (?, 'MUTASI_KLUB', ?, ?, ?)";
            $pdo->prepare($sqlLog)->execute([
                $_SESSION['user_id'],
                $id,
                $logDesc,
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
            ]);
        }
        else {
            // Jika klub TIDAK berubah, tapi data lain berubah, catat sebagai UPDATE biasa
            $logDesc = "Update data profil atlet: $nama";
            $sqlLog = "INSERT INTO system_logs (user_id, action_type, target_id, description, ip_address) 
                       VALUES (?, 'UPDATE_SWIMMER', ?, ?, ?)";
            $pdo->prepare($sqlLog)->execute([
                $_SESSION['user_id'],
                $id,
                $logDesc,
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
            ]);
        }

        // ============================================================
        // B. UPDATE DATA UTAMA ATLET
        // ============================================================
        $sql = "UPDATE swimmers SET 
                    uid = ?, 
                    nama_atlet = ?, 
                    jenis_kelamin = ?, 
                    tanggal_lahir = ?, 
                    asal_sekolah = ?, 
                    club_id = ? 
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$uid, $nama, $gender, $tgl_lahir, $sekolah, $club_id, $id]);

        $pdo->commit(); 

        header("Location: index.php?msg=updated"); exit;

    } catch (PDOException $e) {
        $pdo->rollBack(); 
        $error = "Gagal menyimpan: " . $e->getMessage();
    }
}

include __DIR__ . '/../../../views/layout/topbar.php';
include __DIR__ . '/../../../views/layout/sidebar.php';
?>

<div class="p-4 sm:ml-64">
    <div class="p-4 mt-14 max-w-3xl mx-auto">
        
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-black text-slate-800 uppercase italic">Edit Data Atlet</h1>
                <p class="text-sm text-slate-500">UID: <?= htmlspecialchars($swimmer['uid']) ?></p>
            </div>
            <a href="index.php" class="text-sm font-bold text-slate-500 hover:text-slate-800">← Kembali</a>
        </div>

        <?php if($error): ?>
            <div class="bg-red-100 text-red-700 p-4 rounded-xl mb-4 text-sm font-bold border border-red-200">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-[2rem] shadow-xl border border-slate-100 p-8">
            <form method="POST">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-[10px] font-black uppercase text-slate-400 tracking-widest mb-2">Nomor UID</label>
                        <input type="text" name="uid" value="<?= htmlspecialchars($swimmer['uid']) ?>" 
                               class="w-full px-4 py-3 rounded-xl bg-slate-50 border-slate-200 text-slate-700 font-bold text-sm focus:border-blue-500 focus:bg-white transition">
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase text-slate-400 tracking-widest mb-2">Nama Lengkap</label>
                        <input type="text" name="nama_atlet" value="<?= htmlspecialchars($swimmer['nama_atlet']) ?>" 
                               class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-0 font-bold text-slate-800 text-sm" required>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase text-slate-400 tracking-widest mb-2">Jenis Kelamin</label>
                        <select name="jenis_kelamin" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-blue-500 font-bold text-slate-800 text-sm">
                            <option value="L" <?= $swimmer['jenis_kelamin'] == 'L' ? 'selected' : '' ?>>Laki-laki</option>
                            <option value="P" <?= $swimmer['jenis_kelamin'] == 'P' ? 'selected' : '' ?>>Perempuan</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase text-slate-400 tracking-widest mb-2">Tanggal Lahir</label>
                        <input type="date" name="tanggal_lahir" value="<?= $swimmer['tanggal_lahir'] ?>" 
                               class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-blue-500 font-bold text-slate-800 text-sm" required>
                    </div>

                    <div class="col-span-1 md:col-span-2 bg-blue-50 p-4 rounded-xl border border-blue-100">
                        <label class="block text-[10px] font-black uppercase text-blue-500 tracking-widest mb-2">Klub / Perkumpulan</label>
                        <select name="club_id" class="w-full px-4 py-3 rounded-xl border border-blue-200 focus:border-blue-500 font-bold text-slate-800 text-sm">
                            <option value="">-- Tanpa Klub (Unattached) --</option>
                            <?php foreach($clubs as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $swimmer['club_id'] == $c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['nama_klub']) ?> (<?= htmlspecialchars($c['kota'] ?? '-') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-[10px] text-blue-400 mt-2 italic">
                            ℹ️ Perubahan klub akan dicatat otomatis di Riwayat Mutasi & System Log.
                        </p>
                    </div>

                    <div class="col-span-1 md:col-span-2">
                        <label class="block text-[10px] font-black uppercase text-slate-400 tracking-widest mb-2">Asal Sekolah</label>
                        <input type="text" name="asal_sekolah" value="<?= htmlspecialchars($swimmer['asal_sekolah']) ?>" 
                               class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-blue-500 font-bold text-slate-800 text-sm">
                    </div>
                </div>

                <div class="flex gap-4 pt-4 border-t border-slate-100">
                    <button type="submit" class="flex-1 bg-slate-900 text-white px-6 py-4 rounded-xl font-black uppercase tracking-widest text-xs hover:bg-blue-600 transition shadow-lg">
                        Simpan Perubahan
                    </button>
                    <a href="index.php" class="px-6 py-4 rounded-xl font-bold uppercase tracking-widest text-xs text-slate-400 hover:text-slate-600 hover:bg-slate-50 transition">
                        Batal
                    </a>
                </div>

            </form>
        </div>

    </div>
</div>