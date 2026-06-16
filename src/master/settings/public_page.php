<?php
// FILE: src/master/settings/public_page.php
session_start();

// 1. KONEKSI KE DATABASE
require_once __DIR__ . '/../../config/database.php';

// 2. CEK AKSES (Hanya Master)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'master') {
    header("Location: ../../../public/login.php");
    exit;
}

// --- LOGIC A: UPDATE TEKS & KONTAK ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_text'])) {
    try {
        // Ambil Data dari Form
        $heroTitle = $_POST['hero_title'];
        $running   = $_POST['running_text'];
        $infoTitle = $_POST['info_title'];
        $infoText  = $_POST['info_text'];
        $siteDesc  = $_POST['site_description'];
        
        $email = $_POST['contact_email'];
        $wa    = $_POST['contact_wa'];
        $ig    = $_POST['link_instagram'];
        $fb    = $_POST['link_facebook'];
        
        // Pastikan row ID=1 ada
        $check = $pdo->query("SELECT id FROM site_settings WHERE id=1")->fetch();
        if (!$check) $pdo->query("INSERT INTO site_settings (id) VALUES (1)");

        // Update Database
        $sql = "UPDATE site_settings SET 
                hero_title = ?, 
                running_text = ?, 
                info_title = ?, 
                info_text = ?,
                site_description = ?,
                contact_email = ?,
                contact_wa = ?,
                link_instagram = ?,
                link_facebook = ?
                WHERE id = 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $heroTitle, $running, $infoTitle, $infoText, $siteDesc, 
            $email, $wa, $ig, $fb
        ]);
            
        $_SESSION['swal_type'] = 'success'; 
        $_SESSION['swal_msg']  = 'Pengaturan halaman depan berhasil diperbarui!';
        
    } catch (Exception $e) {
        $_SESSION['swal_type'] = 'error'; 
        $_SESSION['swal_msg']  = 'Gagal: ' . $e->getMessage();
    }
    header("Location: public_page.php"); exit;
}

// --- LOGIC B: UPLOAD SLIDE BARU ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['slide_image'])) {
    try {
        // Folder tujuan (public/img/hero/)
        $targetDir = __DIR__ . "/../../../public/img/hero/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        
        if (!empty($_FILES['slide_image']['name'])) {
            $ext = pathinfo($_FILES['slide_image']['name'], PATHINFO_EXTENSION);
            // Validasi ekstensi
            if(in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'webp'])) {
                $fileName = "slide_" . time() . "_" . rand(100,999) . "." . $ext;
                // Pindahkan file
                if(move_uploaded_file($_FILES['slide_image']['tmp_name'], $targetDir . $fileName)) {
                    // Simpan path relatif ke DB
                    $pdo->prepare("INSERT INTO hero_slides (image_path) VALUES (?)")->execute(["img/hero/" . $fileName]);
                    $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = 'Slide baru berhasil ditambahkan!';
                }
            } else { throw new Exception("Format gambar harus JPG, PNG, atau WEBP."); }
        }
    } catch (Exception $e) {
        $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = $e->getMessage();
    }
    header("Location: public_page.php"); exit;
}

