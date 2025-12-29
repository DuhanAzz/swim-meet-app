<?php
// src/pages/registrant/checkout.php
session_start();
require_once __DIR__ . '/../../config/database.php';

// 1. CEK LOGIN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];
$adminId = $_GET['event_id'] ?? 0; // Ini adalah ID Admin dari tabel users

// 2. AMBIL DATA EVENT DARI TABEL 'events' BERDASARKAN user_id (ID Admin)
$stmtEvt = $pdo->prepare("SELECT * FROM events WHERE user_id = ? LIMIT 1");
$stmtEvt->execute([$adminId]);
$eventData = $stmtEvt->fetch();

if (!$eventData) {
    echo "<script>alert('Event tidak ditemukan.'); window.history.back();</script>"; exit;
}

$namaEvent = $eventData['nama_event'] ?? "Event";

// 3. AMBIL STATUS PEMBAYARAN SEBELUMNYA
$paymentStatus = 'Unpaid';
$adminFile = null; 
$proofFile = null; 
$paymentId = null;

$stmtPay = $pdo->prepare("SELECT * FROM payments WHERE user_id = ? AND event_id = ? LIMIT 1");
$stmtPay->execute([$uid, $adminId]);
$pay = $stmtPay->fetch();

if ($pay) {
    $paymentId = $pay['id'];
    $paymentStatus = $pay['status'];
    $adminFile = $pay['admin_file_path'];
    $proofFile = $pay['file_path'];
}

// 4. HANDLE UPLOAD FORM (POST) - Mempertahankan Fitur Berkas Admin & Bukti Transfer
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = $_POST['total_amount_hidden'] ?? 0;
    
    $dirAdm = __DIR__ . "/../../../public/uploads/admin_files/";
    $dirPay = __DIR__ . "/../../../public/uploads/payments/";
    if (!is_dir($dirAdm)) mkdir($dirAdm, 0777, true);
    if (!is_dir($dirPay)) mkdir($dirPay, 0777, true);

    // A. Upload Berkas Admin (Fitur yang sempat hilang)
    $newAdminFile = $adminFile; 
    if (!empty($_FILES['berkas_admin']['name'])) {
        $ext = strtolower(pathinfo($_FILES['berkas_admin']['name'], PATHINFO_EXTENSION));
        $fName = "ADM_" . $adminId . "_" . $uid . "_" . time() . "." . $ext;
        if (move_uploaded_file($_FILES['berkas_admin']['tmp_name'], $dirAdm . $fName)) {
            $newAdminFile = $fName;
        }
    }

    // B. Upload Bukti Transfer
    $newProofFile = $proofFile;
    if (!empty($_FILES['bukti_transfer']['name'])) {
        $ext = strtolower(pathinfo($_FILES['bukti_transfer']['name'], PATHINFO_EXTENSION));
        $fName = "PAY_" . $adminId . "_" . $uid . "_" . time() . "." . $ext;
        if (move_uploaded_file($_FILES['bukti_transfer']['tmp_name'], $dirPay . $fName)) {
            $newProofFile = $fName;
        }
    }

    if ($newProofFile) {
        if ($pay) {
            // Update / Revisi
            $pdo->prepare("UPDATE payments SET amount=?, file_path=?, admin_file_path=?, status='Pending', updated_at=NOW() WHERE id=?")
                ->execute([$amount, $newProofFile, $newAdminFile, $paymentId]);
        } else {
            // Insert Baru
            $pdo->prepare("INSERT INTO payments (event_id, user_id, amount, file_path, admin_file_path, status, created_at) VALUES (?, ?, ?, ?, ?, 'Pending', NOW())")
                ->execute([$adminId, $uid, $amount, $newProofFile, $newAdminFile]);
        }
        header("Location: checkout.php?event_id=" . $adminId); exit;
    }
}

