<?php
// src/user/atlet/create.php
session_start();

// 1. CONFIG DATABASE
require_once __DIR__ . '/../../config/database.php';

// 2. CEK LOGIN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../../public/login.php"); exit;
}

// 3. PROSES PENYIMPANAN DATA (Jika Form Disubmit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId       = $_SESSION['user_id'];
    $nama_atlet   = trim($_POST['nama_atlet']);
    $jenis_kelamin= $_POST['jenis_kelamin'];
    $tanggal_lahir= $_POST['tanggal_lahir'];
    $asal_sekolah = trim($_POST['asal_sekolah']);

    // Validasi Sederhana
    if (empty($nama_atlet) || empty($jenis_kelamin) || empty($tanggal_lahir)) {
        $error = "Nama, Jenis Kelamin, dan Tanggal Lahir wajib diisi!";
    } else {
        try {
            // Query Insert
            $sql = "INSERT INTO swimmers (user_id, nama_atlet, jenis_kelamin, tanggal_lahir, asal_sekolah, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId, $nama_atlet, $jenis_kelamin, $tanggal_lahir, $asal_sekolah]);

            // Redirect ke Index jika berhasil
            header("Location: index.php?msg=created");
            exit;
        } catch (PDOException $e) {
            $error = "Gagal menyimpan data: " . $e->getMessage();
        }
    }
}

// 4. INCLUDE LAYOUT
include __DIR__ . '/../../../views/layout/topbar.php';
include __DIR__ . '/../../../views/layout/sidebar.php';
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">
    
    <div class="max-w-2xl mx-auto">
        <div class="mb-8">
            <a href="index.php" class="text-slate-400 text-xs font-bold uppercase tracking-widest hover:text-blue-600 transition mb-2 block">
                &larr; Kembali ke Daftar
            </a>
            <h1 class="text-3xl font-black uppercase italic tracking-tighter text-slate-900">Tambah Atlet Baru</h1>
            <p class="text-xs text-slate-500 font-bold uppercase tracking-widest mt-1">Lengkapi profil atlet renang.</p>
        </div>

        <?php if(isset($error)): ?>
            <div class="bg-red-100 text-red-700 px-4 py-3 rounded-xl text-sm font-bold mb-6 border border-red-200 shadow-sm">
                ⚠️ <?= $error ?>
            </div>
        <?php endif; ?>

        <div class="bg-white p-8 rounded-[2rem] shadow-xl shadow-slate-200/50 border border-slate-100 relative overflow-hidden">
            <div class="absolute top-0 right-0 p-6 opacity-5 text-9xl pointer-events-none select-none">🏊</div>

            <form method="POST" class="space-y-6 relative z-10">
                
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Nama Lengkap Atlet</label>
                    <input type="text" name="nama_atlet" placeholder="Contoh: Budi Santoso" required
                        class="w-full bg-slate-50 border-2 border-slate-100 focus:border-blue-500 focus:bg-white rounded-xl px-4 py-3 font-bold text-slate-700 outline-none transition placeholder:text-slate-300">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Jenis Kelamin</label>
                        <div class="flex gap-4">
                            <label class="cursor-pointer w-full">
                                <input type="radio" name="jenis_kelamin" value="L" class="peer sr-only" checked>
                                <div class="w-full text-center px-4 py-3 bg-slate-50 border-2 border-slate-100 rounded-xl font-bold text-slate-400 text-sm peer-checked:bg-blue-50 peer-checked:text-blue-600 peer-checked:border-blue-200 transition">
                                    PUTRA
                                </div>
                            </label>
                            <label class="cursor-pointer w-full">
                                <input type="radio" name="jenis_kelamin" value="P" class="peer sr-only">
                                <div class="w-full text-center px-4 py-3 bg-slate-50 border-2 border-slate-100 rounded-xl font-bold text-slate-400 text-sm peer-checked:bg-pink-50 peer-checked:text-pink-600 peer-checked:border-pink-200 transition">
                                    PUTRI
                                </div>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Tanggal Lahir</label>
                        <input type="date" name="tanggal_lahir" required
                            class="w-full bg-slate-50 border-2 border-slate-100 focus:border-blue-500 focus:bg-white rounded-xl px-4 py-3 font-bold text-slate-700 outline-none transition">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Asal Sekolah / Klub</label>
                    <input type="text" name="asal_sekolah" placeholder="Contoh: SMPN 1 Yogyakarta / Tirta Club"
                        class="w-full bg-slate-50 border-2 border-slate-100 focus:border-blue-500 focus:bg-white rounded-xl px-4 py-3 font-bold text-slate-700 outline-none transition placeholder:text-slate-300">
                </div>

                <div class="pt-4 border-t border-slate-100 mt-6">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-4 rounded-xl shadow-lg shadow-blue-200 hover:shadow-blue-300 transition transform hover:-translate-y-1 uppercase tracking-widest text-sm">
                        Simpan Data Atlet
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>