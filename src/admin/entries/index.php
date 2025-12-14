<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id']; // ID Admin EO

// --- 1. HANDLE VERIFIKASI (REVISI) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $payId = $_POST['payment_id'];
    $status = $_POST['status']; // 'Verified' atau 'Rejected'

    if ($payId) {
        try {
            $pdo->beginTransaction();

            // A. Update status pembayaran di tabel event_payments
            $stmtPay = $pdo->prepare("UPDATE event_payments SET status = ? WHERE id = ?");
            $stmtPay->execute([$status, $payId]);

            // B. LOGIKA SINKRONISASI: Jika di-Approve (Verified)
            if ($status == 'Verified') {
                // 1. Cari tau dulu ini pembayaran untuk Klub mana dan Event apa
                $stmtGetInfo = $pdo->prepare("SELECT club_id, event_id FROM event_payments WHERE id = ?");
                $stmtGetInfo->execute([$payId]);
                $payInfo = $stmtGetInfo->fetch();

                if ($payInfo) {
                    // 2. Update SEMUA pendaftaran atlet (event_entries) milik klub tersebut menjadi 'Approved'
                    // Ini memastikan logic.php bisa menarik data mereka untuk disusun lintasannya
                    $stmtEntries = $pdo->prepare("UPDATE event_entries SET status = 'Approved' WHERE club_id = ? AND event_id = ?");
                    $stmtEntries->execute([$payInfo['club_id'], $payInfo['event_id']]);
                }
            }

            $pdo->commit();
            $_SESSION['toast_type'] = 'success'; 
            $_SESSION['toast_message'] = "Pendaftaran Klub Berhasil Diverifikasi & Atlet Telah Disetujui!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['toast_type'] = 'error'; 
            $_SESSION['toast_message'] = "Gagal memverifikasi: " . $e->getMessage();
        }
    }
    header("Location: index.php"); exit;
}

// --- 2. AMBIL DATA PENGIRIMAN PER KLUB ---
$sql = "SELECT 
            u.id as club_id, u.nama_lengkap as nama_klub, u.email as email_klub,
            p.id as payment_id, p.status as payment_status, p.proof_file, p.requirement_file, p.created_at as submission_date,
            (SELECT COUNT(DISTINCT ee.swimmer_id) FROM event_entries ee WHERE ee.club_id = u.id AND ee.event_id = ?) as total_atlet,
            (SELECT COUNT(*) FROM event_entries ee WHERE ee.club_id = u.id AND ee.event_id = ?) as total_entries
        FROM users u
        LEFT JOIN event_payments p ON (p.club_id = u.id AND p.event_id = ?)
        WHERE EXISTS (SELECT 1 FROM event_entries ee WHERE ee.club_id = u.id AND ee.event_id = ?)
        ORDER BY p.created_at DESC, u.nama_lengkap ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$uid, $uid, $uid, $uid]);
