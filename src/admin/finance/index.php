<?php
// FILE: src/admin/finance/index.php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';

// 1. CEK KEAMANAN
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'master')) {
    header("Location: " . BASE_URL . "/public/login.php"); exit;
}
$uid = $_SESSION['user_id'];

// 2. AMBIL EVENT AKTIF
$stmtEvent = $pdo->prepare("SELECT * FROM events WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmtEvent->execute([$uid]);
$event = $stmtEvent->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    die("Tidak ada event aktif.");
}
$eventId = $event['id'];

// Filter Status
$filterStatus = $_GET['status'] ?? 'ALL';

// 3. Ambil Rekap Pembayaran
$sql = "
    SELECT p.*, u.username, u.email 
    FROM payments p
    LEFT JOIN users u ON p.user_id = u.id
    WHERE p.event_id = :evt
";
if ($filterStatus !== 'ALL') {
    $sql .= " AND p.status = :status";
}
$sql .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':evt', $eventId);
if ($filterStatus !== 'ALL') {
    $stmt->bindValue(':status', $filterStatus);
}
$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung total lunas & pending
$stmtSum = $pdo->prepare("SELECT status, SUM(amount) as total FROM payments WHERE event_id = ? GROUP BY status");
$stmtSum->execute([$eventId]);
$sums = $stmtSum->fetchAll(PDO::FETCH_ASSOC);
$totalLunas = 0;
$totalPending = 0;
foreach ($sums as $s) {
    if (in_array(strtolower($s['status']), ['paid', 'completed'])) {
        $totalLunas += $s['total'];
    } else {
        $totalPending += $s['total'];
    }
}
$totalSemua = $totalLunas + $totalPending;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Keuangan - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .glass-panel { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2); }
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-800">

    <?php include __DIR__ . '/../../../views/layout/sidebar.php'; ?>

    <div class="ml-0 md:ml-64 p-8 pt-24 md:pt-8 transition-all duration-300 min-h-screen">
        
        <!-- Header -->
        <div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-3xl font-black text-slate-900 tracking-tight">Rekap Keuangan</h1>
                <p class="text-slate-500 mt-1">Kelola dan pantau transaksi dari event <strong><?= htmlspecialchars($event['event_name']) ?></strong></p>
            </div>
            <a href="<?= BASE_URL ?>/src/admin/dashboard.php" class="px-5 py-2.5 bg-white border border-slate-200 text-slate-700 rounded-xl hover:bg-slate-50 hover:border-slate-300 font-bold shadow-sm transition-all flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
            </a>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl p-6 text-white shadow-lg relative overflow-hidden group">
                <div class="relative z-10">
                    <p class="text-emerald-100 text-xs font-bold uppercase tracking-widest mb-1">Total Lunas</p>
                    <h2 class="text-3xl font-black">Rp <?= number_format($totalLunas, 0, ',', '.') ?></h2>
                </div>
                <div class="absolute right-[-10px] bottom-[-10px] opacity-20 group-hover:scale-110 transition-transform duration-300 text-6xl">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            
            <div class="bg-gradient-to-br from-amber-500 to-orange-500 rounded-2xl p-6 text-white shadow-lg relative overflow-hidden group">
                <div class="relative z-10">
                    <p class="text-amber-100 text-xs font-bold uppercase tracking-widest mb-1">Total Tertunda (Unpaid)</p>
                    <h2 class="text-3xl font-black">Rp <?= number_format($totalPending, 0, ',', '.') ?></h2>
                </div>
                <div class="absolute right-[-10px] bottom-[-10px] opacity-20 group-hover:scale-110 transition-transform duration-300 text-6xl">
                    <i class="fas fa-clock"></i>
                </div>
            </div>

            <div class="bg-gradient-to-br from-blue-600 to-indigo-600 rounded-2xl p-6 text-white shadow-lg relative overflow-hidden group">
                <div class="relative z-10">
                    <p class="text-blue-100 text-xs font-bold uppercase tracking-widest mb-1">Total Potensi Tagihan</p>
                    <h2 class="text-3xl font-black">Rp <?= number_format($totalSemua, 0, ',', '.') ?></h2>
                </div>
                <div class="absolute right-[-10px] bottom-[-10px] opacity-20 group-hover:scale-110 transition-transform duration-300 text-6xl">
                    <i class="fas fa-wallet"></i>
                </div>
            </div>
        </div>

        <!-- Tabel Rekap -->
        <div class="glass-panel rounded-2xl shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white/50">
                <h2 class="text-xl font-bold text-slate-800">Daftar Transaksi</h2>
                
                <form action="" method="GET" class="flex gap-2 w-full sm:w-auto">
                    <div class="relative w-full sm:w-auto">
                        <select name="status" onchange="this.form.submit()" class="appearance-none w-full bg-white border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 px-4 py-2.5 pr-10 font-medium text-slate-700 shadow-sm cursor-pointer transition-all">
                            <option value="ALL" <?= $filterStatus === 'ALL' ? 'selected' : '' ?>>Semua Transaksi</option>
                            <option value="Paid" <?= strtolower($filterStatus) === 'paid' ? 'selected' : '' ?>>Lunas (Paid)</option>
                            <option value="Unpaid" <?= strtolower($filterStatus) === 'unpaid' ? 'selected' : '' ?>>Tertunda (Unpaid)</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-500">
                            <i class="fas fa-chevron-down text-xs"></i>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="overflow-x-auto bg-white/80">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200">
                            <th class="py-4 px-6 text-xs font-extrabold text-slate-500 uppercase tracking-wider w-24">ID</th>
                            <th class="py-4 px-6 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Pendaftar</th>
                            <th class="py-4 px-6 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Tanggal</th>
                            <th class="py-4 px-6 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Status</th>
                            <th class="py-4 px-6 text-xs font-extrabold text-slate-500 uppercase tracking-wider text-right">Nominal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="5" class="py-12 text-center">
                                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 text-slate-400 mb-4">
                                    <i class="fas fa-inbox text-2xl"></i>
                                </div>
                                <p class="text-slate-500 font-medium">Tidak ada data transaksi ditemukan.</p>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($payments as $p): ?>
                                <?php
                                    $isPaid = in_array(strtolower($p['status']), ['paid', 'completed']);
                                    $badgeClass = $isPaid ? 'bg-emerald-100 text-emerald-700 border border-emerald-200' : 'bg-amber-100 text-amber-700 border border-amber-200';
                                ?>
                                <tr class="hover:bg-slate-50/80 transition-colors group">
                                    <td class="py-4 px-6">
                                        <span class="inline-flex items-center justify-center px-2 py-1 rounded-md bg-slate-100 text-slate-600 text-xs font-bold border border-slate-200">
                                            #<?= str_pad($p['id'], 4, '0', STR_PAD_LEFT) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-6">
                                        <div class="font-bold text-slate-800 flex items-center gap-2">
                                            <?= htmlspecialchars($p['username'] ?? 'User Tidak Dikenal') ?>
                                        </div>
                                        <div class="text-xs text-slate-500 flex items-center gap-1 mt-0.5">
                                            <i class="fas fa-envelope text-[10px]"></i> <?= htmlspecialchars($p['email'] ?? '-') ?>
                                        </div>
                                    </td>
                                    <td class="py-4 px-6">
                                        <div class="text-sm font-semibold text-slate-700"><?= date('d M Y', strtotime($p['created_at'])) ?></div>
                                        <div class="text-xs text-slate-500"><?= date('H:i', strtotime($p['created_at'])) ?> WIB</div>
                                    </td>
                                    <td class="py-4 px-6">
                                        <span class="inline-flex px-3 py-1 text-xs font-bold rounded-full <?= $badgeClass ?> items-center gap-1.5 shadow-sm">
                                            <i class="fas <?= $isPaid ? 'fa-check' : 'fa-clock' ?>"></i>
                                            <?= strtoupper($p['status']) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-6 text-right">
                                        <div class="font-black text-slate-800 text-base">
                                            Rp <?= number_format($p['amount'], 0, ',', '.') ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</body>
</html>
