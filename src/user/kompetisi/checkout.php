<?php
// FILE: src/user/kompetisi/checkout.php
session_start();
require_once __DIR__ . '/../../config/database.php';

// 1. CEK LOGIN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];
$targetEventId = (int)($_GET['event_id'] ?? 0); 

// 2. AMBIL DATA EVENT
$stmtEvt = $pdo->prepare("SELECT * FROM events WHERE id = ? LIMIT 1");
$stmtEvt->execute([$targetEventId]);
$eventData = $stmtEvt->fetch(PDO::FETCH_ASSOC);

if (!$eventData) {
    echo "<script>alert('Event tidak ditemukan.'); window.history.back();</script>"; exit;
}

$namaEvent = $eventData['event_name'] ?? "Event";

// 3. AMBIL STATUS PEMBAYARAN
$paymentStatus = 'Unpaid';
$adminFile = null; 
$proofFile = null; 
$paymentId = null;

$stmtPay = $pdo->prepare("SELECT * FROM payments WHERE user_id = ? AND event_id = ? ORDER BY created_at DESC LIMIT 1");
$stmtPay->execute([$uid, $targetEventId]);
$pay = $stmtPay->fetch(PDO::FETCH_ASSOC);

if ($pay) {
    $paymentId = $pay['id'];
    $paymentStatus = $pay['status']; 
    $adminFile = $pay['admin_file_path'] ?? null; 
    $proofFile = $pay['file_path'] ?? null; 
}

// 4. HITUNG TOTAL TAGIHAN
$stmtSum = $pdo->prepare("
    SELECT SUM(en.price) 
    FROM event_entries ee 
    JOIN event_numbers en ON ee.category_id = en.id 
    WHERE ee.user_id = ? AND ee.event_id = ?
");
$stmtSum->execute([$uid, $targetEventId]);
$totalTagihan = $stmtSum->fetchColumn() ?: 0;

// 5. AMBIL RINCIAN ATLET & NOMOR LOMBA
$stmtDetail = $pdo->prepare("
    SELECT s.nama_atlet, en.distance, en.stroke, en.price, ee.entry_time
    FROM event_entries ee
    JOIN swimmers s ON ee.swimmer_id = s.id
    JOIN event_numbers en ON ee.category_id = en.id
    WHERE ee.user_id = ? AND ee.event_id = ?
    ORDER BY s.nama_atlet ASC
");
$stmtDetail->execute([$uid, $targetEventId]);
$details = $stmtDetail->fetchAll(PDO::FETCH_ASSOC);

// 6. HANDLE UPLOAD BUKTI BAYAR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['bukti_transfer'])) {
    $uploadDir = __DIR__ . '/../../../public/uploads/payments/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $fileExt = strtolower(pathinfo($_FILES['bukti_transfer']['name'], PATHINFO_EXTENSION));
    $fileName = 'PAY_' . $targetEventId . '_' . $uid . '_' . time() . '.' . $fileExt;
    $targetFile = $uploadDir . $fileName;

    // Filter file aman
    if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'pdf'])) {
        if (move_uploaded_file($_FILES['bukti_transfer']['tmp_name'], $targetFile)) {
            if ($paymentId) {
                $stmtUp = $pdo->prepare("UPDATE payments SET file_path = ?, status = 'Pending', created_at = NOW() WHERE id = ?");
                $stmtUp->execute([$fileName, $paymentId]);
            } else {
                $stmtIns = $pdo->prepare("INSERT INTO payments (user_id, event_id, amount, file_path, status, created_at) VALUES (?, ?, ?, ?, 'Pending', NOW())");
                $stmtIns->execute([$uid, $targetEventId, $totalTagihan, $fileName]);
            }
            echo "<script>alert('Bukti transfer berhasil diunggah! Menunggu verifikasi admin.'); window.location.href='checkout.php?event_id=$targetEventId';</script>";
            exit;
        }
    } else {
        echo "<script>alert('Gagal! Format file hanya boleh JPG, PNG, atau PDF.'); window.history.back();</script>"; exit;
    }
}

include __DIR__ . '/../../../views/layout/topbar.php';
include __DIR__ . '/../../../views/layout/sidebar.php';
?>

