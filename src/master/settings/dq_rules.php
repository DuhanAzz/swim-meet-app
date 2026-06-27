<?php
// FILE: src/master/settings/dq_rules.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// CEK OTORITAS KHUSUS MASTER
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'master') {
    header("Location: ../../../public/login.php"); 
    exit;
}

// --- HANDLE DELETE ---
if (isset($_GET['del'])) {
    $id = $_GET['del'];
    $stmt = $pdo->prepare("DELETE FROM dq_rules WHERE id = ?");
    if ($stmt->execute([$id])) {
        $_SESSION['swal_type'] = "success";
        $_SESSION['swal_msg']  = "Pasal DQ berhasil dihapus!";
    }
    header("Location: dq_rules.php"); exit;
}

// --- HANDLE SIMPAN (ADD / EDIT) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? '';
    $kategori = $_POST['kategori_gaya'] ?? '';
    $pasal = $_POST['pasal'] ?? '';
    $deskripsi = $_POST['deskripsi'] ?? '';

    if (!empty($id)) {
        // Mode Edit
        $stmt = $pdo->prepare("UPDATE dq_rules SET kategori_gaya = ?, pasal = ?, deskripsi = ? WHERE id = ?");
        $stmt->execute([$kategori, $pasal, $deskripsi, $id]);
        $_SESSION['swal_type'] = "success";
        $_SESSION['swal_msg']  = "Pasal DQ berhasil diperbarui!";
    } else {
        // Mode Tambah Baru
        $stmt = $pdo->prepare("INSERT INTO dq_rules (kategori_gaya, pasal, deskripsi) VALUES (?, ?, ?)");
        $stmt->execute([$kategori, $pasal, $deskripsi]);
        $_SESSION['swal_type'] = "success";
        $_SESSION['swal_msg']  = "Pasal DQ baru berhasil ditambahkan!";
    }
    header("Location: dq_rules.php"); exit;
}

// --- AMBIL SEMUA DATA ---
// Mengurutkan secara Natural Sort untuk format penomoran baru (contoh: 5.1.2, 10.2a)
$stmt = $pdo->query("SELECT * FROM dq_rules ORDER BY 
    CAST(SUBSTRING_INDEX(pasal, '.', 1) AS UNSIGNED) ASC, 
    CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(pasal, '.', 2), '.', -1) AS UNSIGNED) ASC, 
    pasal ASC");
$rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <!-- HEADER -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-800 uppercase italic tracking-tighter">Master Data DQ</h1>
            <p class="text-sm text-slate-500 font-bold uppercase tracking-widest mt-1">Kelola Regulasi Diskualifikasi Federasi</p>
        </div>
        <button onclick="openModal()" class="bg-blue-600 hover:bg-blue-500 text-white font-black py-3 px-6 rounded-xl uppercase tracking-widest text-xs shadow-lg shadow-blue-900/50 transition transform hover:-translate-y-1">
            + Tambah Pasal
        </button>
    </div>    

    <!-- TABEL DATA -->
    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto p-6">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-400 text-[10px] uppercase tracking-widest">
                        <th class="p-4 font-black">Pasal</th>
                        <th class="p-4 font-black">Kategori</th>
                        <th class="p-4 font-black w-1/2">Deskripsi Pelanggaran</th>
                        <th class="p-4 font-black text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-sm font-bold text-slate-700">
                    <?php if(count($rules) > 0): ?>
                        <?php foreach($rules as $r): ?>
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition group">
                            <!-- Kolom Pasal Dipindah ke Kiri -->
                            <td class="p-4 text-blue-600 font-black whitespace-nowrap">
                                <?= htmlspecialchars($r['pasal']) ?>
                            </td>
                            <!-- Kolom Kategori di Tengah -->
                            <td class="p-4">
                                <span class="bg-indigo-50 text-indigo-700 border border-indigo-100 px-3 py-1.5 rounded-lg text-[10px] uppercase tracking-widest font-black">
                                    <?= htmlspecialchars($r['kategori_gaya']) ?>
                                </span>
                            </td>
                            <!-- Kolom Deskripsi -->
                            <td class="p-4 font-medium text-slate-500 text-xs leading-relaxed">
                                <?= htmlspecialchars($r['deskripsi']) ?>
                            </td>
                            <!-- Kolom Aksi -->
                            <td class="p-4 flex justify-center gap-2 opacity-100 md:opacity-0 group-hover:opacity-100 transition-opacity">
                                <button onclick="editModal(<?= $r['id'] ?>, '<?= htmlspecialchars($r['kategori_gaya'], ENT_QUOTES) ?>', '<?= htmlspecialchars($r['pasal'], ENT_QUOTES) ?>', '<?= htmlspecialchars($r['deskripsi'], ENT_QUOTES) ?>')" class="bg-amber-100 text-amber-700 hover:bg-amber-200 px-3 py-2 rounded-lg text-xs transition">Edit</button>
                                <a href="?del=<?= $r['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus pasal ini?')" class="bg-red-100 text-red-700 hover:bg-red-200 px-3 py-2 rounded-lg text-xs transition">Hapus</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="p-8 text-center text-slate-400 italic font-medium">Belum ada data pasal DQ.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL FORM (ADD / EDIT) -->
