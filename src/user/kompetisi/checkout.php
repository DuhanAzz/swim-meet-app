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

// 3. HITUNG TOTAL TAGIHAN (LIVE)
$stmtSum = $pdo->prepare("
    SELECT SUM(en.price) 
    FROM event_entries ee 
    JOIN event_numbers en ON ee.category_id = en.id 
    WHERE ee.user_id = ? AND ee.event_id = ?
");
$stmtSum->execute([$uid, $targetEventId]);
$totalTagihan = $stmtSum->fetchColumn() ?: 0;

// 4. AUTO-SYNC KE TABEL PAYMENTS (INI KUNCI PENYELESAIAN MASALAHNYA 🚀)
$paymentStatus = 'Unpaid';
$adminFile = null; 
$proofFile = null; 
$paymentId = null;

// Cek apakah sudah ada data pembayaran sebelumnya
$stmtPay = $pdo->prepare("SELECT * FROM payments WHERE user_id = ? AND event_id = ? ORDER BY id DESC LIMIT 1");
$stmtPay->execute([$uid, $targetEventId]);
$pay = $stmtPay->fetch(PDO::FETCH_ASSOC);

if ($pay) {
    $paymentId = $pay['id'];
    $paymentStatus = $pay['status']; 
    $adminFile = $pay['admin_file_path'] ?? null; 
    $proofFile = $pay['file_path'] ?? null; 

    // Jika status masih Unpaid atau Rejected, selalu UPDATE nominalnya sesuai perhitungan terbaru
    if ($paymentStatus === 'Unpaid' || $paymentStatus === 'Rejected') {
        if ($pay['amount'] != $totalTagihan) {
            $stmtUpdateAmount = $pdo->prepare("UPDATE payments SET amount = ? WHERE id = ?");
            $stmtUpdateAmount->execute([$totalTagihan, $paymentId]);
        }
    }
} else {
    // Jika belum ada data sama sekali dan tagihannya lebih dari 0, BUAT nota baru (Unpaid)
    if ($totalTagihan > 0) {
        $stmtInsPay = $pdo->prepare("INSERT INTO payments (user_id, event_id, amount, status, created_at) VALUES (?, ?, ?, 'Unpaid', NOW())");
        $stmtInsPay->execute([$uid, $targetEventId, $totalTagihan]);
        // Ambil ID yang baru dibuat
        $paymentId = $pdo->lastInsertId();
    }
}

// 5. AMBIL RINCIAN ATLET & NOMOR LOMBA (UNTUK TAMPILAN)
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
            // Karena kita sudah Auto-Sync di atas, pasti $paymentId sudah ada
            $stmtUp = $pdo->prepare("UPDATE payments SET file_path = ?, status = 'Pending', updated_at = NOW() WHERE id = ?");
            $stmtUp->execute([$fileName, $paymentId]);
            
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
                            <div class="flex justify-between items-center p-4 bg-slate-50 rounded-2xl border border-slate-100 hover:border-blue-200 transition">
                                <div>
                                    <p class="text-[10px] font-black text-blue-600 uppercase mb-0.5"><?= htmlspecialchars($d['nama_atlet'] ?? '') ?></p>
                                    <p class="text-xs font-bold text-slate-700 uppercase italic"><?= $d['distance'] ?>m <?= $d['stroke'] ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs font-black text-slate-800">Rp <?= number_format($d['price'], 0, ',', '.') ?></p>
                                    <p class="text-[9px] font-bold text-slate-400">Time: <span class="font-mono"><?= $d['entry_time'] ?: 'NT' ?></span></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="mt-6 pt-6 border-t-2 border-dashed border-slate-200 flex justify-between items-center">
                        <p class="text-sm font-black text-slate-800 uppercase italic">Total Tagihan</p>
                        <p class="text-3xl font-black text-blue-600 tracking-tighter">Rp <?= number_format($totalTagihan, 0, ',', '.') ?></p>
                    </div>
                </div>
            </div>

            <div class="w-full md:w-80 space-y-4">
                <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200 sticky top-24">
                    <h3 class="text-sm font-black text-slate-800 uppercase italic mb-4">Status Pembayaran</h3>
                    
                    <?php 
                        $statusColors = [
                            'Unpaid'   => 'bg-slate-100 text-slate-600 border border-slate-200',
                            'Pending'  => 'bg-amber-100 text-amber-700 border border-amber-200',
                            'Paid'     => 'bg-emerald-100 text-emerald-700 border border-emerald-200 shadow-sm',
                            'Rejected' => 'bg-red-100 text-red-700 border border-red-200'
                        ];
                        $c = $statusColors[$paymentStatus] ?? 'bg-slate-100 text-slate-600';
                    ?>
                    <div class="w-full <?= $c ?> py-3 rounded-xl text-center font-black text-xs uppercase tracking-widest mb-6">
                        <?= $paymentStatus ?>
                    </div>

                    <?php if ($paymentStatus === 'Unpaid' || $paymentStatus === 'Rejected'): ?>
                        <form method="POST" enctype="multipart/form-data" class="space-y-4">
                            <div class="bg-blue-50 p-4 rounded-2xl border border-blue-100">
                                <p class="text-[10px] font-bold text-blue-800 uppercase tracking-widest mb-2">Instruksi Pembayaran</p>
                                <p class="text-[10px] text-blue-600 leading-relaxed font-medium italic">Silakan transfer sesuai total tagihan ke rekening panitia yang tertera pada brosur event.</p>
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold text-slate-700 mb-1.5 uppercase tracking-widest">Upload Bukti Transfer <span class="text-red-500">*</span></label>
                                <input type="file" name="bukti_transfer" required accept="image/jpeg,image/png,application/pdf" class="w-full text-xs font-bold text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-[10px] file:uppercase file:tracking-widest file:font-black file:bg-slate-900 file:text-white hover:file:bg-blue-600 file:transition border border-slate-200 rounded-xl p-1 bg-slate-50 cursor-pointer outline-none focus:border-blue-500 transition">
                            </div>

                            <button type="submit" class="<?= $paymentStatus == 'Rejected' ? 'bg-red-600 shadow-red-200' : 'bg-blue-600 shadow-blue-200' ?> hover:bg-slate-900 text-white font-black py-4 w-full rounded-2xl shadow-lg transition-all uppercase text-[10px] tracking-widest active:scale-95 outline-none">
                                <?= $paymentStatus == 'Rejected' ? 'Upload Ulang Bukti' : 'Kirim Bukti Bayar ➜' ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="text-center space-y-3">
                            <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto text-2xl shadow-inner border border-slate-100">
                                <?= ($paymentStatus == 'Pending') ? '⏳' : '✅' ?>
                            </div>
                            <p class="text-[10px] font-bold text-slate-500 leading-relaxed px-4 uppercase tracking-widest">
                                <?= ($paymentStatus == 'Pending') ? 'Menunggu Verifikasi Panitia' : 'Pembayaran Lunas!' ?>
                            </p>
                            <?php if($proofFile): ?>
                                <a href="../../../public/uploads/payments/<?= htmlspecialchars($proofFile) ?>" target="_blank" class="inline-block mt-4 px-6 py-2 bg-slate-100 border border-slate-200 rounded-xl text-[10px] font-black text-slate-600 uppercase tracking-widest hover:bg-slate-900 hover:text-white transition">Lihat Bukti Saya</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <a href="registration.php?event_id=<?= $targetEventId ?>" class="block w-full py-4 bg-white border border-slate-200 rounded-2xl text-center text-[10px] font-black text-slate-400 uppercase tracking-widest hover:bg-slate-50 hover:text-slate-600 transition-all outline-none">
                    &larr; Kembali
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