<div class="p-4 sm:ml-64 pt-20 bg-slate-50 min-h-screen">
    <div class="max-w-4xl mx-auto">
        <div class="flex flex-col md:flex-row gap-6">
            
            <div class="flex-1 space-y-4">
                <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200">
                    <h2 class="text-xl font-black text-slate-800 uppercase italic mb-1">Ringkasan Pendaftaran</h2>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-6"><?= htmlspecialchars($namaEvent) ?></p>

                    <div class="space-y-3 max-h-[500px] overflow-y-auto pr-2 custom-scrollbar">
                        <?php if(empty($details)): ?>
                            <p class="text-center py-10 text-slate-400 text-xs italic font-bold">Belum ada atlet yang didaftarkan.</p>
                        <?php else: ?>
                            <?php foreach($details as $d): ?>
                            <div class="flex justify-between items-center p-4 bg-slate-50 rounded-2xl border border-slate-100">
                                <div>
                                    <p class="text-[10px] font-black text-blue-600 uppercase mb-0.5"><?= htmlspecialchars($d['nama_atlet'] ?? '') ?></p>
                                    <p class="text-xs font-bold text-slate-700 uppercase italic"><?= $d['distance'] ?>m <?= $d['stroke'] ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs font-black text-slate-800">Rp <?= number_format($d['price'], 0, ',', '.') ?></p>
                                    <p class="text-[9px] font-bold text-slate-400">Time: <?= $d['entry_time'] ?: '-' ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="mt-6 pt-6 border-t-2 border-dashed border-slate-200 flex justify-between items-center">
                        <p class="text-sm font-black text-slate-800 uppercase italic">Total Tagihan</p>
                        <p class="text-3xl font-black text-blue-600">Rp <?= number_format($totalTagihan, 0, ',', '.') ?></p>
                    </div>
                </div>
            </div>

            <div class="w-full md:w-80 space-y-4">
                <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200">
                    <h3 class="text-sm font-black text-slate-800 uppercase italic mb-4">Status Pembayaran</h3>
                    
                    <?php 
                        $statusColors = [
                            'Unpaid'   => 'bg-slate-100 text-slate-600',
                            'Pending'  => 'bg-amber-100 text-amber-700',
                            'Paid'     => 'bg-emerald-100 text-emerald-700',
                            'completed'=> 'bg-emerald-100 text-emerald-700',
                            'Rejected' => 'bg-red-100 text-red-700'
                        ];
                        $c = $statusColors[$paymentStatus] ?? 'bg-slate-100 text-slate-600';
                    ?>
                    <div class="w-full <?= $c ?> py-3 rounded-xl text-center font-black text-xs uppercase tracking-widest mb-6">
                        <?= $paymentStatus ?>
                    </div>

                    <?php if ($paymentStatus === 'Unpaid' || $paymentStatus === 'Rejected'): ?>
                        <form method="POST" enctype="multipart/form-data" class="space-y-4">
                            <div class="bg-blue-50 p-4 rounded-2xl border border-blue-100">
                                <p class="text-[10px] font-bold text-blue-800 uppercase mb-2">Instruksi Pembayaran</p>
                                <p class="text-[10px] text-blue-600 leading-relaxed font-medium italic">Silakan transfer sesuai total tagihan ke rekening panitia yang tertera pada brosur event.</p>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase italic">Upload Bukti Transfer <span class="text-red-500">*</span></label>
                                <input type="file" name="bukti_transfer" required accept="image/jpeg,image/png,application/pdf" class="w-full text-xs font-bold text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-slate-800 file:text-white hover:file:bg-slate-700 border border-slate-200 rounded-xl p-2 bg-slate-50">
                            </div>

                            <button type="submit" class="w-full bg-blue-600 hover:bg-slate-900 text-white font-black py-4 rounded-2xl shadow-lg shadow-blue-200 transition-all uppercase text-xs tracking-widest italic active:scale-95">
                                <?= $paymentStatus == 'Rejected' ? 'Upload Ulang Bukti' : 'Konfirmasi Bayar' ?> ➜
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="text-center space-y-3">
                            <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto text-2xl">
                                <?= ($paymentStatus == 'Pending') ? '⏳' : '✅' ?>
                            </div>
                            <p class="text-xs font-bold text-slate-500 leading-relaxed px-4">
                                <?= ($paymentStatus == 'Pending') ? 'Bukti transfer Anda sedang diverifikasi oleh panitia. Mohon tunggu.' : 'Pembayaran lunas! Atlet Anda sudah resmi terdaftar.' ?>
                            </p>
                            <?php if($proofFile): ?>
                                <a href="../../../public/uploads/payments/<?= htmlspecialchars($proofFile) ?>" target="_blank" class="inline-block mt-2 text-[10px] font-bold text-blue-600 underline uppercase italic hover:text-slate-900">Lihat Bukti Saya</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <a href="registration.php?event_id=<?= $targetEventId ?>" class="block w-full py-4 bg-white border border-slate-200 rounded-2xl text-center text-xs font-black text-slate-400 uppercase italic hover:bg-slate-50 hover:text-slate-600 transition-all">
                    &larr; Kembali ke Matrix
                </a>
            </div>

        </div>
    </div>
</div>

<style>
/* Custom Scrollbar untuk kotak tagihan */
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 20px; }
</style>