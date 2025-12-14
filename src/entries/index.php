<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id']; // ID EO/Admin

// --- 1. HANDLE ACTION (VERIFY / REJECT) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $payId = $_POST['payment_id'];
    $status = $_POST['status'];
    
    // Pastikan payment_id ada (bukan pendaftaran tanpa konfirmasi)
    if ($payId) {
        $stmt = $pdo->prepare("UPDATE event_payments SET status = ? WHERE id = ?");
        $stmt->execute([$status, $payId]);
        $_SESSION['toast_type'] = 'success';
        $_SESSION['toast_message'] = "Status pendaftaran diperbarui!";
    }
    header("Location: index.php"); exit;
}

// --- 2. AMBIL DATA SEMUA KLUB YANG MEMILIKI ENTRIES ---
// Menggunakan LEFT JOIN agar klub yang belum konfirmasi bayar tetap terlihat
$sql = "SELECT 
            u.id as club_id,
            u.nama_lengkap as nama_klub,
            u.email as email_klub,
            p.id as payment_id,
            p.status as payment_status,
            p.proof_file,
            p.requirement_file,
            p.created_at as submission_date,
            -- Statistik Atlet & Entries
            (SELECT COUNT(DISTINCT ee.swimmer_id) FROM event_entries ee WHERE ee.club_id = u.id AND ee.event_id = ?) as total_atlet,
            (SELECT COUNT(*) FROM event_entries ee WHERE ee.club_id = u.id AND ee.event_id = ?) as total_entries,
            -- List Atlet untuk Modal
            (SELECT GROUP_CONCAT(CONCAT(s.nama_atlet, ' (', ec.distance, 'm ', ec.style, ')') SEPARATOR '|') 
             FROM event_entries ee 
             JOIN swimmers s ON ee.swimmer_id = s.id 
             JOIN event_categories ec ON ee.category_id = ec.id
             WHERE ee.club_id = u.id AND ee.event_id = ?) as athlete_list
        FROM users u
        LEFT JOIN event_payments p ON (p.club_id = u.id AND p.event_id = ?)
        WHERE EXISTS (SELECT 1 FROM event_entries ee WHERE ee.club_id = u.id AND ee.event_id = ?)
        ORDER BY p.created_at DESC, u.nama_lengkap ASC";

