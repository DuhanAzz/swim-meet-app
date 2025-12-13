<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') die("Akses Ditolak.");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Ambil Input Terpisah
    $nomor  = $_POST['nomor_acara'];
    $jarak  = $_POST['jarak'];
    $gaya   = $_POST['gaya'];
    $bawah  = $_POST['batas_umur_bawah'];
    $atas   = $_POST['batas_umur_atas'];
    $jk     = $_POST['jenis_kelamin'];
    
    // 2. FORMAT NAMA OTOMATIS
    // Format: "101 - 50M Gaya Bebas - KU 10-12"
    // Tips: KU (Kelompok Umur)
    $ku_label = "KU " . $bawah . "-" . $atas;
    $nama_generated = "$nomor - $jarak" . "M " . "$gaya - $ku_label";

    // Data Lain
    $tgl    = $_POST['tanggal_lomba'];
    $harga  = $_POST['harga_pendaftaran'];
    $user_id = $_SESSION['user_id'];

    // Jam Mulai kita set NULL atau Default '08:00' karena inputnya dihapus
    $jam_default = '08:00:00'; 

    $sql = "INSERT INTO events (user_id, nomor_acara, nama_event, jarak, gaya, jenis_kelamin, tanggal_lomba, jam_lomba, batas_umur_bawah, batas_umur_atas, harga_pendaftaran) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $nomor, $nama_generated, $jarak, $gaya, $jk, $tgl, $jam_default, $bawah, $atas, $harga]);
        
        $_SESSION['toast_type'] = 'success'; 
        $_SESSION['toast_message'] = 'Nomor lomba ' . $nomor . ' berhasil dibuat!';
        header("Location: index.php"); exit();
    } catch (PDOException $e) {
        $_SESSION['toast_type'] = 'error'; 
        $_SESSION['toast_message'] = 'Gagal: ' . $e->getMessage();
    }
}

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 mt-16 bg-slate-50 min-h-screen font-sans">
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-800 tracking-tight uppercase">Tambah Nomor Baru</h1>
            <p class="text-sm text-slate-500">Buat kategori perlombaan dengan format otomatis.</p>
        </div>
        <a href="index.php" class="text-slate-500 hover:text-blue-600 font-bold text-sm flex items-center gap-2 transition hover:-translate-x-1">
            <span>&larr;</span> Kembali
        </a>
    </div>

    <div class="bg-white p-8 rounded-xl shadow-sm border border-slate-200 max-w-4xl">
        <form method="POST" class="space-y-8">
            
            <div class="p-6 bg-blue-50/30 rounded-xl border border-blue-100">
                <h3 class="text-sm font-bold text-blue-800 uppercase tracking-wider mb-4 border-b border-blue-100 pb-2">
                    <span class="mr-2">📝</span> Penyusunan Nama Event
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                    
                    <div class="md:col-span-2">
                        <label class="block text-slate-700 font-bold mb-2 text-xs uppercase">No. Acara</label>
                        <input type="number" name="nomor_acara" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-center font-black text-lg focus:ring-2 focus:ring-blue-500" placeholder="101" required>
                        <p class="text-[10px] text-slate-400 mt-1">Ex: 101, 205</p>
                    </div>

                    <div class="md:col-span-3">
                        <label class="block text-slate-700 font-bold mb-2 text-xs uppercase">Jarak (Meter)</label>
                        <select name="jarak" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg font-bold bg-white focus:ring-2 focus:ring-blue-500">
                            <option value="25">25 Meter</option>
                            <option value="50">50 Meter</option>
                            <option value="100">100 Meter</option>
                            <option value="200">200 Meter</option>
                            <option value="400">400 Meter</option>
                            <option value="800">800 Meter</option>
                            <option value="1500">1500 Meter</option>
                            <option value="4x50">4 x 50 Meter</option>
                            <option value="4x100">4 x 100 Meter</option>
                        </select>
                    </div>

                    <div class="md:col-span-4">
                        <label class="block text-slate-700 font-bold mb-2 text-xs uppercase">Gaya Renang</label>
                        <select name="gaya" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg font-bold bg-white focus:ring-2 focus:ring-blue-500">
                            <option value="Gaya Bebas">Gaya Bebas (Freestyle)</option>
                            <option value="Gaya Dada">Gaya Dada (Breaststroke)</option>
                            <option value="Gaya Punggung">Gaya Punggung (Backstroke)</option>
                            <option value="Gaya Kupu-kupu">Gaya Kupu-kupu (Butterfly)</option>
                            <option value="Gaya Ganti">Gaya Ganti (Medley)</option>
                            <option value="Estafet Bebas">Estafet Bebas</option>
                            <option value="Estafet Ganti">Estafet Ganti</option>
                            <option value="Kaki Bebas">Kaki Bebas (Kick)</option>
                        </select>
                    </div>

                    <div class="md:col-span-3">
                        <label class="block text-slate-700 font-bold mb-2 text-xs uppercase">Kelompok Umur (KU)</label>
                        <div class="flex items-center gap-2">
                            <input type="number" name="batas_umur_bawah" class="w-full px-2 py-2.5 border border-slate-300 rounded-lg text-center text-sm" placeholder="Min" required>
                            <span class="text-slate-400">-</span>
                            <input type="number" name="batas_umur_atas" class="w-full px-2 py-2.5 border border-slate-300 rounded-lg text-center text-sm" placeholder="Max" required>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 p-3 bg-blue-100/50 rounded-lg border border-blue-200 text-blue-800 text-xs flex items-center gap-2">
                    <span>💡</span>
                    <span>Sistem akan otomatis memberi nama: <b>[No] - [Jarak]M [Gaya] - KU [Min]-[Max]</b></span>
                </div>
            </div>

            <div>
                <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-4 border-b pb-2">Detail & Biaya</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-slate-700 font-bold mb-2 text-sm">Jenis Kelamin</label>
                        <select name="jenis_kelamin" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-bold bg-slate-50">
                            <option value="L">Putra (Male)</option>
                            <option value="P">Putri (Female)</option>
                            <option value="Campuran">Campuran (Mixed)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-700 font-bold mb-2 text-sm">Tanggal Pelaksanaan</label>
                        <input type="date" name="tanggal_lomba" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" required>
                    </div>
                    <div>
                        <label class="block text-slate-700 font-bold mb-2 text-sm">Biaya Pendaftaran</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 font-bold text-xs">Rp</span>
                            <input type="number" name="harga_pendaftaran" class="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-lg text-sm font-mono font-medium" placeholder="50000" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4">
                <a href="index.php" class="px-6 py-3 rounded-xl border border-slate-300 text-slate-600 text-sm font-bold hover:bg-slate-50 transition">Batal</a>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-xl shadow-lg shadow-blue-600/30 transition transform hover:-translate-y-1">
                    Simpan Nomor Lomba
                </button>
            </div>
        </form>
    </div>
</div>
