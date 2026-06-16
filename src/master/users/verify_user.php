<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// PROTEKSI HALAMAN (HANYA MASTER)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'master') {
    header("Location: /public/login.php"); exit;
}

$id = $_GET['id'] ?? 0;
if (!$id) {
    header("Location: index.php"); exit;
}

// AMBIL DATA PENGGUNA
$stmt = $pdo->prepare("SELECT u.*, c.nama_klub, c.kota, e.event_name, e.event_location 
                       FROM users u 
                       LEFT JOIN clubs c ON u.id = c.user_id 
                       LEFT JOIN events e ON u.id = e.user_id 
                       WHERE u.id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Data pengguna tidak ditemukan.");
}

$entitasName = $user['nama_klub'] ?? $user['event_name'] ?? '-';

// HANDLE APPROVE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['approve'])) {
    $pdo->prepare("UPDATE users SET account_status = 'active' WHERE id = ?")->execute([$id]);
    $_SESSION['swal_type'] = 'success';
    $_SESSION['swal_msg']  = 'Akun berhasil diverifikasi dan diaktifkan!';
    header("Location: verify_user.php?id=$id"); exit;
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>
<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="mb-8">
        <a href="index.php?role=<?= $user['role'] ?>" class="text-blue-600 font-bold text-sm hover:underline flex items-center gap-2 w-max">
            <span class="text-lg">&larr;</span> Kembali ke Daftar Pengguna
        </a>
    </div>

    <div class="bg-white rounded-[2rem] shadow-xl border border-slate-200 p-8 max-w-2xl mx-auto">
        <h1 class="text-2xl font-black text-slate-900 uppercase tracking-tight mb-6 border-b border-slate-100 pb-4">
            Verifikasi Akun Pengguna
        </h1>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Nama Lengkap</label>
                <div class="text-sm font-bold text-slate-800 uppercase flex items-center gap-2">
                    <span class="text-slate-400 text-lg">👤</span> <?= htmlspecialchars($user['nama_lengkap']) ?>
                </div>
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Email</label>
                <div class="text-sm font-bold text-slate-800 flex items-center gap-2">
                    <span class="text-slate-400 text-lg">✉️</span> <?= htmlspecialchars($user['email']) ?>
                </div>
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">No. WhatsApp</label>
                <div class="text-sm font-bold text-slate-800 flex items-center gap-2">
                    <span class="text-emerald-500 text-lg">📱</span> <?= htmlspecialchars($user['phone'] ?? '-') ?>
                </div>
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Nama Klub / Event</label>
                <div class="text-sm font-bold text-slate-800 uppercase flex items-center gap-2">
                    <span class="text-blue-500 text-lg">🏢</span> <?= htmlspecialchars($entitasName) ?>
                </div>
            </div>
            <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Tanggal Mendaftar</label>
                    <div class="text-sm font-bold text-slate-800 uppercase flex items-center gap-2">
                        <span class="text-slate-400 text-lg">📅</span> 
                        <?php 
                            $days = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];
                            $months = ['January'=>'Januari','February'=>'Februari','March'=>'Maret','April'=>'April','May'=>'Mei','June'=>'Juni','July'=>'Juli','August'=>'Agustus','September'=>'September','October'=>'Oktober','November'=>'November','December'=>'Desember'];
                            $time = strtotime($user['created_at']);
                            $hari = $days[date('l', $time)];
                            $tgl = date('d', $time);
                            $bln = $months[date('F', $time)];
                            $thn = date('Y', $time);
                            $jam = date('H:i', $time);
                            echo "$hari, $tgl $bln $thn | $jam WIB";
                        ?>
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Status Saat Ini</label>
                    <?php if ($user['account_status'] === 'pending'): ?>
                        <span class="bg-amber-100 text-amber-700 px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-wider inline-flex items-center gap-2 animate-pulse shadow-sm border border-amber-200">
                            <span class="w-2 h-2 rounded-full bg-amber-500 animate-ping"></span> PENDING VERIFICATION
                        </span>
                    <?php elseif ($user['account_status'] === 'active'): ?>
                        <span class="bg-emerald-100 text-emerald-700 px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-wider inline-flex items-center gap-2 shadow-sm border border-emerald-200">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span> ACTIVE
                        </span>
                    <?php else: ?>
                        <span class="bg-red-100 text-red-700 px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-wider inline-flex items-center gap-2 shadow-sm border border-red-200">
                            <span class="w-2 h-2 rounded-full bg-red-500"></span> <?= strtoupper($user['account_status']) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="border-t border-slate-100 pt-8">
            <?php if ($user['account_status'] === 'pending'): ?>
                <form method="POST">
                    <button type="submit" name="approve" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-4 rounded-xl shadow-lg transition uppercase tracking-widest text-sm flex justify-center items-center gap-2 transform hover:-translate-y-0.5">
                        <span class="text-lg">✅</span> Setujui & Aktifkan Akun
                    </button>
                </form>
            <?php elseif ($user['account_status'] === 'active'): ?>
                <?php 
                    $waNum = preg_replace('/[^0-9]/', '', $user['phone'] ?? '');
                    if(substr($waNum, 0, 1) == '0') $waNum = '62' . substr($waNum, 1);
                    $waLink = "https://wa.me/{$waNum}?text=" . urlencode("Halo, akun Anda di SET System sudah aktif! Silakan masuk ke link berikut untuk mulai mengelola kompetisi: https://setsystem.id/public/login.php");
                ?>
                <a href="<?= $waLink ?>" target="_blank" class="w-full bg-[#25D366] hover:bg-[#128C7E] text-white font-black py-4 rounded-xl shadow-lg transition uppercase tracking-widest text-sm flex justify-center items-center gap-2 transform hover:-translate-y-0.5">
                    <span class="text-xl">📱</span> Kirim Notifikasi via WhatsApp
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    <?php if(isset($_SESSION['swal_type'])): ?>
        Swal.fire({
            icon: '<?= $_SESSION['swal_type'] ?>',
            title: '<?= $_SESSION['swal_msg'] ?>',
            toast: true, position: 'top-end', showConfirmButton: false, timer: 3000
        });
        <?php unset($_SESSION['swal_type']); unset($_SESSION['swal_msg']); ?>
    <?php endif; ?>
</script>
</body>
</html>
