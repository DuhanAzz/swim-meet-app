<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../../public/login.php"); exit; }

$user_id = $_SESSION['user_id'];
$mode = $_GET['mode'] ?? 'profile'; 

// HANDLE SUBMIT
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($mode == 'profile') {
        // --- LOGIKA UPDATE PROFIL (Sama seperti sebelumnya) ---
        $nama = $_POST['nama_lengkap'];
        $user = $_POST['username'];
        
        try {
            $stmt = $pdo->prepare("UPDATE users SET nama_lengkap = ?, username = ? WHERE id = ?");
            $stmt->execute([$nama, $user, $user_id]);
            
            $_SESSION['nama_lengkap'] = $nama;
            $_SESSION['username'] = $user;
            
            if (!empty($_FILES['foto_profil']['name'])) {
                $stmtCek = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
                $stmtCek->execute([$user_id]);
                $oldImg = $stmtCek->fetchColumn();

                $targetDir = __DIR__ . "/../../public/img/profiles/";
                if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

                $ext = pathinfo($_FILES['foto_profil']['name'], PATHINFO_EXTENSION);
                $fileName = "profile_" . $user_id . "_" . time() . "." . $ext;
                $targetFile = $targetDir . $fileName;

                $check = getimagesize($_FILES['foto_profil']['tmp_name']);
                if($check !== false) {
                    if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $targetFile)) {
                        if ($oldImg && file_exists(__DIR__ . "/../../public/" . $oldImg)) {
                            unlink(__DIR__ . "/../../public/" . $oldImg);
                        }
                        $dbPath = "img/profiles/" . $fileName;
                        $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?")->execute([$dbPath, $user_id]);
                        $_SESSION['profile_image'] = $dbPath;
                    }
                }
            }
            $_SESSION['toast_type'] = 'success'; 
            $_SESSION['toast_message'] = 'Profil berhasil diperbarui!';
        } catch (Exception $e) {
            $_SESSION['toast_type'] = 'error'; 
            $_SESSION['toast_message'] = 'Gagal: ' . $e->getMessage();
        }
    } 
    elseif ($mode == 'password') {
        // --- LOGIKA GANTI PASSWORD (DENGAN KEAMANAN GANDA) ---
        $passLama = $_POST['password_lama'];
        $passBaru = $_POST['password_baru'];

        // 1. Ambil Password Hash Saat Ini dari Database
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $userData = $stmt->fetch();

        // 2. Verifikasi Password Lama
        if (password_verify($passLama, $userData['password'])) {
            // Jika Password Lama BENAR, baru boleh ganti
            $passHash = password_hash($passBaru, PASSWORD_BCRYPT);
            try {
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$passHash, $user_id]);
                $_SESSION['toast_type'] = 'success'; 
                $_SESSION['toast_message'] = 'Password berhasil diganti!';
            } catch (Exception $e) {
                $_SESSION['toast_type'] = 'error'; 
                $_SESSION['toast_message'] = 'Gagal update database.';
            }
        } else {
            // Jika Password Lama SALAH
            $_SESSION['toast_type'] = 'error'; 
            $_SESSION['toast_message'] = '⛔ Password Lama SALAH! Ganti password ditolak.';
        }
    }
    header("Location: profile.php?mode=" . $mode); exit;
}

