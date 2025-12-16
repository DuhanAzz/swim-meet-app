<?php
session_start();
require_once __DIR__ . '/../../src/config/database.php';

// Cek Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../public/login.php");
    exit;
}

// HANDLE SIMPAN DATA
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // 1. Ambil Input
        $nomor  = $_POST['nomor_acara'];
        $jarak  = $_POST['jarak'];
        $gaya   = $_POST['gaya'];
        $jk     = $_POST['jenis_kelamin'];
        $bawah  = $_POST['batas_umur_bawah'];
        $atas   = $_POST['batas_umur_atas'];
        
        // 2. FORMAT DATA
        // Format: KU 10-12 Th
        $age_group = "KU " . $bawah . "-" . $atas . " Th";
        // Format: 50M Gaya Bebas
        $event_name = $jarak . "M " . $gaya; 

        // 3. QUERY INSERT
        $sql = "INSERT INTO event_numbers (event_number, event_name, distance, jenis_kelamin, age_group, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nomor, $event_name, $jarak, $jk, $age_group]);
        
        // Notifikasi Toast
        $_SESSION['swal_type'] = 'success'; 
        $_SESSION['swal_msg'] = 'Nomor ' . $nomor . ' berhasil ditambahkan!';
        
        header("Location: create.php"); 
        exit();

    } catch (PDOException $e) {
        $_SESSION['swal_type'] = 'error'; 
        $_SESSION['swal_msg'] = 'Gagal: ' . $e->getMessage();
    }
}

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tight">Buat Nomor Lomba</h1>
            <p class="text-sm text-slate-500">Data ini akan otomatis muncul di menu Seeding.</p>
        </div>
        <a href="index.php" class="text-slate-500 hover:text-blue-600 font-bold text-xs uppercase tracking-widest flex items-center gap-2">
            <span>&larr;</span> Lihat Daftar Event
        </a>
    </div>

    <div class="bg-white p-8 rounded-xl shadow-sm border border-slate-200 max-w-4xl">
        <form method="POST" class="space-y-8">
            
            <div class="p-6 bg-blue-50 rounded-xl border border-blue-100">
                <h3 class="text-xs font-black text-blue-800 uppercase tracking-widest mb-4 border-b border-blue-200 pb-2">
                    Detail Nomor
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-slate-700 font-bold mb-2 text-[10px] uppercase tracking-wide">No. Acara</label>
                        <input type="number" name="nomor_acara" class="w-full px-3 py-3 border border-slate-300 rounded-lg text-center font-black text-lg focus:ring-2 focus:ring-blue-500" placeholder="101" required>
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-slate-700 font-bold mb-2 text-[10px] uppercase tracking-wide">Jarak</label>
                        <select name="jarak" class="w-full px-3 py-3 border border-slate-300 rounded-lg font-bold text-sm bg-white focus:ring-2 focus:ring-blue-500">
                            <option value="25">25 Meter</option>
                            <option value="50">50 Meter</option>
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

            <div>
                <label class="block text-slate-700 font-bold mb-2 text-[10px] uppercase tracking-wide">Jenis Kelamin</label>
                <div class="grid grid-cols-3 gap-4">
                    <label class="cursor-pointer">
                        <input type="radio" name="jenis_kelamin" value="L" class="peer sr-only" required checked>
                        <div class="p-3 border-2 border-slate-200 rounded-lg text-center peer-checked:border-blue-600 peer-checked:bg-blue-50 peer-checked:text-blue-700 hover:bg-slate-50 transition">
                            <span class="font-bold text-sm">Putra (Male)</span>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="jenis_kelamin" value="P" class="peer sr-only">
                        <div class="p-3 border-2 border-slate-200 rounded-lg text-center peer-checked:border-pink-600 peer-checked:bg-pink-50 peer-checked:text-pink-700 hover:bg-slate-50 transition">
                            <span class="font-bold text-sm">Putri (Female)</span>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="jenis_kelamin" value="Campuran" class="peer sr-only">
                        <div class="p-3 border-2 border-slate-200 rounded-lg text-center peer-checked:border-purple-600 peer-checked:bg-purple-50 peer-checked:text-purple-700 hover:bg-slate-50 transition">
                            <span class="font-bold text-sm">Mixed</span>
                        </div>
                    </label>
                </div>
            </div>

            <button type="submit" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-black py-4 rounded-xl shadow-lg transition transform hover:-translate-y-1 uppercase tracking-widest text-xs">
                Simpan Nomor Lomba
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../views/layout/notification.php'; ?>