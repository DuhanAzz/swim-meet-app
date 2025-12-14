<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];
$search = $_GET['q'] ?? '';

// --- 1. HANDLE UPLOAD LOGO & SPONSOR ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $targetDir = "../../../public/uploads/logos/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

    // Upload Logo Kiri/Kanan
    if (isset($_POST['upload_main_logo'])) {
        $type = $_POST['logo_type'];
        $ext = pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION);
        $fileName = "main_" . $uid . "_" . $type . "_" . time() . "." . $ext;
        if(move_uploaded_file($_FILES['logo_file']['tmp_name'], $targetDir . $fileName)) {
            $pdo->prepare("UPDATE users SET $type = ? WHERE id = ?")->execute(["uploads/logos/" . $fileName, $uid]);
        }
    }

    // Upload Sponsor Baru (Bisa Banyak)
    if (isset($_POST['upload_sponsor'])) {
        $ext = pathinfo($_FILES['sponsor_file']['name'], PATHINFO_EXTENSION);
        $fileName = "sp_" . $uid . "_" . time() . "." . $ext;
        if(move_uploaded_file($_FILES['sponsor_file']['tmp_name'], $targetDir . $fileName)) {
            $pdo->prepare("INSERT INTO event_sponsors (user_id, image_path) VALUES (?, ?)")->execute([$uid, "uploads/logos/" . $fileName]);
        }
    }

    // Hapus Sponsor
    if (isset($_POST['delete_sponsor'])) {
        $pdo->prepare("DELETE FROM event_sponsors WHERE id = ? AND user_id = ?")->execute([$_POST['sponsor_id'], $uid]);
    }
    
    header("Location: index.php"); exit;
}

// --- 2. AMBIL DATA ---
$user = $pdo->query("SELECT * FROM users WHERE id = $uid")->fetch();
$sponsors = $pdo->prepare("SELECT * FROM event_sponsors WHERE user_id = ?");
$sponsors->execute([$uid]);
$sponsorList = $sponsors->fetchAll();

$sql = "SELECT ec.*, (SELECT COUNT(*) FROM race_heats rh WHERE rh.category_id = ec.id) as total_heats 
        FROM event_categories ec WHERE ec.user_id = ? ORDER BY ec.event_no ASC";
