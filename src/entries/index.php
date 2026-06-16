<?php
session_start();
require_once __DIR__ . '/../../src/config/database.php';

// 1. CEK KEAMANAN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

$uid = $_SESSION['user_id']; // ID Admin

// 2. HANDLE VERIFIKASI & LOCKING (APPROVE / REJECT / UNLOCK)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $payId = $_POST['payment_id'];
    $status = $_POST['status']; // 'Paid', 'Rejected', atau 'Pending' (untuk unlock)
    
    if ($payId) {
        // Update Status Pembayaran di tabel 'payments'
        // 'Paid' = Valid & Terkunci
        // 'Pending' = Buka Kunci (User bisa edit lagi)
        $stmt = $pdo->prepare("UPDATE payments SET status = ? WHERE id = ?");
        $stmt->execute([$status, $payId]);
        
        // Pesan Notifikasi
        $_SESSION['swal_type'] = 'success';
        if ($status === 'Paid') {
            $_SESSION['swal_msg'] = "Pendaftaran DITERIMA & DIKUNCI. User tidak bisa mengubah data lagi.";
        } elseif ($status === 'Pending') {
            $_SESSION['swal_msg'] = "Pendaftaran DIBUKA KEMBALI (Unlock). User bisa merevisi data.";
        } else {
            $_SESSION['swal_msg'] = "Status pendaftaran diperbarui menjadi $status!";
        }
    }
    header("Location: index.php"); exit;
}

// 3. AMBIL DATA KLUB & ENTRIES
// Menggunakan 'event_number_id' sesuai struktur tabel baru
$sql = "SELECT 
            u.id as club_id,
            u.nama_lengkap as nama_klub,
            u.email as email_klub,
            p.id as payment_id,
            p.status as payment_status,
            p.proof_file,
            p.created_at as submission_date,
            
            -- Hitung Jumlah Atlet Unik
            (SELECT COUNT(DISTINCT ee.swimmer_id) 
             FROM event_entries ee 
             WHERE ee.club_id = u.id AND ee.user_id = ?) as total_atlet,
             
            -- Hitung Total Splash (Jumlah Nomor yang diikuti)
            (SELECT COUNT(*) 
             FROM event_entries ee 
             WHERE ee.club_id = u.id AND ee.user_id = ?) as total_entries,

            -- Ambil List Detail Atlet & Nomor (JOIN ke tabel event_numbers)
            (SELECT GROUP_CONCAT(
                CONCAT(s.nama_atlet, ' (', en.event_name, ')') 
                SEPARATOR '|'
             ) 
             FROM event_entries ee 
             JOIN swimmers s ON ee.swimmer_id = s.id 
             JOIN event_numbers en ON ee.event_number_id = en.id 
             WHERE ee.club_id = u.id AND ee.user_id = ?) as athlete_list

        FROM users u
        -- Join ke tabel payments
        LEFT JOIN payments p ON (p.user_id = u.id) 
        
        -- Hanya tampilkan user (klub) yang memiliki entry di event ini
        WHERE EXISTS (SELECT 1 FROM event_entries ee WHERE ee.club_id = u.id AND ee.user_id = ?)
        AND u.role = 'club'
        ORDER BY p.created_at DESC, u.nama_lengkap ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$uid, $uid, $uid, $uid]);
