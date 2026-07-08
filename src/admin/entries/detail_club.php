<?php
// FILE: src/admin/entries/detail_club.php
session_start();

// 1. CONFIG DATABASE
require_once __DIR__ . '/../../config/database.php';

// CEK LOGIN ADMIN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../public/login.php"); exit;
}

// Tangkap ID User & Event
$targetUserId = $_GET['id'] ?? 0;
$eventId      = $_GET['event_id'] ?? 0;

if ($targetUserId == 0 || $eventId == 0) { echo "Parameter URL tidak lengkap."; exit; }

// --- HANDLE POST AKSI ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    $payId = $_POST['payment_id'];
    $action = $_POST['action_type']; 
    $newStatus = ($action === 'approve') ? 'Paid' : 'Rejected';
    
    try {
        $stmtUpd = $pdo->prepare("UPDATE payments SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmtUpd->execute([$newStatus, $payId]);
        header("Location: detail_club.php?id=$targetUserId&event_id=$eventId"); exit;
    } catch (Exception $e) { echo "Error update: " . $e->getMessage(); }
}

// 2. AMBIL DATA
$stmtEvt = $pdo->prepare("SELECT * FROM events WHERE id = ? LIMIT 1");
$stmtEvt->execute([$eventId]);
$eventData = $stmtEvt->fetch();
if (!$eventData) die("Event tidak ditemukan");

$namaEventDB = $eventData['nama_event'] ?? $eventData['judul_event'] ?? $eventData['nama'] ?? 'Event Swimming';

try {
    $stmtSpon = $pdo->prepare("SELECT image_path FROM event_sponsors WHERE event_id = ?");
    $stmtSpon->execute([$eventId]);
    $sponsors = $stmtSpon->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) { $sponsors = []; }

$stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->execute([$targetUserId]);
$userData = $stmtUser->fetch();

$namaUser = $userData['nama_lengkap'] ?? $userData['nama'] ?? 'User ID: ' . $targetUserId;
$emailUser = $userData['email'] ?? '-';
$clubName = !empty($userData['club_name']) ? $userData['club_name'] : $namaUser;

$stmtPay = $pdo->prepare("SELECT * FROM payments WHERE user_id = ? AND event_id = ? ORDER BY created_at DESC LIMIT 1");
$stmtPay->execute([$targetUserId, $eventId]);
$payData = $stmtPay->fetch();

// 3. AMBIL ENTRIES & HITUNG
$groupedSwimmers = [];
$totalTagihan = 0;

