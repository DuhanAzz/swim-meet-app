<?php
// FILE: src/master/users/index.php
session_start();
require_once __DIR__ . '/../../config/database.php';

// 1. PROTEKSI HALAMAN (HANYA MASTER)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'master') {
    header("Location: ../../../public/login.php"); exit;
}

// 2. SETUP VARIABEL
$targetRole = $_GET['role'] ?? 'admin'; // 'admin' = EO Event, 'user' = Klub
$search     = $_GET['q'] ?? '';

// --- HELPER LOGGING ---
if (!function_exists('writeLog')) {
    function writeLog($pdo, $userId, $action, $targetId, $desc) {
        try {
            $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, action_type, target_id, description, ip_address) VALUES (?, ?, ?, ?, ?)");
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $stmt->execute([$userId, $action, $targetId, $desc, $ip]);
        } catch (Exception $e) { /* Silent Error */ }
    }
}

// ==================================================================================
// HANDLE ACTION: UBAH STATUS (APPROVE/SUSPEND)
// ==================================================================================
if (isset($_GET['action']) && isset($_GET['uid']) && isset($_GET['status'])) {
    $uid = $_GET['uid'];
    $newStatus = $_GET['status'];
    
    if (in_array($newStatus, ['active', 'pending', 'suspended'])) {
        if ($uid == $_SESSION['user_id']) {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = 'Tidak bisa memblokir akun sendiri!';
        } else {
            try {
                $pdo->prepare("UPDATE users SET account_status = ? WHERE id = ?")->execute([$newStatus, $uid]);
                writeLog($pdo, $_SESSION['user_id'], 'CHANGE_STATUS', $uid, "Ubah status user ID $uid menjadi $newStatus");
                
                $_SESSION['swal_type'] = 'success';
                $_SESSION['swal_msg'] = 'Status akun berhasil diperbarui';
            } catch (Exception $e) {
                $_SESSION['swal_type'] = 'error';
                $_SESSION['swal_msg'] = 'Gagal update: ' . $e->getMessage();
            }
        }
        header("Location: index.php?role=$targetRole"); exit;
    }
}

