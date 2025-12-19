<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// CEK LOGIN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];
$eventId = $_GET['event_id'] ?? 0;

// AMBIL EVENT & DATA BANK ADMIN
$stmtEvt = $pdo->prepare("SELECT e.*, u.bank_name, u.bank_account_number, u.bank_account_name FROM events e JOIN users u ON e.user_id = u.id WHERE e.id = ?");
$stmtEvt->execute([$eventId]);
$eventData = $stmtEvt->fetch();

$namaEvent = $eventData['nama_event'] ?? "Event Tidak Ditemukan";
$hargaPerNomor = ($eventData['price'] ?? 0) > 0 ? $eventData['price'] : 50000;
$bankInfo = [
    'bank' => $eventData['bank_name'] ?? 'HUBUNGI PANITIA', 
    'rek' => $eventData['bank_account_number'] ?? '-', 
    'an' => $eventData['bank_account_name'] ?? '-'
];

// AMBIL STATUS PEMBAYARAN
$paymentStatus = 'Unpaid';
$adminFile = null; $proofFile = null; $paymentId = null;

$stmtPay = $pdo->prepare("SELECT * FROM payments WHERE user_id = ? AND event_id = ? LIMIT 1");
$stmtPay->execute([$uid, $eventId]);
$pay = $stmtPay->fetch();

if ($pay) {
    $paymentId = $pay['id'];
    $paymentStatus = $pay['status'];
    $adminFile = $pay['admin_file_path'];
    $proofFile = $pay['file_path'];
}

// HANDLE UPLOAD (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = $_POST['total_amount_hidden'] ?? 0;
    $dirAdm = __DIR__ . "/../../../public/uploads/admin_files/";
    $dirPay = __DIR__ . "/../../../public/uploads/payments/";
    if (!is_dir($dirAdm)) mkdir($dirAdm, 0777, true);
    if (!is_dir($dirPay)) mkdir($dirPay, 0777, true);

    // Upload Admin File
    $newAdminFile = $adminFile;
    if (!empty($_FILES['berkas_admin']['name'])) {
        $fName = "ADM_" . $eventId . "_" . $uid . "_" . time() . "." . strtolower(pathinfo($_FILES['berkas_admin']['name'], PATHINFO_EXTENSION));
        if (move_uploaded_file($_FILES['berkas_admin']['tmp_name'], $dirAdm . $fName)) $newAdminFile = $fName;
    }

    // Upload Proof
    $newProofFile = $proofFile;
    if (!empty($_FILES['bukti_transfer']['name'])) {
        $fName = "PAY_" . $eventId . "_" . $uid . "_" . time() . "." . strtolower(pathinfo($_FILES['bukti_transfer']['name'], PATHINFO_EXTENSION));
        if (move_uploaded_file($_FILES['bukti_transfer']['tmp_name'], $dirPay . $fName)) $newProofFile = $fName;
    }

    if ($newProofFile) {
        if ($pay) {
            $pdo->prepare("UPDATE payments SET amount=?, file_path=?, admin_file_path=?, status='Pending', updated_at=NOW() WHERE id=?")->execute([$amount, $newProofFile, $newAdminFile, $paymentId]);
        } else {
            $pdo->prepare("INSERT INTO payments (event_id, user_id, amount, file_path, admin_file_path, status, created_at) VALUES (?, ?, ?, ?, ?, 'Pending', NOW())")->execute([$eventId, $uid, $amount, $newProofFile, $newAdminFile]);
        }
        header("Location: checkout.php?event_id=" . $eventId); exit;
    }
}

// AMBIL ENTRIES (TAGIHAN)
$stmtEnt = $pdo->prepare("SELECT s.nama_atlet, s.jenis_kelamin, en.distance, en.stroke, en.age_group, ee.entry_time FROM event_entries ee JOIN swimmers s ON ee.swimmer_id = s.id JOIN event_numbers en ON ee.category_id = en.id WHERE ee.user_id = ? AND ee.event_id = ? ORDER BY s.nama_atlet ASC");
$stmtEnt->execute([$uid, $eventId]);
$entries = $stmtEnt->fetchAll();

$grouped = []; $totalTagihan = 0;
foreach($entries as $r) {
    if(!isset($grouped[$r['nama_atlet']])) $grouped[$r['nama_atlet']] = ['gender'=>$r['jenis_kelamin'], 'items'=>[], 'subtotal'=>0];
    $grouped[$r['nama_atlet']]['items'][] = $r;
    $grouped[$r['nama_atlet']]['subtotal'] += $hargaPerNomor;
    $totalTagihan += $hargaPerNomor;
}

