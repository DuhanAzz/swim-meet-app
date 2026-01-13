<?php
session_start();
// Mode Debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../src/config/database.php';

// Cek Login
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$uid = $_SESSION['user_id'];
$role = $_SESSION['role'];

// --- HANDLE POST (SIMPAN DATA) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = $_POST['nama_lengkap'];
    $email = $_POST['email'];
    
    try {
        $pdo->beginTransaction();

        // 1. UPLOAD FOTO PROFIL
        if (!empty($_FILES['photo']['name'])) {
            $file = $_FILES['photo'];
            if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception("Error Upload Code: " . $file['error']);
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png'])) throw new Exception("Format foto harus JPG/PNG.");
            
            $targetDir = __DIR__ . "/img/users/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

            $fileName = "user_" . $uid . "_" . time() . "." . $ext;
            $targetPath = $targetDir . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $dbPath = "img/users/" . $fileName;
                $pdo->prepare("UPDATE users SET photo = ? WHERE id = ?")->execute([$dbPath, $uid]);
            }
        }

        // 2. UPDATE USER
        $stmt = $pdo->prepare("UPDATE users SET nama_lengkap = ?, email = ? WHERE id = ?");
        $stmt->execute([$nama, $email, $uid]);
        $_SESSION['nama_lengkap'] = $nama;
        $_SESSION['email'] = $email;

        // 3. UPDATE KLUB (Jika User)
        if ($role == 'user') {
            $klub = $_POST['nama_klub'];
            $kota = $_POST['kota'];
            
            if (!empty($_FILES['logo']['name'])) {
                $targetDirKlub = __DIR__ . "/img/logos/";
                if (!is_dir($targetDirKlub)) mkdir($targetDirKlub, 0777, true);
                
                $extKlub = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                $fileNameKlub = "logo_" . $uid . "_" . time() . "." . $extKlub;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetDirKlub . $fileNameKlub)) {
                    $dbPathLogo = "img/logos/" . $fileNameKlub;
                    $pdo->prepare("UPDATE clubs SET logo = ? WHERE user_id = ?")->execute([$dbPathLogo, $uid]);
                }
            }
            $cek = $pdo->prepare("SELECT id FROM clubs WHERE user_id=?");
            $cek->execute([$uid]);
            if($cek->rowCount() > 0) {
                $pdo->prepare("UPDATE clubs SET nama_klub = ?, kota = ? WHERE user_id = ?")->execute([$klub, $kota, $uid]);
            }
        }

        $pdo->commit();
        $_SESSION['toast_type'] = 'success'; $_SESSION['toast_message'] = 'Profil berhasil diperbarui!';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['toast_type'] = 'error'; $_SESSION['toast_message'] = 'Gagal: ' . $e->getMessage();
    }
    header("Location: profile_edit.php"); exit;
}

// AMBIL DATA
$user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user->execute([$uid]);
$u = $user->fetch();
$emailVal = $u['email'] ?? ''; $photoVal = $u['photo'] ?? '';

$c = null;
if ($role == 'user') {
    $club = $pdo->prepare("SELECT * FROM clubs WHERE user_id = ?");
    $club->execute([$uid]);
    $c = $club->fetch();
}

include __DIR__ . '/../views/layout/topbar.php'; 
include __DIR__ . '/../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tight">Edit Profil</h1>
            <p class="text-sm text-slate-500">Kelola akun dan identitas Anda.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
        
        <div class="xl:col-span-2 space-y-6">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8">
                <form method="POST" enctype="multipart/form-data" class="space-y-8" id="profileForm">
                    
                    <div class="flex items-center gap-6 pb-6 border-b border-slate-100">
                        <div class="relative group">
                            <?php if(!empty($photoVal)): ?>
                                <img id="previewPhoto" src="/swim-meet/public/<?= $photoVal ?>?t=<?= time() ?>" class="w-28 h-28 rounded-full object-cover border-4 border-slate-50 shadow-lg">
                            <?php else: ?>
                                <div id="previewPlaceholder" class="w-28 h-28 rounded-full bg-blue-100 flex items-center justify-center text-3xl text-blue-600 font-bold border-4 border-slate-50 shadow-lg">
                                    <?= strtoupper(substr($u['nama_lengkap'], 0, 1)) ?>
                                </div>
                                <img id="previewPhoto" src="" class="w-28 h-28 rounded-full object-cover border-4 border-slate-50 shadow-lg hidden">
                            <?php endif; ?>
                            
                            <label for="photoInput" class="absolute bottom-1 right-1 bg-blue-600 text-white p-2.5 rounded-full cursor-pointer shadow-lg hover:bg-blue-700 transition border-2 border-white transform hover:scale-110">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            </label>
                            <input type="file" name="photo" id="photoInput" accept="image/png, image/jpeg, image/jpg" class="hidden" onchange="previewImage(this, 'previewPhoto', 'previewPlaceholder')">
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-800 mb-1">Foto Profil</h3>
                            <p class="text-xs text-slate-500 mb-2">Ditampilkan di navigasi atas.</p>
                            <span class="text-[10px] bg-slate-100 text-slate-500 px-2 py-1 rounded font-medium">Max 2MB</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-slate-700 font-bold mb-2 text-xs uppercase tracking-wide">Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($u['nama_lengkap']) ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 font-bold" required>
                        </div>
                        <div>
                            <label class="block text-slate-700 font-bold mb-2 text-xs uppercase tracking-wide">Email</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($emailVal) ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 font-bold" required>
                        </div>
                    </div>

                    <?php if($role == 'user'): ?>
                        <div class="pt-6 mt-6 border-t border-slate-100">
                            <h3 class="text-sm font-bold text-blue-600 uppercase tracking-wider mb-6 flex items-center gap-2">🏢 Identitas Klub</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-slate-700 font-bold mb-2 text-xs uppercase tracking-wide">Nama Klub</label>
                                    <input type="text" name="nama_klub" value="<?= htmlspecialchars($c['nama_klub'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-bold">
                                </div>
                                <div>
                                    <label class="block text-slate-700 font-bold mb-2 text-xs uppercase tracking-wide">Kota</label>
                                    <input type="text" name="kota" value="<?= htmlspecialchars($c['kota'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-bold">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-slate-700 font-bold mb-2 text-xs uppercase tracking-wide">Logo Klub</label>
                                    <input type="file" name="logo" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 border border-slate-200 rounded-lg cursor-pointer">
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="flex justify-end pt-4 border-t border-slate-100">
                        <button type="submit" id="saveBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-xl shadow-lg transition">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-slate-800 text-white p-6 rounded-xl shadow-lg relative overflow-hidden group">
                <h3 class="font-bold text-lg mb-2 relative z-10">Keamanan Akun</h3>
                <p class="text-sm text-slate-300 mb-6 relative z-10">Ganti password secara berkala.</p>
                <a href="change_password.php" class="block w-full text-center bg-white text-slate-900 font-bold py-3 rounded-lg hover:bg-blue-50 transition shadow relative z-10">🔒 Ganti Password</a>
            </div>
        </div>

    </div>
</div>

<script>
function previewImage(input, imgId, placeholderId) {
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById(imgId).src = e.target.result;
            document.getElementById(imgId).classList.remove('hidden');
            if(document.getElementById(placeholderId)) document.getElementById(placeholderId).classList.add('hidden');
        }
        reader.readAsDataURL(file);
    }
}
</script>
