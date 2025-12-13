<?php
session_start();
require_once __DIR__ . '/../src/config/database.php';

// Cek Login
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$uid = $_SESSION['user_id'];

// --- HANDLE SUBMIT ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $old_pass = $_POST['old_password'];
    $new_pass = $_POST['new_password'];
    $confirm  = $_POST['confirm_password'];

    // 1. Ambil Password Lama dari DB
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($old_pass, $user['password'])) {
        $_SESSION['toast_type'] = 'error';
        $_SESSION['toast_message'] = 'Password lama salah.';
    } elseif ($new_pass !== $confirm) {
        $_SESSION['toast_type'] = 'error';
        $_SESSION['toast_message'] = 'Konfirmasi password baru tidak cocok.';
    } elseif (strlen($new_pass) < 6) {
        $_SESSION['toast_type'] = 'error';
        $_SESSION['toast_message'] = 'Password minimal 6 karakter.';
    } else {
        // 2. Update Password Baru
        $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $upd->execute([$new_hash, $uid]);
        
        $_SESSION['toast_type'] = 'success';
        $_SESSION['toast_message'] = 'Password berhasil diubah!';
    }
    
    // Refresh halaman
    header("Location: change_password.php"); exit;
}

// --- LOAD LAYOUT UTAMA (Sidebar & Topbar) ---
include __DIR__ . '/../views/layout/topbar.php'; 
include __DIR__ . '/../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tight">Ganti Password</h1>
            <p class="text-sm text-slate-500">Perbarui kata sandi Anda secara berkala untuk keamanan akun.</p>
        </div>
        
        <a href="profile_edit.php" class="bg-white border border-slate-300 text-slate-600 px-5 py-2.5 rounded-lg font-bold text-sm hover:bg-slate-50 shadow-sm flex items-center gap-2 transition">
            <span>&larr;</span> Kembali ke Profil
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8">
                
                <h3 class="text-sm font-bold text-blue-600 uppercase tracking-wider mb-6 pb-2 border-b border-blue-100 flex items-center gap-2">
                    <span class="text-lg">🔒</span> Form Perubahan Password
                </h3>

                <form method="POST" class="space-y-6">
                    
                    <div>
                        <label class="block text-slate-700 font-bold mb-2 text-xs uppercase tracking-wide">Password Lama</label>
                        <div class="relative">
                            <input type="password" name="old_password" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 font-bold outline-none transition pl-10" required placeholder="Masukkan password saat ini">
                            <span class="absolute left-3 top-2.5 text-slate-400 text-lg">🔑</span>
                        </div>
                    </div>

                    <hr class="border-slate-100 border-dashed my-4">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-slate-700 font-bold mb-2 text-xs uppercase tracking-wide">Password Baru</label>
                            <input type="password" name="new_password" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 font-bold outline-none transition" required placeholder="Minimal 6 karakter">
                        </div>
                        <div>
                            <label class="block text-slate-700 font-bold mb-2 text-xs uppercase tracking-wide">Konfirmasi Password Baru</label>
                            <input type="password" name="confirm_password" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 font-bold outline-none transition" required placeholder="Ulangi password baru">
                        </div>
                    </div>

                    <div class="flex justify-end pt-6 border-t border-slate-100 mt-2">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-xl shadow-lg shadow-blue-600/30 transition transform hover:-translate-y-0.5 flex items-center gap-2">
                            <span>💾</span> Simpan Password Baru
                        </button>
                    </div>

                </form>
            </div>
        </div>

        <div class="space-y-6">
            
            <div class="bg-yellow-50 border border-yellow-100 p-6 rounded-xl">
                <div class="flex items-center gap-3 mb-4">
                    <div class="bg-yellow-100 p-2 rounded-lg text-xl">💡</div>
                    <h3 class="font-bold text-yellow-800">Tips Keamanan</h3>
                </div>
                <ul class="text-sm text-yellow-800 space-y-3 list-disc ml-4 leading-relaxed">
                    <li>Gunakan minimal <strong>8 karakter</strong>.</li>
                    <li>Campurkan huruf besar, huruf kecil, dan angka.</li>
                    <li>Jangan gunakan tanggal lahir atau nama klub.</li>
                    <li>Ganti password setidaknya <strong>3 bulan sekali</strong>.</li>
                </ul>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                <h3 class="font-bold text-slate-700 text-xs uppercase tracking-wide mb-4">Status Akun</h3>
                <div class="flex items-center gap-3 text-sm text-slate-500">
                    <span class="bg-green-100 text-green-600 p-2 rounded-lg text-lg">🛡️</span>
                    <div>
                        <p class="font-bold text-slate-700">Akun Aman</p>
                        <p class="text-xs">Password terakhir diubah: <span class="font-mono">Belum pernah</span></p>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>