// ==================================================================================
// HANDLE ACTION: SIMPAN (TAMBAH / EDIT)
// ==================================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_user'])) {
    try {
        $pdo->beginTransaction();

        // 1. Ambil Data Form
        $userId   = $_POST['user_id'] ?? ''; 
        $role     = $_POST['role_type'];
        
        // Data Akun (Tabel Users)
        $namaAkun = trim($_POST['nama_lengkap']); 
        $email    = trim($_POST['email']);
        $phone    = trim($_POST['phone']); // AMBIL DATA PHONE DARI FORM
        $username = $email; 
        $pass     = $_POST['password'];

        // Data Detail (Tabel Clubs / Events)
        $namaEntitas = trim($_POST['nama_detail']); 

        if ($role == 'admin') {
            // Data Events
            $compSystem = $_POST['competition_system'] ?? 'Langsung Final';
            $location   = $_POST['event_location'] ?? null;
            $eventDate  = !empty($_POST['event_date_start']) ? $_POST['event_date_start'] : date('Y-m-d');
        } else {
            // Data Clubs
            $kota       = $_POST['kota'] ?? null;
        }

        // 3. PROSES UPDATE / INSERT
        if ($userId) {
            // === UPDATE ===
            
            // A. Update Users (SUDAH TERMASUK PHONE)
            if (!empty($pass)) {
                $pdo->prepare("UPDATE users SET nama_lengkap=?, email=?, phone=?, username=?, password=? WHERE id=?")
                    ->execute([$namaAkun, $email, $phone, $username, password_hash($pass, PASSWORD_DEFAULT), $userId]);
            } else {
                $pdo->prepare("UPDATE users SET nama_lengkap=?, email=?, phone=?, username=? WHERE id=?")
                    ->execute([$namaAkun, $email, $phone, $username, $userId]);
            }

            // B. Update Detail
            if ($role == 'admin') {
                $check = $pdo->prepare("SELECT id FROM events WHERE user_id = ?");
                $check->execute([$userId]);
                if ($check->rowCount() > 0) {
                    $pdo->prepare("UPDATE events SET event_name=?, competition_system=?, event_location=?, event_date_start=? WHERE user_id=?")
                        ->execute([$namaEntitas, $compSystem, $location, $eventDate, $userId]);
                } else {
                    $pdo->prepare("INSERT INTO events (user_id, event_name, competition_system, event_location, event_date_start, event_status, event_type, lane_count, pool_type) VALUES (?, ?, ?, ?, ?, 'Upcoming', 'Standard', 8, '50m')")
                        ->execute([$userId, $namaEntitas, $compSystem, $location, $eventDate]);
                }
            } elseif ($role == 'user') {
                $check = $pdo->prepare("SELECT id FROM clubs WHERE user_id = ?");
                $check->execute([$userId]);
                if ($check->rowCount() > 0) {
                    $pdo->prepare("UPDATE clubs SET nama_klub=?, kota=? WHERE user_id=?")
                        ->execute([$namaEntitas, $kota, $userId]);
                } else {
                    $pdo->prepare("INSERT INTO clubs (user_id, nama_klub, kota) VALUES (?, ?, ?)")
                        ->execute([$userId, $namaEntitas, $kota]);
                }
            }
            $msg = 'Data berhasil diperbarui!';

        } else {
            // === INSERT BARU ===
            $cekMail = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $cekMail->execute([$email]);
            if($cekMail->rowCount() > 0) throw new Exception("Email $email sudah terdaftar!");

            // Insert Users (SUDAH TERMASUK PHONE)
            $pdo->prepare("INSERT INTO users (nama_lengkap, email, phone, username, password, role, account_status) VALUES (?, ?, ?, ?, ?, ?, 'active')")
                ->execute([$namaAkun, $email, $phone, $username, password_hash($pass, PASSWORD_DEFAULT), $role]);
            $newUserId = $pdo->lastInsertId();

            if ($role == 'admin') {
                $pdo->prepare("INSERT INTO events (user_id, event_name, competition_system, event_location, event_date_start, event_status, event_type, lane_count, pool_type) VALUES (?, ?, ?, ?, ?, 'Upcoming', 'Standard', 8, '50m')")
                    ->execute([$newUserId, $namaEntitas, $compSystem, $location, $eventDate]);
            } elseif ($role == 'user') {
                $pdo->prepare("INSERT INTO clubs (user_id, nama_klub, kota) VALUES (?, ?, ?)")
                    ->execute([$newUserId, $namaEntitas, $kota]);
            }
            $msg = 'Data berhasil ditambahkan!';
        }

        $pdo->commit();
        $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = $msg;
        header("Location: index.php?role=" . $role); exit;

    } catch (Exception $e) { 
        $pdo->rollBack();
        $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = 'Error: ' . $e->getMessage();
        header("Location: index.php?role=" . $role); exit;
    }
}

// ==================================================================================
// HANDLE ACTION: HAPUS
// ==================================================================================
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    if ($id == $_SESSION['user_id']) {
        $_SESSION['swal_type'] = 'error';
        $_SESSION['swal_msg'] = 'Tidak bisa menghapus akun sendiri';
    } else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM events WHERE user_id = ?")->execute([$id]); 
            $pdo->prepare("DELETE FROM clubs WHERE user_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);        
            $pdo->commit();
            $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = 'Data berhasil dihapus permanen';
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = 'Gagal menghapus: ' . $e->getMessage();
        }
    }
    header("Location: index.php?role=" . $targetRole); exit;
}

// ==================================================================================
// QUERY GET DATA
// ==================================================================================
$params = ['role' => $targetRole];
$searchSql = "";
if (!empty($search)) {
    $searchSql = " AND (u.nama_lengkap LIKE :s OR u.email LIKE :s OR u.username LIKE :s) ";
    $params['s'] = "%$search%";
}

