<?php
// FILE: src/master/users/view_athletes.php
session_start();
require_once __DIR__ . '/../../config/database.php';

// 1. PROTEKSI
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'master') {
    header("Location: ../../../public/login.php"); exit;
}

// 2. HANDLE LOGIC VERIFIKASI (POST)
// Ini menangani saat tombol "Verifikasi" diklik
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_athlete') {
    $athleteId = $_POST['athlete_id'];
    // Update status di database
    $stmtUpdate = $pdo->prepare("UPDATE swimmers SET status = 'verified' WHERE id = ?");
    $stmtUpdate->execute([$athleteId]);
    
    // Refresh halaman agar status berubah
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// 3. AMBIL DATA
$userId = $_GET['uid'] ?? null;
if (!$userId) die("Error: ID Klub tidak ditemukan.");

// Info Klub
$stmtClub = $pdo->prepare("SELECT u.nama_lengkap, c.nama_klub, c.city, c.club_code FROM users u LEFT JOIN clubs c ON u.id = c.user_id WHERE u.id = ?");
$stmtClub->execute([$userId]);
$club = $stmtClub->fetch();

// Data Atlet
$stmtAtlet = $pdo->prepare("SELECT * FROM swimmers WHERE user_id = ? ORDER BY nama_atlet ASC");
$stmtAtlet->execute([$userId]);
$atlet = $stmtAtlet->fetchAll();

// --- HELPER FUNCTION: Hitung KU ---
function hitungKU($tglLahir) {
    if (empty($tglLahir)) return '-';
    $usia = date('Y') - date('Y', strtotime($tglLahir));
    if ($usia <= 10) return "KU 4 ($usia Th)";
    if ($usia <= 12) return "KU 3 ($usia Th)";
    if ($usia <= 14) return "KU 2 ($usia Th)";
    if ($usia <= 17) return "KU 1 ($usia Th)";
    return "Senior ($usia Th)";
}

// --- INCLUDE LAYOUT ---
include __DIR__ . '/../../../views/layout/sidebar.php';
include __DIR__ . '/../../../views/layout/topbar.php';
?>