// Ambil Data User Terbaru
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$u = $stmt->fetch();

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 mt-16 bg-slate-50 min-h-screen font-sans">
    
    <h1 class="text-2xl font-black text-slate-800 tracking-tight uppercase mb-6">Pengaturan Akun</h1>

    <div class="flex flex-col lg:flex-row gap-8">
        
        <div class="w-full lg:w-1/4">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-6 text-center border-b border-slate-100 bg-slate-50">
                    <div class="w-24 h-24 mx-auto rounded-full border-4 border-white shadow-md overflow-hidden bg-gray-200 mb-3 relative group">
                        <?php if(!empty($u['profile_image'])): ?>
                            <img src="../../public/<?= $u['profile_image'] ?>?v=<?= time() ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-4xl text-slate-400">👤</div>
                        <?php endif; ?>
                    </div>
                    <h3 class="font-bold text-slate-800"><?= htmlspecialchars($u['nama_lengkap']) ?></h3>
                    <p class="text-xs text-slate-500 uppercase tracking-wider"><?= $u['role'] ?></p>
                </div>
                <a href="?mode=profile" class="block px-6 py-4 font-bold text-sm hover:bg-slate-50 border-b border-slate-100 <?= ($mode=='profile') ? 'text-blue-600 bg-blue-50' : 'text-slate-600' ?>">
                    👤 Edit Profil & Foto
                </a>
                <a href="?mode=password" class="block px-6 py-4 font-bold text-sm hover:bg-slate-50 <?= ($mode=='password') ? 'text-blue-600 bg-blue-50' : 'text-slate-600' ?>">
                    🔒 Ganti Password
                </a>
            </div>
        </div>

        <div class="w-full lg:w-3/4">
            <div class="bg-white p-8 rounded-xl shadow-sm border border-slate-200">
                
                <?php if($mode == 'profile'): ?>
                    <h2 class="text-lg font-bold text-slate-800 mb-6 border-b pb-2">Ubah Informasi Profil</h2>
                    <form method="POST" enctype="multipart/form-data" class="space-y-6">
                        <div class="p-4 bg-blue-50/50 rounded-xl border border-dashed border-blue-200">
                            <label class="block text-slate-600 font-bold mb-2 text-sm">Ganti Foto Profil</label>
                            <input type="file" name="foto_profil" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-blue-600 file:text-white hover:file:bg-blue-700 cursor-pointer">
                            <p class="text-[10px] text-slate-400 mt-1">Format: JPG, PNG. Maks 2MB.</p>
                        </div>
                        <div>
                            <label class="block text-slate-600 font-bold mb-2 text-sm">Nama Lengkap / Nama Event</label>
                            <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($u['nama_lengkap']) ?>" class="w-full border border-slate-300 rounded-lg px-4 py-2.5 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-slate-600 font-bold mb-2 text-sm">Username (Login)</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">@</span>
                                <input type="text" name="username" value="<?= htmlspecialchars($u['username']) ?>" class="w-full pl-8 border border-slate-300 rounded-lg px-4 py-2.5 focus:ring-blue-500">
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="bg-blue-600 text-white font-bold py-2.5 px-6 rounded-lg shadow hover:bg-blue-700 transition">Simpan Perubahan</button>
                        </div>
                    </form>

                <?php elseif($mode == 'password'): ?>
                    <h2 class="text-lg font-bold text-slate-800 mb-6 border-b pb-2">Ganti Password Keamanan</h2>
                    
                    <form method="POST" class="space-y-6">
                        
                        <div>
                            <label class="block text-slate-700 font-bold mb-2 text-sm">Password Lama (Saat Ini)</label>
                            <input type="password" name="password_lama" class="w-full border border-slate-300 bg-slate-50 rounded-lg px-4 py-2.5 focus:ring-blue-500" placeholder="Masukkan password lama untuk verifikasi" required>
                        </div>

                        <div>
                            <label class="block text-blue-700 font-bold mb-2 text-sm">Password Baru</label>
                            <input type="password" name="password_baru" class="w-full border border-blue-200 rounded-lg px-4 py-2.5 focus:ring-blue-500" placeholder="Masukkan password baru" required>
                        </div>

                        <div class="bg-yellow-50 text-yellow-800 text-xs p-3 rounded border border-yellow-200 flex items-start gap-2">
                            <span>🔒</span> 
                            <span>Demi keamanan, Anda <b>wajib</b> memasukkan password lama sebelum membuat password baru.</span>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="bg-blue-600 text-white font-bold py-2.5 px-6 rounded-lg shadow hover:bg-blue-700 transition">Update Password</button>
                        </div>
                    </form>
                <?php endif; ?>

            </div>
        </div>

    </div>
</div>
