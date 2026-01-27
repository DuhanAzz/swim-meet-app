<?php
// FILE: src/master/users/index.php
session_start();
require_once __DIR__ . '/../../config/database.php';

// 1. PROTEKSI HALAMAN (HANYA MASTER)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'master') {
    header("Location: ../../../public/login.php"); exit;
}

// 2. SETUP VARIABEL
$targetRole = $_GET['role'] ?? 'admin'; 
$search     = $_GET['q'] ?? '';
$msg        = $_GET['msg'] ?? '';

// --- HELPER LOGGING (Jaga-jaga jika fungsi belum ada di config) ---
if (!function_exists('writeLog')) {
    function writeLog($pdo, $userId, $action, $targetId, $desc) {
        try {
            $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, action_type, target_id, description, ip_address) VALUES (?, ?, ?, ?, ?)");
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $stmt->execute([$userId, $action, $targetId, $desc, $ip]);
        } catch (Exception $e) { /* Silent Error */ }
    }
}

// --- HANDLE ACTION: UBAH STATUS (APPROVE/SUSPEND) ---
if (isset($_GET['action']) && isset($_GET['uid']) && isset($_GET['status'])) {
    $uid = $_GET['uid'];
    $newStatus = $_GET['status'];
    
    if (in_array($newStatus, ['active', 'pending', 'suspended'])) {
        // Cek jangan blokir diri sendiri
        if ($uid == $_SESSION['user_id']) {
            echo "<script>alert('Tidak bisa mengubah status akun sendiri!'); window.location='index.php?role=$targetRole';</script>"; exit;
        }

        try {
            $pdo->prepare("UPDATE users SET account_status = ? WHERE id = ?")->execute([$newStatus, $uid]);
            
            // CATAT LOG
            writeLog($pdo, $_SESSION['user_id'], 'CHANGE_STATUS', $uid, "Ubah status user ID $uid menjadi $newStatus");
            
            header("Location: index.php?role=$targetRole&msg=status_updated"); exit;
        } catch (Exception $e) {
            die("Error: " . $e->getMessage());
        }
    }
}

