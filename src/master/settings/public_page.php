<?php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'master') die("Akses Ditolak.");

// --- 1. HANDLE UPDATE SEMUA TEKS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_text'])) {
    try {
        $heroTitle = $_POST['hero_title'];
        $running   = $_POST['running_text'];
        $infoTitle = $_POST['info_title']; // Baru
        $infoText  = $_POST['info_text'];  // Baru
        
        // Pastikan row ada
        $check = $pdo->query("SELECT id FROM site_settings WHERE id=1")->fetch();
        if (!$check) $pdo->query("INSERT INTO site_settings (id) VALUES (1)");

        // Update Database
        $sql = "UPDATE site_settings SET 
                hero_title = ?, 
                running_text = ?, 
                info_title = ?, 
                info_text = ? 
                WHERE id = 1";
                
        $pdo->prepare($sql)->execute([$heroTitle, $running, $infoTitle, $infoText]);
            
        $_SESSION['toast_type'] = 'success'; $_SESSION['toast_message'] = 'Semua teks berhasil disimpan!';
        
    } catch (Exception $e) {
        $_SESSION['toast_type'] = 'error'; $_SESSION['toast_message'] = 'Gagal: ' . $e->getMessage();
    }
    header("Location: public_page.php"); exit;
}

// --- 2. HANDLE UPLOAD SLIDE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['slide_image'])) {
    try {
        $targetDir = __DIR__ . "/../../../public/img/hero/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        
        if (!empty($_FILES['slide_image']['name'])) {
            $ext = pathinfo($_FILES['slide_image']['name'], PATHINFO_EXTENSION);
            $fileName = "slide_" . time() . "_" . rand(100,999) . "." . $ext;
            
            if(move_uploaded_file($_FILES['slide_image']['tmp_name'], $targetDir . $fileName)) {
                $imgPath = "img/hero/" . $fileName;
                $pdo->prepare("INSERT INTO hero_slides (image_path) VALUES (?)")->execute([$imgPath]);
                $_SESSION['toast_type'] = 'success'; $_SESSION['toast_message'] = 'Slide ditambahkan!';
            }
        }
    } catch (Exception $e) {
        $_SESSION['toast_type'] = 'error'; $_SESSION['toast_message'] = $e->getMessage();
    }
    header("Location: public_page.php"); exit;
}

// --- 3. HANDLE HAPUS SLIDE ---
if (isset($_POST['delete_id'])) {
    $id = $_POST['delete_id'];
    $stmt = $pdo->prepare("SELECT image_path FROM hero_slides WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row && file_exists(__DIR__ . "/../../../public/" . $row['image_path'])) unlink(__DIR__ . "/../../../public/" . $row['image_path']);
    $pdo->prepare("DELETE FROM hero_slides WHERE id = ?")->execute([$id]);
    header("Location: public_page.php"); exit;
}

// AMBIL DATA
$settings = $pdo->query("SELECT * FROM site_settings WHERE id=1")->fetch();
$slides = $pdo->query("SELECT * FROM hero_slides ORDER BY id DESC")->fetchAll();

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tight">Editor Halaman Depan</h1>
            <p class="text-sm text-slate-500">Kontrol penuh konten teks dan visual website.</p>
        </div>
        <a href="../../../public/index.php" target="_blank" class="bg-slate-800 text-white px-5 py-2.5 rounded-lg font-bold text-xs hover:bg-slate-900 shadow-lg">
            👁️ Lihat Website
        </a>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
        
        <div class="xl:col-span-1 space-y-8">
            <div class="bg-white rounded-xl shadow-md border border-slate-200 overflow-hidden">
                <div class="bg-blue-600 px-6 py-4 border-b border-blue-500">
                    <h3 class="text-white font-black text-sm uppercase tracking-wider">🅰️ Pengaturan Teks</h3>
                </div>
                <div class="p-6">
                    <form method="POST">
                        <input type="hidden" name="update_text" value="1">
                        
                        <div class="mb-5">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Judul Utama (Tengah)</label>
                            <input type="text" name="hero_title" value="<?= htmlspecialchars($settings['hero_title'] ?? '') ?>" class="w-full px-4 py-2 border rounded-lg font-bold text-slate-800">
                        </div>

                        <div class="mb-5">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Running Text (News)</label>
                            <textarea name="running_text" rows="2" class="w-full px-4 py-2 border rounded-lg text-sm"><?= htmlspecialchars($settings['running_text'] ?? '') ?></textarea>
                        </div>

                        <hr class="my-6 border-slate-100">
                        
                        <div class="mb-5">
                            <label class="block text-xs font-bold text-blue-600 uppercase mb-1">Judul Info Box (Kiri)</label>
                            <input type="text" name="info_title" value="<?= htmlspecialchars($settings['info_title'] ?? 'OPEN REGISTRATION') ?>" class="w-full px-4 py-2 border rounded-lg font-bold text-slate-800">
                        </div>

                        <div class="mb-6">
                            <label class="block text-xs font-bold text-blue-600 uppercase mb-1">Deskripsi Info Box</label>
                            <textarea name="info_text" rows="4" class="w-full px-4 py-2 border rounded-lg text-sm"><?= htmlspecialchars($settings['info_text'] ?? 'Deskripsi singkat...') ?></textarea>
                        </div>

                        <button type="submit" class="w-full bg-slate-900 text-white font-bold py-3 rounded-lg hover:bg-slate-800 shadow-lg">
                            Simpan Semua Teks
                        </button>
                    </form>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-md border border-slate-200 overflow-hidden">
                <div class="bg-slate-800 px-6 py-4 border-b border-slate-700">
                    <h3 class="text-white font-black text-sm uppercase tracking-wider">🖼️ Tambah Slide</h3>
                </div>
                <div class="p-6">
                    <form method="POST" enctype="multipart/form-data">
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Upload Background</label>
                        <div class="flex items-center justify-center w-full">
                            <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-slate-300 border-dashed rounded-lg cursor-pointer bg-slate-50 hover:bg-slate-100">
                                <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                    <svg class="w-8 h-8 mb-2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    <p class="text-xs text-slate-500">Klik Upload (JPG/PNG)</p>
                                </div>
                                <input name="slide_image" type="file" class="hidden" onchange="this.form.submit()" />
                            </label>
                        </div> 
                    </form>
                </div>
            </div>
        </div>

        <div class="xl:col-span-2">
            <div class="bg-white rounded-xl shadow-md border border-slate-200 overflow-hidden min-h-[500px]">
                <div class="bg-slate-50 px-6 py-4 border-b border-slate-200">
                    <h3 class="font-black text-slate-700 text-sm uppercase tracking-wider">Galeri Slider Aktif (<?= count($slides) ?>)</h3>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach($slides as $s): ?>
                    <div class="relative group rounded-xl overflow-hidden shadow-sm hover:shadow-xl transition border border-slate-100">
                        <div class="aspect-video bg-slate-200">
                            <img src="../../../public/<?= $s['image_path'] ?>" class="w-full h-full object-cover">
                        </div>
                        <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition flex items-center justify-center">
                            <form method="POST" onsubmit="return confirm('Hapus slide ini?')">
                                <input type="hidden" name="delete_id" value="<?= $s['id'] ?>">
                                <button class="bg-red-600 text-white p-2 rounded-full hover:bg-red-700">🗑</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div>
</div>
