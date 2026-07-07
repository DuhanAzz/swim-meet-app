<?php
session_start();
// Aktifkan Error Reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';

// Cek Login Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../public/login.php"); exit;
}

$uid = $_SESSION['user_id']; // ID Admin (Event Organizer)

// --- 1. HANDLE UPLOAD FILE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file_upload'])) {
    try {
        $category = $_POST['category']; // 'Result', 'StartList', atau 'Other'
        $customName = trim($_POST['custom_name']);
        $file = $_FILES['file_upload'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            if ($file['error'] === UPLOAD_ERR_INI_SIZE) {
                throw new Exception("Ukuran file PDF terlalu besar untuk server Hostinger!");
            }
            throw new Exception("Upload Gagal. Kode Error: " . $file['error']);
        }

        // Validasi Format
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg'];
        
        if (!in_array($ext, $allowed)) {
            throw new Exception("Format tidak diizinkan. Gunakan PDF, Word, Excel, atau Gambar.");
        }
        
        // Validasi Ukuran (Max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception("Ukuran file terlalu besar. Maksimal 5MB.");
        }

        // Siapkan Folder
        $targetDir = __DIR__ . "/../../public/uploads/documents/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
        if (!is_writable($targetDir)) { @chmod($targetDir, 0755); if (!is_writable($targetDir)) throw new Exception("Error: Direktori upload documents tidak writeable oleh server."); }

        // Nama unik untuk file di server (menghindari duplikasi)
        $fileSaveName = "DOC_" . $category . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
        
        if (move_uploaded_file($file['tmp_name'], $targetDir . $fileSaveName)) {
            chmod($targetDir . $fileSaveName, 0644); // Amankan file
            $dbPath = "uploads/documents/" . $fileSaveName;
            
            // Simpan ke Database
            $stmt = $pdo->prepare("INSERT INTO event_results (event_id, file_name, file_path, category) VALUES (?, ?, ?, ?)");
            $stmt->execute([$uid, $customName, $dbPath, $category]);

            $_SESSION['toast_type'] = 'success';
            $_SESSION['toast_message'] = 'Dokumen berhasil diunggah!';
        } else {
            throw new Exception("Gagal memindahkan file ke folder server.");
        }

    } catch (Exception $e) {
        $_SESSION['toast_type'] = 'error';
        $_SESSION['toast_message'] = 'Gagal: ' . $e->getMessage();
    }
    header("Location: upload.php"); exit;
}

// --- 2. HANDLE HAPUS FILE ---
if (isset($_POST['delete_id'])) {
    $delId = $_POST['delete_id'];
    
    $stmt = $pdo->prepare("SELECT file_path FROM event_results WHERE id = ? AND event_id = ?");
    $stmt->execute([$delId, $uid]);
    $row = $stmt->fetch();

    if ($row) {
        $fullPath = __DIR__ . "/../../public/" . $row['file_path'];
        if (file_exists($fullPath)) unlink($fullPath);
        
        $pdo->prepare("DELETE FROM event_results WHERE id = ?")->execute([$delId]);
        
        $_SESSION['toast_type'] = 'success';
        $_SESSION['toast_message'] = 'Dokumen telah dihapus.';
    }
    header("Location: upload.php"); exit;
}