<div id="dqModal" class="fixed inset-0 bg-slate-900/60 hidden items-center justify-center z-50 px-4 backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-lg overflow-hidden transform scale-95 transition-transform" id="modalContent">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <h3 id="modalTitle" class="font-black text-slate-800 uppercase italic text-lg tracking-tight">Tambah Pasal DQ</h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-red-500 font-bold text-2xl leading-none">&times;</button>
        </div>
        <form method="POST" class="p-6 space-y-5">
            <input type="hidden" name="id" id="dqId">
            
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Kategori Gaya / Tipe</label>
                <select name="kategori_gaya" id="dqKategori" required class="w-full px-4 py-3 rounded-xl border border-slate-200 font-bold text-slate-700 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition">
                    <option value="START">START</option>
                    <option value="GAYA BEBAS">GAYA BEBAS</option>
                    <option value="GAYA PUNGGUNG">GAYA PUNGGUNG</option>
                    <option value="GAYA DADA">GAYA DADA</option>
                    <option value="GAYA KUPU-KUPU">GAYA KUPU-KUPU</option>
                    <option value="GAYA GANTI">GAYA GANTI</option>
                    <option value="LOMBA">LOMBA</option>
                    <option value="LAIN-LAIN">LAIN-LAIN</option>
                </select>
            </div>
            
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Nomor Pasal</label>
                <input type="text" name="pasal" id="dqPasal" required placeholder="Contoh: 5.1.5.2" class="w-full px-4 py-3 rounded-xl border border-slate-200 font-bold text-slate-700 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition">
            </div>
            
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Deskripsi Pelanggaran</label>
                <textarea name="deskripsi" id="dqDeskripsi" required rows="4" placeholder="Deskripsikan pelanggaran secara jelas..." class="w-full px-4 py-3 rounded-xl border border-slate-200 font-medium text-slate-600 text-sm focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition"></textarea>
            </div>
            
            <div class="pt-4 flex justify-end gap-3">
                <button type="button" onclick="closeModal()" class="px-5 py-3 rounded-xl font-bold text-slate-500 bg-slate-100 hover:bg-slate-200 text-xs uppercase tracking-widest transition">Batal</button>
                <button type="submit" class="px-5 py-3 rounded-xl font-bold text-white bg-blue-600 hover:bg-blue-500 text-xs uppercase tracking-widest shadow-lg shadow-blue-500/30 transition transform hover:-translate-y-1">Simpan Pasal</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    <?php if(isset($_SESSION['swal_type'])): ?>
        Swal.fire({
            toast: true, position: 'top-end', showConfirmButton: false, timer: 3000,
            icon: '<?= $_SESSION['swal_type'] ?>', title: '<?= $_SESSION['swal_msg'] ?>'
        });
        <?php unset($_SESSION['swal_type'], $_SESSION['swal_msg']); ?>
    <?php endif; ?>

    const modal = document.getElementById('dqModal');
    const modalContent = document.getElementById('modalContent');

    function openModal() {
        document.getElementById('dqId').value = '';
        document.getElementById('dqKategori').value = 'START';
        document.getElementById('dqPasal').value = '';
        document.getElementById('dqDeskripsi').value = '';
        document.getElementById('modalTitle').innerText = 'Tambah Pasal DQ';
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(() => {
            modalContent.classList.remove('scale-95');
            modalContent.classList.add('scale-100');
        }, 10);
    }

    function editModal(id, kat, pasal, desc) {
        document.getElementById('dqId').value = id;
        
        let select = document.getElementById('dqKategori');
        let exists = Array.from(select.options).some(opt => opt.value === kat);
        if (!exists) select.add(new Option(kat, kat));
        
        select.value = kat;
        document.getElementById('dqPasal').value = pasal;
        document.getElementById('dqDeskripsi').value = desc;
        document.getElementById('modalTitle').innerText = 'Edit Pasal DQ';
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(() => {
            modalContent.classList.remove('scale-95');
            modalContent.classList.add('scale-100');
        }, 10);
    }

    function closeModal() {
        modalContent.classList.remove('scale-100');
        modalContent.classList.add('scale-95');
        setTimeout(() => {
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }, 200); 
    }
</script>