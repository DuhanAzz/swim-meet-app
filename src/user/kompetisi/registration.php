<?php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') die("Akses Ditolak.");

$admin_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

// 1. INFO KOMPETISI & KLUB
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role='admin'");
$stmt->execute([$admin_id]);
$comp = $stmt->fetch();
if(!$comp) die("Kompetisi tidak ditemukan.");

$stmtClub = $pdo->prepare("SELECT id, nama_klub FROM clubs WHERE user_id = ?");
$stmtClub->execute([$user_id]);
$club = $stmtClub->fetch();
$club_id = $club['id'] ?? 0;
$club_name = $club['nama_klub'] ?? 'Unknown Club';

// --- LOGIKA HITUNG BIAYA ---
$sqlBill = "SELECT COUNT(se.id) as total_entries, SUM(e.harga_pendaftaran) as total_biaya 
            FROM swimmer_events se 
            JOIN events e ON se.event_id = e.id 
            JOIN swimmers s ON se.swimmer_id = s.id
            WHERE e.user_id = ? AND s.club_id = ?";
$stmtBill = $pdo->prepare($sqlBill);
$stmtBill->execute([$admin_id, $club_id]);
$billData = $stmtBill->fetch();
$totalEntries = $billData['total_entries'] ?? 0;
$totalCost = $billData['total_biaya'] ?? 0;

$stmtInv = $pdo->prepare("SELECT * FROM invoices WHERE club_id = ? AND admin_id = ? ORDER BY id DESC LIMIT 1");
$stmtInv->execute([$club_id, $admin_id]);
$lastInvoice = $stmtInv->fetch();
$isSubmitted = ($lastInvoice && $lastInvoice['status'] == 'unpaid');

// 2. ACTION: CHECKOUT (KIRIM)
if (isset($_POST['action']) && $_POST['action'] == 'checkout') {
    if ($totalEntries == 0) {
        echo "<script>alert('Belum ada atlet yang didaftarkan!'); window.location.href='registration.php?id=$admin_id';</script>"; exit;
    }
    if ($isSubmitted) {
        $upd = $pdo->prepare("UPDATE invoices SET jumlah_tagihan = ?, tanggal_terbit = CURRENT_DATE WHERE id = ?");
        $upd->execute([$totalCost, $lastInvoice['id']]);
    } else {
        $judul = "Pendaftaran " . $comp['nama_lengkap'];
        $ins = $pdo->prepare("INSERT INTO invoices (club_id, admin_id, judul_tagihan, jumlah_tagihan, status) VALUES (?, ?, ?, ?, 'unpaid')");
        $ins->execute([$club_id, $admin_id, $judul, $totalCost]);
    }
    $_SESSION['toast_type'] = 'success';
    $_SESSION['toast_message'] = 'Pendaftaran berhasil dikirim! Silakan cek menu Pembayaran.';
    header("Location: ../pembayaran.php"); exit;
}

// 3. ACTION: MODIFIKASI TIM
if (isset($_POST['action'])) {
    if ($_POST['action'] == 'add_team') {
        if (!empty($_POST['swimmer_ids'])) {
            $ins = $pdo->prepare("INSERT INTO event_participants (event_organizer_id, club_id, swimmer_id) VALUES (?, ?, ?)");
            foreach($_POST['swimmer_ids'] as $sid) {
                $cek = $pdo->prepare("SELECT id FROM event_participants WHERE event_organizer_id=? AND swimmer_id=?");
                $cek->execute([$admin_id, $sid]);
                if($cek->rowCount() == 0) $ins->execute([$admin_id, $club_id, $sid]);
            }
        }
    } elseif ($_POST['action'] == 'remove_team') {
        $pdo->prepare("DELETE FROM event_participants WHERE event_organizer_id=? AND swimmer_id=?")->execute([$admin_id, $_POST['swimmer_id']]);
        $pdo->prepare("DELETE FROM swimmer_events WHERE swimmer_id=? AND event_id IN (SELECT id FROM events WHERE user_id=?)")->execute([$_POST['swimmer_id'], $admin_id]);
    }
    header("Location: registration.php?id=" . $admin_id); exit;
}

