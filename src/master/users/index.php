<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Proteksi Master Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'master') {
    header("Location: ../../../public/login.php"); exit;
}

// Tentukan role mana yang sedang dikelola (default: admin)
$targetRole = $_GET['role'] ?? 'admin';

// --- HANDLE SIMPAN (TAMBAH/EDIT) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_user'])) {
    $nama     = $_POST['nama_lengkap'];
    $email    = $_POST['email'];
    // PERBAIKAN: Gunakan email sebagai username agar database tidak error
    $username = $_POST['email']; 
    $pass     = $_POST['password'];
    $mode     = $_POST['event_type'] ?? 'Langsung Final'; 
    $role     = $_POST['role_type'];
    $userId   = $_POST['user_id'] ?? '';

    try {
        if ($userId) {
            // MODE EDIT
            if (!empty($pass)) {
                $sql = "UPDATE users SET nama_lengkap=?, email=?, username=?, password=?, event_type=?, role=? WHERE id=?";
                $pdo->prepare($sql)->execute([$nama, $email, $username, password_hash($pass, PASSWORD_DEFAULT), $mode, $role, $userId]);
            } else {
                $sql = "UPDATE users SET nama_lengkap=?, email=?, username=?, event_type=?, role=? WHERE id=?";
                $pdo->prepare($sql)->execute([$nama, $email, $username, $mode, $role, $userId]);
            }
        } else {
            // MODE TAMBAH BARU
            // PERBAIKAN: Tambahkan kolom 'username' ke dalam query INSERT
            $sql = "INSERT INTO users (nama_lengkap, email, username, password, role, event_type) VALUES (?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$nama, $email, $username, password_hash($pass, PASSWORD_DEFAULT), $role, $mode]);
        }
        header("Location: index.php?role=" . $role); exit;
    } catch (Exception $e) { 
        // Jika masih error, tampilkan pesan agar mudah didebug
        die("Gagal Simpan Database: " . $e->getMessage()); 
    }
}

// --- HANDLE HAPUS ---
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$_GET['delete']]);
    header("Location: index.php?role=" . $targetRole); exit;
}