<div class="p-4 sm:ml-64">
    <div class="p-4 mt-14">

        <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
            <div>
                <a href="index.php?role=user" class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 hover:text-blue-600 mb-2 transition">
                    &larr; Kembali ke List Klub
                </a>
                <h1 class="text-3xl font-black text-slate-800 uppercase italic tracking-tighter">
                    <?= htmlspecialchars($club['nama_klub']) ?>
                </h1>
                <p class="text-sm font-medium text-slate-500 flex items-center gap-2 mt-1">
                    <span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded text-xs font-bold">
                        <?= htmlspecialchars($club['club_code'] ?? '-') ?>
                    </span>
    
                    <span>
                        <?= htmlspecialchars($club['city'] ?? 'Kota Tidak Diketahui') ?>
                    </span>
                </p>
            </div>
            <div class="text-right">
                <span class="text-xs font-bold text-slate-400 uppercase">Total Atlet</span>
                <div class="text-4xl font-black text-blue-600 leading-none"><?= count($atlet) ?></div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-4 font-black uppercase text-[10px] text-slate-500 tracking-widest w-10">#</th>
                            <th class="px-6 py-4 font-black uppercase text-[10px] text-slate-500 tracking-widest">Identitas Atlet</th>
                            <th class="px-6 py-4 font-black uppercase text-[10px] text-slate-500 tracking-widest">KU / Usia</th>
                            <th class="px-6 py-4 font-black uppercase text-[10px] text-slate-500 tracking-widest text-center">Data Waktu</th>
                            <th class="px-6 py-4 font-black uppercase text-[10px] text-slate-500 tracking-widest text-right">Verifikasi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if(empty($atlet)): ?>
                            <tr><td colspan="5" class="p-8 text-center text-slate-400 italic">Tidak ada data atlet.</td></tr>
                        <?php else: foreach($atlet as $i => $a): ?>
                        <tr class="hover:bg-slate-50 transition group">
                            
                            <td class="px-6 py-4 font-bold text-slate-300 text-center"><?= $i + 1 ?></td>
                            
                            <td class="px-6 py-4">
                                <div class="font-black text-slate-800 uppercase text-xs tracking-wide">
                                    <?= htmlspecialchars($a['nama_atlet']) ?>
                                </div>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded border <?= $a['jenis_kelamin'] == 'L' ? 'bg-blue-50 text-blue-600 border-blue-100' : 'bg-pink-50 text-pink-600 border-pink-100' ?>">
                                        <?= $a['jenis_kelamin'] == 'L' ? 'L' : 'P' ?>
                                    </span>
                                    <span class="text-[11px] text-slate-500">
                                        Lahir: <?= date('d/m/Y', strtotime($a['tanggal_lahir'])) ?>
                                    </span>
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <span class="bg-slate-100 text-slate-600 px-2 py-1 rounded text-xs font-bold border border-slate-200">
                                    <?= hitungKU($a['tanggal_lahir']) ?>
                                </span>
                            </td>

                            <td class="px-6 py-4 text-center">
                                <button onclick="openHistoryModal(<?= $a['id'] ?>, '<?= htmlspecialchars($a['nama_atlet']) ?>')" 
                                        class="inline-flex items-center gap-2 bg-white border border-slate-300 text-slate-600 px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-blue-50 hover:text-blue-600 hover:border-blue-300 transition shadow-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Lihat Record
                                </button>
                            </td>

                            <td class="px-6 py-4 text-right">
                                <?php if(($a['status'] ?? 'pending') == 'verified'): ?>
                                    <div class="inline-flex items-center gap-1 text-emerald-600 bg-emerald-50 px-3 py-1 rounded-full border border-emerald-100 text-xs font-bold">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                        Verified
                                    </div>
                                <?php else: ?>
                                    <form method="POST" onsubmit="return confirm('Verifikasi atlet ini? Pastikan dokumen sah.');">
                                        <input type="hidden" name="action" value="verify_athlete">
                                        <input type="hidden" name="athlete_id" value="<?= $a['id'] ?>">
                                        <button type="submit" class="inline-flex items-center gap-1 bg-blue-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-blue-700 shadow-sm shadow-blue-200 transition">
                                            Verifikasi Sekarang
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>

                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="historyModal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeModal()"></div>

    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            
            <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-2xl border border-slate-200">
                
                <div class="bg-slate-50 px-4 py-4 sm:px-6 border-b border-slate-100 flex justify-between items-center">
                    <div>
                        <h3 class="text-base font-black leading-6 text-slate-800 uppercase" id="modalAthleteName">
                            NAMA ATLET
                        </h3>
                        <p class="text-xs text-slate-500 mt-1">Riwayat Waktu Terbaik (PB) vs Entry Time</p>
                    </div>
                    <button type="button" onclick="closeModal()" class="text-slate-400 hover:text-red-500 transition">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="px-4 py-5 sm:p-6 max-h-[60vh] overflow-y-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-slate-50 text-slate-500 font-bold border-b border-slate-200">
                            <tr>
                                <th class="py-2 px-3">Gaya / Jarak</th>
                                <th class="py-2 px-3 text-right">Waktu</th>
                                <th class="py-2 px-3">Keterangan / Event</th>
                            </tr>
                        </thead>
                        <tbody id="modalContent" class="divide-y divide-slate-100">
                            <tr><td colspan="3" class="text-center py-4">Memuat data...</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 border-t border-slate-100">
                    <button type="button" onclick="closeModal()" class="mt-3 inline-flex w-full justify-center rounded-lg bg-white px-3 py-2 text-sm font-semibold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // --- 1. DATA SIMULASI (NANTINYA DIAMBIL DARI DATABASE) ---
    // Karena kita belum buat tabel 'results', kita simulasikan data JSON disini.
    // Format: id_atlet : [ { gaya, waktu, event, is_official } ]
    const mockHistoryData = {
        // Contoh ID Atlet: Data History
        <?php foreach($atlet as $a): ?>
        <?= $a['id'] ?>: [
            { style: '50m Gaya Bebas', time: '00:28.45', event: 'Kejurda Jatim 2024', type: 'official' },
            { style: '100m Gaya Bebas', time: '01:05.10', event: 'Entry Time (Latihan)', type: 'entry' },
            { style: '50m Gaya Kupu', time: '00:31.22', event: 'Walikota Cup 2023', type: 'official' },
        ],
        <?php endforeach; ?>
    };

    // --- 2. FUNGSI BUKA MODAL ---
    function openHistoryModal(id, name) {
        const modal = document.getElementById('historyModal');
        const title = document.getElementById('modalAthleteName');
        const tbody = document.getElementById('modalContent');
        
        // Set Judul
        title.innerText = name;
        
        // Ambil Data Mock berdasarkan ID
        const records = mockHistoryData[id] || [];
        
        // Kosongkan tabel
        tbody.innerHTML = '';

        if (records.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-slate-400 italic">Belum ada history waktu.</td></tr>';
        } else {
            // Loop data record dan masukkan ke tabel
            records.forEach(rec => {
                let badgeClass = rec.type === 'official' 
                    ? 'text-emerald-700 bg-emerald-50 border-emerald-200' 
                    : 'text-slate-500 bg-slate-100 border-slate-200 italic';
                
                let badgeLabel = rec.type === 'official' ? 'Official Result' : 'Entry Time (Unverified)';

                let row = `
                    <tr class="hover:bg-slate-50">
                        <td class="py-3 px-3 font-bold text-slate-700">${rec.style}</td>
                        <td class="py-3 px-3 text-right font-black text-slate-800 text-base">${rec.time}</td>
                        <td class="py-3 px-3">
                            <div class="text-xs font-bold ${badgeClass} border px-2 py-0.5 rounded inline-block mb-1">
                                ${badgeLabel}
                            </div>
                            <div class="text-xs text-slate-500">${rec.event}</div>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
        }

        // Tampilkan Modal
        modal.classList.remove('hidden');
    }

    // --- 3. FUNGSI TUTUP MODAL ---
    function closeModal() {
        document.getElementById('historyModal').classList.add('hidden');
    }

    // Tutup jika tekan tombol ESC
    document.addEventListener('keydown', function(event) {
        if(event.key === "Escape"){
            closeModal();
        }
    });
</script>