// 4. ACTION: SIMPAN MATRIKS
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['action'])) {
    $swimmer_id = $_POST['swimmer_id'];
    $pdo->prepare("DELETE FROM swimmer_events WHERE swimmer_id = ? AND event_id IN (SELECT id FROM events WHERE user_id = ?)")->execute([$swimmer_id, $admin_id]);

    if(isset($_POST['events']) && is_array($_POST['events'])) {
        $ins = $pdo->prepare("INSERT INTO swimmer_events (swimmer_id, event_id, entry_time) VALUES (?, ?, ?)");
        foreach($_POST['events'] as $eid => $val) {
            $time = $_POST['times'][$eid] ?? '99:99.99'; 
            $ins->execute([$swimmer_id, $eid, $time]);
        }
    }
    header("Location: registration.php?id=" . $admin_id); exit;
}

// --- LOAD DATA ---
$stmtEvents = $pdo->prepare("SELECT * FROM events WHERE user_id = ? ORDER BY jarak ASC, gaya ASC");
$stmtEvents->execute([$admin_id]);
$rawEvents = $stmtEvents->fetchAll();

// Grouping Logic
$mergedColumns = [];
$distanceOrder = ['25', '50', '100', '200', '400', '800', '1500', '4x50', '4x100', '4x200'];
foreach($rawEvents as $ev) {
    $key = $ev['jarak'] . '_' . $ev['gaya'];
    if (!isset($mergedColumns[$key])) $mergedColumns[$key] = ['jarak' => $ev['jarak'], 'gaya' => $ev['gaya'], 'ids' => []];
    $mergedColumns[$key]['ids'][$ev['jenis_kelamin']] = ['id' => $ev['id'], 'nomor' => $ev['nomor_acara']];
}
uksort($mergedColumns, function($a, $b) use ($mergedColumns, $distanceOrder) {
    $posA = array_search($mergedColumns[$a]['jarak'], $distanceOrder);
    $posB = array_search($mergedColumns[$b]['jarak'], $distanceOrder);
    if ($posA === false) return 1; if ($posB === false) return -1;
    if ($posA == $posB) return strcmp($mergedColumns[$a]['gaya'], $mergedColumns[$b]['gaya']);
    return $posA - $posB;
});
$finalGrouped = [];
foreach($mergedColumns as $col) $finalGrouped[$col['jarak']][] = $col;

$stmtTeam = $pdo->prepare("SELECT s.* FROM swimmers s JOIN event_participants ep ON s.id = ep.swimmer_id WHERE ep.event_organizer_id = ? AND ep.club_id = ? ORDER BY s.nama_atlet ASC");
$stmtTeam->execute([$admin_id, $club_id]);
$myTeam = $stmtTeam->fetchAll();

$stmtAvail = $pdo->prepare("SELECT * FROM swimmers WHERE club_id = ? AND id NOT IN (SELECT swimmer_id FROM event_participants WHERE event_organizer_id = ?)");
$stmtAvail->execute([$club_id, $admin_id]);
$availSwimmers = $stmtAvail->fetchAll();

$stmtReg = $pdo->prepare("SELECT swimmer_id, event_id, entry_time FROM swimmer_events se JOIN events e ON se.event_id = e.id WHERE e.user_id = ?");
$stmtReg->execute([$admin_id]);
$mapReg = [];
foreach($stmtReg->fetchAll() as $r) $mapReg[$r['swimmer_id']][$r['event_id']] = $r['entry_time'];

$stmtRec = $pdo->prepare("SELECT swimmer_id, nomor_lomba, waktu FROM swimmer_records WHERE swimmer_id IN (SELECT id FROM swimmers WHERE club_id = ?)");
$stmtRec->execute([$club_id]);
$mapRec = [];
foreach($stmtRec->fetchAll() as $rec) {
    $key = strtolower(str_replace([' ', 'm'], '', $rec['nomor_lomba'])); 
    $mapRec[$rec['swimmer_id']][$key] = $rec['waktu'];
}