// 5. AMBIL ITEM BELANJA & HITUNG DENGAN LOGIKA PAKET (PRICING MODE)
$stmtEnt = $pdo->prepare("
    SELECT s.nama_atlet, s.jenis_kelamin, en.distance, en.stroke, en.age_group, ee.entry_time 
    FROM event_entries ee 
    JOIN swimmers s ON ee.swimmer_id = s.id 
    JOIN event_numbers en ON ee.category_id = en.id 
    WHERE ee.user_id = ? AND ee.event_id = ? 
    ORDER BY s.nama_atlet ASC
");
$stmtEnt->execute([$uid, $adminId]);
$entries = $stmtEnt->fetchAll();

$grouped = []; 
$totalTagihan = 0;
foreach($entries as $r) {
    if(!isset($grouped[$r['nama_atlet']])) {
        $grouped[$r['nama_atlet']] = ['gender' => $r['jenis_kelamin'], 'items' => [], 'subtotal' => 0];
    }
    $grouped[$r['nama_atlet']]['items'][] = $r;
}

// Logika Perhitungan Harga Berdasarkan Mode
foreach($grouped as $nama => &$data) {
    $count = count($data['items']);
    if ($eventData['pricing_mode'] === 'package') {
        $limit = (int)$eventData['package_limit'];
        $base = (float)$eventData['package_price'];
        $extra = (float)$eventData['extra_price'];
        
        $data['subtotal'] = ($count <= $limit) ? $base : $base + (($count - $limit) * $extra);
    } else {
        $data['subtotal'] = $count * (float)$eventData['price'];
    }
    $totalTagihan += $data['subtotal'];
}

include __DIR__ . '/../../../views/layout/topbar.php';
include __DIR__ . '/../../../views/layout/sidebar.php';
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">
    <div class="max-w-6xl mx-auto pb-20">
        
        <div class="flex flex-col md:flex-row justify-between items-end mb-8 gap-4">
            <div>
                <a href="register_event.php?event_id=<?= $adminId ?>" class="text-xs font-bold text-slate-400 hover:text-blue-600 mb-1 block">← Kembali ke Pendaftaran</a>
                <h1 class="text-3xl font-black text-slate-900 uppercase italic tracking-tight">Checkout & Pembayaran</h1>
                <p class="text-sm text-slate-500 mt-1 font-bold">Event: <strong class="text-blue-600"><?= htmlspecialchars($namaEvent) ?></strong></p>
            </div>
            
            <div class="flex items-center gap-2">
                <span class="px-5 py-2 rounded-xl text-xs font-black uppercase border tracking-wider shadow-sm
                    <?= match($paymentStatus) { 
                        'Pending' => 'bg-amber-100 text-amber-700 border-amber-200', 
                        'Paid'    => 'bg-emerald-100 text-emerald-700 border-emerald-200', 
                        'Rejected'=> 'bg-red-100 text-red-700 border-red-200', 
                        default   => 'bg-slate-200 text-slate-600 border-slate-300' 
                    } ?>">
                    <?= match($paymentStatus) { 'Pending'=>'Verifikasi', 'Paid'=>'Lunas', 'Rejected'=>'Ditolak', default=>'Belum Bayar' } ?>
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-6">
                <?php foreach($grouped as $nama=>$d): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="bg-slate-50 px-5 py-3 border-b border-slate-100 flex justify-between items-center">
                        <div class="flex items-center gap-3">
                            <span class="w-6 h-6 flex items-center justify-center rounded-full text-[10px] font-black text-white <?= ($d['gender'] == 'L') ? 'bg-blue-500' : 'bg-pink-500' ?>">
                                <?= ($d['gender'] == 'L') ? 'P' : 'W' ?>
                            </span>
                            <h3 class="font-bold text-slate-700 text-sm uppercase italic"><?= htmlspecialchars($nama) ?></h3>
                        </div>
                        <span class="font-mono font-bold text-blue-600 text-xs">Rp <?= number_format($d['subtotal'],0,',','.') ?></span>
                    </div>
                    <div class="p-0">
                        <table class="w-full text-xs text-left text-slate-600">
                            <?php foreach($d['items'] as $i): ?>
                                <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50 transition">
                                    <td class="py-3 pl-5 font-medium"><?= $i['distance'] ?>M <?= $i['stroke'] ?> (KU <?= $i['age_group'] ?>)</td>
                                    <td class="text-right pr-5 font-mono text-slate-500">Waktu: <?= $i['entry_time'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="lg:col-span-1">
                <div class="bg-white rounded-3xl shadow-xl border border-slate-200 p-6 sticky top-24">
                    <div class="mb-6 pb-6 border-b border-dashed border-slate-200">
                        <h2 class="font-bold text-slate-400 uppercase text-[10px] tracking-wider mb-2">Total Tagihan</h2>
                        <div class="text-4xl font-black text-slate-800 tracking-tighter italic">Rp <?= number_format($totalTagihan,0,',','.') ?></div>
                    </div>
                    
                    <div class="bg-gradient-to-br from-blue-600 to-blue-800 rounded-2xl p-5 mb-6 text-white relative overflow-hidden">
                        <p class="text-[10px] font-black uppercase tracking-widest mb-3 opacity-70">Transfer Bank</p>
                        <p class="font-black uppercase text-lg mb-1"><?= htmlspecialchars($eventData['bank_name']) ?></p>
                        <p class="font-mono text-xl font-bold tracking-wider mb-2 bg-white/10 p-2 rounded-lg"><?= htmlspecialchars($eventData['bank_account_number']) ?></p>
                        <p class="text-xs font-bold uppercase italic">a.n. <?= htmlspecialchars($eventData['bank_account_name']) ?></p>
                    </div>

                    <?php if($paymentStatus == 'Unpaid' || $paymentStatus == 'Rejected'): ?>
                        <form method="POST" enctype="multipart/form-data" class="space-y-5">
                            <input type="hidden" name="total_amount_hidden" value="<?= $totalTagihan ?>">
                            
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Berkas Admin (PDF/ZIP)</label>
                                <input type="file" name="berkas_admin" accept=".pdf,.zip,.rar" class="w-full text-xs border border-slate-300 rounded-xl p-2">
                                <?php if($adminFile): ?><p class="text-[9px] text-blue-500 mt-1 italic">File terupload: <?= $adminFile ?></p><?php endif; ?>
                            </div>

                            <div class="pt-4 border-t border-slate-100">
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Bukti Transfer <span class="text-red-500">*</span></label>
                                <input type="file" name="bukti_transfer" required accept="image/*,.pdf" class="w-full text-xs border border-slate-300 rounded-xl p-2">
                            </div>

                            <button type="submit" class="w-full bg-slate-900 hover:bg-blue-600 text-white font-black py-4 rounded-xl shadow-lg transition uppercase text-xs tracking-widest italic">
                                <?= $paymentStatus=='Rejected' ? 'Upload Ulang Bukti' : 'Konfirmasi Bayar' ?> ➜
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="bg-slate-50 border border-slate-200 rounded-2xl p-6 text-center">
                            <p class="text-xs font-black text-slate-500 uppercase italic italic">Status: <?= $paymentStatus ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>