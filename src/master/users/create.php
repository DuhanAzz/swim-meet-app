<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'master') die("Akses Ditolak.");

$preRole = $_GET['pre_role'] ?? 'user'; 

// Variabel default
$username = '';
$nama = '';
$role = $preRole;
$event_date_txt = '';
$event_start = '';
$venue = '';
$loc = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $nama     = $_POST['nama_lengkap'];
    $role     = $_POST['role'];
    
    // PERBAIKAN UTAMA DI SINI:
    // Jika input kosong, set jadi NULL. Jangan biarkan string kosong "".
    $event_date_txt = !empty($_POST['event_date_display']) ? $_POST['event_date_display'] : null;
    $event_start    = !empty($_POST['event_start_date']) ? $_POST['event_start_date'] : null;
    $venue          = !empty($_POST['venue_name']) ? $_POST['venue_name'] : null;
    $loc            = !empty($_POST['location']) ? $_POST['location'] : null;
    
    $event_image_path = null;
    if ($role == 'admin' && !empty($_FILES['event_image']['name'])) {
        $targetDir = "../../../public/img/events/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $ext = pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION);
        $fileName = "event_" . time() . "." . $ext;
        if (move_uploaded_file($_FILES['event_image']['tmp_name'], $targetDir . $fileName)) {
            $event_image_path = "img/events/" . $fileName;
        }
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, role, event_date_display, event_start_date, venue_name, location, event_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$username, $password, $nama, $role, $event_date_txt, $event_start, $venue, $loc, $event_image_path]);
        
        $_SESSION['toast_type'] = 'success'; 
        $_SESSION['toast_message'] = 'Akun berhasil dibuat dengan sukses!'; 
        
        header("Location: index.php?role=" . $role); 
        exit();

    } catch (PDOException $e) {
        $_SESSION['toast_type'] = 'error'; 
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            $_SESSION['toast_message'] = 'Username sudah dipakai! Ganti yang lain.';
        } else {
            $_SESSION['toast_message'] = 'Gagal Database: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 mt-16 bg-slate-50 min-h-screen font-sans">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-800 tracking-tight uppercase">Tambah Akun Baru</h1>
            <p class="text-sm text-slate-500">Isi formulir di bawah untuk mendaftarkan akun baru.</p>
        </div>
        <a href="index.php?role=<?= $preRole ?>" class="text-slate-500 hover:text-blue-600 font-bold text-sm flex items-center gap-2 transition hover:-translate-x-1">
            <span>&larr;</span> Kembali ke Daftar
        </a>
    </div>

    <div class="bg-white p-8 rounded-xl shadow-sm border border-slate-200 max-w-4xl">
        <form method="POST" enctype="multipart/form-data" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-slate-700 font-bold mb-2 text-sm">Username</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">@</span>
                        <input type="text" name="username" value="<?= htmlspecialchars($username) ?>" class="w-full pl-8 pr-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 transition" placeholder="username_unik" required>
                    </div>
                </div>
                <div>
                    <label class="block text-slate-700 font-bold mb-2 text-sm">Password</label>
                    <input type="password" name="password" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 transition" placeholder="••••••••" required>
                </div>
            </div>

            <div>
                <label class="block text-slate-700 font-bold mb-2 text-sm">Role Access</label>
                <select name="role" id="roleSelect" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-bold bg-slate-50 focus:bg-white transition focus:ring-2 focus:ring-blue-500" onchange="toggleEventFields()">
                    <option value="user" <?= ($role == 'user') ? 'selected' : '' ?>>👤 USER (Peserta / Klub)</option>
                    <option value="admin" <?= ($role == 'admin') ? 'selected' : '' ?>>🏆 ADMIN (Panitia Lomba)</option>
                </select>
            </div>

            <div>
                <label id="nameLabel" class="block text-slate-700 font-bold mb-2 text-sm">
                    <?= ($role == 'admin') ? 'Nama Kompetisi / Event' : 'Nama Lengkap / Nama Klub' ?>
                </label>
                <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($nama) ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 transition" placeholder="Isi nama sesuai role..." required>
            </div>

            <div id="eventFields" class="<?= ($role == 'admin') ? '' : 'hidden' ?> pt-6 mt-6 border-t border-dashed border-slate-200 bg-blue-50/50 -mx-8 px-8 pb-6">
                <h3 class="text-blue-800 font-black text-sm uppercase tracking-wide mb-4 flex items-center gap-2">
                    <span class="bg-blue-100 p-1 rounded">🏆</span> Detail Informasi Kompetisi
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                    <div>
                        <label class="block text-slate-600 font-bold mb-2 text-xs">Tampilan Tanggal (Teks)</label>
                        <input type="text" name="event_date_display" value="<?= htmlspecialchars($event_date_txt) ?>" class="w-full border border-blue-200 rounded-lg px-4 py-2.5 text-sm focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-slate-600 font-bold mb-2 text-xs">Tanggal Mulai (Sorting)</label>
                        <input type="date" name="event_start_date" value="<?= htmlspecialchars($event_start) ?>" class="w-full border border-blue-200 rounded-lg px-4 py-2.5 text-sm focus:ring-blue-500">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-slate-600 font-bold mb-2 text-xs">Nama Kolam Renang</label>
                    <input type="text" name="venue_name" value="<?= htmlspecialchars($venue) ?>" class="w-full border border-blue-200 rounded-lg px-4 py-2.5 text-sm focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-slate-600 font-bold mb-2 text-xs">Lokasi Daerah</label>
                    <input type="text" name="location" value="<?= htmlspecialchars($loc) ?>" class="w-full border border-blue-200 rounded-lg px-4 py-2.5 text-sm focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-slate-600 font-bold mb-2 text-xs">Upload Gambar Event</label>
                    <input type="file" name="event_image" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-blue-600 file:text-white hover:file:bg-blue-700 cursor-pointer">
                </div>
            </div>

            <div class="flex items-center justify-end pt-6 border-t border-slate-100 gap-3">
                <a href="index.php?role=<?= $preRole ?>" class="px-6 py-2.5 rounded-lg border border-slate-300 text-slate-600 text-sm font-bold hover:bg-slate-50 transition">Batal</a>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-8 rounded-lg shadow-lg shadow-blue-600/30 transition transform hover:-translate-y-0.5">
                    Simpan Data
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleEventFields() {
    var role = document.getElementById("roleSelect").value;
    var fields = document.getElementById("eventFields");
    var label = document.getElementById("nameLabel");
    if(role === 'admin') {
        fields.classList.remove('hidden');
        label.innerText = "Nama Kompetisi / Event";
    } else {
        fields.classList.add('hidden');
        label.innerText = "Nama Lengkap / Nama Klub";
    }
}
toggleEventFields();
</script>
