<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Proteksi Master Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'master') {
    header("Location: ../../../public/login.php"); exit;
}

// --- HANDLE SIMPAN (TAMBAH/EDIT) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_user'])) {
    $nama     = $_POST['nama_lengkap'];
    $email    = $_POST['email'];
    $pass     = $_POST['password'];
    $mode     = $_POST['event_type']; 
    $userId   = $_POST['user_id'] ?? '';

    try {
        if ($userId) {
            // MODE EDIT
            if (!empty($pass)) {
                $sql = "UPDATE users SET nama_lengkap=?, email=?, password=?, event_type=? WHERE id=?";
                $pdo->prepare($sql)->execute([$nama, $email, password_hash($pass, PASSWORD_DEFAULT), $mode, $userId]);
            } else {
                $sql = "UPDATE users SET nama_lengkap=?, email=?, event_type=? WHERE id=?";
                $pdo->prepare($sql)->execute([$nama, $email, $mode, $userId]);
            }
        } else {
            // MODE TAMBAH BARU
            $sql = "INSERT INTO users (nama_lengkap, email, password, role, event_type) VALUES (?, ?, ?, 'admin', ?)";
            $pdo->prepare($sql)->execute([$nama, $email, password_hash($pass, PASSWORD_DEFAULT), $mode]);
        }
        header("Location: index.php?role=admin"); exit;
    } catch (Exception $e) { die("Gagal: " . $e->getMessage()); }
}

// --- HANDLE HAPUS ---
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$_GET['delete']]);
    header("Location: index.php?role=admin"); exit;
}

// Ambil Data Admin
$admins = $pdo->query("SELECT * FROM users WHERE role = 'admin' ORDER BY id DESC")->fetchAll();

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="flex justify-between items-center mb-8">
        <div>
            <nav class="text-slate-400 text-xs mb-2">
                <a href="../dashboard.php" class="hover:text-blue-600">← Kembali ke Dashboard</a>
            </nav>
            <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tight">Manajemen EO (Admin)</h1>
        </div>
        
        <div class="flex gap-2 bg-white p-1.5 rounded-2xl shadow-sm border border-slate-200">
            <a href="index.php?role=user" class="px-6 py-2 rounded-xl text-xs font-bold text-slate-400 hover:bg-slate-50 transition">User (Klub)</a>
            <a href="index.php?role=admin" class="px-6 py-2 rounded-xl text-xs font-bold bg-blue-600 text-white shadow-lg">Admin (EO)</a>
        </div>
    </div>

    <div class="flex justify-end mb-6">
        <button onclick="openModal()" class="bg-slate-900 text-white px-8 py-3 rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl hover:bg-blue-600 transition">
            + Tambah Admin Baru
        </button>
    </div>

    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 border-b">
                <tr>
                    <th class="px-8 py-5 font-black uppercase text-[10px] text-slate-400 tracking-widest">Nama Penyelenggara</th>
                    <th class="px-8 py-5 font-black uppercase text-[10px] text-slate-400 tracking-widest text-center">Sistem</th>
                    <th class="px-8 py-5 font-black uppercase text-[10px] text-slate-400 tracking-widest text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach($admins as $a): ?>
                <tr class="hover:bg-slate-50 transition">
                    <td class="px-8 py-5">
                        <div class="font-black text-slate-800 uppercase"><?= htmlspecialchars($a['nama_lengkap']) ?></div>
                        <div class="text-[11px] text-slate-400"><?= htmlspecialchars($a['email']) ?></div>
                    </td>
                    <td class="px-8 py-5 text-center">
                        <?php if($a['event_type'] == 'Babak Penyisihan'): ?>
                            <span class="bg-orange-100 text-orange-700 px-4 py-1.5 rounded-full text-[9px] font-black uppercase border border-orange-200">Babak Penyisihan</span>
                        <?php else: ?>
                            <span class="bg-blue-100 text-blue-700 px-4 py-1.5 rounded-full text-[9px] font-black uppercase border border-blue-200">Langsung Final</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-8 py-5 text-right">
                        <button onclick='editAdmin(<?= json_encode($a) ?>)' class="bg-blue-50 text-blue-600 px-4 py-2 rounded-xl text-[10px] font-black uppercase hover:bg-blue-600 hover:text-white transition">Edit</button>
                        <a href="?delete=<?= $a['id'] ?>" onclick="return confirm('Hapus?')" class="bg-red-50 text-red-500 px-4 py-2 rounded-xl text-[10px] font-black uppercase hover:bg-red-500 hover:text-white transition">Hapus</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="modal-admin" class="fixed inset-0 z-[70] hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-lg rounded-[3rem] shadow-2xl overflow-hidden">
        <div class="bg-slate-900 p-8 text-white flex justify-between items-center">
            <h3 id="modal-title" class="font-black uppercase tracking-widest italic">Tambah Admin</h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-white">✕</button>
        </div>
        <form method="POST" class="p-10 space-y-6">
            <input type="hidden" name="save_user" value="1">
            <input type="hidden" name="user_id" id="form-id">
            
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Nama EO</label>
                <input type="text" name="nama_lengkap" id="form-nama" class="w-full px-5 py-4 border-2 border-slate-50 bg-slate-50 rounded-2xl font-bold focus:bg-white focus:border-blue-500 transition outline-none" required>
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Email</label>
                <input type="email" name="email" id="form-email" class="w-full px-5 py-4 border-2 border-slate-50 bg-slate-50 rounded-2xl font-bold focus:bg-white focus:border-blue-500 transition outline-none" required>
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Sistem Perlombaan</label>
                <select name="event_type" id="form-mode" class="w-full px-5 py-4 border-2 border-slate-50 bg-slate-50 rounded-2xl font-black text-xs uppercase focus:bg-white focus:border-blue-500 outline-none">
                    <option value="Langsung Final">Langsung Final (Timed Final)</option>
                    <option value="Babak Penyisihan">Babak Penyisihan (Prelims)</option>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Password</label>
                <input type="password" name="password" id="form-pass" class="w-full px-5 py-4 border-2 border-slate-50 bg-slate-50 rounded-2xl font-bold focus:bg-white focus:border-blue-500 transition outline-none">
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white font-black py-5 rounded-3xl shadow-xl hover:bg-blue-700 transition uppercase tracking-widest text-xs mt-4">Simpan Akun</button>
        </form>
    </div>
</div>

<script>
const modal = document.getElementById('modal-admin');
function openModal() {
    document.getElementById('modal-title').innerText = "Tambah Admin Baru";
    document.getElementById('form-id').value = "";
    document.getElementById('form-nama').value = "";
    document.getElementById('form-email').value = "";
    document.getElementById('form-mode').value = "Langsung Final";
    document.getElementById('form-pass').required = true;
    modal.classList.remove('hidden');
}
function editAdmin(data) {
    document.getElementById('modal-title').innerText = "Edit Akun Admin";
    document.getElementById('form-id').value = data.id;
    document.getElementById('form-nama').value = data.nama_lengkap;
    document.getElementById('form-email').value = data.email;
    document.getElementById('form-mode').value = data.event_type; // Mengisi dropdown mode
    document.getElementById('form-pass').required = false;
    modal.classList.remove('hidden');
}
function closeModal() { modal.classList.add('hidden'); }
</script>