// --- HANDLE ACTION: SIMPAN (TAMBAH/EDIT) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_user'])) {
    $nama     = trim($_POST['nama_lengkap']);
    $email    = trim($_POST['email']);
    $phone    = $_POST['phone'] ?? null;
    $username = $email; // Username disamakan email
    $pass     = $_POST['password'];
    $role     = $_POST['role_type'];
    $userId   = $_POST['user_id'] ?? '';
    
    // Data Spesifik Role
    $location   = $_POST['location'] ?? null;
    $eventType  = $_POST['event_type'] ?? 'Langsung Final';
    $eventDate  = $_POST['event_date'] ?? date('Y-m-d');
    $city       = $_POST['city'] ?? null;

    try {
        $pdo->beginTransaction();

        if ($userId) {
            // --- MODE EDIT ---
            if (!empty($pass)) {
                $pdo->prepare("UPDATE users SET nama_lengkap=?, email=?, phone=?, username=?, password=? WHERE id=?")
                    ->execute([$nama, $email, $phone, $username, password_hash($pass, PASSWORD_DEFAULT), $userId]);
            } else {
                $pdo->prepare("UPDATE users SET nama_lengkap=?, email=?, phone=?, username=? WHERE id=?")
                    ->execute([$nama, $email, $phone, $username, $userId]);
            }

            // Update Detail Table
            if ($role == 'admin') {
                $check = $pdo->prepare("SELECT id FROM events WHERE created_by = ?");
                $check->execute([$userId]);
                if ($check->rowCount() > 0) {
                    $pdo->prepare("UPDATE events SET event_name=?, event_type=?, event_location=?, event_date_start=? WHERE created_by=?")
                        ->execute([$nama, $eventType, $location, $eventDate, $userId]);
                } else {
                    $pdo->prepare("INSERT INTO events (created_by, event_name, event_type, event_location, event_date_start) VALUES (?, ?, ?, ?, ?)")
                        ->execute([$userId, $nama, $eventType, $location, $eventDate]);
                }
            } elseif ($role == 'user') {
                $check = $pdo->prepare("SELECT id FROM clubs WHERE user_id = ?");
                $check->execute([$userId]);
                if ($check->rowCount() > 0) {
                    $pdo->prepare("UPDATE clubs SET nama_klub=?, city=? WHERE user_id=?")
                        ->execute([$nama, $city, $userId]);
                } else {
                    $pdo->prepare("INSERT INTO clubs (user_id, nama_klub, city) VALUES (?, ?, ?)")
                        ->execute([$userId, $nama, $city]);
                }
            }

            // CATAT LOG EDIT
            writeLog($pdo, $_SESSION['user_id'], 'EDIT_USER', $userId, "Edit user: $nama ($role)");

        } else {
            // --- MODE TAMBAH BARU ---
            // Cek Email Kembar
            $cekMail = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $cekMail->execute([$email]);
            if($cekMail->rowCount() > 0) {
                throw new Exception("Email $email sudah terdaftar!");
            }

            $pdo->prepare("INSERT INTO users (nama_lengkap, email, phone, username, password, role, account_status) VALUES (?, ?, ?, ?, ?, ?, 'active')")
                ->execute([$nama, $email, $phone, $username, password_hash($pass, PASSWORD_DEFAULT), $role]);
            $newUserId = $pdo->lastInsertId();

            if ($role == 'admin') {
                $pdo->prepare("INSERT INTO events (created_by, event_name, event_type, event_location, event_date_start, event_status) VALUES (?, ?, ?, ?, ?, 'Registration')")
                    ->execute([$newUserId, $nama, $eventType, $location, $eventDate]);
            } elseif ($role == 'user') {
                $pdo->prepare("INSERT INTO clubs (user_id, nama_klub, city) VALUES (?, ?, ?)")
                    ->execute([$newUserId, $nama, $city]);
            }

            // CATAT LOG CREATE
            writeLog($pdo, $_SESSION['user_id'], 'CREATE_USER', $newUserId, "User baru: $nama ($role)");
        }

        $pdo->commit();
        header("Location: index.php?role=" . $role . "&msg=saved"); exit;

    } catch (Exception $e) { 
        $pdo->rollBack();
        $errorMsg = urlencode($e->getMessage());
        header("Location: index.php?role=$role&msg=error&text=$errorMsg"); exit;
    }
}

// --- HANDLE ACTION: HAPUS ---
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    if ($id == $_SESSION['user_id']) die("Access Denied: Cannot delete yourself.");
    
    try {
        $pdo->beginTransaction();
        
        // CATAT LOG DELETE SEBELUM DIHAPUS
        writeLog($pdo, $_SESSION['user_id'], 'DELETE_USER', $id, "Hapus permanen user ID: $id");

        // Hapus Data
        $pdo->prepare("DELETE FROM events WHERE created_by = ?")->execute([$id]); 
        $pdo->prepare("DELETE FROM clubs WHERE user_id = ?")->execute([$id]);   
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);        
        
        $pdo->commit();
        header("Location: index.php?role=" . $targetRole . "&msg=deleted"); exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Gagal Hapus: " . $e->getMessage());
    }
}

// --- QUERY GET DATA ---
$params = ['role' => $targetRole];
$searchSql = "";
if (!empty($search)) {
    $searchSql = " AND (u.nama_lengkap LIKE :s OR u.email LIKE :s OR u.username LIKE :s) ";
    $params['s'] = "%$search%";
}