$stmt = $pdo->prepare($sql); $stmt->execute([$uid]);
$events = $stmt->fetchAll();

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">
    
    <div class="mb-10 flex flex-col xl:flex-row justify-between items-center gap-6">
        <div>
            <h1 class="text-3xl font-black uppercase italic text-slate-900 leading-none tracking-tighter">Start List Manager</h1>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-2">Penyusunan Lintasan & Branding Buku Acara</p>
        </div>
        <a href="print_full_book.php" target="_blank" class="bg-blue-600 text-white font-black px-10 py-4 rounded-2xl text-[10px] uppercase shadow-xl shadow-blue-100 hover:bg-blue-700 transition flex items-center gap-3">
            <span>📚</span> Cetak Buku Acara Lengkap
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-10">
        
        <div class="bg-white p-8 rounded-[2.5rem] border border-slate-200 shadow-sm">
            <h3 class="font-black text-xs uppercase tracking-widest text-slate-400 mb-6 italic">Main Logos (Header)</h3>
            <div class="grid grid-cols-2 gap-6">
                <div onclick="document.getElementById('in-l').click()" class="relative aspect-square bg-slate-50 rounded-3xl border-2 border-dashed border-slate-200 flex items-center justify-center cursor-pointer hover:border-blue-400 transition overflow-hidden group">
                    <?php if($user['logo_left']): ?><img src="../../../public/<?= $user['logo_left'] ?>" class="w-full h-full object-contain p-4"><?php else: ?><span class="text-2xl opacity-20">🖼️</span><?php endif; ?>
                    <div class="absolute inset-0 bg-blue-600/80 text-white opacity-0 group-hover:opacity-100 flex items-center justify-center text-[10px] font-black uppercase">Ganti Kiri</div>
                </div>
                <div onclick="document.getElementById('in-r').click()" class="relative aspect-square bg-slate-50 rounded-3xl border-2 border-dashed border-slate-200 flex items-center justify-center cursor-pointer hover:border-blue-400 transition overflow-hidden group">
                    <?php if($user['logo_right']): ?><img src="../../../public/<?= $user['logo_right'] ?>" class="w-full h-full object-contain p-4"><?php else: ?><span class="text-2xl opacity-20">🖼️</span><?php endif; ?>
                    <div class="absolute inset-0 bg-blue-600/80 text-white opacity-0 group-hover:opacity-100 flex items-center justify-center text-[10px] font-black uppercase">Ganti Kanan</div>
                </div>
            </div>
        </div>

        <div class="bg-white p-8 rounded-[2.5rem] border border-slate-200 shadow-sm">
            <div class="flex justify-between items-center mb-6">
                <h3 class="font-black text-xs uppercase tracking-widest text-slate-400 italic">Sponsors (Footer)</h3>
                <button onclick="document.getElementById('in-sp').click()" class="bg-slate-900 text-white px-5 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest hover:bg-emerald-600 transition">Add Sponsor</button>
            </div>
            <div class="flex flex-wrap gap-4 min-h-[100px] items-center p-4 bg-slate-50 rounded-3xl border border-slate-100">
                <?php if(empty($sponsorList)): ?>
                    <p class="text-[10px] font-bold text-slate-300 uppercase mx-auto">Belum ada sponsor</p>
                <?php else: foreach($sponsorList as $sp): ?>
                    <div class="relative group">
                        <img src="../../../public/<?= $sp['image_path'] ?>" class="h-12 w-auto object-contain bg-white p-1 rounded-lg border">
                        <form method="POST" class="absolute -top-2 -right-2 opacity-0 group-hover:opacity-100 transition">
                            <input type="hidden" name="sponsor_id" value="<?= $sp['id'] ?>">
                            <button name="delete_sponsor" class="w-5 h-5 bg-red-500 text-white rounded-full text-[10px] flex items-center justify-center">✕</button>
                        </form>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <form id="f-l" method="POST" enctype="multipart/form-data" class="hidden"><input type="hidden" name="upload_main_logo" value="1"><input type="hidden" name="logo_type" value="logo_left"><input type="file" id="in-l" name="logo_file" onchange="document.getElementById('f-l').submit()"></form>
    <form id="f-r" method="POST" enctype="multipart/form-data" class="hidden"><input type="hidden" name="upload_main_logo" value="1"><input type="hidden" name="logo_type" value="logo_right"><input type="file" id="in-r" name="logo_file" onchange="document.getElementById('f-r').submit()"></form>
    <form id="f-sp" method="POST" enctype="multipart/form-data" class="hidden"><input type="hidden" name="upload_sponsor" value="1"><input type="file" id="in-sp" name="sponsor_file" onchange="document.getElementById('f-sp').submit()"></form>

    <div class="bg-white rounded-[2.5rem] border border-slate-200 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-900 text-white text-[10px] font-black uppercase tracking-[0.2em]">
                    <tr>
                        <th class="px-8 py-5 text-center w-20">#</th>
                        <th class="px-8 py-5">Event Description</th>
                        <th class="px-8 py-5 text-center">Status</th>
                        <th class="px-8 py-5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach($events as $e): $has = $e['total_heats'] > 0; ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-8 py-5 text-center font-black text-xl text-slate-400 italic">#<?= $e['event_no'] ?></td>
                            <td class="px-8 py-5">
                                <div class="font-black text-slate-800 uppercase italic tracking-tighter"><?= $e['distance'] ?>m <?= $e['style'] ?> (<?= $e['gender'] == 'Male' ? 'Putra' : 'Putri' ?>)</div>
                                <div class="text-[9px] font-bold text-slate-400 mt-1 uppercase"><?= $e['age_group'] ?> • <?= date('d M Y', strtotime($e['event_date'])) ?></div>
                            </td>
                            <td class="px-8 py-5 text-center">
                                <span class="px-4 py-1.5 rounded-full text-[9px] font-black uppercase <?= $has ? 'bg-emerald-100 text-emerald-700' : 'bg-orange-100 text-orange-700 animate-pulse' ?>">
                                    <?= $has ? 'Ready to Race' : 'Needs Seeding' ?>
                                </span>
                            </td>
                            <td class="px-8 py-5 text-right">
                                <div class="flex justify-end gap-2">
                                    <form action="logic.php" method="POST"><input type="hidden" name="category_id" value="<?= $e['id'] ?>"><button name="generate_startlist" class="bg-slate-900 text-white font-black px-5 py-2.5 rounded-xl text-[9px] uppercase hover:bg-blue-600 transition">⚡ SEED</button></form>
                                    <?php if($has): ?>
                                        <a href="view_startlist.php?category_id=<?= $e['id'] ?>" class="bg-white border-2 border-slate-100 text-slate-400 font-black px-5 py-2 rounded-xl text-[9px] uppercase hover:bg-slate-900 hover:text-white transition">👁️ View</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>