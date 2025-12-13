<?php
session_start();
error_reporting(E_ALL); ini_set('display_errors', 1);
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'master') { die("Akses Ditolak."); }

// DELETE IMAGE
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt = $pdo->prepare("SELECT image_path FROM hero_images WHERE id = ?");
    $stmt->execute([$id]);
    $img = $stmt->fetch();
    if ($img && file_exists("../../../public/" . $img['image_path'])) { unlink("../../../public/" . $img['image_path']); }
    $pdo->prepare("DELETE FROM hero_images WHERE id = ?")->execute([$id]);
    $_SESSION['toast_type'] = 'warning'; $_SESSION['toast_message'] = 'Gambar slider dihapus.';
    header("Location: index.php"); exit();
}

// SAVE SETTINGS
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $stmt = $pdo->prepare("UPDATE site_settings SET hero_title=?, hero_subtitle=?, running_text=? WHERE id=1");
    $stmt->execute([$_POST['hero_title'], $_POST['hero_subtitle'], $_POST['running_text']]);
    $msg = "Pengaturan berhasil disimpan.";

    if (!empty($_FILES['new_images']['name'][0])) {
        $total = count($_FILES['new_images']['name']);
        $targetDir = __DIR__ . "/../../../public/img/hero/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

        for ($i = 0; $i < $total; $i++) {
            $tmp = $_FILES['new_images']['tmp_name'][$i];
            if ($tmp != "") {
                $ext = pathinfo($_FILES['new_images']['name'][$i], PATHINFO_EXTENSION);
                $newName = "hero_" . time() . "_$i." . $ext;
                if (move_uploaded_file($tmp, $targetDir . $newName)) {
                    $dbPath = "img/hero/" . $newName;
                    $pdo->prepare("INSERT INTO hero_images (image_path) VALUES (?)")->execute([$dbPath]);
                }
            }
        }
        $msg .= " & Gambar baru diupload.";
    }
    $_SESSION['toast_type'] = 'success'; $_SESSION['toast_message'] = $msg;
    header("Location: index.php"); exit();
}

$settings = $pdo->query("SELECT * FROM site_settings LIMIT 1")->fetch();
$heroes = $pdo->query("SELECT * FROM hero_images ORDER BY id DESC")->fetchAll();

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-8 sm:ml-64 mt-20 bg-slate-50 min-h-screen font-sans">
    <div class="max-w-5xl mx-auto">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-black text-slate-800 tracking-tight">SYSTEM SETTINGS</h1>
                <p class="text-slate-500">Atur tampilan dan download data sistem.</p>
            </div>
            
            <a href="backup.php" class="bg-slate-800 hover:bg-slate-900 text-white px-6 py-3 rounded-xl font-bold shadow-lg flex items-center gap-2 hover:-translate-y-1 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                Backup Database (.SQL)
            </a>
        </div>

        <form method="POST" enctype="multipart/form-data" class="bg-white p-8 rounded-3xl shadow-sm border border-slate-200">
            <div class="grid md:grid-cols-2 gap-8 mb-6">
                <div>
                    <label class="block text-slate-700 font-bold mb-2">Main Title</label>
                    <input type="text" name="hero_title" value="<?= htmlspecialchars($settings['hero_title']) ?>" class="w-full border border-slate-300 rounded-xl px-4 py-3 font-bold">
                </div>
                <div>
                    <label class="block text-slate-700 font-bold mb-2">Subtitle</label>
                    <input type="text" name="hero_subtitle" value="<?= htmlspecialchars($settings['hero_subtitle']) ?>" class="w-full border border-slate-300 rounded-xl px-4 py-3 text-slate-600">
                </div>
            </div>

            <div class="mb-8">
                <label class="block text-slate-700 font-bold mb-2 flex items-center gap-2"><span>📢</span> Running Text (Info Bar)</label>
                <input type="text" name="running_text" value="<?= htmlspecialchars($settings['running_text'] ?? '') ?>" class="w-full border border-yellow-300 bg-yellow-50 rounded-xl px-4 py-3 text-slate-800 font-medium" placeholder="Contoh: Pendaftaran ditutup...">
            </div>

            <hr class="border-slate-100 my-8">

            <div class="mb-6">
                <label class="block text-slate-700 font-bold text-lg mb-4">Hero Slider Images</label>
                <div class="flex items-center justify-center w-full mb-6">
                    <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-blue-300 border-dashed rounded-xl cursor-pointer bg-blue-50 hover:bg-blue-100 transition">
                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                            <p class="text-sm text-blue-500 font-bold">Klik untuk Upload Gambar Baru</p>
                        </div>
                        <input name="new_images[]" type="file" multiple class="hidden" accept="image/*" />
                    </label>
                </div>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <?php foreach($heroes as $h): ?>
                    <div class="relative group rounded-xl overflow-hidden shadow-md h-40 bg-slate-200">
                        <?php $src = (strpos($h['image_path'], 'http') === 0) ? $h['image_path'] : "../../../public/" . $h['image_path']; ?>
                        <img src="<?= $src ?>" class="w-full h-full object-cover">
                        <a href="index.php?delete_id=<?= $h['id'] ?>" onclick="return confirm('Hapus?')" class="absolute top-2 right-2 bg-red-600 text-white p-1 rounded shadow text-xs">Hapus</a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex justify-end pt-4">
                <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-xl font-bold shadow-lg hover:bg-blue-700 transition">SIMPAN PERUBAHAN</button>
            </div>
        </form>
    </div>
</div>