$submissions = $stmt->fetchAll();

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-6">
        <div>
            <h1 class="text-3xl font-black uppercase tracking-tighter italic text-slate-900 leading-none">Registration Manager</h1>
            <p class="text-sm text-slate-500 font-bold uppercase tracking-widest mt-2">Manajemen Pendaftaran Kolektif per Klub</p>
        </div>
        <div class="bg-white p-5 rounded-[2rem] border border-slate-200 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 bg-blue-50 rounded-2xl flex items-center justify-center text-2xl">📊</div>
            <div>
                <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">Klub Terdaftar</div>
                <div class="font-black text-xl text-slate-900"><?= count($submissions) ?></div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-[2.5rem] border border-slate-200 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm border-collapse">
                <thead class="bg-slate-900 text-white text-[10px] font-black uppercase tracking-widest">
                    <tr>
                        <th class="px-8 py-6">Klub / Pengirim</th>
                        <th class="px-8 py-6 text-center">Summary Atlet</th>
                        <th class="px-8 py-6 text-center">Berkas & Bukti</th>
                        <th class="px-8 py-6 text-center">Status</th>
                        <th class="px-8 py-6 text-right">Konfirmasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if(empty($submissions)): ?>
                        <tr><td colspan="5" class="px-8 py-20 text-center font-black text-slate-300 uppercase text-xs italic">Belum ada kiriman pendaftaran.</td></tr>
                    <?php else: foreach($submissions as $s): ?>
                        <tr class="hover:bg-slate-50 transition h-24">
                            <td class="px-8 py-4">
                                <div class="font-black uppercase text-slate-800 text-sm leading-tight"><?= htmlspecialchars($s['nama_klub']) ?></div>
                                <div class="text-[10px] font-bold text-blue-500 mt-1"><?= $s['email_klub'] ?></div>
                                <div class="text-[9px] text-slate-400 mt-1 italic"><?= $s['submission_date'] ? 'Submit: '.date('d/m/y H:i', strtotime($s['submission_date'])) : '⚠️ Belum Checkout' ?></div>
                            </td>
                            <td class="px-8 py-4 text-center">
                                <a href="view_matrix.php?club_id=<?= $s['club_id'] ?>" class="group flex flex-col items-center mx-auto">
                                    <span class="font-black text-xl text-slate-800 group-hover:text-blue-600 transition"><?= $s['total_atlet'] ?></span>
                                    <span class="text-[9px] font-black text-purple-600 uppercase tracking-tighter bg-purple-50 px-3 py-1 rounded-full border border-purple-100 group-hover:bg-purple-600 group-hover:text-white transition">👁️ Matriks Peserta</span>
                                </a>
                            </td>
                            <td class="px-8 py-4 text-center">
                                <div class="flex justify-center gap-2">
                                    <?php if($s['payment_id']): ?>
                                        <a href="../../../public/<?= $s['proof_file'] ?>" target="_blank" class="w-11 h-11 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center hover:bg-emerald-500 hover:text-white transition shadow-sm border border-emerald-100" title="Bukti Transfer">💰</a>
                                        <?php if($s['requirement_file']): ?>
                                            <a href="../../../public/<?= $s['requirement_file'] ?>" target="_blank" class="w-11 h-11 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center hover:bg-blue-500 hover:text-white transition shadow-sm border border-blue-100" title="Berkas Persyaratan">📄</a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="w-11 h-11 bg-slate-50 text-slate-200 rounded-xl flex items-center justify-center border border-slate-100 italic text-[9px]">Empty</div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-8 py-4 text-center">
                                <?php if(!$s['payment_id']): ?>
                                    <span class="px-4 py-1.5 rounded-full text-[9px] font-black uppercase bg-slate-100 text-slate-400 border border-slate-200">Draft</span>
                                <?php elseif($s['payment_status'] == 'Verified'): ?>
                                    <span class="px-4 py-1.5 rounded-full text-[9px] font-black uppercase bg-emerald-100 text-emerald-700 border border-emerald-200">✅ Verified</span>
                                <?php elseif($s['payment_status'] == 'Rejected'): ?>
                                    <span class="px-4 py-1.5 rounded-full text-[9px] font-black uppercase bg-red-100 text-red-700 border border-red-200">❌ Rejected</span>
                                <?php else: ?>
                                    <span class="px-4 py-1.5 rounded-full text-[9px] font-black uppercase bg-orange-100 text-orange-700 border border-orange-200 animate-pulse">⏳ Pending</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-8 py-4 text-right">
                                <?php if($s['payment_id'] && $s['payment_status'] != 'Verified'): ?>
                                <form method="POST" class="flex justify-end gap-2">
                                    <input type="hidden" name="payment_id" value="<?= $s['payment_id'] ?>">
                                    <input type="hidden" name="update_status" value="1">
                                    <button name="status" value="Verified" class="bg-emerald-600 text-white px-5 py-2.5 rounded-xl text-[10px] font-black uppercase hover:bg-emerald-700 transition shadow-lg shadow-emerald-100">Approve</button>
                                    <button name="status" value="Rejected" class="bg-white text-slate-400 border border-slate-200 px-5 py-2.5 rounded-xl text-[10px] font-black uppercase hover:bg-red-500 hover:text-white transition">Reject</button>
                                </form>
                                <?php elseif($s['payment_status'] == 'Verified'): ?>
                                    <span class="text-[10px] font-black text-slate-300 uppercase italic">Sudah Disetujui</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../views/layout/footer.php'; ?>