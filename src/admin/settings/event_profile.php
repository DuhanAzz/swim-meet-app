<?php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $namaLomba = $_POST['nama_lengkap'];
        $lokasi    = $_POST['location'];
        $venue     = $_POST['venue_name'];
        $tglMulai  = $_POST['event_start_date'];
        $tglSelesai= $_POST['event_end_date'];
        
        $sql = "UPDATE users SET 
                nama_lengkap = ?, 
                location = ?, 
                venue_name = ?, 
                event_start_date = ?, 
                event_end_date = ? 
                WHERE id = ?";
        $pdo->prepare($sql)->execute([$namaLomba, $lokasi, $venue, $tglMulai, $tglSelesai, $uid]);

        if (!empty($_FILES['banner']['name'])) {
            $targetDir = __DIR__ . "/../../../public/uploads/banners/";
            $ext = pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION);
            $fileName = "banner_" . $uid . "_" . time() . "." . $ext;
            
            if(move_uploaded_file($_FILES['banner']['tmp_name'], $targetDir . $fileName)) {
                $dbPath = "uploads/banners/" . $fileName;
                $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?")->execute([$dbPath, $uid]);
                try {
                    $pdo->prepare("UPDATE users SET event_image = ? WHERE id = ?")->execute([$dbPath, $uid]);
                } catch(Exception $e) {} 
            }
        }

        $_SESSION['toast_type'] = 'success'; 
        $_SESSION['toast_message'] = 'Profil Lomba Berhasil Diupdate!';
        
    } catch (Exception $e) {
        $_SESSION['toast_type'] = 'error'; 
        $_SESSION['toast_message'] = 'Gagal: ' . $e->getMessage();
    }
    header("Location: event_profile.php"); exit;
}

$data = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$data->execute([$uid]);
$row = $data->fetch();

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tight">Edit Profil Lomba</h1>
            <p class="text-sm text-slate-500">Atur informasi event yang akan tampil di halaman publik.</p>
        </div>
        <a href="../../../public/index.php" target="_blank" class="bg-white border border-slate-300 text-slate-700 px-4 py-2 rounded-lg font-bold text-xs hover:bg-slate-50 shadow-sm flex items-center gap-2">
            <span>👁️</span> Cek Tampilan
        </a>
    </div>

    <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-2 bg-white rounded-xl shadow-md border border-slate-200 p-6">
            <h3 class="font-bold text-slate-800 mb-6 border-b pb-2">📝 Detail Informasi</h3>
            
            <div class="space-y-5">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Nama Lomba / Event</label>
                    <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($row['nama_lengkap']) ?>" class="w-full px-4 py-3 border rounded-lg font-bold text-slate-800 focus:ring-2 focus:ring-blue-600 outline-none" placeholder="Contoh: KEJUARAAN RENANG PELAJAR 2025">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Tanggal Mulai</label>
                        <input type="date" name="event_start_date" value="<?= $row['event_start_date'] ?>" class="w-full px-4 py-2 border rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Tanggal Selesai</label>
                        <input type="date" name="event_end_date" value="<?= $row['event_end_date'] ?>" class="w-full px-4 py-2 border rounded-lg text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Lokasi (Kota/Provinsi)</label>
                        <input type="text" name="location" value="<?= htmlspecialchars($row['location'] ?? '') ?>" class="w-full px-4 py-2 border rounded-lg text-sm font-semibold" placeholder="Contoh: Jakarta">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Nama Venue (Kolam Renang)</label>
                        <input type="text" name="venue_name" value="<?= htmlspecialchars($row['venue_name'] ?? '') ?>" class="w-full px-4 py-2 border rounded-lg text-sm font-semibold" placeholder="Contoh: Stadion Akuatik GBK">
                    </div>
                </div>
            </div>
        </div>

        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white rounded-xl shadow-md border border-slate-200 p-6">
                <h3 class="font-bold text-slate-800 mb-4 border-b pb-2">🖼️ Banner / Poster</h3>
                
                <div class="mb-4">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Preview Saat Ini</label>
                    <div class="aspect-video bg-slate-100 rounded-lg overflow-hidden border border-slate-200">
                        <?php 
                            $banner = $row['profile_image'] ?? '';
                            if ($banner && strpos($banner, 'http') !== 0) $banner = "../../../public/" . $banner;
                        ?>
                        <?php if(!empty($banner)): ?>
                            <img id="preview" src="<?= $banner ?>?t=<?= time() ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-slate-400 text-xs">Belum ada banner</div>
                            <img id="preview" class="hidden w-full h-full object-cover">
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Ganti Gambar</label>
                    <input type="file" name="banner" accept="image/*" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" onchange="previewImage(this)">
                    <p class="text-[10px] text-slate-400 mt-2">Format: JPG/PNG. Ukuran rekomen: Landscape.</p>
                </div>
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-4 rounded-xl shadow-lg transition transform hover:-translate-y-1 flex items-center justify-center gap-2">
                <span>💾</span> SIMPAN PERUBAHAN
            </button>
        </div>

    </form>
</div>

<script>
function previewImage(input) {
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById('preview');
            img.src = e.target.result;
            img.classList.remove('hidden');
        }
        reader.readAsDataURL(file);
    }
}
</script>