// Ambil Data berdasarkan Role yang dipilih
$stmt = $pdo->prepare("SELECT * FROM users WHERE role = ? ORDER BY id DESC");
$stmt->execute([$targetRole]);
$users = $stmt->fetchAll();

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <nav class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mb-2">
                <a href="../dashboard.php" class="hover:text-blue-600">← Control Center</a>
            </nav>
            <h1 class="text-3xl font-black text-slate-900 uppercase tracking-tighter italic leading-none">
                Manajemen <?= $targetRole == 'admin' ? 'Admin EO' : 'User Klub' ?>
            </h1>
        </div>
        
        <div class="flex gap-2 bg-white p-1.5 rounded-[1.5rem] shadow-sm border border-slate-200">
            <a href="index.php?role=user" class="px-6 py-2.5 rounded-2xl text-[10px] font-black uppercase transition <?= $targetRole == 'user' ? 'bg-slate-900 text-white shadow-lg' : 'text-slate-400 hover:bg-slate-50' ?>">User (Klub)</a>
            <a href="index.php?role=admin" class="px-6 py-2.5 rounded-2xl text-[10px] font-black uppercase transition <?= $targetRole == 'admin' ? 'bg-blue-600 text-white shadow-lg' : 'text-slate-400 hover:bg-slate-50' ?>">Admin (EO)</a>
        </div>
    </div>

    <div class="flex justify-end mb-6">
        <button onclick="openModal()" class="bg-slate-900 text-white px-8 py-3.5 rounded-2xl font-black text-[10px] uppercase tracking-[0.2em] shadow-xl hover:bg-blue-600 transition">
            + Daftarkan <?= strtoupper($targetRole) ?> Baru
        </button>
    </div>

    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr>
                    <th class="px-8 py-6 font-black uppercase text-[10px] text-slate-400 tracking-widest">Identitas</th>
                    <?php if($targetRole == 'admin'): ?>
                        <th class="px-8 py-6 font-black uppercase text-[10px] text-slate-400 tracking-widest text-center">Sistem Seeding</th>
                    <?php endif; ?>
                    <th class="px-8 py-6 font-black uppercase text-[10px] text-slate-400 tracking-widest text-right">Manajemen</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if(empty($users)): ?>
                    <tr><td colspan="3" class="px-8 py-20 text-center text-slate-300 font-bold italic uppercase text-xs">Belum ada data <?= $targetRole ?>.</td></tr>
                <?php else: foreach($users as $u): ?>
                <tr class="hover:bg-slate-50/50 transition">
                    <td class="px-8 py-6">
                        <div class="font-black text-slate-800 uppercase italic leading-tight"><?= htmlspecialchars($u['nama_lengkap']) ?></div>
                        <div class="text-[10px] font-bold text-blue-500 mt-1"><?= htmlspecialchars($u['email']) ?></div>
                    </td>
                    <?php if($targetRole == 'admin'): ?>
                    <td class="px-8 py-6 text-center">
                        <span class="px-4 py-1.5 rounded-full text-[9px] font-black uppercase border <?= $u['event_type'] == 'Babak Penyisihan' ? 'bg-orange-50 text-orange-600 border-orange-100' : 'bg-blue-50 text-blue-600 border-blue-100' ?>">
                            <?= $u['event_type'] == 'Babak Penyisihan' ? 'Heats & Finals' : 'Timed Final' ?>
                        </span>
                    </td>
                    <?php endif; ?>
                    <td class="px-8 py-6 text-right">
                        <div class="flex justify-end gap-2">
                            <button onclick='editAdmin(<?= json_encode($u) ?>)' class="bg-white border border-slate-200 text-slate-600 px-5 py-2 rounded-xl text-[10px] font-black uppercase hover:bg-slate-900 hover:text-white transition shadow-sm">Edit</button>
                            <a href="?delete=<?= $u['id'] ?>&role=<?= $targetRole ?>" onclick="return confirm('Hapus permanen?')" class="bg-red-50 text-red-500 px-5 py-2 rounded-xl text-[10px] font-black uppercase hover:bg-red-600 hover:text-white transition">Hapus</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="modal-admin" class="fixed inset-0 z-[100] hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-lg rounded-[3rem] shadow-2xl overflow-hidden">
        <div class="bg-slate-900 p-8 text-white flex justify-between items-center">
            <div>
                <h3 id="modal-title" class="font-black uppercase tracking-widest italic text-lg leading-none">Tambah Akun</h3>
            </div>
            <button onclick="closeModal()" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center hover:bg-red-500 transition">✕</button>
        </div>
        <form method="POST" class="p-10 space-y-5">
            <input type="hidden" name="save_user" value="1">
            <input type="hidden" name="user_id" id="form-id">
            <input type="hidden" name="role_type" value="<?= $targetRole ?>">
            
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase ml-1 italic">Nama Lengkap</label>
                <input type="text" name="nama_lengkap" id="form-nama" class="w-full px-6 py-4 border-2 border-slate-50 bg-slate-50 rounded-2xl font-bold focus:bg-white focus:border-blue-500 transition outline-none" required>
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase ml-1 italic">Email Address</label>
                <input type="email" name="email" id="form-email" class="w-full px-6 py-4 border-2 border-slate-50 bg-slate-50 rounded-2xl font-bold focus:bg-white focus:border-blue-500 transition outline-none" required>
            </div>

            <?php if($targetRole == 'admin'): ?>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase ml-1 italic">Sistem Pertandingan</label>
                <select name="event_type" id="form-mode" class="w-full px-6 py-4 border-2 border-slate-50 bg-slate-50 rounded-2xl font-black text-xs uppercase focus:bg-white focus:border-blue-500 outline-none">
                    <option value="Langsung Final">Langsung Final (Timed Final)</option>
                    <option value="Babak Penyisihan">Babak Penyisihan (Heats & Finals)</option>
                </select>
            </div>
            <?php endif; ?>

            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase ml-1 italic">Password</label>
                <input type="password" name="password" id="form-pass" class="w-full px-6 py-4 border-2 border-slate-50 bg-slate-50 rounded-2xl font-bold focus:bg-white focus:border-blue-500 transition outline-none">
            </div>

            <button type="submit" class="w-full bg-blue-600 text-white font-black py-5 rounded-[2rem] shadow-xl transition uppercase tracking-[0.2em] text-[11px] mt-6 transform active:scale-95">
                Simpan Perubahan
            </button>
        </form>
    </div>
</div>

<script>
const modal = document.getElementById('modal-admin');
function openModal() {
    document.getElementById('modal-title').innerText = "Daftarkan <?= strtoupper($targetRole) ?> Baru";
    document.getElementById('form-id').value = "";
    document.getElementById('form-nama').value = "";
    document.getElementById('form-email').value = "";
    document.getElementById('form-pass').required = true;
    modal.classList.remove('hidden');
}
function editAdmin(data) {
    document.getElementById('modal-title').innerText = "Update Data <?= strtoupper($targetRole) ?>";
    document.getElementById('form-id').value = data.id;
    document.getElementById('form-nama').value = data.nama_lengkap;
    document.getElementById('form-email').value = data.email;
    if(document.getElementById('form-mode')) document.getElementById('form-mode').value = data.event_type;
    document.getElementById('form-pass').required = false;
    modal.classList.remove('hidden');
}
function closeModal() { modal.classList.add('hidden'); }
</script>