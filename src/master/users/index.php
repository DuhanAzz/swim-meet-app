<?php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'master') {
    header("Location: ../../../public/login.php"); exit;
}

$roleFilter = $_GET['role'] ?? 'user'; 
$roleTitle = ($roleFilter == 'admin') ? 'Event Organizer (Admin)' : 'Klub Renang (User)';

// --- 1. HANDLE EDIT DATA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    try {
        $id = $_POST['edit_id'];
        $nama = $_POST['nama_lengkap'];
        $user = $_POST['username'];
        $email = $_POST['email'];
        
        $pdo->beginTransaction();

        // Update Tabel Users
        $sqlUser = "UPDATE users SET nama_lengkap = ?, username = ?, email = ? WHERE id = ?";
        $pdo->prepare($sqlUser)->execute([$nama, $user, $email, $id]);

        // Jika Role User (Klub), Update juga tabel Clubs
        if ($roleFilter == 'user') {
            $klub = $_POST['nama_klub'];
            $kota = $_POST['kota'];
            // Cek apakah data klub sudah ada
            $check = $pdo->prepare("SELECT id FROM clubs WHERE user_id = ?");
            $check->execute([$id]);
            if ($check->rowCount() > 0) {
                $sqlClub = "UPDATE clubs SET nama_klub = ?, kota = ? WHERE user_id = ?";
                $pdo->prepare($sqlClub)->execute([$klub, $kota, $id]);
            } else {
                // Jika belum ada (kasus jarang), insert baru
                $sqlClub = "INSERT INTO clubs (user_id, nama_klub, kota) VALUES (?, ?, ?)";
                $pdo->prepare($sqlClub)->execute([$id, $klub, $kota]);
            }
        }

        $pdo->commit();
        $_SESSION['toast_type'] = 'success'; $_SESSION['toast_message'] = 'Data berhasil diperbarui!';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['toast_type'] = 'error'; $_SESSION['toast_message'] = 'Gagal: ' . $e->getMessage();
    }
    header("Location: index.php?role=$roleFilter"); exit;
}

// --- 2. HANDLE DELETE USER ---
if (isset($_POST['delete_id'])) {
    $delId = $_POST['delete_id'];
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$delId]);
    $_SESSION['toast_type'] = 'success'; $_SESSION['toast_message'] = 'User berhasil dihapus.';
    header("Location: index.php?role=$roleFilter"); exit;
}

// --- 3. HANDLE RESET PASSWORD ---
if (isset($_POST['reset_id'])) {
    $resId = $_POST['reset_id'];
    $defaultPass = password_hash('123456', PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$defaultPass, $resId]);
    $_SESSION['toast_type'] = 'success'; $_SESSION['toast_message'] = 'Password direset menjadi: 123456';
    header("Location: index.php?role=$roleFilter"); exit;
}