$submissions = $stmt->fetchAll();

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-6">
        <div>
            <h1 class="text-3xl font-black uppercase tracking-tighter italic text-slate-900 leading-none">Registration Manager</h1>
            <p class="text-sm text-slate-500 font-bold uppercase tracking-widest mt-2">Verifikasi & Kunci Data Pendaftaran</p>
        </div>
        
        <div class="flex gap-4">
            <div class="bg-white px-6 py-3 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-3">
                <span class="text-2xl">🏰</span>
                <div>
                    <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Klub Masuk</div>
                    <div class="font-black text-xl text-slate-900 leading-none"><?= count($submissions) ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php if(isset($_SESSION['swal_msg'])): ?>
        <div class="mb-6 px-6 py-4 rounded-xl shadow-lg font-bold text-white flex items-center gap-3 <?= $_SESSION['swal_type']=='success' ? 'bg-green-500' : 'bg-red-500' ?>">
            <span><?= $_SESSION['swal_type']=='success' ? '✅' : '⚠️' ?></span>
            <?= $_SESSION['swal_msg']; unset($_SESSION['swal_msg']); unset($_SESSION['swal_type']); ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-[2.5rem] border border-slate-200 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-900 text-white text-[10px] font-black uppercase tracking-widest">
                    <tr>
                        <th class="px-8 py-6">Klub & Tanggal</th>
                        <th class="px-8 py-6 text-center">Jml Atlet</th>
                        <th class="px-8 py-6 text-center">Bukti Bayar</th>
                        <th class="px-8 py-6 text-center">Status</th>
                        <th class="px-8 py-6 text-right">Aksi Verifikasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if(empty($submissions)): ?>
                        <tr><td colspan="5" class="px-8 py-20 text-center font-black text-slate-300 uppercase text-xs">Belum ada data pendaftaran masuk.</td></tr>
                    <?php else: foreach($submissions as $s): ?>
                        <tr class="hover:bg-slate-50 transition h-24 group">
                            <td class="px-8 py-4">
                                <div class="font-black uppercase text-slate-800 text-sm leading-tight"><?= htmlspecialchars($s['nama_klub']) ?></div>
                                <div class="text-[10px] font-bold text-blue-500 uppercase mt-1"><?= $s['email_klub'] ?></div>
                                <?php if($s['submission_date']): ?>
                                    <div class="text-[9px] text-slate-400 mt-1 italic flex items-center gap-1">
                                        <span>📅</span> <?= date('d M Y H:i', strtotime($s['submission_date'])) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-[9px] text-orange-400 mt-1 font-bold italic uppercase tracking-tighter">⚠️ Belum Checkout</div>
                                <?php endif; ?>
                            </td>
                            
                            <td class="px-8 py-4 text-center">
                                <button onclick='showAthletes("<?= htmlspecialchars($s['nama_klub']) ?>", "<?= htmlspecialchars($s['athlete_list'] ?? '') ?>")' class="group/btn flex flex-col items-center mx-auto cursor-pointer hover:scale-105 transition">
                                    <span class="font-black text-lg text-slate-800 group-hover/btn:text-blue-600 transition"><?= $s['total_atlet'] ?> <span class="text-[10px] text-slate-400">Org</span></span>
                                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter"><?= $s['total_entries'] ?> Splash</span>
                                </button>
                            </td>

                            <td class="px-8 py-4 text-center">
                                <?php if($s['payment_id'] && $s['proof_file']): ?>
                                    <a href="../../../public/<?= $s['proof_file'] ?>" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-50 text-emerald-700 rounded-xl text-[10px] font-black uppercase hover:bg-emerald-600 hover:text-white transition border border-emerald-100">
                                        <span>📎</span> Lihat Bukti
                                    </a>
                                <?php else: ?>
                                    <span class="text-[10px] font-bold text-slate-300 italic">Tidak ada file</span>
                                <?php endif; ?>
                            </td>

                            <td class="px-8 py-4 text-center">
                                <?php 
                                    $st = $s['payment_status'] ?? 'Pending';
                                    $badge = match($st) {
                                        'Verified', 'Paid' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                                        'Rejected' => 'bg-red-100 text-red-700 border-red-200',
                                        default    => 'bg-yellow-50 text-yellow-700 border-yellow-200 animate-pulse'
                                    };
                                ?>
                                <span class="px-4 py-2 rounded-xl text-[9px] font-black uppercase border <?= $badge ?>">
                                    <?= $st ?>
                                </span>
                            </td>

                            <td class="px-8 py-4 text-right">
                                <?php if($s['payment_id']): ?>
                                <form method="POST" class="flex justify-end gap-2" onsubmit="return confirm('Ubah status pendaftaran?')">
                                    <input type="hidden" name="payment_id" value="<?= $s['payment_id'] ?>">
                                    <input type="hidden" name="update_status" value="1">
                                    
                                    <?php if($s['payment_status'] !== 'Paid'): ?>
                                        <button name="status" value="Paid" class="bg-emerald-600 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase hover:bg-emerald-700 transition shadow-lg shadow-emerald-200 flex items-center gap-2" title="Terima dan Kunci Data">
                                            <span>🔒</span> Approve & Lock
                                        </button>
                                        
                                        <button name="status" value="Rejected" class="w-10 h-10 rounded-xl bg-white border border-slate-200 text-red-400 flex items-center justify-center hover:bg-red-500 hover:text-white transition" title="Tolak Pendaftaran">
                                            ✕
                                        </button>

                                    <?php else: ?>
                                        <div class="flex items-center gap-2">
                                            <span class="text-[9px] font-bold text-emerald-600 uppercase tracking-widest mr-2 flex items-center gap-1">
                                                ✅ Valid
                                            </span>
                                            <button name="status" value="Pending" class="bg-slate-100 text-slate-500 px-3 py-2 rounded-xl text-[10px] font-bold uppercase hover:bg-yellow-500 hover:text-white transition border border-slate-200 flex items-center gap-2" title="Izinkan user merevisi data (Status kembali ke Pending)">
                                                <span>🔓</span> Buka Kunci
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </form>
                                <?php else: ?>
                                    <span class="text-[9px] font-black text-slate-300 uppercase italic">Menunggu Upload</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="modal-athletes" class="fixed inset-0 z-[100] hidden bg-slate-900/80 backdrop-blur-sm flex items-center justify-center p-4 transition-all opacity-0 pointer-events-none" style="transition: opacity 0.3s ease;">
    <div class="bg-white w-full max-w-2xl rounded-[2.5rem] shadow-2xl overflow-hidden transform scale-95 transition-transform duration-300">
        <div class="bg-slate-900 px-8 py-6 text-white flex justify-between items-center">
            <div>
                <h3 class="font-black uppercase tracking-widest italic text-xl">Rincian Pendaftaran</h3>
                <div class="flex items-center gap-2 mt-1">
                    <span class="text-slate-400 text-[10px] font-bold uppercase tracking-widest">Klub:</span>
                    <span id="modal-club-name" class="text-blue-400 text-[10px] font-black uppercase tracking-widest"></span>
                </div>
            </div>
            <button onclick="closeModal()" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center text-white hover:bg-red-500 hover:rotate-90 transition">✕</button>
        </div>
        
        <div class="p-8 max-h-[60vh] overflow-y-auto bg-slate-50 space-y-3" id="athlete-container">
            </div>

        <div class="p-6 bg-white border-t border-slate-100 text-right">
            <button onclick="closeModal()" class="bg-slate-200 text-slate-700 hover:bg-slate-300 font-bold px-8 py-3 rounded-xl text-xs uppercase tracking-widest transition">Tutup</button>
        </div>
    </div>