try {
    $sqlEntries = "
        SELECT 
            ent.id as entry_id, ent.entry_time,
            s.id as swimmer_id, s.nama_atlet, s.jenis_kelamin as swimmer_gender, s.tanggal_lahir,
            en.distance, en.stroke, en.age_group, en.price as item_price
        FROM event_entries ent
        JOIN swimmers s ON ent.swimmer_id = s.id
        JOIN event_numbers en ON ent.category_id = en.id
        WHERE ent.user_id = ? AND ent.event_id = ?
        ORDER BY s.nama_atlet ASC, en.distance ASC
    ";
    
    $stmtEntries = $pdo->prepare($sqlEntries);
    $stmtEntries->execute([$targetUserId, $eventId]);
    $rawEntries = $stmtEntries->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rawEntries as $row) {
        $sid = $row['swimmer_id'];
        if (!isset($groupedSwimmers[$sid])) {
            $groupedSwimmers[$sid] = [
                'info' => [
                    'nama' => $row['nama_atlet'],
                    'gender' => $row['swimmer_gender'],
                    'lahir' => $row['tanggal_lahir']
                ],
                'items' => [],
                'subtotal' => 0
            ];
        }
        $groupedSwimmers[$sid]['items'][] = $row;
    }

    $pricingMode = $eventData['pricing_mode'] ?? 'per_item';
    foreach ($groupedSwimmers as $sid => &$data) {
        $count = count($data['items']);
        if ($pricingMode === 'package') {
            $limit = (int)($eventData['package_limit'] ?? 0);
            $basePrice = (float)($eventData['package_price'] ?? 0);
            $extraPrice = (float)($eventData['extra_price'] ?? 0);
            $data['subtotal'] = ($count <= $limit) ? $basePrice : ($basePrice + (($count - $limit) * $extraPrice));
        } else {
            $sub = 0;
            foreach($data['items'] as $item) {
                $sub += (float)$item['item_price'];
            }
            $data['subtotal'] = $sub;
        }
        $totalTagihan += $data['subtotal'];
    }
    unset($data);

    // AMBIL RELAY ENTRIES
    $relayEntries = [];
    $stmtClub = $pdo->prepare("SELECT id FROM clubs WHERE user_id = ?");
    $stmtClub->execute([$targetUserId]);
    $clubId = $stmtClub->fetchColumn();

    if ($clubId) {
        $sqlRelay = "
            SELECT 
                re.id as relay_id, re.team_name, re.seed_time,
                en.distance, en.stroke, en.age_group, en.price as item_price, en.jenis_kelamin
            FROM relay_entries re
            JOIN event_numbers en ON re.category_id = en.id
            WHERE re.club_id = ? AND re.event_id = ?
            ORDER BY re.team_name ASC, en.distance ASC
        ";
        $stmtRelay = $pdo->prepare($sqlRelay);
        $stmtRelay->execute([$clubId, $eventId]);
        $relayEntries = $stmtRelay->fetchAll(PDO::FETCH_ASSOC);

        foreach($relayEntries as $re) {
            $totalTagihan += (float)$re['item_price'];
        }
    }
} catch (Exception $e) { echo "Error Database: " . $e->getMessage(); exit; }

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<style>
    /* CSS ini hanya agar tampilan di layar Admin enak dilihat */
    .card-atlet { 
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        border-radius: 0.75rem;
        margin-bottom: 1rem;
    }
    /* Warna Gender */
    .bg-blue-500 { background-color: #3b82f6; color: white; }
    .bg-pink-500 { background-color: #ec4899; color: white; }
    .text-blue-600 { color: #2563eb; }
    .text-pink-600 { color: #db2777; }
</style>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans text-slate-800">

    <div class="max-w-7xl mx-auto mb-6 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="index.php?event_id=<?= $eventId ?>" class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-400 hover:bg-slate-900 hover:text-white transition shadow-sm">←</a>
            <div>
                <h1 class="text-2xl font-black uppercase italic text-slate-900 leading-none">Verifikasi & Tagihan</h1>
                <p class="text-xs text-slate-500 font-bold uppercase tracking-widest mt-1">
                    Klub/Kontingen: <span class="text-blue-600"><?= htmlspecialchars($clubName) ?></span>
                </p>
            </div>
        </div>
        
        <a href="print_invoice.php?id=<?= $targetUserId ?>&event_id=<?= $eventId ?>" target="_blank" class="bg-slate-900 text-white px-6 py-2 rounded-xl text-xs font-bold uppercase shadow-lg hover:bg-slate-800 flex items-center gap-2 hover:translate-y-[-2px] transition-transform">
            🖨️ Cetak PDF A4
        </a>
    </div>

    <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-8 pb-20 main-content">
        
        <div class="space-y-6 h-fit sticky top-24">
            <div class="bg-slate-900 rounded-3xl p-6 border border-slate-800 shadow-xl text-white relative overflow-hidden">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Total Tagihan Seharusnya</p>
                <h2 class="text-3xl font-black text-white tracking-tighter">Rp <?= number_format($totalTagihan,0,',','.') ?></h2>
                <div class="mt-4 pt-4 border-t border-slate-700 flex justify-between items-center">
                    <span class="text-xs font-bold text-slate-400">Total Atlet</span>
                    <span class="text-xs font-black bg-blue-600 px-2 py-1 rounded text-white"><?= count($groupedSwimmers) ?></span>
                </div>
            </div>

            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm">
                <div class="flex items-center gap-3 mb-4 border-b border-slate-100 pb-4">
                    <div class="w-10 h-10 bg-blue-50 rounded-full flex items-center justify-center text-xl">👤</div>
                    <div class="overflow-hidden">
                        <h2 class="text-sm font-black text-slate-800 uppercase leading-tight truncate"><?= htmlspecialchars($namaUser) ?></h2>
                        <p class="text-[10px] font-bold text-slate-400 truncate"><?= htmlspecialchars($emailUser) ?></p>
                    </div>
                </div>

                <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Bukti Upload</h3>
                <?php if(!empty($payData['file_path'])): ?>
                    <a href="../../../public/uploads/payments/<?= htmlspecialchars($payData['file_path']) ?>" target="_blank" class="block group relative rounded-xl overflow-hidden border border-slate-200 bg-slate-100 aspect-video flex items-center justify-center cursor-pointer shadow-sm mb-4">
                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white font-bold text-[10px] uppercase backdrop-blur-sm">Lihat File</div>
                        <?php $ext = pathinfo($payData['file_path'], PATHINFO_EXTENSION); ?>
                        <?php if(in_array(strtolower($ext), ['jpg','jpeg','png'])): ?>
                            <img src="../../../public/uploads/payments/<?= htmlspecialchars($payData['file_path']) ?>" class="object-contain w-full h-full">
                        <?php else: ?>
                            <span class="text-4xl">📄</span>
                        <?php endif; ?>
                    </a>
                    
                    <?php $statusPay = $payData['status'] ?? 'Unpaid'; if($statusPay == 'Pending' || $statusPay == 'Paid'): ?>
                        <div class="grid grid-cols-2 gap-2">
                            <?php if($statusPay == 'Pending'): ?>
                                <button onclick="openModal('approve')" class="col-span-2 bg-emerald-500 hover:bg-emerald-600 text-white py-3 rounded-xl text-xs font-black uppercase shadow-md">✓ Terima</button>
                                <button onclick="openModal('reject')" class="col-span-2 bg-red-100 hover:bg-red-200 text-red-600 py-3 rounded-xl text-xs font-black uppercase">✕ Tolak</button>
                            <?php elseif($statusPay == 'Paid'): ?>
                                <button onclick="openModal('reject')" class="col-span-2 bg-slate-100 hover:bg-red-100 text-slate-500 hover:text-red-600 border border-slate-200 py-2 rounded-lg text-[10px] font-bold uppercase">🔓 Buka Kunci</button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center py-6 border border-dashed border-slate-300 rounded-xl bg-slate-50 text-[10px] text-slate-400 font-bold">Belum ada bukti</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="lg:col-span-2">
            <?php if(empty($groupedSwimmers)): ?>
                <div class="bg-white rounded-[2.5rem] p-10 text-center border border-slate-200">
                    <p class="font-bold text-slate-400 text-sm">Belum ada atlet yang didaftarkan.</p>
                </div>
            <?php else: ?>
                <div class="space-y-6">
                <?php foreach($groupedSwimmers as $swimmerId => $data): 
                    $info = $data['info'];
                    $events = $data['items'];
                    $isMale = ($info['gender'] == 'L');
                    $bgHeader = $isMale ? 'bg-blue-50' : 'bg-pink-50'; 
                    $textColor = $isMale ? 'text-blue-600' : 'text-pink-600';
                    $iconColor = $isMale ? 'bg-blue-500' : 'bg-pink-500';
                    $subtotal = $data['subtotal'];
                ?>
                    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm card-atlet">
                        <div class="p-4 flex justify-between items-center border-b border-slate-100 <?= $bgHeader ?> card-header">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full <?= $iconColor ?> text-white flex items-center justify-center text-[10px] font-black shadow-sm">
                                    <?= $isMale ? 'P' : 'W' ?>
                                </div>
                                <div>
                                    <h3 class="text-sm font-black text-slate-800 uppercase italic leading-tight">
                                        <?= htmlspecialchars($info['nama']) ?>
                                    </h3>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                        <?= $info['gender'] ?> • <?= date('Y', strtotime($info['lahir'])) ?>
                                    </p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="block font-mono font-bold text-sm <?= $textColor ?>">
                                    Rp <?= number_format($subtotal, 0, ',', '.') ?>
                                </span>
                            </div>
                        </div>
                        <div class="divide-y divide-slate-50">
                            <?php foreach($events as $ev): ?>
                                <div class="px-4 py-3 flex justify-between items-center hover:bg-slate-50 transition-colors card-row">
                                    <div class="flex flex-col">
                                        <span class="text-xs font-bold text-slate-700 uppercase">
                                            <?= $ev['distance'] ?>M <?= strtoupper($ev['stroke']) ?>
                                        </span>
                                        <span class="text-[10px] text-slate-400 font-medium mt-0.5 uppercase">
                                            KU <?= $ev['age_group'] ?>
                                        </span>
                                    </div>
                                    <div class="text-right">
                                        <span class="font-mono text-xs font-bold text-slate-500">
                                            <?= $ev['entry_time'] ? htmlspecialchars($ev['entry_time']) : 'NT' ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if(!empty($relayEntries)): ?>
                <div class="mt-8 space-y-6">
                    <h3 class="text-sm font-black uppercase text-indigo-900 tracking-widest border-b border-slate-200 pb-2">Tim Estafet (Relay)</h3>
                    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm card-atlet">
                        <div class="divide-y divide-slate-50">
                            <?php foreach($relayEntries as $re): ?>
                                <div class="px-4 py-4 flex justify-between items-center hover:bg-indigo-50/30 transition-colors card-row">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-indigo-500 text-white flex items-center justify-center text-[10px] font-black shadow-sm">R</div>
                                        <div class="flex flex-col">
                                            <span class="text-xs font-black text-slate-800 uppercase">
                                                <?= htmlspecialchars($re['team_name']) ?>
                                            </span>
                                            <span class="text-[10px] text-slate-500 font-bold mt-0.5 uppercase">
                                                <?= $re['distance'] ?>M <?= strtoupper($re['stroke']) ?> • KU <?= $re['age_group'] ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="block font-mono font-bold text-sm text-indigo-600">
                                            Rp <?= number_format($re['item_price'], 0, ',', '.') ?>
                                        </span>
                                        <span class="font-mono text-[10px] font-bold text-slate-400">
                                            <?= $re['seed_time'] ? htmlspecialchars($re['seed_time']) : 'NT' ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<form id="actionForm" method="POST" class="hidden">
    <input type="hidden" name="payment_id" value="<?= $payData['id'] ?? '' ?>">
    <input type="hidden" name="action_type" id="modalActionInput">
</form>

<script>
function openModal(action) {
    if(confirm(action === 'approve' ? 'Terima & Kunci Data?' : 'Tolak & Minta Revisi?')) {
        document.getElementById('modalActionInput').value = action;
        document.getElementById('actionForm').submit();
    }
}
</script>