// --- 4. AMBIL DATA ---
$stmt = $pdo->prepare("SELECT u.*, c.nama_klub, c.kota 
                       FROM users u 
                       LEFT JOIN clubs c ON u.id = c.user_id 
                       WHERE u.role = ? ORDER BY u.created_at DESC");
$stmt->execute([$roleFilter]);
$users = $stmt->fetchAll();

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="flex justify-between items-center mb-6">
        <div>
            <a href="../dashboard.php" class="text-xs font-bold text-slate-400 hover:text-blue-600 mb-1 block">&larr; Kembali ke Dashboard</a>
            <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tight">Manajemen <?= $roleTitle ?></h1>
        </div>
        <div class="flex gap-2 bg-white p-1 rounded-lg border border-slate-200">
            <a href="?role=user" class="px-4 py-2 rounded-md text-xs font-bold transition <?= $roleFilter=='user'?'bg-blue-600 text-white shadow':'text-slate-500 hover:bg-slate-50' ?>">User (Klub)</a>
            <a href="?role=admin" class="px-4 py-2 rounded-md text-xs font-bold transition <?= $roleFilter=='admin'?'bg-blue-600 text-white shadow':'text-slate-500 hover:bg-slate-50' ?>">Admin (EO)</a>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 border-b font-bold text-slate-500 uppercase text-xs">
                <tr>
                    <th class="px-6 py-4">Nama Akun / Klub</th>
                    <th class="px-6 py-4">Username & Email</th>
                    <th class="px-6 py-4">Terdaftar</th>
                    <th class="px-6 py-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if(empty($users)): ?>
                    <tr><td colspan="4" class="p-8 text-center text-slate-400 italic">Tidak ada data ditemukan.</td></tr>
                <?php else: ?>
                    <?php foreach($users as $u): ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-6 py-4">
                            <div class="font-bold text-slate-700"><?= htmlspecialchars($u['nama_klub'] ?? $u['nama_lengkap']) ?></div>
                            <?php if($u['role']=='user'): ?>
                                <div class="text-xs text-slate-400">Kota: <?= htmlspecialchars($u['kota'] ?? '-') ?></div>
                                <div class="text-[10px] text-slate-400">CP: <?= htmlspecialchars($u['nama_lengkap']) ?></div>
                            <?php else: ?>
                                <div class="text-xs text-slate-400"><?= htmlspecialchars($u['nama_lengkap']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-mono text-xs text-blue-600">@<?= htmlspecialchars($u['username']) ?></div>
                            <div class="text-xs text-slate-400"><?= htmlspecialchars($u['email'] ?? '-') ?></div>
                        </td>
                        <td class="px-6 py-4 text-slate-500 text-xs">
                            <?= date('d M Y', strtotime($u['created_at'])) ?>
                        </td>
                        <td class="px-6 py-4 text-right flex justify-end gap-2">
                            
                            <button type="button" 
                                onclick="openEditModal(
                                    '<?= $u['id'] ?>', 
                                    '<?= addslashes($u['nama_lengkap']) ?>', 
                                    '<?= addslashes($u['username']) ?>', 
                                    '<?= addslashes($u['email']) ?>',
                                    '<?= addslashes($u['nama_klub'] ?? '') ?>',
                                    '<?= addslashes($u['kota'] ?? '') ?>'
                                )"
                                class="bg-blue-100 text-blue-700 px-3 py-1.5 rounded text-[10px] font-bold hover:bg-blue-200 transition flex items-center gap-1">
                                ✏️ Edit
                            </button>

                            <form method="POST" onsubmit="return confirm('Reset password akun ini menjadi 123456?')">
                                <input type="hidden" name="reset_id" value="<?= $u['id'] ?>">
                                <button class="bg-yellow-100 text-yellow-700 px-3 py-1.5 rounded text-[10px] font-bold hover:bg-yellow-200 transition flex items-center gap-1">
                                    🔑 Reset
                                </button>
                            </form>

                            <form method="POST" onsubmit="return confirm('Hapus permanen akun ini beserta seluruh datanya?')">
                                <input type="hidden" name="delete_id" value="<?= $u['id'] ?>">
                                <button class="bg-red-100 text-red-700 px-3 py-1.5 rounded text-[10px] font-bold hover:bg-red-200 transition flex items-center gap-1">
                                    🗑 Hapus
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="editModal" class="fixed inset-0 z-[60] hidden overflow-y-auto overflow-x-hidden flex justify-center items-center backdrop-blur-sm bg-black/30">
    <div class="relative p-4 w-full max-w-lg max-h-full">
        <div class="relative bg-white rounded-xl shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t">
                <h3 class="text-lg font-bold text-slate-900">Edit Data <?= $roleTitle ?></h3>
                <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 inline-flex justify-center items-center">
                    ✕
                </button>
            </div>
            
            <form method="POST" class="p-4 md:p-5">
                <input type="hidden" name="edit_id" id="edit_id">
                
                <div class="grid gap-4 mb-4 grid-cols-2">
                    <div class="col-span-2">
                        <label class="block mb-2 text-xs font-bold text-slate-700 uppercase">Nama Lengkap (CP)</label>
                        <input type="text" name="nama_lengkap" id="edit_nama" class="bg-slate-50 border border-slate-300 text-slate-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 font-bold" required>
                    </div>
                    
                    <div class="col-span-2 sm:col-span-1">
                        <label class="block mb-2 text-xs font-bold text-slate-700 uppercase">Username</label>
                        <input type="text" name="username" id="edit_username" class="bg-slate-50 border border-slate-300 text-slate-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 font-mono" required>
                    </div>
                    
                    <div class="col-span-2 sm:col-span-1">
                        <label class="block mb-2 text-xs font-bold text-slate-700 uppercase">Email</label>
                        <input type="email" name="email" id="edit_email" class="bg-slate-50 border border-slate-300 text-slate-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 font-bold" required>
                    </div>

                    <?php if($roleFilter == 'user'): ?>
                    <div class="col-span-2 mt-2 pt-4 border-t border-slate-100">
                        <p class="text-xs text-blue-600 font-bold mb-3 uppercase">Informasi Klub</p>
                    </div>
                    <div class="col-span-2">
                        <label class="block mb-2 text-xs font-bold text-slate-700 uppercase">Nama Klub</label>
                        <input type="text" name="nama_klub" id="edit_klub" class="bg-blue-50 border border-blue-200 text-slate-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 font-bold">
                    </div>
                    <div class="col-span-2">
                        <label class="block mb-2 text-xs font-bold text-slate-700 uppercase">Kota Asal</label>
                        <input type="text" name="kota" id="edit_kota" class="bg-blue-50 border border-blue-200 text-slate-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 font-bold">
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="flex justify-end pt-4 border-t border-slate-100">
                    <button type="submit" class="text-white inline-flex items-center bg-blue-700 hover:bg-blue-800 font-bold rounded-lg text-sm px-5 py-2.5 text-center shadow-lg transform hover:-translate-y-0.5 transition">
                        💾 Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditModal(id, nama, username, email, klub, kota) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_nama').value = nama;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_email').value = email;
    
    // Isi field klub jika ada
    const fieldKlub = document.getElementById('edit_klub');
    const fieldKota = document.getElementById('edit_kota');
    
    if(fieldKlub) fieldKlub.value = klub;
    if(fieldKota) fieldKota.value = kota;
    
    document.getElementById('editModal').classList.remove('hidden');
}
</script>
