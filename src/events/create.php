<?php
session_start();
require_once __DIR__ . '/../../src/config/database.php'; // Pastikan path ini benar

// 1. Cek Login & Role Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../public/login.php"); exit;
}

$uid = $_SESSION['user_id']; 

// 2. HANDLE SIMPAN DATA (CREATE)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Ambil Input
        $nomor  = $_POST['nomor_acara'];
        $jarak  = $_POST['jarak'];
        $gaya   = $_POST['gaya'];
        $jk_raw = $_POST['jenis_kelamin']; // L, P, atau Campuran
        $bawah  = $_POST['batas_umur_bawah'];
        $atas   = $_POST['batas_umur_atas'];
        $harga  = $_POST['biaya_pendaftaran'] ?? 50000;
        
        // Buat Label Gender untuk Nama Event
        $labelJK = ($jk_raw == 'L') ? 'Putra' : (($jk_raw == 'P') ? 'Putri' : 'Mixed');

        // Format Nama Event: "50 M Gaya Bebas Putra KU 10-12"
        $nama_event = "$jarak M $gaya $labelJK KU $bawah-$atas Th"; 
        
        // Format Age Group String (untuk tampilan index)
        $age_group_str = "KU $bawah - $atas Th";

        // 3. QUERY INSERT KE TABEL 'event_numbers' (FIXED)
        $sql = "INSERT INTO event_numbers 
                (organizer_id, event_number, event_name, distance, stroke, jenis_kelamin, age_group, age_min, age_max, price, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $uid,           // organizer_id (Sesuai index.php)
            $nomor,         // event_number
            $nama_event,    // event_name
            $jarak,         // distance
            $gaya,          // stroke
            $jk_raw,        // jenis_kelamin (L/P/Campuran)
            $age_group_str, // age_group (String)
            $bawah,         // age_min (Int)
            $atas,          // age_max (Int)
            $harga          // price
        ]);
        
        // Notifikasi Sukses
        $_SESSION['swal_type'] = 'success'; 
        $_SESSION['swal_msg'] = 'Nomor ' . $nomor . ' berhasil ditambahkan!';
        
        // Refresh halaman
        header("Location: create.php"); exit();

    } catch (PDOException $e) {
        $_SESSION['swal_type'] = 'error'; 
        $_SESSION['swal_msg'] = 'Gagal Database: ' . $e->getMessage();
    }
}

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tight">Buat Nomor Lomba</h1>
            <p class="text-sm text-slate-500">Input nomor baru untuk event Anda.</p>
        </div>
        <a href="index.php" class="text-slate-500 hover:text-blue-600 font-bold text-xs uppercase tracking-widest flex items-center gap-2">
            <span>&larr;</span> Lihat Daftar Event
        </a>
    </div>

    <?php if(isset($_SESSION['swal_msg'])): ?>
        <div class="mb-6 px-6 py-4 rounded-xl shadow-lg font-bold text-white flex items-center gap-3 <?= $_SESSION['swal_type']=='success' ? 'bg-green-500' : 'bg-red-500' ?>">
            <span><?= $_SESSION['swal_type']=='success' ? '✅' : '⚠️' ?></span>
            <?= $_SESSION['swal_msg']; unset($_SESSION['swal_msg']); unset($_SESSION['swal_type']); ?>
        </div>
    <?php endif; ?>

    <div class="bg-white p-8 rounded-xl shadow-sm border border-slate-200 max-w-4xl">
        <form method="POST" class="space-y-8">
            
            <div class="p-6 bg-blue-50 rounded-xl border border-blue-100">
                <h3 class="text-xs font-black text-blue-800 uppercase tracking-widest mb-4 border-b border-blue-200 pb-2">
                    Detail Nomor
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-slate-700 font-bold mb-2 text-[10px] uppercase tracking-wide">No. Acara</label>
                        <input type="text" name="nomor_acara" class="w-full px-3 py-3 border border-slate-300 rounded-lg text-center font-black text-lg focus:ring-2 focus:ring-blue-500" placeholder="101" required>
                    </div>

                    <div class="md:col-span-3">
                        <label class="block text-slate-700 font-bold mb-2 text-[10px] uppercase tracking-wide">Jarak</label>
                        <select name="jarak" class="w-full px-3 py-3 border border-slate-300 rounded-lg font-bold text-sm bg-white focus:ring-2 focus:ring-blue-500">
                            <option value="25">25 Meter</option>
                            <option value="50" selected>50 Meter</option>
                            <option value="100">100 Meter</option>
                            <option value="200">200 Meter</option>
                            <option value="400">400 Meter</option>
                            <option value="800">800 Meter</option>
                            <option value="1500">1500 Meter</option>
                        </select>
                    </div>

                    <div class="md:col-span-4">
                        <label class="block text-slate-700 font-bold mb-2 text-[10px] uppercase tracking-wide">Gaya</label>
                        <select name="gaya" class="w-full px-3 py-3 border border-slate-300 rounded-lg font-bold text-sm bg-white focus:ring-2 focus:ring-blue-500">
                            <option value="Gaya Bebas">Gaya Bebas</option>
                            <option value="Gaya Dada">Gaya Dada</option>
                            <option value="Gaya Punggung">Gaya Punggung</option>
                            <option value="Gaya Kupu-kupu">Gaya Kupu-kupu</option>
                            <option value="Gaya Ganti">Gaya Ganti</option>
                            <option value="Kick Bebas">Kick Bebas</option>
                            <option value="Kick Dada">Kick Dada</option>
                            <option value="Kick Punggung">Kick Punggung</option>
                            <option value="Kick Kupu">Kick Kupu</option>
                        </select>
                    </div>

                    <div class="md:col-span-3">
                        <label class="block text-slate-700 font-bold mb-2 text-[10px] uppercase tracking-wide">Umur (Min - Max)</label>
                        <div class="flex items-center gap-2">
                            <input type="number" name="batas_umur_bawah" class="w-full px-2 py-3 border border-slate-300 rounded-lg text-center font-bold text-sm" placeholder="Min" required>
                            <span class="text-slate-400 font-bold">-</span>
                            <input type="number" name="batas_umur_atas" class="w-full px-2 py-3 border border-slate-300 rounded-lg text-center font-bold text-sm" placeholder="Max" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <label class="block text-slate-700 font-bold mb-2 text-[10px] uppercase tracking-wide">Jenis Kelamin</label>
                    <div class="grid grid-cols-3 gap-3">
                        <label class="cursor-pointer">
                            <input type="radio" name="jenis_kelamin" value="L" class="peer sr-only" required checked>
                            <div class="p-3 border-2 border-slate-200 rounded-lg text-center peer-checked:border-blue-600 peer-checked:bg-blue-50 peer-checked:text-blue-700 hover:bg-slate-50 transition">
                                <span class="font-bold text-xs">Putra (L)</span>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="jenis_kelamin" value="P" class="peer sr-only">
                            <div class="p-3 border-2 border-slate-200 rounded-lg text-center peer-checked:border-pink-600 peer-checked:bg-pink-50 peer-checked:text-pink-700 hover:bg-slate-50 transition">
                                <span class="font-bold text-xs">Putri (P)</span>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="jenis_kelamin" value="Campuran" class="peer sr-only">
                            <div class="p-3 border-2 border-slate-200 rounded-lg text-center peer-checked:border-purple-600 peer-checked:bg-purple-50 peer-checked:text-purple-700 hover:bg-slate-50 transition">
                                <span class="font-bold text-xs">Mixed</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-slate-700 font-bold mb-2 text-[10px] uppercase tracking-wide">Biaya Pendaftaran (Rp)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-3 text-slate-400 font-bold text-sm">Rp</span>
                        <input type="number" name="biaya_pendaftaran" value="50000" class="w-full px-3 py-3 pl-10 border border-slate-300 rounded-lg font-bold text-slate-700 focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <p class="text-[9px] text-slate-400 mt-1 italic">*Harga default sistem.</p>
                </div>
            </div>

            <button type="submit" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-black py-4 rounded-xl shadow-lg transition transform hover:-translate-y-1 uppercase tracking-widest text-xs mt-6">
                Simpan Nomor Lomba
            </button>
        </form>
    </div>
</div>