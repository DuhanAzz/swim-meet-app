<?php
// FILE: src/master/swimmers/index.php
session_start();
require_once __DIR__ . '/../../config/database.php';

// PROTEKSI HALAMAN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'master') {
    header("Location: ../../../public/login.php"); exit;
}

// ============================================================
// ⚡ LOGIKA GENERATE UID MASSAL (FORMAT CUSTOM)
// ============================================================
$success_msg = '';
$error_msg = '';

// Fungsi mengubah huruf jadi angka (A=01, B=02, dst)
function getAlphaIndex($char) {
    $char = strtoupper($char);
    if ($char >= 'A' && $char <= 'Z') {
        return str_pad(ord($char) - 64, 2, '0', STR_PAD_LEFT);
    }
    return '00';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_uids'])) {
    // Cari atlet yang UID-nya kosong ATAU yang masih nyangkut di format 'SW'
    $stmtGet = $pdo->query("SELECT id, nama_atlet, tanggal_lahir, jenis_kelamin FROM swimmers WHERE uid IS NULL OR uid = '' OR uid LIKE 'SW%'");
    $unassigned = $stmtGet->fetchAll();
    
    if (count($unassigned) > 0) {
        $pdo->beginTransaction();
        try {
            $stmtUpdate = $pdo->prepare("UPDATE swimmers SET uid = ? WHERE id = ?");
            $count = 0;
            
            foreach ($unassigned as $row) {
                // Bersihkan spasi berlebih
                $nama = trim($row['nama_atlet']);
                $words = explode(' ', preg_replace('/\s+/', ' ', $nama));
                
                // Ambil inisial
                $char1 = isset($words[0][0]) ? $words[0][0] : 'A';
                if (count($words) > 1) {
                    $char2 = isset($words[1][0]) ? $words[1][0] : 'A';
                } else {
                    $char2 = isset($words[0][1]) ? $words[0][1] : 'A'; // Untuk nama 1 kata
                }
                
                $part1 = getAlphaIndex($char1);
                $part2 = getAlphaIndex($char2);
                
                // Ambil Tahun
                $tahunLahir = '0000';
                if (!empty($row['tanggal_lahir'])) {
                    $tahunLahir = date('Y', strtotime($row['tanggal_lahir']));
                }
                
                // Ambil Gender
                $jk = strtoupper($row['jenis_kelamin'] ?? 'L');
                $genderDigit = ($jk == 'L' || $jk == 'M') ? '1' : '9';
                
                // Format Base UID (contoh: 011820181)
                $baseUid = $part1 . $part2 . $tahunLahir . $genderDigit;
                
                // Pengecekan Anti Bentrok untuk anak kembar / nama inisial sama
                $stmtCek = $pdo->prepare("SELECT uid FROM swimmers WHERE uid LIKE ? AND id != ? ORDER BY uid DESC LIMIT 1");
                $stmtCek->execute([$baseUid . '%', $row['id']]);
                $last_uid = $stmtCek->fetchColumn();
                
                $twinDigit = 0;
                if ($last_uid) {
                    $last_digit = (int) substr($last_uid, -1);
                    $twinDigit = $last_digit + 1;
                    if ($twinDigit > 9) $twinDigit = 9; 
                }
                
                // Gabungkan Base dengan digit akhir
                $newUid = $baseUid . $twinDigit;
                
                // Update ke database
                $stmtUpdate->execute([$newUid, $row['id']]);
                $count++;
            }
            
            $pdo->commit();
            header("Location: index.php?msg=uid_success&count=" . $count);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "Gagal generate UID: " . $e->getMessage();
        }
    } else {
        header("Location: index.php?msg=uid_none");
        exit;
    }
}

// Tangkap alert dari URL Redirect
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'uid_success') {
        $count = $_GET['count'] ?? 0;
        $success_msg = "🎉 Berhasil membuat & menimpa <strong>$count UID</strong> menjadi format baru secara otomatis!";
    } elseif ($_GET['msg'] === 'uid_none') {
        $error_msg = "Semua atlet sudah memiliki UID dengan format baru. Tidak ada yang perlu di-generate.";
    }
}

// ============================================================
// 1. QUERY UTAMA MENAMPILKAN DATA
// ============================================================
$search = $_GET['search'] ?? '';
$sql = "SELECT s.*, 
               c.nama_klub, 
               COALESCE(c.kota, '-') as lokasi_klub
        FROM swimmers s
        LEFT JOIN clubs c ON s.club_id = c.id 
        WHERE 1=1";