// --- LOGIC C: HAPUS SLIDE ---
if (isset($_POST['delete_id'])) {
    $id = $_POST['delete_id'];
    // Ambil path file dulu untuk dihapus dari folder
    $stmt = $pdo->prepare("SELECT image_path FROM hero_slides WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    
    if ($row) {
        $fullPath = __DIR__ . "/../../../public/" . $row['image_path'];
        if (file_exists($fullPath)) unlink($fullPath);
    }
    // Hapus dari DB
    $pdo->prepare("DELETE FROM hero_slides WHERE id = ?")->execute([$id]);
    $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = 'Slide berhasil dihapus.';
    header("Location: public_page.php"); exit;
}

// --- AMBIL DATA UNTUK DITAMPILKAN ---
$settings = $pdo->query("SELECT * FROM site_settings WHERE id=1")->fetch();
$slides = $pdo->query("SELECT * FROM hero_slides ORDER BY id DESC")->fetchAll();

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tight">Editor Halaman Depan</h1>
            <p class="text-sm text-slate-500 font-medium">Kontrol konten visual, teks, dan kontak website.</p>
        </div>
        <a href="../../../public/index.php" target="_blank" class="bg-slate-800 text-white px-6 py-3 rounded-full font-bold text-xs hover:bg-slate-900 shadow-xl transition transform hover:scale-105 flex items-center gap-2">
            <span>👁️</span> Lihat Website
        </a>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
        
        <div class="xl:col-span-1 space-y-8">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-blue-600 px-6 py-4 border-b border-blue-500">
                    <h3 class="text-white font-black text-sm uppercase tracking-wider flex items-center gap-2">
                        <span>🅰️</span> Konten & Informasi
                    </h3>
                </div>
                <div class="p-6">
                    <form method="POST">
                        <input type="hidden" name="update_text" value="1">
                        
                        <div class="mb-5">
                            <label class="block text-[10px] font-black text-slate-500 uppercase mb-1 tracking-wider">Judul Utama (Hero)</label>
                            <input type="text" name="hero_title" value="<?= htmlspecialchars($settings['hero_title'] ?? '') ?>" class="w-full px-4 py-3 border border-slate-200 rounded-xl font-bold text-slate-800 focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>

                        <div class="mb-5">
                            <label class="block text-[10px] font-black text-slate-500 uppercase mb-1 tracking-wider">Running Text (Berita)</label>
                            <textarea name="running_text" rows="2" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm font-medium focus:ring-2 focus:ring-blue-500 outline-none"><?= htmlspecialchars($settings['running_text'] ?? '') ?></textarea>
                        </div>

                        <hr class="my-6 border-slate-100">
                        
                        <div class="mb-5">
                            <label class="block text-[10px] font-black text-blue-600 uppercase mb-1 tracking-wider">Judul Info (How to Join)</label>
                            <input type="text" name="info_title" value="<?= htmlspecialchars($settings['info_title'] ?? 'PENDAFTARAN DIBUKA') ?>" class="w-full px-4 py-3 border border-slate-200 rounded-xl font-bold text-slate-800 focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>

                        <div class="mb-5">
                            <label class="block text-[10px] font-black text-blue-600 uppercase mb-1 tracking-wider">Deskripsi Info</label>
                            <textarea name="info_text" rows="3" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm font-medium focus:ring-2 focus:ring-blue-500 outline-none"><?= htmlspecialchars($settings['info_text'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-5">
                            <label class="block text-[10px] font-black text-emerald-600 uppercase mb-1 tracking-wider">Deskripsi Footer</label>
                            <textarea name="site_description" rows="3" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm font-medium focus:ring-2 focus:ring-emerald-500 outline-none" placeholder="Teks ringkas tentang website..."><?= htmlspecialchars($settings['site_description'] ?? '') ?></textarea>
                        </div>

                        <hr class="my-6 border-slate-100">
                        
                        <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4">Kontak & Sosmed</h4>

                        <div class="grid grid-cols-2 gap-4 mb-5">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Email</label>
                                <input type="email" name="contact_email" value="<?= htmlspecialchars($settings['contact_email'] ?? '') ?>" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs font-bold focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">WhatsApp (628...)</label>
                                <input type="text" name="contact_wa" value="<?= htmlspecialchars($settings['contact_wa'] ?? '') ?>" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs font-bold focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Instagram URL</label>
                                <input type="text" name="link_instagram" value="<?= htmlspecialchars($settings['link_instagram'] ?? '') ?>" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs font-bold focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Facebook URL</label>
                                <input type="text" name="link_facebook" value="<?= htmlspecialchars($settings['link_facebook'] ?? '') ?>" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs font-bold focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-slate-900 text-white font-black uppercase text-xs tracking-widest py-4 rounded-xl hover:bg-blue-700 shadow-lg transition duration-300">
                            Simpan Perubahan
                        </button>
                    </form>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-slate-800 px-6 py-4 border-b border-slate-700">
                    <h3 class="text-white font-black text-sm uppercase tracking-wider flex items-center gap-2">
                        <span>🖼️</span> Upload Slider Baru
                    </h3>
                </div>
                <div class="p-6">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="flex items-center justify-center w-full group">
                            <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-slate-300 border-dashed rounded-2xl cursor-pointer bg-slate-50 hover:bg-blue-50 hover:border-blue-400 transition duration-300">
                                <div class="flex flex-col items-center justify-center pt-5 pb-6 text-slate-400 group-hover:text-blue-500 transition">
                                    <svg class="w-8 h-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    <p class="text-[10px] font-black uppercase tracking-wider">Klik untuk Upload</p>
                                </div>
                                <input name="slide_image" type="file" class="hidden" onchange="this.form.submit()" accept="image/*" />
                            </label>
                        </div> 
                        <p class="text-center text-[10px] text-slate-400 mt-3 font-bold uppercase">Format: JPG, PNG, WEBP (Max 2MB)</p>
                    </form>
                </div>
            </div>
        </div>

        <div class="xl:col-span-2">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden min-h-[600px] flex flex-col">
                <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex justify-between items-center">
                    <h3 class="font-black text-slate-700 text-sm uppercase tracking-wider">Galeri Slider Aktif</h3>
                    <span class="bg-blue-100 text-blue-600 py-1 px-3 rounded-full text-[10px] font-black"><?= count($slides) ?> Foto</span>
                </div>
                
                <?php if(empty($slides)): ?>
                    <div class="flex-1 flex flex-col items-center justify-center text-slate-300 p-10">
                        <div class="text-6xl mb-4">📷</div>
                        <p class="font-bold text-sm">Belum ada slide gambar.</p>
                    </div>
                <?php else: ?>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6 content-start">
                        <?php foreach($slides as $s): ?>
                        <div class="relative group rounded-xl overflow-hidden shadow-sm hover:shadow-2xl transition-all duration-300 border border-slate-100 bg-slate-900">
                            <div class="aspect-video">
                                <img src="../../../public/<?= $s['image_path'] ?>" class="w-full h-full object-cover opacity-90 group-hover:opacity-60 transition duration-500 transform group-hover:scale-110">
                            </div>
                            
                            <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition duration-300">
                                <form method="POST" class="delete-form">
                                    <input type="hidden" name="delete_id" value="<?= $s['id'] ?>">
                                    <button type="button" class="btn-delete bg-red-600 text-white px-5 py-2 rounded-full font-bold text-xs uppercase tracking-wider shadow-lg hover:bg-red-700 hover:scale-105 transition transform flex items-center gap-2">
                                        <span>🗑</span> Hapus
                                    </button>
                                </form>
                            </div>
                            
                            <div class="absolute top-3 left-3 bg-black/50 backdrop-blur-sm text-white text-[9px] font-bold px-2 py-1 rounded">
                                #<?= $s['id'] ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // NOTIFIKASI SUKSES/GAGAL
    <?php if(isset($_SESSION['swal_type'])): ?>
        Swal.fire({
            icon: '<?= $_SESSION['swal_type'] ?>',
            title: '<?= $_SESSION['swal_type'] == 'success' ? 'Berhasil!' : 'Gagal!' ?>',
            text: '<?= $_SESSION['swal_msg'] ?>', 
            confirmButtonColor: '#0F172A',
            confirmButtonText: 'OK'
        });
        <?php unset($_SESSION['swal_type']); unset($_SESSION['swal_msg']); ?>
    <?php endif; ?>

    // KONFIRMASI HAPUS
    const deleteBtns = document.querySelectorAll('.btn-delete');
    deleteBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const form = this.closest('form');
            Swal.fire({
                title: 'Hapus Slide?',
                text: "Gambar akan dihapus permanen dari halaman depan.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
</script>