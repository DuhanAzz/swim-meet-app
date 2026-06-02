<?php
// FILE: src/user/kompetisi/explore.php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../../public/login.php"); exit;
}

// 1. Ambil Event yang masih Buka/Akan Datang
try {
    $sql = "SELECT e.id as event_id, e.event_name as nama_event, e.poster_image, e.logo_left as banner_image, 
                   e.event_location as lokasi, e.event_date_start as tanggal_pelaksanaan, e.event_status as status, 
                   u.nama_lengkap as penyelenggara
            FROM events e
            LEFT JOIN users u ON e.user_id = u.id
            WHERE e.event_status IN ('Active', 'Open', 'Upcoming', 'Registration')
            ORDER BY e.event_date_start ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $competitions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Ambil Dokumen Khusus (Juknis & Formulir)
    $documentsByEvent = [];
    if (!empty($competitions)) {
        $eventIds = array_column($competitions, 'event_id');
        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
        
        $docSql = "SELECT event_id, judul_file, file_path, kategori FROM documents 
                   WHERE event_id IN ($placeholders) 
                   AND kategori IN ('JUKNIS', 'FORMULIR') 
                   ORDER BY kategori DESC";
        $docStmt = $pdo->prepare($docSql);
        $docStmt->execute($eventIds);
        $docs = $docStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($docs as $d) {
            $documentsByEvent[$d['event_id']][] = $d;
        }
    }
} catch (PDOException $e) {
    die("Error mengambil data event: " . $e->getMessage());
}

include __DIR__ . '/../../../views/layout/topbar.php';
include __DIR__ . '/../../../views/layout/sidebar.php';
?>

<div class="p-6 sm:ml-64 pt-24 min-h-screen bg-slate-50 font-sans">
    <div class="max-w-5xl mx-auto">
        <div class="bg-blue-600 rounded-[2rem] p-8 md:p-10 mb-8 shadow-xl shadow-blue-200 text-white relative overflow-hidden flex flex-col justify-center">
            <div class="absolute -right-10 -bottom-10 text-9xl opacity-20">🏊‍♂️</div>
            <div class="relative z-10">
                <h1 class="text-3xl md:text-4xl font-black uppercase tracking-tighter italic mb-2">Jelajah Kompetisi</h1>
                <p class="text-blue-100 font-bold text-sm tracking-wide">Pelajari JUKNIS & Daftarkan atlet Anda pada event terbaik.</p>
            </div>
        </div>

        <?php if(empty($competitions)): ?>
            <div class="bg-white p-12 text-center rounded-3xl border-2 border-dashed border-slate-200 shadow-sm">
                <span class="text-6xl block mb-4 opacity-30">📭</span>
                <p class="text-slate-500 font-black uppercase tracking-widest">Belum ada kompetisi yang dibuka.</p>
            </div>
        <?php else: ?>
            <div class="flex flex-col space-y-6">
                <?php foreach($competitions as $comp): 
                    $tgl = !empty($comp['tanggal_pelaksanaan']) ? date('d F Y', strtotime($comp['tanggal_pelaksanaan'])) : 'TBA';
                    $statusLomba = strtoupper($comp['status'] ?? 'UPCOMING');
                    
                   // 🚀 LOGIKA GAMBAR ULTIMATE (Auto-Extract)
                    $imgSrc = 'https://images.unsplash.com/photo-1530549387789-4c100476466c?w=800&auto=format&fit=crop';
                    $dbPath = !empty($comp['poster_image']) ? $comp['poster_image'] : (!empty($comp['banner_image']) ? $comp['banner_image'] : '');
                    
                    if (!empty($dbPath)) {
                        if (filter_var($dbPath, FILTER_VALIDATE_URL)) {
                            $imgSrc = $dbPath; // Jika sudah berupa link http/https
                        } else {
                            // Cari kata 'uploads', 'assets', atau 'img' dan ambil sisanya
                            if (preg_match('/(uploads\/.*|assets\/.*|img\/.*)/i', $dbPath, $matches)) {
                                $imgSrc = '/swim-meet/' . $matches[1];
                            } else {
                                // Jika tidak ada kata di atas, bersihkan ../ dan garing di depan
                                $cleanPath = preg_replace('/^(\.\.\/)+/', '', $dbPath);
                                $cleanPath = ltrim($cleanPath, '/');
                                $imgSrc = '/swim-meet/' . $cleanPath;
                            }
                        }
                    }
                ?>
                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-xl transition-all flex flex-col md:flex-row group">
                    
                    <div class="w-full md:w-56 bg-slate-900 relative shrink-0 aspect-[4/3] md:aspect-auto md:min-h-[220px]">
                        <div class="absolute top-3 left-3 z-20 bg-emerald-500 text-white px-2 py-1 rounded text-[8px] font-black uppercase tracking-widest shadow-md">
                            <?= $statusLomba ?>
                        </div>
                        <img src="<?= htmlspecialchars($imgSrc) ?>" class="w-full h-full object-cover opacity-90 group-hover:opacity-100 group-hover:scale-105 transition-all duration-700">
                    </div>

                    <div class="p-5 md:p-6 flex-1 flex flex-col justify-between">
                        <div>
                            <h2 class="text-xl font-black uppercase text-slate-800 italic leading-tight mb-2"><?= htmlspecialchars($comp['nama_event']) ?></h2>
                            <div class="flex flex-wrap gap-4 text-xs font-bold text-slate-500 uppercase tracking-wide mb-4">
                                <div class="flex items-center gap-1.5"><span class="text-sm">📅</span> <?= $tgl ?></div>
                                <div class="flex items-center gap-1.5 line-clamp-1"><span class="text-sm">📍</span> <?= htmlspecialchars($comp['lokasi'] ?? 'TBA') ?></div>
                            </div>
                            
                            <div class="border-t border-slate-100 pt-3">
                                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2">📥 File Pendaftaran:</p>
                                <div class="flex flex-wrap gap-2">
                                    <?php if(!empty($documentsByEvent[$comp['event_id']])): ?>
                                        <?php foreach($documentsByEvent[$comp['event_id']] as $doc): 
                                            $cat = strtoupper($doc['kategori']);
                                            $btnStyle = ($cat == 'JUKNIS') ? 'bg-blue-50 text-blue-700 hover:bg-blue-600 hover:text-white border-blue-200' : 'bg-green-50 text-green-700 hover:bg-green-600 hover:text-white border-green-200';
                                        ?>
                                            <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="px-2.5 py-1 rounded border text-[9px] font-black tracking-widest uppercase transition-colors <?= $btnStyle ?>">
                                                📄 <?= htmlspecialchars($doc['judul_file'] ?? $cat) ?>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-[9px] font-black text-slate-300 uppercase tracking-widest">Belum ada Juknis</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 flex justify-end">
                            <a href="detail.php?event_id=<?= $comp['event_id'] ?>" class="px-6 py-2.5 rounded-xl bg-slate-900 text-white font-black uppercase text-[10px] tracking-widest hover:bg-blue-600 transition shadow-lg whitespace-nowrap">
                                Info Lomba & Daftar &rarr;
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>