include __DIR__ . '/../../../views/layout/topbar.php';
include __DIR__ . '/../../../views/layout/sidebar.php';
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    <div class="max-w-6xl mx-auto">
        <div class="flex flex-col md:flex-row justify-between items-end mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tight">Checkout</h1>
                <p class="text-sm text-slate-500 mt-1">Review pendaftaran untuk <strong><?= htmlspecialchars($namaEvent) ?></strong></p>
            </div>
            <div class="flex items-center gap-2">
                <?php if($paymentStatus == 'Unpaid' || $paymentStatus == 'Rejected'): ?>
                    <a href="register_event.php?event_id=<?= $eventId ?>" class="bg-white border border-slate-300 text-slate-700 px-4 py-2 rounded-xl text-xs font-bold hover:bg-slate-50 transition shadow-sm">✏️ Edit Pendaftaran</a>
                <?php endif; ?>
                <span class="px-4 py-2 rounded-xl text-xs font-black uppercase border 
                    <?= match($paymentStatus) { 'Pending'=>'bg-amber-100 text-amber-700 border-amber-200', 'Paid'=>'bg-emerald-100 text-emerald-700 border-emerald-200', 'Rejected'=>'bg-red-100 text-red-700 border-red-200', default=>'bg-slate-200 text-slate-600 border-slate-300' } ?>">
                    <?= match($paymentStatus) { 'Pending'=>'Menunggu Verifikasi', 'Paid'=>'Lunas', 'Rejected'=>'Ditolak', default=>'Belum Bayar' } ?>
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-6">
                <?php if(empty($grouped)): ?>
                    <div class="p-12 text-center border-dashed border-2 border-slate-200 rounded-3xl"><p class="font-bold text-slate-400">Keranjang Kosong</p></div>
                <?php else: foreach($grouped as $nama=>$d): ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="bg-slate-50 px-5 py-3 border-b border-slate-100 flex justify-between items-center">
                            <h3 class="font-bold text-slate-700 text-xs uppercase">👤 <?= htmlspecialchars($nama) ?></h3>
                            <span class="font-mono font-bold text-slate-600 text-xs">Rp <?= number_format($d['subtotal'],0,',','.') ?></span>
                        </div>
                        <div class="p-4"><table class="w-full text-xs text-left text-slate-600">
                            <?php foreach($d['items'] as $i): ?>
                                <tr class="border-b border-slate-50 last:border-0"><td class="py-1"><?= $i['distance'] ?>m <?= ucfirst($i['stroke']) ?></td><td class="text-right"><?= $i['entry_time'] ?></td></tr>
                            <?php endforeach; ?>
                        </table></div>
                    </div>
                <?php endforeach; endif; ?>
            </div>

            <div class="lg:col-span-1">
                <div class="bg-white rounded-3xl shadow-xl border border-slate-200 p-6 sticky top-24">
                    <div class="mb-6 pb-6 border-b border-slate-100">
                        <h2 class="font-bold text-slate-400 uppercase text-xs tracking-wider mb-2">Total Tagihan</h2>
                        <div class="text-4xl font-black text-slate-800">Rp <?= number_format($totalTagihan,0,',','.') ?></div>
                    </div>
                    
                    <div class="bg-blue-50 rounded-xl p-4 mb-6 border border-blue-100">
                        <p class="text-[10px] font-bold text-blue-400 uppercase">Transfer Bank</p>
                        <p class="font-bold text-blue-800 uppercase mb-1"><?= htmlspecialchars($bankInfo['bank']) ?></p>
                        <p class="font-mono text-xl font-black text-slate-700 tracking-wider"><?= htmlspecialchars($bankInfo['rek']) ?></p>
                        <p class="text-xs text-slate-500 mt-1">a.n. <?= htmlspecialchars($bankInfo['an']) ?></p>
                    </div>

                    <?php if($totalTagihan > 0): ?>
                        <?php if($paymentStatus == 'Unpaid' || $paymentStatus == 'Rejected'): ?>
                            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                                <input type="hidden" name="total_amount_hidden" value="<?= $totalTagihan ?>">
                                <div><label class="block text-xs font-bold text-slate-700 mb-1">1. Berkas Admin (PDF/ZIP)</label><input type="file" name="berkas_admin" accept=".pdf,.zip,.rar" class="w-full text-xs file:mr-2 file:py-2 file:px-3 file:rounded-lg file:bg-slate-100 file:text-slate-700 border border-slate-300 rounded-lg"></div>
                                <div class="pt-2 border-t border-slate-100"><label class="block text-xs font-bold text-slate-700 mb-1">2. Bukti Transfer (Gambar)</label><input type="file" name="bukti_transfer" required accept="image/*,.pdf" class="w-full text-xs file:mr-2 file:py-2 file:px-3 file:rounded-lg file:bg-blue-50 file:text-blue-700 border border-slate-300 rounded-lg"></div>
                                <button type="submit" class="w-full bg-slate-900 hover:bg-black text-white font-bold py-4 rounded-xl shadow-lg mt-4">🚀 <?= $paymentStatus=='Rejected'?'Upload Ulang':'Konfirmasi Bayar' ?></button>
                            </form>
                        <?php elseif($paymentStatus == 'Pending'): ?>
                            <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 text-center"><p class="text-2xl mb-2">⏳</p><h3 class="font-bold text-amber-800 text-xs uppercase">Sedang Diverifikasi Admin</h3></div>
                        <?php elseif($paymentStatus == 'Paid'): ?>
                            <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-6 text-center"><p class="text-2xl mb-2">🎉</p><h3 class="font-bold text-emerald-800 text-xs uppercase">Pembayaran Lunas</h3></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>