include __DIR__ . '/../../../views/layout/topbar.php'; 
include __DIR__ . '/../../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 mt-16 bg-slate-50 min-h-screen font-sans pb-32">
    
    <div class="mb-6">
        <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tight mb-1"><?= htmlspecialchars($comp['nama_lengkap']) ?></h1>
        <div class="flex items-center gap-4 text-sm text-slate-500">
            <a href="explore.php" class="text-blue-600 font-bold hover:underline">Kompetisi</a>
            <span>/</span>
            <span class="text-slate-800 font-bold">Registrasi Tim</span>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
            <div>
                <h2 class="text-lg font-bold text-slate-700">Matriks Pendaftaran</h2>
                <div class="flex gap-4 text-xs mt-1">
                    <span class="flex items-center gap-1"><span class="w-3 h-3 bg-blue-50 border border-blue-200 rounded"></span> Terdaftar</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 bg-slate-100 rounded"></span> Tidak Ikut</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 bg-slate-200 diagonal-stripe rounded"></span> Blocked</span>
                </div>
            </div>
            <button onclick="document.getElementById('modalAddTeam').classList.remove('hidden')" class="bg-blue-50 text-blue-600 border border-blue-200 px-4 py-2 rounded-lg text-xs font-bold hover:bg-blue-100 transition shadow flex items-center gap-2">
                <span class="text-lg">+</span> Atur Pasukan
            </button>
        </div>

        <div class="overflow-x-auto border border-slate-200 rounded-lg relative">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
                    <tr>
                        <th rowspan="2" class="px-4 py-3 sticky left-0 bg-slate-100 z-30 w-28 text-center border-r shadow">PENDAFTARAN</th>
                        <th rowspan="2" class="px-4 py-3 sticky left-28 bg-slate-100 z-30 w-48 border-r shadow">Nama Atlet</th>
                        <th rowspan="2" class="px-4 py-3 w-16 text-center border-r">Umur</th>
                        <th rowspan="2" class="px-4 py-3 w-16 text-center border-r">Gender</th>
                        <th rowspan="2" class="px-4 py-3 w-40 text-center border-r">Klub</th>
                        <?php foreach($finalGrouped as $dist => $cols): ?>
                            <th colspan="<?= count($cols) ?>" class="px-4 py-2 text-center border-r border-b bg-slate-100 text-slate-700 font-black text-sm"><?= $dist ?>m</th>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <?php foreach($finalGrouped as $dist => $cols): foreach($cols as $col): ?>
                            <th class="px-4 py-2 text-center border-r min-w-[80px] bg-white text-[10px] text-slate-600">
                                <?= str_replace(['Gaya ', 'Renang '], '', $col['gaya']) ?>
                            </th>
                        <?php endforeach; endforeach; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if(empty($myTeam)): ?>
                        <tr><td colspan="100" class="p-12 text-center text-slate-400">Tim masih kosong. Klik tombol 'Atur Pasukan'.</td></tr>
                    <?php else: ?>
                        <?php foreach($myTeam as $s): 
                            $bday = new DateTime($s['tanggal_lahir']);
                            $age = (new DateTime())->diff($bday)->y;
                            
                            // JSON Data
                            $suggestions = [];
                            $allEventList = [];
                            foreach($rawEvents as $re) {
                                $key = strtolower(str_replace([' ', 'm'], '', $re['jarak'] . $re['gaya'])); 
                                $suggestions[$re['id']] = $mapRec[$s['id']][$key] ?? '99:99.99';
                                $allEventList[] = $re;
                            }
                            $jsonAtlet = htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8');
                            $jsonReg = htmlspecialchars(json_encode($mapReg[$s['id']] ?? []), ENT_QUOTES, 'UTF-8');
                            $jsonSug = htmlspecialchars(json_encode($suggestions), ENT_QUOTES, 'UTF-8');
                            $jsonEvents = htmlspecialchars(json_encode($allEventList), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr class="hover:bg-slate-50 transition group">
                            <td class="px-2 py-3 sticky left-0 bg-white group-hover:bg-slate-50 z-20 text-center border-r border-slate-200 shadow">
                                <button type="button" onclick="openEditFromButton(this)" data-atlet="<?= $jsonAtlet ?>" data-reg="<?= $jsonReg ?>" data-sug="<?= $jsonSug ?>" data-events="<?= $jsonEvents ?>" class="bg-green-500 hover:bg-green-600 text-white text-[10px] font-bold py-1.5 px-3 rounded shadow active:scale-95 cursor-pointer relative z-50 w-full">+ PILIH LOMBA</button>
                            </td>
                            <td class="px-4 py-3 sticky left-28 bg-white group-hover:bg-slate-50 z-20 font-bold text-slate-700 border-r border-slate-200 shadow"><?= htmlspecialchars($s['nama_atlet']) ?></td>
                            <td class="px-4 py-3 text-center border-r border-slate-200"><?= $age ?></td>
                            <td class="px-4 py-3 text-center border-r border-slate-200"><?= $s['jenis_kelamin'] ?></td>
                            <td class="px-4 py-3 text-center border-r border-slate-200 text-xs text-slate-500 truncate max-w-[150px]"><?= htmlspecialchars($club_name) ?></td>
                            <?php foreach($mergedColumns as $col): 
                                $targetEvent = (isset($col['ids'][$s['jenis_kelamin']])) ? $col['ids'][$s['jenis_kelamin']] : ($col['ids']['Campuran'] ?? null);
                                $cellContent = ''; $cellClass = '';
                                if ($targetEvent) {
                                    $isReg = isset($mapReg[$s['id']][$targetEvent['id']]);
                                    $cellContent = $isReg ? '<span class="text-[10px] font-mono font-bold text-blue-700 bg-blue-50 px-2 py-1 rounded border border-blue-100 shadow-sm">' . $mapReg[$s['id']][$targetEvent['id']] . '</span>' : '<span class="text-slate-300 font-bold">-</span>';
                                    $cellClass = 'text-center border-r border-slate-100';
                                } else { $cellClass = 'border-r border-slate-200 bg-slate-100 diagonal-stripe'; }
                            ?>
                                <td class="px-4 py-3 <?= $cellClass ?>"><?= $cellContent ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="fixed bottom-0 right-0 w-full md:w-[calc(100%-16rem)] bg-slate-900 text-white p-4 shadow-[0_-5px_20px_rgba(0,0,0,0.2)] z-[60] border-t border-slate-700">
    <div class="flex flex-col md:flex-row justify-between items-center gap-4 max-w-6xl mx-auto px-4">
        
        <div class="flex items-center gap-8">
            <div>
                <p class="text-xs text-slate-400 uppercase font-bold">Total Nomor Lomba</p>
                <p class="text-2xl font-black text-white"><?= $totalEntries ?></p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase font-bold">Total Biaya (Estimasi)</p>
                <p class="text-2xl font-black text-green-400 font-mono">Rp<?= number_format($totalCost, 0, ',', '.') ?></p>
            </div>
        </div>

        <form method="POST" id="formCheckout">
            <input type="hidden" name="action" value="checkout">
            <button type="button" onclick="document.getElementById('modalCheckout').classList.remove('hidden')" class="bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-8 rounded-lg shadow-lg flex items-center gap-2 transition transform hover:-translate-y-1">
                <span>📩</span>
                <?php if($isSubmitted): ?>
                    Update Data & Kirim Ulang
                <?php else: ?>
                    Kirim & Minta Tagihan
                <?php endif; ?>
            </button>
        </form>

    </div>
</div>

<style>.diagonal-stripe { background-image: repeating-linear-gradient(45deg, transparent, transparent 5px, #e2e8f0 5px, #e2e8f0 10px); }</style>

<div id="modalCheckout" class="hidden fixed inset-0 bg-slate-900/60 z-[100] flex items-center justify-center p-4 backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6 transform transition-all scale-100">
        <h3 class="font-bold text-lg text-slate-800 mb-2">Konfirmasi Pendaftaran</h3>
        <p class="text-sm text-slate-600 mb-6">
            Apakah data yang Anda masukkan sudah benar? <br>
            Data akan dikirim ke Admin untuk proses verifikasi dan pembuatan Tagihan.
        </p>
        
        <div class="flex justify-end gap-3">
            <button onclick="document.getElementById('modalCheckout').classList.add('hidden')" class="px-4 py-2 rounded-lg text-slate-500 font-bold hover:bg-slate-50 transition">
                Batal
            </button>
            <button onclick="document.getElementById('formCheckout').submit()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-bold shadow-lg transition">
                Ya, Kirim Data
            </button>
        </div>
    </div>
</div>

<div id="modalAddTeam" class="hidden fixed inset-0 bg-slate-900/60 z-[100] flex items-center justify-center p-4 backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg max-h-[80vh] flex flex-col">
        <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-xl">
            <h3 class="font-bold text-lg text-slate-800">Pilih Anggota Tim</h3>
            <button onclick="document.getElementById('modalAddTeam').classList.add('hidden')" class="text-slate-400 hover:text-red-500 text-2xl font-bold">&times;</button>
        </div>
        <div class="p-4 border-b border-slate-100 bg-white">
            <input type="text" id="searchTeamInput" onkeyup="filterTeamList()" placeholder="🔍 Cari nama atlet..." class="w-full pl-4 pr-4 py-2 border border-slate-300 rounded-lg text-sm outline-none">
        </div>
        <form method="POST" class="flex-1 flex flex-col overflow-hidden">
            <input type="hidden" name="action" value="add_team">
            <div class="p-4 overflow-y-auto flex-1 bg-white" id="teamListContainer">
                <?php if(empty($availSwimmers)): ?>
                    <p class="text-center text-slate-400 italic py-8">Semua atlet Anda sudah masuk tim.</p>
                <?php else: ?>
                    <div class="space-y-2">
                        <?php foreach($availSwimmers as $as): ?>
                        <label class="flex items-center gap-3 p-3 border border-slate-200 rounded-lg hover:bg-slate-50 cursor-pointer team-item">
                            <input type="checkbox" name="swimmer_ids[]" value="<?= $as['id'] ?>" class="w-5 h-5 text-blue-600 rounded">
                            <div>
                                <div class="font-bold text-slate-700 text-sm swimmer-name"><?= htmlspecialchars($as['nama_atlet']) ?></div>
                                <div class="text-xs text-slate-400"><?= $as['jenis_kelamin']=='L'?'Putra':'Putri' ?> • <?= date('Y', strtotime($as['tanggal_lahir'])) ?></div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="p-4 border-t border-slate-100 flex justify-end bg-slate-50 rounded-b-xl">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg shadow">Masukkan ke Matriks</button>
            </div>
        </form>
    </div>
</div>

<div id="regModal" class="hidden fixed inset-0 bg-slate-900/60 z-[100] flex items-center justify-center p-4 backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">
        <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-xl">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold text-xl">✏️</div>
                <div>
                    <h3 class="font-bold text-lg text-slate-800" id="modalAtletName">-</h3>
                    <p class="text-xs text-slate-500 uppercase font-bold" id="modalAtletInfo">-</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <form method="POST" onsubmit="return confirm('Hapus atlet ini dari tim lomba?')">
                    <input type="hidden" name="action" value="remove_team">
                    <input type="hidden" name="swimmer_id" id="removeSwimmerId">
                    <button type="submit" class="text-red-500 text-xs font-bold hover:underline px-2">Hapus dari Tim</button>
                </form>
                <button type="button" onclick="document.getElementById('regModal').classList.add('hidden')" class="text-slate-400 hover:text-red-500 text-2xl font-bold">&times;</button>
            </div>
        </div>
        <div class="p-6 overflow-y-auto flex-1">
            <form id="formReg" method="POST">
                <input type="hidden" name="swimmer_id" id="inputSwimmerId">
                <div class="grid grid-cols-1 gap-2" id="modalEventList"></div>
                <div class="mt-6 flex justify-end pt-4 border-t border-slate-100">
                    <button type="button" onclick="document.getElementById('regModal').classList.add('hidden')" class="mr-3 px-4 py-2 text-slate-500 font-bold hover:text-slate-700">Batal</button>
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-8 rounded-lg shadow-lg">SIMPAN & DAFTAR</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function filterTeamList() {
    var input = document.getElementById("searchTeamInput");
    var filter = input.value.toUpperCase();
    var list = document.getElementById("teamListContainer");
    var items = list.getElementsByClassName("team-item");
    for (var i = 0; i < items.length; i++) {
        var name = items[i].getElementsByClassName("swimmer-name")[0];
        if (name) {
            var txtValue = name.textContent || name.innerText;
            items[i].style.display = (txtValue.toUpperCase().indexOf(filter) > -1) ? "" : "none";
        }
    }
}
function openEditFromButton(btn) {
    try {
        const atlet = JSON.parse(btn.getAttribute('data-atlet'));
        const registeredEvents = JSON.parse(btn.getAttribute('data-reg'));
        const suggestions = JSON.parse(btn.getAttribute('data-sug'));
        const allEvents = JSON.parse(btn.getAttribute('data-events'));
        document.getElementById('inputSwimmerId').value = atlet.id;
        document.getElementById('removeSwimmerId').value = atlet.id;
        document.getElementById('modalAtletName').innerText = atlet.nama_atlet;
        document.getElementById('modalAtletInfo').innerText = (atlet.jenis_kelamin == 'L' ? 'PUTRA' : 'PUTRI');
        const listContainer = document.getElementById('modalEventList');
        listContainer.innerHTML = ''; 
        allEvents.forEach(ev => {
            if (ev.jenis_kelamin === atlet.jenis_kelamin || ev.jenis_kelamin === 'Campuran') {
                const isReg = registeredEvents[ev.id] !== undefined;
                const timeVal = isReg ? registeredEvents[ev.id] : (suggestions[ev.id] || '99:99.99');
                const checked = isReg ? 'checked' : '';
                const activeColor = isReg ? 'text-blue-600' : 'text-slate-400';
                const html = `
                <div class="flex items-center justify-between p-3 border border-slate-200 rounded-lg hover:bg-slate-50 transition">
                    <div class="flex items-center gap-3">
                        <input type="checkbox" name="events[${ev.id}]" id="chk_${ev.id}" class="w-5 h-5 rounded text-blue-600 focus:ring-blue-500 cursor-pointer" ${checked} onchange="toggleTimeInput(${ev.id})">
                        <div>
                            <span class="text-xs font-black bg-slate-200 px-1.5 py-0.5 rounded text-slate-600 mr-2">${ev.nomor_acara}</span>
                            <span class="font-bold text-slate-700 text-sm">${ev.jarak}m ${ev.gaya}</span>
                            <span class="text-xs text-slate-400 ml-1">(KU ${ev.batas_umur_bawah}-${ev.batas_umur_atas})</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2"><span class="text-[10px] text-slate-400 font-bold">WAKTU:</span><input type="text" name="times[${ev.id}]" id="time_${ev.id}" value="${timeVal}" class="w-24 text-right text-sm font-mono font-bold border border-slate-300 rounded px-2 py-1 focus:ring-2 focus:ring-blue-500 outline-none ${activeColor}" readonly></div>
                </div>`;
                listContainer.insertAdjacentHTML('beforeend', html);
            }
        });
        document.getElementById('regModal').classList.remove('hidden');
    } catch (e) { console.error(e); }
}
function toggleTimeInput(eventId) {
    const chk = document.getElementById('chk_' + eventId);
    const input = document.getElementById('time_' + eventId);
    if (chk.checked) { input.classList.remove('text-slate-400'); input.classList.add('text-blue-600'); } 
    else { input.classList.remove('text-blue-600'); input.classList.add('text-slate-400'); }
}
</script>