if ($targetRole == 'admin') {
    $sql = "SELECT u.*, 
                   e.competition_system, e.event_name, e.event_location, e.event_date_start, e.event_status 
            FROM users u 
            LEFT JOIN events e ON u.id = e.user_id 
            WHERE u.role = :role $searchSql ORDER BY u.created_at DESC";
} else {
    $sql = "SELECT u.*, 
                   c.nama_klub, c.kota,
                   (SELECT COUNT(*) FROM swimmers s WHERE s.user_id = u.id) as total_atlet 
            FROM users u 
            LEFT JOIN clubs c ON u.id = c.user_id 
            WHERE u.role = :role $searchSql ORDER BY u.created_at DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                Manajemen <?= $targetRole == 'admin' ? 'Event Organizer' : 'User Klub' ?>
            </h1>
            <p class="text-slate-500 text-xs font-medium mt-2">Total Data: <?= count($users) ?></p>
        </div>
        
        <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto">
            <form method="GET" class="relative">
                <input type="hidden" name="role" value="<?= $targetRole ?>">
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Cari..." 
                       class="pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold w-full md:w-64 focus:outline-none focus:border-blue-500 shadow-sm">
                <span class="absolute left-3 top-2.5 text-slate-400">🔍</span>
            </form>

            <div class="flex gap-1 bg-white p-1 rounded-xl shadow-sm border border-slate-200">
                <a href="index.php?role=user" class="px-4 py-2 rounded-lg text-[10px] font-black uppercase transition <?= $targetRole == 'user' ? 'bg-slate-900 text-white shadow-md' : 'text-slate-400 hover:bg-slate-50' ?>">Klub</a>
                <a href="index.php?role=admin" class="px-4 py-2 rounded-lg text-[10px] font-black uppercase transition <?= $targetRole == 'admin' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-400 hover:bg-slate-50' ?>">EO / Event</a>
            </div>
        </div>
    </div>

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
                                    <span class="text-[9px] italic text-slate-400"><?= htmlspecialchars($u['competition_system'] ?? '-') ?></span>
                                </div>
                            <?php else: ?>
                                <div class="space-y-1">
                                    <div class="text-xs font-black text-slate-700 uppercase">
                                        <?= htmlspecialchars($u['nama_klub'] ?? '-') ?>
                                    </div>
                                    <span class="bg-slate-100 px-1.5 py-0.5 rounded text-[9px] font-bold text-slate-500 border border-slate-200 inline-block">
                                        🏠 <?= htmlspecialchars($u['kota'] ?? '-') ?>
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
                                <button 
                                    type="button"
                                    data-user="<?= htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8') ?>"
                                    onclick="editAdmin(this)"
                                    class="w-8 h-8 flex items-center justify-center bg-white border border-slate-200 rounded-lg hover:border-blue-500 hover:text-blue-600 transition text-slate-400 shadow-sm">
                                    ✏️
                                </button>
                                
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
                <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-1">Info Login (Akun)</h4>
                
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase">Nama Pemilik Akun</label>
                    <input type="text" name="nama_lengkap" id="form-nama" class="w-full px-4 py-3 border border-slate-200 bg-slate-50 rounded-xl text-sm font-bold focus:bg-white focus:border-blue-500 outline-none" required placeholder="Nama Admin / Ketua Klub">
                </div>

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
                    <?= $targetRole == 'admin' ? 'Detail Event (Tabel Events)' : 'Detail Klub (Tabel Clubs)' ?>
                </h4>
                
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase">
                        <?= $targetRole == 'admin' ? 'Nama Event (Kejuaraan)' : 'Nama Klub Renang' ?>
                    </label>
                    <input type="text" name="nama_detail" id="form-nama-detail" class="w-full px-4 py-3 border border-slate-200 bg-slate-50 rounded-xl text-sm font-bold focus:bg-white focus:border-blue-500 outline-none" required placeholder="<?= $targetRole == 'admin' ? 'Contoh: O2SN 2026' : 'Contoh: Pari Sakti SC' ?>">
                </div>

                <?php if($targetRole == 'admin'): ?>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] font-bold text-slate-500 uppercase">Sistem Lomba</label>
                            <select name="competition_system" id="form-mode" class="w-full px-4 py-3 border border-slate-200 bg-slate-50 rounded-xl text-xs font-bold uppercase outline-none">
                                <option value="Langsung Final">Langsung Final</option>
                                <option value="Babak Penyisihan">Babak Penyisihan</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-slate-500 uppercase">Tanggal Mulai</label>
                            <input type="date" name="event_date_start" id="form-date" class="w-full px-4 py-3 border border-slate-200 bg-slate-50 rounded-xl text-sm font-bold focus:bg-white focus:border-blue-500 outline-none">
                        </div>
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-slate-500 uppercase">Lokasi (Venue)</label>
                        <input type="text" name="event_location" id="form-location" class="w-full px-4 py-3 border border-slate-200 bg-slate-50 rounded-xl text-sm font-bold focus:bg-white focus:border-blue-500 outline-none">
                    </div>
                <?php else: ?>
                    <div>
                        <label class="text-[10px] font-bold text-slate-500 uppercase">Kota Asal Klub</label>
                        <input type="text" name="kota" id="form-kota" class="w-full px-4 py-3 border border-slate-200 bg-slate-50 rounded-xl text-sm font-bold focus:bg-white focus:border-blue-500 outline-none" placeholder="Cth: Yogyakarta (Boleh Kosong)">
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