if ($targetRole == 'admin') {
    // Query Admin (EO)
    $sql = "SELECT u.*, 
                   e.event_type, e.event_name, e.event_location, e.event_date_start, e.event_status 
            FROM users u 
            LEFT JOIN events e ON u.id = e.created_by 
            WHERE u.role = :role $searchSql ORDER BY u.created_at DESC";
} else {
    // Query User (Klub) + Hitung Atlet
    $sql = "SELECT u.*, 
                   c.nama_klub, c.city, c.club_code,
                   (SELECT COUNT(*) FROM swimmers s WHERE s.user_id = u.id) as total_atlet 
            FROM users u 
            LEFT JOIN clubs c ON u.id = c.user_id 
            WHERE u.role = :role $searchSql ORDER BY u.created_at DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- VIEW UTAMA ---
include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-4">
        <div>
            <nav class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mb-2">
                <a href="../dashboard.php" class="hover:text-blue-600">← Control Center</a>
            </nav>
            <h1 class="text-3xl font-black text-slate-900 uppercase tracking-tighter italic leading-none">
                Manajemen <?= $targetRole == 'admin' ? 'Admin EO' : 'User Klub' ?>
            </h1>
            <p class="text-slate-500 text-xs font-medium mt-2">Total Data: <?= count($users) ?></p>
        </div>
        
        <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto">
            <form method="GET" class="relative">
                <input type="hidden" name="role" value="<?= $targetRole ?>">
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama / email..." 
                       class="pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold w-full md:w-64 focus:outline-none focus:border-blue-500 shadow-sm">
                <span class="absolute left-3 top-2.5 text-slate-400">🔍</span>
            </form>

            <div class="flex gap-1 bg-white p-1 rounded-xl shadow-sm border border-slate-200">
                <a href="index.php?role=user" class="px-4 py-2 rounded-lg text-[10px] font-black uppercase transition <?= $targetRole == 'user' ? 'bg-slate-900 text-white shadow-md' : 'text-slate-400 hover:bg-slate-50' ?>">Klub</a>
                <a href="index.php?role=admin" class="px-4 py-2 rounded-lg text-[10px] font-black uppercase transition <?= $targetRole == 'admin' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-400 hover:bg-slate-50' ?>">EO</a>
            </div>
        </div>
    </div>

    <?php if($msg == 'error'): ?>
        <div class="bg-red-50 text-red-700 p-4 rounded-xl mb-6 border border-red-200 text-sm font-bold flex items-center gap-2 shadow-sm">
            <span class="text-xl">⚠️</span> Gagal: <?= htmlspecialchars($_GET['text'] ?? 'Unknown Error') ?>
        </div>
    <?php endif; ?>

    <div class="flex justify-end mb-6">
        <button onclick="openModal()" class="bg-slate-900 text-white px-6 py-3 rounded-xl font-black text-[10px] uppercase tracking-[0.1em] shadow-xl hover:bg-blue-600 transition flex items-center gap-2 hover:-translate-y-1 transform duration-200">
            <span>+</span> Tambah <?= strtoupper($targetRole) ?>
        </button>
    </div>

    <div class="bg-white rounded-[2rem] shadow-xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50/50 border-b border-slate-100">
                    <tr>
                        <th class="px-6 py-5 font-black uppercase text-[9px] text-slate-400 tracking-widest w-1/4">User Account</th>
                        <th class="px-6 py-5 font-black uppercase text-[9px] text-slate-400 tracking-widest">Kontak</th>
                        <th class="px-6 py-5 font-black uppercase text-[9px] text-slate-400 tracking-widest w-1/3">
                            <?= $targetRole == 'admin' ? 'Detail Event' : 'Detail Klub' ?>
                        </th>
                        <?php if($targetRole == 'user'): ?>
                            <th class="px-6 py-5 font-black uppercase text-[9px] text-slate-400 tracking-widest text-center">Atlet</th>
                        <?php endif; ?>
                        <th class="px-6 py-5 font-black uppercase text-[9px] text-slate-400 tracking-widest text-center">Status</th>
                        <th class="px-6 py-5 font-black uppercase text-[9px] text-slate-400 tracking-widest text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if(empty($users)): ?>
                        <tr><td colspan="6" class="px-8 py-20 text-center text-slate-300 font-bold italic uppercase text-xs">Belum ada data.</td></tr>
                    <?php else: foreach($users as $u): ?>
                    <tr class="hover:bg-blue-50/30 transition group">
                        
                        <td class="px-6 py-5 align-top">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-slate-100 flex items-center justify-center text-slate-500 font-black text-xs border border-slate-200 shadow-sm shrink-0">
                                    <?= strtoupper(substr($u['nama_lengkap'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="font-black text-slate-800 uppercase italic leading-tight text-xs"><?= htmlspecialchars($u['nama_lengkap']) ?></div>
                                    <div class="text-[10px] font-mono text-slate-400 mt-0.5">@<?= htmlspecialchars($u['username']) ?></div>
                                </div>
                            </div>
                        </td>

                        <td class="px-6 py-5 align-top">
                            <div class="flex flex-col gap-1.5">
                                <a href="mailto:<?= htmlspecialchars($u['email']) ?>" class="text-[10px] font-bold text-blue-500 hover:underline flex items-center gap-1">
                                    📧 <?= htmlspecialchars($u['email']) ?>
                                </a>
                                <?php if(!empty($u['phone'])): 
                                    $waNum = preg_replace('/[^0-9]/', '', $u['phone']);
                                    if(substr($waNum, 0, 1) == '0') $waNum = '62' . substr($waNum, 1);
                                ?>
                                    <a href="https://wa.me/<?= $waNum ?>" target="_blank" class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded w-fit hover:bg-emerald-100 transition flex items-center gap-1">
                                        📱 WhatsApp
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                        
                        <td class="px-6 py-5 align-top">
                            <?php if($targetRole == 'admin'): ?>
                                <div class="space-y-1">
                                    <div class="text-xs font-black text-slate-700 uppercase">
                                        <?= htmlspecialchars($u['event_name'] ?? '-') ?>
                                    </div>
                                    <div class="flex flex-wrap gap-1 items-center">
                                        <span class="bg-slate-100 px-1.5 py-0.5 rounded text-[9px] font-bold text-slate-500 border border-slate-200">
                                            📍 <?= htmlspecialchars($u['event_location'] ?? '-') ?>
                                        </span>
                                        <span class="text-[9px] text-slate-400 font-medium">
                                            📅 <?= !empty($u['event_date_start']) ? date('d M Y', strtotime($u['event_date_start'])) : '-' ?>
                                        </span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="space-y-1">
                                    <div class="text-xs font-black text-slate-700 uppercase">
                                        <?= htmlspecialchars($u['nama_klub'] ?? 'No Club Name') ?>
                                    </div>
                                    <span class="bg-slate-100 px-1.5 py-0.5 rounded text-[9px] font-bold text-slate-500 border border-slate-200 inline-block">
                                        🏠 <?= htmlspecialchars($u['city'] ?? 'Kota -') ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </td>

                        <?php if($targetRole == 'user'): ?>
                            <td class="px-6 py-5 align-top text-center">
                                <a href="../swimmers/index.php?search=<?= urlencode($u['nama_klub']) ?>" class="inline-block bg-slate-50 border border-slate-200 rounded-lg px-3 py-1 hover:bg-blue-50 hover:border-blue-200 hover:scale-105 transition cursor-pointer group/card">
                                    <span class="block text-lg font-black text-blue-600 leading-none group-hover/card:text-blue-700"><?= $u['total_atlet'] ?></span>
                                    <span class="text-[8px] uppercase font-bold text-slate-400">Atlet &rarr;</span>
                                </a>
                            </td>
                        <?php endif; ?>

                        <td class="px-6 py-5 align-top text-center">
                            <?php 
                                $status = $u['account_status'] ?? 'pending';
                                $statusClass = match($status) {
                                    'active' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                                    'pending' => 'bg-amber-100 text-amber-700 border-amber-200',
                                    'suspended' => 'bg-red-100 text-red-700 border-red-200',
                                    default => 'bg-slate-100 text-slate-500'
                                };
                            ?>
                            <div class="flex flex-col items-center gap-2">
                                <span class="px-2 py-1 rounded-md text-[9px] font-black uppercase tracking-wider border <?= $statusClass ?>">
                                    <?= $status ?>
                                </span>
                                <div class="flex gap-1 opacity-100 lg:opacity-30 lg:group-hover:opacity-100 transition">
                                    <?php if($status != 'active'): ?>
                                        <a href="?action=status&uid=<?= $u['id'] ?>&status=active&role=<?= $targetRole ?>" 
                                           class="w-5 h-5 rounded bg-emerald-500 text-white flex items-center justify-center hover:bg-emerald-600 shadow-sm text-[10px]" title="Aktifkan">✓</a>
                                    <?php endif; ?>
                                    <?php if($status != 'suspended'): ?>
                                        <a href="?action=status&uid=<?= $u['id'] ?>&status=suspended&role=<?= $targetRole ?>" 
                                           class="w-5 h-5 rounded bg-red-500 text-white flex items-center justify-center hover:bg-red-600 shadow-sm text-[10px]" title="Blokir" onclick="return confirm('Blokir user ini?')">✕</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>

                        <td class="px-6 py-5 align-top text-right">
                            <div class="flex justify-end gap-2">
                                <button onclick='editAdmin(<?= json_encode($u) ?>)' class="w-8 h-8 flex items-center justify-center bg-white border border-slate-200 rounded-lg hover:border-blue-500 hover:text-blue-600 transition text-slate-400 shadow-sm">✏️</button>
                                <a href="?delete=<?= $u['id'] ?>&role=<?= $targetRole ?>" onclick="return confirm('Hapus permanen? Data event/klub terkait akan hilang.')" class="w-8 h-8 flex items-center justify-center bg-white border border-slate-200 rounded-lg hover:border-red-500 hover:text-red-600 transition text-slate-400 shadow-sm">🗑️</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="modal-admin" class="fixed inset-0 z-[100] hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-lg rounded-[2rem] shadow-2xl overflow-hidden max-h-[90vh] overflow-y-auto">
        <div class="bg-slate-900 p-6 text-white flex justify-between items-center sticky top-0 z-10">
            <div><h3 id="modal-title" class="font-black uppercase tracking-widest italic text-lg leading-none">Tambah Akun</h3></div>
            <button onclick="closeModal()" class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center hover:bg-red-500 transition">✕</button>
        </div>
        <form method="POST" class="p-8 space-y-6">
            <input type="hidden" name="save_user" value="1">
            <input type="hidden" name="user_id" id="form-id">
            <input type="hidden" name="role_type" value="<?= $targetRole ?>">
            
            <div class="space-y-3">
                <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-1">Info Login & Kontak</h4>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-[10px] font-bold text-slate-500 uppercase">Email (Username)</label>
                        <input type="email" name="email" id="form-email" class="w-full px-4 py-3 border border-slate-200 bg-slate-50 rounded-xl text-sm font-bold focus:bg-white focus:border-blue-500 outline-none" required>
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-slate-500 uppercase">No. WhatsApp</label>
                        <input type="text" name="phone" id="form-phone" class="w-full px-4 py-3 border border-slate-200 bg-slate-50 rounded-xl text-sm font-bold focus:bg-white focus:border-blue-500 outline-none" placeholder="08...">
                    </div>
                </div>

                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase">Password</label>
                    <input type="password" name="password" id="form-pass" class="w-full px-4 py-3 border border-slate-200 bg-slate-50 rounded-xl text-sm font-bold focus:bg-white focus:border-blue-500 outline-none" placeholder="Kosongi jika edit user (tidak ubah pass)">
                </div>
            </div>

            <div class="space-y-3 pt-2">
                <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-1">
                    <?= $targetRole == 'admin' ? 'Detail Event' : 'Detail Klub' ?>
                </h4>
                
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase">Nama Lengkap / Nama Klub</label>
                    <input type="text" name="nama_lengkap" id="form-nama" class="w-full px-4 py-3 border border-slate-200 bg-slate-50 rounded-xl text-sm font-bold focus:bg-white focus:border-blue-500 outline-none" required>
                </div>

                <?php if($targetRole == 'admin'): ?>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] font-bold text-slate-500 uppercase">Tipe Event</label>
                            <select name="event_type" id="form-mode" class="w-full px-4 py-3 border border-slate-200 bg-slate-50 rounded-xl text-xs font-bold uppercase outline-none">
                                <option value="Langsung Final">Timed Final</option>
                                <option value="Babak Penyisihan">Heats & Finals</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-slate-500 uppercase">Tanggal Event</label>
                            <input type="date" name="event_date" id="form-date" class="w-full px-4 py-3 border border-slate-200 bg-slate-50 rounded-xl text-sm font-bold focus:bg-white focus:border-blue-500 outline-none">
                        </div>
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-slate-500 uppercase">Lokasi (Kota)</label>
                        <input type="text" name="location" id="form-location" class="w-full px-4 py-3 border border-slate-200 bg-slate-50 rounded-xl text-sm font-bold focus:bg-white focus:border-blue-500 outline-none">
                    </div>
                <?php else: ?>
                    <div>
                        <label class="text-[10px] font-bold text-slate-500 uppercase">Kota Asal Klub</label>
                        <input type="text" name="city" id="form-city" class="w-full px-4 py-3 border border-slate-200 bg-slate-50 rounded-xl text-sm font-bold focus:bg-white focus:border-blue-500 outline-none" placeholder="Cth: Surabaya">
                    </div>
                <?php endif; ?>
            </div>

            <button type="submit" class="w-full bg-slate-900 hover:bg-blue-600 text-white font-black py-4 rounded-xl shadow-lg transition uppercase tracking-widest text-xs mt-4">
                Simpan Data
            </button>
        </form>
    </div>
</div>

<script>
const modal = document.getElementById('modal-admin');

// NOTIFIKASI TOAST SEDERHANA
window.onload = function() {
    const urlParams = new URLSearchParams(window.location.search);
    const msg = urlParams.get('msg');
    if(msg === 'saved') alert('Berhasil menyimpan data!');
    if(msg === 'deleted') alert('Data berhasil dihapus.');
    if(msg === 'status_updated') alert('Status akun diperbarui.');
}

function openModal() {
    document.getElementById('modal-title').innerText = "Tambah <?= strtoupper($targetRole) ?> Baru";
    document.getElementById('form-id').value = "";
    document.getElementById('form-nama').value = "";
    document.getElementById('form-email').value = "";
    document.getElementById('form-phone').value = "";
    document.getElementById('form-pass').required = true;
    
    // Reset optional fields
    if(document.getElementById('form-location')) document.getElementById('form-location').value = "";
    if(document.getElementById('form-city')) document.getElementById('form-city').value = "";
    if(document.getElementById('form-date')) document.getElementById('form-date').value = ""; 
    
    modal.classList.remove('hidden');
}

function editAdmin(data) {
    document.getElementById('modal-title').innerText = "Edit <?= strtoupper($targetRole) ?>";
    document.getElementById('form-id').value = data.id;
    document.getElementById('form-nama').value = data.nama_lengkap;
    document.getElementById('form-email').value = data.email;
    document.getElementById('form-phone').value = data.phone || '';
    
    // Isi data spesifik
    if(document.getElementById('form-mode')) document.getElementById('form-mode').value = data.event_type || 'Langsung Final';
    if(document.getElementById('form-location')) document.getElementById('form-location').value = data.event_location || '';
    if(document.getElementById('form-date')) document.getElementById('form-date').value = data.event_date_start || ''; 
    if(document.getElementById('form-city')) document.getElementById('form-city').value = data.city || '';

    document.getElementById('form-pass').required = false; 
    modal.classList.remove('hidden');
}

function closeModal() { 
    modal.classList.add('hidden'); 
}
</script>