$stmt = $pdo->prepare($sql);
// Kita perlu mengirimkan $uid sebanyak 5 kali untuk subqueries dan join
$stmt->execute([$uid, $uid, $uid, $uid, $uid]);
$submissions = $stmt->fetchAll();

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-6">
        <div>
            <h1 class="text-3xl font-black uppercase tracking-tighter italic text-slate-900">Registration Manager</h1>
            <p class="text-sm text-slate-500 font-bold uppercase tracking-widest">Pantau & Verifikasi Pendaftaran Masuk</p>
        </div>
        
        <div class="bg-white p-5 rounded-[2rem] border border-slate-200 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 bg-blue-50 rounded-2xl flex items-center justify-center text-2xl">📊</div>
            <div>
                <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Klub Terdaftar</div>
                <div class="font-black text-xl text-slate-900"><?= count($submissions) ?></div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-[2.5rem] border border-slate-200 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-900 text-white text-[10px] font-black uppercase tracking-widest">
                    <tr>
                        <th class="px-8 py-6">Klub / Pengirim</th>
                        <th class="px-8 py-6 text-center">Summary Atlet</th>
                        <th class="px-8 py-6 text-center">Berkas & Bukti</th>
                        <th class="px-8 py-6 text-center">Status</th>
                        <th class="px-8 py-6 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if(empty($submissions)): ?>
                        <tr><td colspan="5" class="px-8 py-20 text-center font-black text-slate-300 uppercase text-xs">Belum ada klub yang mendaftarkan atlet.</td></tr>
                    <?php else: foreach($submissions as $s): ?>
                        <tr class="hover:bg-slate-50 transition h-24">
                            <td class="px-8 py-4">
                                <div class="font-black uppercase text-slate-800 text-sm leading-tight"><?= htmlspecialchars($s['nama_klub']) ?></div>
                                <div class="text-[10px] font-bold text-blue-500 uppercase mt-1"><?= $s['email_klub'] ?></div>
                                <?php if($s['submission_date']): ?>
                                    <div class="text-[9px] text-slate-400 mt-1 italic">Submit: <?= date('d/m/Y H:i', strtotime($s['submission_date'])) ?></div>
                                <?php else: ?>
                                    <div class="text-[9px] text-red-400 mt-1 font-bold italic uppercase tracking-tighter">⚠️ Belum Konfirmasi Bayar</div>
                                <?php endif; ?>
                            </td>
                            
                            <td class="px-8 py-4 text-center">
                                <button onclick='showAthletes("<?= htmlspecialchars($s['nama_klub']) ?>", "<?= $s['athlete_list'] ?>")' class="group flex flex-col items-center mx-auto">
                                    <span class="font-black text-lg text-slate-800 group-hover:text-blue-600 transition"><?= $s['total_atlet'] ?> <small class="text-[10px]">PAX</small></span>
                                    <span class="text-[9px] font-bold text-purple-600 uppercase tracking-tighter bg-purple-50 px-2 rounded-lg">👁️ Detail</span>
                                </button>
                            </td>

                            <td class="px-8 py-4 text-center">
                                <div class="flex justify-center gap-2">
                                    <?php if($s['payment_id']): ?>
                                        <a href="../../../public/<?= $s['proof_file'] ?>" target="_blank" class="w-11 h-11 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center hover:bg-emerald-500 hover:text-white transition shadow-sm border border-emerald-100" title="Cek Bukti Transfer">💰</a>
                                        <?php if($s['requirement_file']): ?>
                                            <a href="../../../public/<?= $s['requirement_file'] ?>" target="_blank" class="w-11 h-11 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center hover:bg-blue-500 hover:text-white transition shadow-sm border border-blue-100" title="Cek Dokumen">📄</a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="w-11 h-11 bg-slate-50 text-slate-300 rounded-xl flex items-center justify-center border border-slate-100 italic text-[10px]">Empty</div>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td class="px-8 py-4 text-center">
                                <?php if(!$s['payment_id']): ?>
                                    <span class="px-4 py-1.5 rounded-full text-[9px] font-black uppercase bg-slate-100 text-slate-400 border border-slate-200">Not Submitted</span>
                                <?php elseif($s['payment_status'] == 'Verified'): ?>
                                    <span class="px-4 py-1.5 rounded-full text-[9px] font-black uppercase bg-emerald-100 text-emerald-700 border border-emerald-200">✅ Verified</span>
                                <?php elseif($s['payment_status'] == 'Rejected'): ?>
                                    <span class="px-4 py-1.5 rounded-full text-[9px] font-black uppercase bg-red-100 text-red-700 border border-red-200">❌ Rejected</span>
                                <?php else: ?>
                                    <span class="px-4 py-1.5 rounded-full text-[9px] font-black uppercase bg-orange-100 text-orange-700 border border-orange-200 animate-pulse">⏳ Pending</span>
                                <?php endif; ?>
                            </td>

                            <td class="px-8 py-4 text-right">
                                <?php if($s['payment_id']): ?>
                                <form method="POST" class="flex justify-end gap-2">
                                    <input type="hidden" name="payment_id" value="<?= $s['payment_id'] ?>">
                                    <input type="hidden" name="update_status" value="1">
                                    <button name="status" value="Verified" class="bg-emerald-600 text-white px-5 py-2.5 rounded-xl text-[10px] font-black uppercase hover:bg-emerald-700 transition">Approve</button>
                                    <button name="status" value="Rejected" class="bg-white text-slate-400 border border-slate-200 px-5 py-2.5 rounded-xl text-[10px] font-black uppercase hover:bg-red-500 hover:text-white transition">Reject</button>
                                </form>
                                <?php else: ?>
                                    <span class="text-[9px] font-black text-slate-300 uppercase tracking-widest italic">Menunggu Pembayaran</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="modal-athletes" class="fixed inset-0 z-[70] hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-xl rounded-[3rem] shadow-2xl overflow-hidden transform transition-all">
        <div class="bg-slate-900 p-8 text-white flex justify-between items-center">
            <div>
                <h3 class="font-black uppercase tracking-widest italic text-lg">Daftar Atlet Peserta</h3>
                <p id="modal-club-name" class="text-blue-400 text-[10px] font-bold uppercase tracking-[0.2em] mt-1"></p>
            </div>
            <button onclick="closeModal()" class="text-slate-400 hover:text-white text-xl">✕</button>
        </div>
        <div class="p-8 max-h-[60vh] overflow-y-auto" id="athlete-container"></div>
        <div class="p-6 bg-slate-50 border-t border-slate-100 text-center">
            <button onclick="closeModal()" class="bg-slate-900 text-white font-black px-10 py-3 rounded-2xl text-[10px] uppercase tracking-widest">Tutup</button>
        </div>
    </div>
</div>

<script>
function showAthletes(clubName, athleteStr) {
    const modal = document.getElementById('modal-athletes');
    const container = document.getElementById('athlete-container');
    const clubLabel = document.getElementById('modal-club-name');
    clubLabel.innerText = clubName;
    container.innerHTML = "";
    if(!athleteStr) {
        container.innerHTML = "<p class='text-center text-slate-400 font-bold py-10 uppercase text-xs'>Belum ada pendaftaran nomor lomba.</p>";
    } else {
        const list = athleteStr.split('|');
        list.forEach((item, index) => {
            const div = document.createElement('div');
            div.className = "flex items-center gap-4 p-4 border-b border-slate-50 last:border-0";
            div.innerHTML = `<div class="w-7 h-7 rounded-full bg-slate-100 flex items-center justify-center text-[10px] font-black text-slate-400">${index + 1}</div><div class="font-black text-slate-700 uppercase text-xs">${item}</div>`;
            container.appendChild(div);
        });
    }
    modal.classList.remove('hidden');
}
function closeModal() { document.getElementById('modal-athletes').classList.add('hidden'); }
</script>