$params = [];
if (!empty($search)) {
    $sql .= " AND (s.nama_atlet LIKE ? OR s.uid LIKE ? OR c.nama_klub LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Mengurutkan dari yang terbaru, menampilkan semua
$sql .= " ORDER BY s.created_at DESC"; 

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$swimmers = $stmt->fetchAll();

// HELPER FUNCTION (Usia per Tahun Berjalan)
function hitungUsia($tgl) {
    if(empty($tgl)) return 0;
    return date('Y') - date('Y', strtotime($tgl));
}

function hitungKU($tglLahir) {
    if(empty($tglLahir)) return '-';
    $usia = hitungUsia($tglLahir);
    if ($usia <= 10) return "KU 4";
    if ($usia <= 12) return "KU 3";
    if ($usia <= 14) return "KU 2";
    if ($usia <= 17) return "KU 1";
    return "Senior";
}

include __DIR__ . '/../../../views/layout/topbar.php';
include __DIR__ . '/../../../views/layout/sidebar.php';
?>

<div class="p-4 sm:ml-64">
    <div class="p-4 mt-14">

        <?php if (!empty($success_msg)): ?>
            <div class="mb-4 p-4 text-xs text-emerald-800 bg-emerald-50 rounded-xl border border-emerald-200 shadow-sm flex items-center gap-2">
                <span>💡</span> <div><?= $success_msg ?></div>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="mb-4 p-4 text-xs text-amber-800 bg-amber-50 rounded-xl border border-amber-200 shadow-sm flex items-center gap-2">
                <span>⚠️</span> <div><?= $error_msg ?></div>
            </div>
        <?php endif; ?>
        
        <div class="flex flex-col md:flex-row justify-between items-end gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-black text-slate-800 uppercase italic tracking-tighter">Database Atlet</h1>
                <p class="text-xs text-slate-500 font-medium">Manajemen Data & Status Verifikasi</p>
            </div>
            
            <div class="w-full md:w-auto flex flex-col sm:flex-row items-center gap-2">
                
                <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin me-reset dan meng-generate ulang UID otomatis (Format AZRIL) untuk semua atlet?');" class="w-full sm:w-auto">
                    <button type="submit" name="generate_uids" style="background-color: #3b82f6; color: white;" class="w-full sm:w-auto justify-center hover:opacity-80 px-4 py-2.5 rounded-lg shadow-sm text-xs font-bold tracking-wide transition flex items-center gap-2 whitespace-nowrap">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        Generate UID Massal
                    </button>
                </form>

                <form method="GET" class="relative w-full sm:w-auto">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           class="pl-10 pr-4 py-2.5 text-xs font-bold border rounded-lg w-full md:w-80 shadow-sm focus:ring-blue-500 focus:border-blue-500" 
                           placeholder="Cari UID, Nama, atau Klub...">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                </form>

            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200 text-[10px] uppercase text-slate-500 tracking-wider">
                        <tr>
                            <th class="px-6 py-4">Profil Atlet</th>
                            <th class="px-6 py-4">Afiliasi & Sekolah</th>
                            <th class="px-6 py-4 text-center">Kategori</th>
                            <th class="px-6 py-4 text-center">Status</th>
                            <th class="px-6 py-4 text-center">Kontrol</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach($swimmers as $s): ?>
                        <tr class="hover:bg-slate-50 transition duration-150 group">
                            
                            <td class="px-6 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold border-2 <?= $s['jenis_kelamin'] == 'L' ? 'bg-blue-50 text-blue-600 border-blue-100' : 'bg-pink-50 text-pink-600 border-pink-100' ?>">
                                        <?= $s['jenis_kelamin'] ?? '?' ?>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-black text-slate-800 text-xs uppercase"><?= htmlspecialchars($s['nama_atlet']) ?></span>
                                            <?php if(($s['status']??'pending') == 'verified'): ?>
                                                <svg class="w-3 h-3 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                            <?php endif; ?>
                                        </div>
                                        <div class="font-mono text-slate-400 text-[10px] tracking-wide">
                                            UID: <span class="text-blue-600 font-bold"><?= $s['uid'] ?? '-' ?></span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-3">
                                <div class="font-bold text-slate-700 text-xs"><?= htmlspecialchars($s['nama_klub'] ?? 'Unattached') ?></div>
                                <?php if(!empty($s['asal_sekolah'])): ?>
                                    <div class="text-[10px] text-slate-500 italic mt-0.5 flex items-center gap-1">
                                        <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                        <?= htmlspecialchars($s['asal_sekolah']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-3 text-center">
                                <span class="bg-slate-100 text-slate-600 px-2.5 py-1 rounded-full text-[10px] font-bold border border-slate-200">
                                    <?= hitungKU($s['tanggal_lahir']) ?>
                                </span>
                                <div class="text-[10px] text-slate-400 mt-1"><?= hitungUsia($s['tanggal_lahir']) ?> Tahun</div>
                            </td>

                            <td class="px-6 py-3 text-center">
                                <?php if(($s['status']??'pending') == 'verified'): ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-100">
                                        Verified
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-bold bg-amber-50 text-amber-600 border border-amber-100">
                                        Pending
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-3 text-center">
                                <button onclick="openManageModal(this)" 
                                        data-json='<?= htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8') ?>'
                                        data-ku="<?= hitungKU($s['tanggal_lahir']) ?>"
                                        data-usia="<?= hitungUsia($s['tanggal_lahir']) ?>"
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg shadow-sm text-[10px] font-bold tracking-wide transition flex items-center gap-2 mx-auto">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    Manage
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="manageModal" class="fixed inset-0 z-50 hidden transition-opacity duration-300">
    <div class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm" onclick="closeModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-3xl rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
            
            <div class="bg-slate-800 p-6 text-white flex justify-between items-start">
                <div class="flex gap-4 items-center">
                    <div id="mAvatar" class="w-16 h-16 rounded-full bg-slate-600 flex items-center justify-center text-2xl font-bold border-4 border-slate-700">L</div>
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <h2 class="text-xl font-black uppercase tracking-wide" id="mName">NAMA ATLET</h2>
                            <span id="mStatusBadge" class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500 text-white uppercase">Verified</span>
                        </div>
                        <p class="text-sm text-slate-300 flex items-center gap-2">
                            <span id="mClub">Nama Klub</span>
                            <span class="w-1 h-1 bg-slate-500 rounded-full"></span>
                            <span id="mUid" class="font-mono text-blue-300">UID</span>
                        </p>
                    </div>
                </div>
                <button onclick="closeModal()" class="text-slate-400 hover:text-white transition">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="bg-slate-100 px-6 py-3 border-b border-slate-200 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <span class="text-xs font-bold text-slate-500 uppercase">Status Verifikasi:</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="toggleVerify" class="sr-only peer" onchange="toggleStatus()">
                        <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                        <span class="ml-2 text-xs font-bold text-slate-700" id="toggleLabel">Pending</span>
                    </label>
                </div>
                
                <a id="btnEdit" href="#" class="bg-white border border-slate-300 text-slate-700 hover:bg-amber-50 hover:text-amber-600 hover:border-amber-300 px-4 py-2 rounded-lg text-xs font-bold flex items-center gap-2 transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Edit Biodata
                </a>
            </div>

            <div class="flex-1 overflow-y-auto bg-slate-50 p-6">
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-white p-3 rounded-lg border border-slate-200 shadow-sm">
                        <span class="text-[10px] text-slate-400 uppercase font-bold">Sekolah</span>
                        <div class="font-bold text-slate-800 text-sm" id="mSchool">-</div>
                    </div>
                    <div class="bg-white p-3 rounded-lg border border-slate-200 shadow-sm">
                        <span class="text-[10px] text-slate-400 uppercase font-bold">Tgl Lahir / Usia</span>
                        <div class="font-bold text-slate-800 text-sm" id="mDob">-</div>
                    </div>
                    <div class="bg-white p-3 rounded-lg border border-slate-200 shadow-sm">
                        <span class="text-[10px] text-slate-400 uppercase font-bold">Kelompok Umur</span>
                        <div class="font-bold text-blue-600 text-sm" id="mKU">-</div>
                    </div>
                </div>

                <h3 class="text-xs font-black text-slate-700 uppercase mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Riwayat Waktu Terbaik (Personal Best)
                </h3>
                <div class="bg-white border border-slate-200 rounded-lg overflow-hidden shadow-sm">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-slate-100 text-slate-500 font-bold text-xs uppercase">
                            <tr>
                                <th class="px-4 py-2">Nomor</th>
                                <th class="px-4 py-2">Waktu</th>
                                <th class="px-4 py-2 text-right">Tanggal</th>
                            </tr>
                        </thead>
                        <tbody id="recordTableBody" class="divide-y divide-slate-100 text-xs">
                            <tr><td colspan="3" class="p-4 text-center text-slate-400 italic">Memuat data...</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 p-3 bg-blue-50 border border-blue-100 rounded text-[11px] text-blue-800 leading-relaxed">
                    <strong>Catatan Sistem:</strong><br>
                    Jika status atlet <strong>Pending</strong>, nama klub saat pendaftaran lomba akan otomatis berubah menjadi <strong>"UNATTACHED"</strong>.
                    Jika <strong>Verified</strong>, akan menggunakan nama <strong><span id="mClubNote">KLUB</span></strong>.
                </div>

            </div>
        </div>
    </div>
</div>

<script>
    let currentSwimmerId = null;

    function openManageModal(btn) {
        try {
            const data = JSON.parse(btn.getAttribute('data-json'));
            const ku = btn.getAttribute('data-ku');
            const usia = btn.getAttribute('data-usia');
            
            currentSwimmerId = data.id;

            document.getElementById('mName').innerText = data.nama_atlet;
            document.getElementById('mClub').innerText = data.nama_klub || 'Unattached';
            document.getElementById('mClubNote').innerText = data.nama_klub || '...';
            document.getElementById('mUid').innerText = data.uid || 'NO UID';
            document.getElementById('mSchool').innerText = data.asal_sekolah || '-';
            document.getElementById('mDob').innerText = `${data.tanggal_lahir} (${usia} Th)`;
            document.getElementById('mKU').innerText = ku;
            
            const gender = data.jenis_kelamin || 'L';
            const av = document.getElementById('mAvatar');
            av.innerText = gender;
            av.className = `w-16 h-16 rounded-full flex items-center justify-center text-2xl font-bold border-4 ${gender === 'L' ? 'bg-blue-600 border-blue-200 text-white' : 'bg-pink-500 border-pink-200 text-white'}`;

            document.getElementById('btnEdit').href = `edit.php?id=${data.id}`;

            const toggle = document.getElementById('toggleVerify');
            
            if (data.status === 'verified') {
                toggle.checked = true;
                updateStatusUI('verified');
            } else {
                toggle.checked = false;
                updateStatusUI('pending');
            }

            document.getElementById('manageModal').classList.remove('hidden');
            loadRecords(data.id);
        } catch (e) {
            console.error("Gagal parse JSON:", e);
            alert("Terjadi kesalahan saat membuka data atlet.");
        }
    }

    function closeModal() {
        document.getElementById('manageModal').classList.add('hidden');
    }

    function toggleStatus() {
        const isChecked = document.getElementById('toggleVerify').checked;
        const newStatus = isChecked ? 'verified' : 'pending';
        
        updateStatusUI(newStatus);
        
        fetch('api_verify.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: currentSwimmerId, status: newStatus })
        })
        .then(res => {
            if (!res.ok) {
                throw new Error(`HTTP Error! Status: ${res.status}`);
            }
            return res.json();
        })
        .then(data => {
            if(!data.success) {
                alert('Gagal update: ' + (data.message || 'Error tidak diketahui'));
                document.getElementById('toggleVerify').checked = !isChecked;
                updateStatusUI(!isChecked ? 'verified' : 'pending');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Gagal terhubung ke server.');
            document.getElementById('toggleVerify').checked = !isChecked;
            updateStatusUI(!isChecked ? 'verified' : 'pending');
        });
    }

    function updateStatusUI(status) {
        const badge = document.getElementById('mStatusBadge');
        const label = document.getElementById('toggleLabel');

        if(status === 'verified') {
            badge.className = "px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500 text-white uppercase";
            badge.innerText = "VERIFIED";
            label.innerText = "Verified";
            label.className = "ml-2 text-xs font-bold text-emerald-600";
        } else {
            badge.className = "px-2 py-0.5 rounded text-[10px] font-bold bg-amber-400 text-white uppercase";
            badge.innerText = "PENDING";
            label.innerText = "Pending";
            label.className = "ml-2 text-xs font-bold text-slate-400";
        }
    }

    function loadRecords(id) {
        const tbody = document.getElementById('recordTableBody');
        tbody.innerHTML = '<tr><td colspan="3" class="p-4 text-center text-slate-400 animate-pulse">Mengambil data...</td></tr>';

        fetch(`get_detail.php?id=${id}`)
            .then(res => res.json())
            .then(data => {
                let html = '';
                if(data.records && data.records.length > 0) {
                    data.records.forEach(r => {
                        html += `
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-2 font-bold text-slate-700">${r.nomor_lomba}</td>
                                <td class="px-4 py-2 font-mono text-blue-600 font-bold">${r.waktu_terbaik}</td>
                                <td class="px-4 py-2 text-right text-slate-400">${r.tanggal_dicapai}</td>
                            </tr>`;
                    });
                } else {
                    html = '<tr><td colspan="3" class="p-4 text-center text-slate-400 text-[10px] italic">Belum ada data rekor.</td></tr>';
                }
                tbody.innerHTML = html;
            })
            .catch(err => {
                tbody.innerHTML = '<tr><td colspan="3" class="p-4 text-center text-red-400 text-[10px]">Gagal memuat data.</td></tr>';
            });
    }
</script>