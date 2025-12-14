<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id'];
$eventId = $_GET['event_id'] ?? 0;

// Hitung Ulang Total
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_count, SUM(ec.price) as total_price 
    FROM event_entries ee 
    JOIN event_categories ec ON ee.category_id = ec.id 
    WHERE ee.event_id = ? AND ee.club_id = ?
");
$stmt->execute([$eventId, $uid]);
$summary = $stmt->fetch();

if ($summary['total_count'] == 0) { header("Location: register_event.php?event_id=$eventId"); exit; }

// Handle Submit Payment & Requirements
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $targetDir = "../../../public/uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

        // Upload Proof
        $proofName = "proof_" . $uid . "_" . time() . "_" . $_FILES['proof']['name'];
        move_uploaded_file($_FILES['proof']['tmp_name'], $targetDir . $proofName);

        // Upload Requirements
        $reqName = null;
        if (!empty($_FILES['req']['name'])) {
            $reqName = "req_" . $uid . "_" . time() . "_" . $_FILES['req']['name'];
            move_uploaded_file($_FILES['req']['tmp_name'], $targetDir . $reqName);
        }

        $sql = "INSERT INTO event_payments (event_id, club_id, total_amount, proof_file, requirement_file, status) 
                VALUES (?, ?, ?, ?, ?, 'Pending')";
        $pdo->prepare($sql)->execute([$eventId, $uid, $summary['total_price'], "uploads/" . $proofName, $reqName ? "uploads/" . $reqName : null]);

        $_SESSION['toast_type'] = 'success';
        $_SESSION['toast_message'] = 'Pendaftaran berhasil dikirim! Menunggu verifikasi admin.';
        header("Location: ../pembayaran.php"); exit;
    } catch (Exception $e) { $error = $e->getMessage(); }
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-black uppercase italic mb-8">Checkout & Verification</h1>
        
        <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-10">
            <div class="bg-white p-8 rounded-[3rem] border border-slate-200 shadow-sm">
                <h3 class="font-black uppercase text-xs text-slate-400 mb-6 tracking-widest">Ringkasan Tagihan</h3>
                <div class="flex justify-between mb-4">
                    <span class="font-bold text-slate-600">Total Atlet/Entries</span>
                    <span class="font-black"><?= $summary['total_count'] ?> Splash</span>
                </div>
                <div class="flex justify-between text-2xl border-t pt-4">
                    <span class="font-black uppercase italic">Total Bayar</span>
                    <span class="font-black text-blue-600 italic">Rp<?= number_format($summary['total_price'], 0, ',', '.') ?></span>
                </div>
                <div class="mt-8 p-6 bg-slate-50 rounded-3xl border border-dashed border-slate-200 text-[11px] font-medium text-slate-500 leading-relaxed">
                    Silakan transfer ke rekening berikut:<br>
                    <strong class="text-slate-900">BANK MANDIRI: 123-000-456-789</strong><br>
                    A/N: PT. SWIMMEET DIGITAL SYSTEM
                </div>
            </div>

            <div class="bg-white p-8 rounded-[3rem] border border-slate-200 shadow-sm space-y-6">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-2">Upload Bukti Transfer (JPG/PNG)</label>
                    <input type="file" name="proof" class="w-full" required>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-2">Berkas Persyaratan (ZIP/PDF)</label>
                    <input type="file" name="req" class="w-full">
                </div>
                <button type="submit" class="w-full bg-emerald-600 text-white font-black py-4 rounded-2xl uppercase tracking-widest text-xs hover:bg-emerald-700 transition shadow-xl shadow-emerald-100">
                    Konfirmasi Sekarang
                </button>
            </div>
        </form>
    </div>
</div>