// 1. Reset Form saat Tambah Baru
function openModal() {
    document.getElementById('modal-title').innerText = "Tambah <?= strtoupper($targetRole) ?> Baru";
    document.getElementById('form-id').value = ""; // ID Kosong = Insert
    
    // Reset Data Dasar
    document.getElementById('form-nama').value = "";
    document.getElementById('form-email').value = "";
    document.getElementById('form-phone').value = "";
    document.getElementById('form-pass').required = true;
    document.getElementById('form-pass').value = "";
    
    // Reset Detail
    document.getElementById('form-nama-detail').value = "";
    
    if(document.getElementById('form-location')) document.getElementById('form-location').value = "";
    if(document.getElementById('form-date')) document.getElementById('form-date').value = ""; 
    if(document.getElementById('form-mode')) document.getElementById('form-mode').selectedIndex = 0;
    if(document.getElementById('form-kota')) document.getElementById('form-kota').value = "";
    
    modal.classList.remove('hidden');
}

// 2. Isi Form saat Edit (MAPPING DATA)
function editAdmin(buttonElement) {
    try {
        const jsonString = buttonElement.getAttribute('data-user');
        const data = JSON.parse(jsonString);

        document.getElementById('modal-title').innerText = "Edit <?= strtoupper($targetRole) ?>";
        
        // Isi Data Dasar Users
        document.getElementById('form-id').value = data.id; 
        document.getElementById('form-nama').value = data.nama_lengkap; // Nama User Akun
        document.getElementById('form-email').value = data.email;
        document.getElementById('form-phone').value = data.phone || '';
        document.getElementById('form-pass').required = false; 
        document.getElementById('form-pass').value = ""; 

        // --- MAPPING DETAIL ---
        // Prioritas ambil dari tabel detail. Jika kosong, baru ambil dari user.
        const detailName = data.nama_klub || data.event_name || data.nama_lengkap;
        document.getElementById('form-nama-detail').value = detailName;

        // --- ADMIN / EVENT FIELDS ---
        if(document.getElementById('form-mode')) {
            document.getElementById('form-mode').value = data.competition_system || 'Langsung Final';
        }
        if(document.getElementById('form-location')) {
            document.getElementById('form-location').value = data.event_location || '';
        }
        if(document.getElementById('form-date')) {
            document.getElementById('form-date').value = data.event_date_start || ''; 
        }
        
        // --- USER / CLUB FIELDS ---
        if(document.getElementById('form-kota')) {
            document.getElementById('form-kota').value = data.kota || '';
        }

        modal.classList.remove('hidden');
    } catch (e) {
        console.error("Gagal parse data user:", e);
        alert("Terjadi kesalahan saat mengambil data. Cek console.");
    }
}

function closeModal() { 
    modal.classList.add('hidden'); 
}
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    <?php if(isset($_SESSION['swal_type'])): ?>
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false, 
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        Toast.fire({
            icon: '<?= $_SESSION['swal_type'] ?>',
            title: '<?= $_SESSION['swal_msg'] ?>'
        });

        <?php unset($_SESSION['swal_type']); unset($_SESSION['swal_msg']); ?>
    <?php endif; ?>
</script>