</div>

<script>
// === LOGIC MODAL ===
function showAthletes(clubName, athleteStr) {
    const modal = document.getElementById('modal-athletes');
    const container = document.getElementById('athlete-container');
    const clubLabel = document.getElementById('modal-club-name');
    
    clubLabel.innerText = clubName;
    container.innerHTML = "";

    if(!athleteStr) {
        container.innerHTML = `
            <div class='flex flex-col items-center justify-center py-10 opacity-50'>
                <span class='text-4xl mb-2'>📂</span>
                <p class='font-bold text-slate-400 uppercase text-xs'>Belum ada atlet terdaftar.</p>
            </div>`;
    } else {
        const list = athleteStr.split('|');
        let counter = 1;
        
        list.forEach((item) => {
            // Regex untuk memisahkan Nama dan Event: "Nama Atlet (Event Name)"
            let match = item.match(/^(.*)\s\((.*)\)$/);
            let nama = match ? match[1] : item;
            let event = match ? match[2] : '-';

            const div = document.createElement('div');
            div.className = "flex items-center gap-4 p-4 bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition";
            div.innerHTML = `
                <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xs font-black shrink-0">${counter++}</div>
                <div>
                    <div class="font-black text-slate-800 text-xs uppercase tracking-wide">${nama}</div>
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">🏊 ${event}</div>
                </div>
            `;
            container.appendChild(div);
        });
    }

    // Tampilkan
    modal.classList.remove('hidden', 'opacity-0', 'pointer-events-none');
    modal.querySelector('div').classList.remove('scale-95');
    modal.querySelector('div').classList.add('scale-100');
}

function closeModal() { 
    const modal = document.getElementById('modal-athletes');
    modal.classList.add('opacity-0', 'pointer-events-none');
    modal.querySelector('div').classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}
</script>