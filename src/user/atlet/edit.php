<?php
// src/user/atlet/edit.php
session_start();

// Config database ada di src/config, jadi cukup mundur 2 langkah
require_once __DIR__ . '/../../config/database.php';

// Cek Login (Mundur 3 langkah ke folder public)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../../public/login.php"); exit;
}

$id = $_GET['id'] ?? null;
if (!$id) { header("Location: index.php"); exit; }

// Ambil Data Lama
$stmt = $pdo->prepare("SELECT * FROM swimmers WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $_SESSION['user_id']]);
$atlet = $stmt->fetch();

if (!$atlet) { echo "Data tidak ditemukan."; exit; }

// PROSES UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama   = strtoupper($_POST['nama_atlet']);
    $sekolah= strtoupper($_POST['asal_sekolah']);
    
    // PENTING: Pastikan ini menerima 'L' atau 'P' saja (untuk cegah error Data Truncated)
    $jk     = $_POST['jenis_kelamin']; 
    $tgl    = $_POST['tanggal_lahir'];

    try {
        $sql = "UPDATE swimmers SET nama_atlet=?, asal_sekolah=?, jenis_kelamin=?, tanggal_lahir=? WHERE id=? AND user_id=?";
        $update = $pdo->prepare($sql);
        $update->execute([$nama, $sekolah, $jk, $tgl, $id, $_SESSION['user_id']]);
        
        header("Location: index.php?msg=updated");
        exit;
    } catch (PDOException $e) {
        $error = "Gagal Update: " . $e->getMessage();
    }
}

// --- PERBAIKAN PATH INCLUDE DI BAWAH INI ---
// Menggunakan ../../../ (3 kali mundur)
include __DIR__ . '/../../../views/layout/topbar.php';
include __DIR__ . '/../../../views/layout/sidebar.php';
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">
    <div class="max-w-2xl mx-auto bg-white p-8 rounded-[2rem] shadow-sm border border-slate-100">
        
        <div class="mb-8">
            <h1 class="text-2xl font-black uppercase italic text-slate-800">Edit Atlet</h1>
            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-1">Perbarui data perenang.</p>
        </div>

        <?php if(isset($error)): ?>
            <div class="bg-red-100 text-red-600 p-4 rounded-xl mb-4 text-sm font-bold">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            
            <div>
                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Nama Lengkap</label>
                <input type="text" name="nama_atlet" value="<?= htmlspecialchars($atlet['nama_atlet']) ?>" class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-blue-500 focus:bg-white transition font-bold text-slate-800 uppercase" required>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Asal Sekolah</label>
                    <input type="text" name="asal_sekolah" value="<?= htmlspecialchars($atlet['asal_sekolah']) ?>" class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-blue-500 focus:bg-white transition font-bold text-slate-800 uppercase">
                </div>

                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" value="<?= $atlet['tanggal_lahir'] ?>" class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-blue-500 focus:bg-white transition font-bold text-slate-800" required>
                </div>
            </div>

            <div>
                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Jenis Kelamin</label>
                <div class="grid grid-cols-2 gap-4">
                    <label class="cursor-pointer">
                        <input type="radio" name="jenis_kelamin" value="L" class="peer sr-only" <?= $atlet['jenis_kelamin'] == 'L' ? 'checked' : '' ?>>
                        <div class="p-4 rounded-xl border-2 border-slate-100 bg-slate-50 text-center peer-checked:border-blue-500 peer-checked:bg-blue-50 peer-checked:text-blue-700 transition hover:bg-white">
                            <span class="text-2xl block mb-1">Putra</span>
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 peer-checked:text-blue-400">Laki-Laki</span>
                        </div>
                    </label>

                    <label class="cursor-pointer">
                        <input type="radio" name="jenis_kelamin" value="P" class="peer sr-only" <?= $atlet['jenis_kelamin'] == 'P' ? 'checked' : '' ?>>
                        <div class="p-4 rounded-xl border-2 border-slate-100 bg-slate-50 text-center peer-checked:border-pink-500 peer-checked:bg-pink-50 peer-checked:text-pink-700 transition hover:bg-white">
                            <span class="text-2xl block mb-1">Putri</span>
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 peer-checked:text-pink-400">Perempuan</span>
                        </div>
                    </label>
                </div>
                <p class="text-[10px] text-slate-400 mt-2">*Opsi ini otomatis mengirim kode 'L' atau 'P' ke database.</p>
            </div>

            <div class="flex gap-4 pt-4">
                <a href="index.php" class="w-1/3 py-3 rounded-xl border border-slate-200 text-center font-bold text-slate-500 hover:bg-slate-50 transition">Batal</a>
                <button type="submit" class="w-2/3 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl shadow-lg transition transform active:scale-95">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>