// --- 3. AMBIL DATA FILE ---
$stmtFiles = $pdo->prepare("SELECT * FROM event_results WHERE event_id = ? ORDER BY created_at DESC");
$stmtFiles->execute([$uid]);
$dataFiles = $stmtFiles->fetchAll();

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tight italic">Document Management</h1>
            <p class="text-sm text-slate-500 font-medium">Kelola dokumen Persyaratan, Buku Acara, dan Hasil Lomba Anda.</p>
        </div>
        <a href="../../public/results.php?event_id=<?= $uid ?>&cat=StartList" target="_blank" class="bg-white border border-slate-300 text-slate-700 px-4 py-2.5 rounded-xl font-bold text-xs hover:bg-slate-50 shadow-sm flex items-center gap-2 transition">
            <span>👁️</span> Cek Tampilan Publik
        </a>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
        
        <div class="xl:col-span-1">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden h-fit">
                <div class="bg-slate-900 px-6 py-4 border-b border-slate-800">
                    <h3 class="text-white font-black text-xs uppercase tracking-widest flex items-center gap-2">
                        <span class="text-lg">📤</span> Upload Berkas Baru
                    </h3>
                </div>
                <div class="p-6">
                    <form method="POST" enctype="multipart/form-data" class="space-y-5">
                        
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 tracking-wider">Pilih Berkas (PDF/Excel/Gambar)</label>
                            <label for="file_input" class="flex flex-col items-center justify-center w-full h-32 border-2 border-slate-200 border-dashed rounded-2xl cursor-pointer bg-slate-50 hover:bg-blue-50 hover:border-blue-300 transition relative group">
                                <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                    <span class="text-3xl mb-2 group-hover:scale-110 transition">📁</span>
                                    <p class="text-[10px] text-slate-500 font-bold uppercase">Klik atau Seret Berkas ke Sini</p>
                                </div>
                                <input id="file_input" name="file_upload" type="file" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" required />
                            </label>
                            <div id="file_name_display" class="text-[10px] text-center mt-2 text-blue-600 font-black uppercase tracking-tighter"></div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1 tracking-wider">Judul Dokumen (Akan Tampil di Publik)</label>
                            <input type="text" name="custom_name" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm font-bold focus:ring-2 focus:ring-blue-600 outline-none bg-slate-50 transition" placeholder="Misal: Hasil Sesi 1 / Undangan Lomba" required>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1 tracking-wider">Kategori (Tujuan Tombol)</label>
                            <select name="category" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm font-bold bg-slate-50 focus:ring-2 focus:ring-blue-600 outline-none cursor-pointer">
                                <option value="Other">📄 Persyaratan (Manual Book)</option>
                                <option value="StartList">📖 Buku Acara (Start List)</option>
                                <option value="Result">🏆 Hasil Lomba (Official Result)</option>
                            </select>
                            <p class="text-[9px] text-slate-400 mt-2 italic">* Pilih kategori agar file muncul di tombol yang tepat pada halaman publik.</p>
                        </div>

                        <button type="submit" class="w-full bg-blue-600 text-white font-black py-4 rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-100 flex justify-center items-center gap-2 uppercase text-xs tracking-widest transform hover:-translate-y-0.5">
                            <span>🚀</span> Unggah Sekarang
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="xl:col-span-2">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex justify-between items-center">
                    <h3 class="font-black text-slate-700 text-xs uppercase tracking-widest">Riwayat Unggahan (<?= count($dataFiles) ?>)</h3>
                </div>
                
                <?php if(empty($dataFiles)): ?>
                    <div class="p-20 text-center">
                        <span class="text-5xl block mb-4 grayscale opacity-20">📂</span>
                        <p class="text-slate-400 font-bold uppercase text-xs">Belum ada dokumen yang diunggah.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-slate-400 font-black uppercase text-[10px] border-b">
                                <tr>
                                    <th class="px-6 py-4">Dokumen</th>
                                    <th class="px-6 py-4">Kategori</th>
                                    <th class="px-6 py-4">Tanggal</th>
                                    <th class="px-6 py-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach($dataFiles as $f): ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-6 py-4">
                                        <div class="font-black text-slate-800 uppercase text-xs"><?= htmlspecialchars($f['file_name']) ?></div>
                                        <a href="<?= BASE_URL ?>/<?= $f['file_path'] ?>" target="_blank" class="text-[10px] text-blue-600 font-bold hover:underline flex items-center gap-1 mt-1">
                                            VIEW FILE &raquo;
                                        </a>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php 
                                            $badge = match($f['category']) {
                                                'Result' => 'bg-green-100 text-green-700',
                                                'StartList' => 'bg-blue-100 text-blue-700',
                                                'Other' => 'bg-purple-100 text-purple-700',
                                                default => 'bg-gray-100 text-gray-700'
                                            };
                                            $label = match($f['category']) {
                                                'Result' => 'HASIL',
                                                'StartList' => 'BUKU ACARA',
                                                'Other' => 'PERSYARATAN',
                                                default => $f['category']
                                            };
                                        ?>
                                        <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase <?= $badge ?>">
                                            <?= $label ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase">
                                        <?= date('d M Y', strtotime($f['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <form method="POST" onsubmit="return confirm('Hapus dokumen ini secara permanen?')">
                                            <input type="hidden" name="delete_id" value="<?= $f['id'] ?>">
                                            <button class="text-red-500 hover:text-white hover:bg-red-500 p-2.5 rounded-xl transition shadow-sm border border-red-50 border-transparent">
                                                🗑️
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
// Tampilkan nama file saat dipilih
document.getElementById('file_input').addEventListener('change', function(){
    const fileName = this.files[0] ? this.files[0].name : '';
    document.getElementById('file_name_display').textContent = fileName ? 'Terpilih: ' + fileName : '';
});
</script>