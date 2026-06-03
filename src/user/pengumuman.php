<?php
// FILE: src/user/pengumuman.php
session_start();
require_once __DIR__ . '/../../src/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../public/login.php"); exit;
}

$user_id = $_SESSION['user_id'];

// 1. Ambil event yang pernah diikuti oleh User ini (punya entri pendaftaran)
$sql = "SELECT DISTINCT e.id as event_id, e.event_name, e.event_location, e.event_date_start, e.event_status, e.is_result_published, e.poster_image, e.logo_left 
        FROM events e
        JOIN event_entries ee ON e.id = ee.event_id
        WHERE ee.user_id = ? AND e.event_status != 'draft' 
        ORDER BY e.event_date_start DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Ambil Dokumen Khusus (BUKU ACARA & BUKU HASIL SAJA) untuk event yang diikuti
$documentsByEvent = [];
if (!empty($events)) {
    $eventIds = array_column($events, 'event_id');
    $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
    
    $docSql = "SELECT event_id, judul_file, file_path, kategori FROM documents 
               WHERE event_id IN ($placeholders) 
               AND kategori IN ('buku_acara', 'buku_hasil') 
               ORDER BY kategori ASC";
    $docStmt = $pdo->prepare($docSql);
    $docStmt->execute($eventIds);
    $docs = $docStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($docs as $d) {
        $documentsByEvent[$d['event_id']][] = $d;
    }
}

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    <div class="max-w-5xl mx-auto">
        
        <div class="bg-slate-900 text-white p-8 md:p-10 rounded-[2rem] shadow-xl mb-8 relative overflow-hidden flex flex-col justify-center">
            <div class="absolute -right-10 -top-10 text-9xl opacity-10">📢</div>
            <div class="relative z-10">
                <span class="inline-block px-3 py-1 bg-blue-600 text-white text-[9px] font-black uppercase tracking-widest rounded-lg mb-3">Dashboard Peserta</span>
                <h1 class="text-3xl md:text-4xl font-black uppercase italic leading-tight mb-2">Pusat Informasi Lomba</h1>
                <p class="text-sm text-slate-300 font-bold tracking-wide">Unduh Buku Acara (Startlist), Buku Hasil, dan pantau Live Result dari event yang Anda ikuti.</p>
            </div>
        </div>

        <?php if(empty($events)): ?>
            <div class="bg-white p-16 text-center rounded-3xl border border-slate-200 border-dashed shadow-sm">
                <span class="text-6xl block mb-4 opacity-30">📭</span>
                <h2 class="text-lg font-black text-slate-500 uppercase italic mb-2">Belum Mengikuti Event</h2>
                <p class="text-sm text-slate-400 font-bold">Silakan daftar atlet Anda pada event yang tersedia di menu Jelajah Kompetisi.</p>
                <a href="kompetisi/explore.php" class="inline-block mt-6 px-6 py-3 bg-blue-600 text-white font-black text-xs uppercase tracking-widest rounded-xl hover:bg-slate-900 transition">Mulai Jelajah &rarr;</a>
            </div>
        <?php else: ?>
            <div class="flex flex-col space-y-6">
                <?php foreach($events as $e): 
                    // Status Badge
                    $rawStatus = strtolower($e['event_status'] ?? 'upcoming');
                    if ($rawStatus == 'open') { $badge = "bg-emerald-500"; $statusText = "OPEN"; } 
                    elseif ($rawStatus == 'closed' || $rawStatus == 'running') { $badge = "bg-red-600 animate-pulse"; $statusText = "RUNNING"; } 
                    elseif ($rawStatus == 'done') { $badge = "bg-slate-600"; $statusText = "FINISHED"; } 
                    else { $badge = "bg-amber-500"; $statusText = "UPCOMING"; }

                  // 🚀 LOGIKA GAMBAR ULTIMATE (Auto-Extract)
                    $imgSrc = 'https://images.unsplash.com/photo-1530549387789-4c100476466c?w=800&auto=format&fit=crop';
                    $dbPath = !empty($e['poster_image']) ? $e['poster_image'] : (!empty($e['logo_left']) ? $e['logo_left'] : '');
                    
                    if (!empty($dbPath)) {
                        if (filter_var($dbPath, FILTER_VALIDATE_URL)) {
                            $imgSrc = $dbPath;
                        } else {
                            if (preg_match('/(uploads\/.*|assets\/.*|img\/.*)/i', $dbPath, $matches)) {
                                $imgSrc = '/swim-meet/' . $matches[1];
                            } else {
                                $cleanPath = preg_replace('/^(\.\.\/)+/', '', $dbPath);
                                $cleanPath = ltrim($cleanPath, '/');
                                $imgSrc = '/swim-meet/' . $cleanPath;
                            }
                        }
                    }
                ?>
                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden hover:shadow-xl transition-all duration-300 flex flex-col md:flex-row group">
                    
                    <div class="w-full md:w-56 bg-slate-900 relative shrink-0 aspect-[4/3] md:aspect-auto md:min-h-[200px]">
                        <div class="absolute top-3 left-3 z-20 <?= $badge ?> text-white px-2 py-1 rounded text-[8px] font-black uppercase tracking-widest shadow-md">
                            <?= $statusText ?>
                        </div>
                        <img src="<?= htmlspecialchars($imgSrc) ?>" class="w-full h-full object-cover opacity-90 group-hover:opacity-100 group-hover:scale-105 transition-all duration-700">
                    </div>

                    <div class="p-5 md:p-6 flex-1 flex flex-col justify-between">
                        <div>
                            <h3 class="text-xl font-black uppercase text-slate-800 mb-2 italic leading-tight line-clamp-2"><?= htmlspecialchars($e['event_name']) ?></h3>
                            <div class="flex flex-wrap gap-4 text-xs font-bold text-slate-500 uppercase tracking-wide mb-4">
                                <div class="flex items-center gap-1.5"><span class="text-sm">📅</span> <?= date('d M Y', strtotime($e['event_date_start'])) ?></div>
                                <div class="flex items-center gap-1.5 line-clamp-1"><span class="text-sm">📍</span> <?= htmlspecialchars($e['event_location']) ?></div>
                            </div>

                            <div class="border-t border-slate-100 pt-3">
                                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2">📥 Berkas Perlombaan:</p>
                                <div class="flex flex-wrap gap-2 mb-3">
                                    <?php if(!empty($documentsByEvent[$e['event_id']])): ?>
                                        <?php foreach($documentsByEvent[$e['event_id']] as $doc): 
                                            $cat = strtoupper($doc['kategori']);
                                            $btnStyle = ($cat == 'BUKU_ACARA') ? 'bg-amber-50 text-amber-700 hover:bg-amber-500 hover:text-white border-amber-200' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white border-emerald-200';
                                        ?>
                                            <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="px-2.5 py-1 rounded border text-[9px] font-black tracking-widest uppercase transition-colors <?= $btnStyle ?>">
                                                📄 <?= htmlspecialchars($doc['judul_file'] ?? $cat) ?>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-[9px] font-black text-slate-300 uppercase tracking-widest">Belum ada berkas hasil/startlist</span>
                                    <?php endif; ?>
                                </div>

                                <!-- 🚀 TEROBOSAN: TOMBOL REKAP JADWAL ANAK DI BAWAH BERKAS -->
                                <a href="recap_starting_list.php?event_id=<?= $e['event_id'] ?>" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 text-blue-700 hover:bg-blue-600 hover:text-white border border-blue-200 hover:border-blue-600 rounded text-[10px] font-black uppercase tracking-widest shadow-sm transition-colors">
                                    📝 Rekap Starting List Personal
                                </a>
                            </div>
                        </div>

                        <div class="mt-4 flex justify-end">
                            <?php if ($e['is_result_published'] == 1): ?>
                                <a href="live_result.php?event_id=<?= $e['event_id'] ?>" class="px-6 py-2.5 rounded-xl bg-slate-900 text-white hover:bg-blue-600 transition shadow-lg text-[10px] font-black tracking-widest uppercase whitespace-nowrap">
                                    <span class="animate-pulse mr-1">🏆</span> Live Result &rarr;
                                </a>
                            <?php else: ?>
                                <div class="px-6 py-2.5 rounded-xl bg-slate-50 border-2 border-slate-100 text-slate-400 cursor-not-allowed text-[10px] font-black tracking-widest uppercase whitespace-nowrap" title="Live Result belum dipublikasikan">
                                    <span>🔒</span> Live Result Tertutup
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>