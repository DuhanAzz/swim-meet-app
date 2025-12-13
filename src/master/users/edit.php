<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'master') die("Akses Ditolak.");

$id = $_GET['id'] ?? null;
if (!$id) { header("Location: index.php"); exit; }

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) die("User tidak ditemukan.");

$currentRole = $user['role']; 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $nama     = $_POST['nama_lengkap'];
    $password = $_POST['password'];
    
    // PERBAIKAN: Ubah string kosong jadi NULL
    $event_date_txt = !empty($_POST['event_date_display']) ? $_POST['event_date_display'] : null;
    $event_start    = !empty($_POST['event_start_date']) ? $_POST['event_start_date'] : null;
    $venue          = !empty($_POST['venue_name']) ? $_POST['venue_name'] : null;
    $loc            = !empty($_POST['location']) ? $_POST['location'] : null;

    try {
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $id]);
        }

        $sql = "UPDATE users SET username=?, nama_lengkap=?, event_date_display=?, event_start_date=?, venue_name=?, location=? WHERE id=?";
        $pdo->prepare($sql)->execute([$username, $nama, $event_date_txt, $event_start, $venue, $loc, $id]);

        if (!empty($_FILES['event_image']['name'])) {
            $targetDir = "../../../public/img/events/";
            $ext = pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION);
            $fileName = "event_" . time() . "_" . uniqid() . "." . $ext;
            if (move_uploaded_file($_FILES['event_image']['tmp_name'], $targetDir . $fileName)) {
                $dbPath = "img/events/" . $fileName;
                $pdo->prepare("UPDATE users SET event_image = ? WHERE id = ?")->execute([$dbPath, $id]);
            }
        }

        $_SESSION['toast_type'] = 'success'; 
        $_SESSION['toast_message'] = 'Data berhasil diperbarui!'; 
        
        header("Location: index.php?role=" . $currentRole);
        exit();

    } catch (PDOException $e) {
        $_SESSION['toast_type'] = 'error'; 
        $_SESSION['toast_message'] = 'Update Gagal: ' . $e->getMessage();
    }
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-8 sm:ml-64 mt-20 bg-slate-50 min-h-screen font-sans">
    <div class="max-w-3xl mx-auto">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-black text-slate-800 tracking-tight uppercase">EDIT <?= strtoupper($currentRole) ?></h1>
                <p class="text-slate-500 text-sm">Update data atau reset password.</p>
            </div>
            <a href="index.php?role=<?= $currentRole ?>" class="text-slate-500 hover:text-blue-600 font-bold">&larr; Back to List</a>
        </div>

        <div class="bg-white p-10 rounded-3xl shadow-lg border border-slate-200">
            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-slate-700 font-bold mb-2 text-sm">Username</label>
                        <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" class="w-full border border-slate-300 rounded-lg px-4 py-3 bg-slate-50">
                    </div>
                    <div>
                        <label class="block text-slate-700 font-bold mb-2 text-sm">Reset Password</label>
                        <input type="password" name="password" class="w-full border border-yellow-300 bg-yellow-50 rounded-lg px-4 py-3 focus:ring-yellow-500" placeholder="Isi jika ingin ubah password">
                    </div>
                </div>

                <div>
                    <label class="block text-slate-700 font-bold mb-2 text-sm">Nama Lengkap / Event</label>
                    <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($user['nama_lengkap']) ?>" class="w-full border border-slate-300 rounded-lg px-4 py-3 bg-slate-50">
                </div>

                <?php if($currentRole == 'admin'): ?>
                <div class="pt-6 border-t border-dashed border-slate-300 bg-blue-50 p-6 rounded-xl space-y-6">
                    <h3 class="text-blue-800 font-black text-sm uppercase tracking-wide">🏆 Detail Kompetisi</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div><label class="block text-blue-900 font-bold mb-2 text-xs">Tampilan Tanggal</label><input type="text" name="event_date_display" value="<?= htmlspecialchars($user['event_date_display'] ?? '') ?>" class="w-full border border-blue-200 rounded-lg px-4 py-3"></div>
                        <div><label class="block text-blue-900 font-bold mb-2 text-xs">Tanggal Sorting</label><input type="date" name="event_start_date" value="<?= htmlspecialchars($user['event_start_date'] ?? '') ?>" class="w-full border border-blue-200 rounded-lg px-4 py-3"></div>
                    </div>
                    <div><label class="block text-blue-900 font-bold mb-2 text-xs">Lokasi & Kolam</label><div class="grid grid-cols-1 md:grid-cols-2 gap-4"><input type="text" name="venue_name" value="<?= htmlspecialchars($user['venue_name'] ?? '') ?>" class="w-full border border-blue-200 rounded-lg px-4 py-3"><input type="text" name="location" value="<?= htmlspecialchars($user['location'] ?? '') ?>" class="w-full border border-blue-200 rounded-lg px-4 py-3"></div></div>
                    <div><label class="block text-blue-900 font-bold mb-2 text-xs">Ganti Gambar Event</label><input type="file" name="event_image" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-100 file:text-blue-700 hover:file:bg-blue-200">
                    <?php if(!empty($user['event_image'])): ?><div class="mt-4 p-2 bg-white rounded border border-blue-100 inline-block"><p class="text-[10px] text-slate-400 mb-1">Gambar saat ini:</p><img src="../../../public/<?= $user['event_image'] ?>?v=<?= time() ?>" class="h-32 w-auto rounded object-cover"></div><?php endif; ?></div>
                </div>
                <?php endif; ?>

                <div class="flex justify-end pt-6">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-xl shadow-lg transition transform hover:-translate-y-1">Update Data</button>
                </div>
            </form>
        </div>
    </div>
</div>
