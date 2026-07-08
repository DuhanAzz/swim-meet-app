<?php
// FILE: src/admin/relay_management.php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { 
    header("Location: ../../public/login.php"); exit; 
}

$adminId = $_SESSION['user_id'];

// 1. GET ACTIVE EVENT
$stmtEvent = $pdo->prepare("SELECT * FROM events WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmtEvent->execute([$adminId]);
$activeEvent = $stmtEvent->fetch(PDO::FETCH_ASSOC);

if (!$activeEvent) {
    die("Belum ada event yang dikelola.");
}
$eventId = $activeEvent['id'];

$calcType = $activeEvent['age_calculation_type'] ?? 'Dec 31'; 
$startDate = $activeEvent['event_date_start'] ?? date('Y-m-d');
$compYear = (int)date('Y', strtotime($startDate));
$compDateObj = new DateTime($startDate);

function hitungUmur($tglLahir, $calcType, $compYear, $compDateObj) {
    if (empty($tglLahir)) return 0;
    $dobObj = new DateTime($tglLahir);
    $birthYear = (int)$dobObj->format('Y');
    return ($calcType === 'Meet Start') ? $dobObj->diff($compDateObj)->y : ($compYear - $birthYear);
}

// 2. HANDLE POST (SAVE SWIMMERS)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_swimmers') {
    $relayId = (int)$_POST['relay_id'];
    $s1 = !empty($_POST['swimmer_1']) ? (int)$_POST['swimmer_1'] : null;
    $s2 = !empty($_POST['swimmer_2']) ? (int)$_POST['swimmer_2'] : null;
    $s3 = !empty($_POST['swimmer_3']) ? (int)$_POST['swimmer_3'] : null;
    $s4 = !empty($_POST['swimmer_4']) ? (int)$_POST['swimmer_4'] : null;

    $stmtUpdate = $pdo->prepare("UPDATE relay_entries SET swimmer_1_id=?, swimmer_2_id=?, swimmer_3_id=?, swimmer_4_id=? WHERE id=? AND event_id=?");
    $stmtUpdate->execute([$s1, $s2, $s3, $s4, $relayId, $eventId]);
    
    $_SESSION['toast'] = "Perenang estafet berhasil di-update!";
    header("Location: relay_management.php"); exit;
}

// 3. FETCH RELAY TEAMS
$sql = "SELECT re.*, c.nama_klub, en.event_name, en.event_number, en.jenis_kelamin, en.age_group, en.age_min, en.age_max, en.selected_ku_ids,
          s1.nama_atlet as name1, s2.nama_atlet as name2, s3.nama_atlet as name3, s4.nama_atlet as name4
        FROM relay_entries re
        JOIN clubs c ON re.club_id = c.id
        JOIN event_numbers en ON re.category_id = en.id
        LEFT JOIN swimmers s1 ON re.swimmer_1_id = s1.id
        LEFT JOIN swimmers s2 ON re.swimmer_2_id = s2.id
        LEFT JOIN swimmers s3 ON re.swimmer_3_id = s3.id
        LEFT JOIN swimmers s4 ON re.swimmer_4_id = s4.id
        WHERE re.event_id = ?
        ORDER BY CAST(en.event_number AS UNSIGNED) ASC, c.nama_klub ASC";
$stmtTeams = $pdo->prepare($sql);
$stmtTeams->execute([$eventId]);
$relayTeams = $stmtTeams->fetchAll(PDO::FETCH_ASSOC);

// 4. FETCH AGE GROUPS FOR FILTERING
$stmtGroups = $pdo->prepare("SELECT id, min_age, max_age FROM event_age_groups WHERE event_id = ?");
$stmtGroups->execute([$eventId]);
$ageRules = $stmtGroups->fetchAll(PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC);

// 5. FETCH SWIMMERS FOR RELEVANT CLUBS
$clubIds = array_unique(array_column($relayTeams, 'club_id'));
$clubSwimmers = [];
if (!empty($clubIds)) {
    $placeholders = implode(',', array_fill(0, count($clubIds), '?'));
    $stmtSwimmers = $pdo->prepare("SELECT id, club_id, nama_atlet, jenis_kelamin, tanggal_lahir FROM swimmers WHERE club_id IN ($placeholders)");
    $stmtSwimmers->execute($clubIds);
    while($row = $stmtSwimmers->fetch(PDO::FETCH_ASSOC)) {
        $age = hitungUmur($row['tanggal_lahir'], $calcType, $compYear, $compDateObj);
        $birthYear = (int)date('Y', strtotime($row['tanggal_lahir']));
        $clubSwimmers[$row['club_id']][] = [
            'id' => $row['id'],
            'nama' => $row['nama_atlet'],
            'gender' => $row['jenis_kelamin'],
            'age' => $age,
            'birth_year' => $birthYear
        ];
    }
}

include __DIR__ . '/../../views/layout/topbar.php'; 
include __DIR__ . '/../../views/layout/sidebar.php'; 
?>

<div class="p-6 sm:ml-64 pt-24 bg-slate-50 min-h-screen font-sans">
    
    <?php if(isset($_SESSION['toast'])): ?>
        <div class="fixed top-24 right-8 bg-emerald-500 text-white px-6 py-3 rounded-xl font-bold shadow-2xl z-50 animate-bounce">
            <?= htmlspecialchars($_SESSION['toast']) ?>
        </div>
        <?php unset($_SESSION['toast']); ?>
    <?php endif; ?>

    <div class="flex justify-between items-end mb-8">
        <div>
            <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tighter italic">Manajemen Estafet</h1>
            <p class="text-xs text-slate-500 font-bold uppercase tracking-widest mt-2">
                Atur 4 Nama Perenang untuk Masing-masing Tim
            </p>
        </div>
    </div>

    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        <?php if(empty($relayTeams)): ?>
            <div class="p-16 text-center">
                <div class="text-6xl mb-4 opacity-30">🏃‍♂️</div>
                <p class="text-slate-500 font-bold text-lg uppercase tracking-widest">Belum ada tim estafet terdaftar</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-400 border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-4">Nomor Lomba</th>
                            <th class="px-6 py-4">Klub & Tim</th>
                            <th class="px-6 py-4">Seed Time</th>
                            <th class="px-6 py-4">Susunan Perenang</th>
                            <th class="px-6 py-4 text-center">Status</th>
                            <th class="px-6 py-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <?php foreach($relayTeams as $team): 
                            $isComplete = (!empty($team['swimmer_1_id']) && !empty($team['swimmer_2_id']) && !empty($team['swimmer_3_id']) && !empty($team['swimmer_4_id']));
                            $missingCount = 0;
                            if(empty($team['swimmer_1_id'])) $missingCount++;
                            if(empty($team['swimmer_2_id'])) $missingCount++;
                            if(empty($team['swimmer_3_id'])) $missingCount++;
                            if(empty($team['swimmer_4_id'])) $missingCount++;
                            
                            $catGender = in_array(strtoupper($team['jenis_kelamin']), ['L', 'MALE', 'PUTRA']) ? 'L' : (in_array(strtoupper($team['jenis_kelamin']), ['P', 'FEMALE', 'PUTRI']) ? 'P' : 'MIX');
                        ?>
                        <tr class="hover:bg-slate-50 transition group">
                            <td class="px-6 py-4">
                                <span class="font-black text-slate-300 text-lg block mb-1">#<?= htmlspecialchars($team['event_number']) ?></span>
                                <span class="font-bold text-slate-700 uppercase"><?= htmlspecialchars($team['event_name']) ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-black text-slate-800 uppercase block"><?= htmlspecialchars($team['nama_klub']) ?></span>
                                <span class="font-bold text-blue-600 text-xs uppercase bg-blue-50 px-2 py-1 rounded inline-block mt-1"><?= htmlspecialchars($team['team_name']) ?></span>
                            </td>
                            <td class="px-6 py-4 font-mono font-bold text-slate-500">
                                <?= htmlspecialchars($team['seed_time'] ?? '00.00.00') ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-[10px] font-bold space-y-1">
                                    <div class="flex items-center gap-2"><span class="w-4 text-slate-400">1.</span> <span class="<?= empty($team['name1']) ? 'text-red-400 italic' : 'text-slate-700' ?>"><?= htmlspecialchars($team['name1'] ?? '(Kosong)') ?></span></div>
                                    <div class="flex items-center gap-2"><span class="w-4 text-slate-400">2.</span> <span class="<?= empty($team['name2']) ? 'text-red-400 italic' : 'text-slate-700' ?>"><?= htmlspecialchars($team['name2'] ?? '(Kosong)') ?></span></div>
                                    <div class="flex items-center gap-2"><span class="w-4 text-slate-400">3.</span> <span class="<?= empty($team['name3']) ? 'text-red-400 italic' : 'text-slate-700' ?>"><?= htmlspecialchars($team['name3'] ?? '(Kosong)') ?></span></div>
                                    <div class="flex items-center gap-2"><span class="w-4 text-slate-400">4.</span> <span class="<?= empty($team['name4']) ? 'text-red-400 italic' : 'text-slate-700' ?>"><?= htmlspecialchars($team['name4'] ?? '(Kosong)') ?></span></div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if($isComplete): ?>
                                    <span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border border-emerald-200">Lengkap</span>
                                <?php else: ?>
                                    <span class="bg-orange-100 text-orange-700 px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border border-orange-200">Kurang <?= $missingCount ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <button type="button" onclick='openModal(<?= json_encode($team) ?>)' class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-xs font-black uppercase tracking-widest shadow-md transition">
                                    Atur Perenang
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL ASSIGNMENT -->
<div id="modalAssign" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col">
        <div class="bg-slate-800 p-6 text-white flex justify-between items-center">
            <div>
                <h2 class="text-xl font-black italic uppercase tracking-tighter" id="mTitle">Atur Tim</h2>
                <p class="text-[10px] font-bold text-blue-400 uppercase mt-1" id="mSubtitle">Info Lomba</p>
            </div>
            <button onclick="closeModal()" class="text-3xl hover:text-red-400">&times;</button>
        </div>
        <form method="POST" class="p-6 bg-slate-50 flex-1 space-y-4">
            <input type="hidden" name="action" value="assign_swimmers">
            <input type="hidden" name="relay_id" id="mRelayId">
            
            <div class="p-3 bg-blue-50 border border-blue-100 rounded-xl mb-4">
                <p class="text-[10px] text-blue-700 font-bold leading-relaxed text-justify">
                    ⚠️ <span class="font-black">SMART FILTERING:</span> Opsi yang muncul pada dropdown di bawah ini telah difilter otomatis agar sesuai dengan <span class="underline">Jenis Kelamin</span> dan batas <span class="underline">Kelompok Umur</span> lomba ini, serta hanya memunculkan atlet dari Klub <span id="mClubName" class="font-black"></span>.
                </p>
            </div>

            <div class="space-y-4" id="dropdownContainer">
                <!-- Dropdowns will be injected here via JS -->
            </div>
            
            <div class="mt-6 pt-4 border-t border-slate-200">
                <button type="submit" class="w-full bg-blue-600 text-white py-3 rounded-xl font-black text-xs uppercase tracking-widest shadow-xl hover:bg-blue-700 transition">SIMPAN FORMASI</button>
            </div>
        </form>
    </div>
</div>

<script>
const clubSwimmers = <?= json_encode($clubSwimmers) ?>;
const ageRules = <?= json_encode($ageRules) ?>;

function openModal(team) {
    document.getElementById('mTitle').innerText = team.team_name;
    document.getElementById('mSubtitle').innerText = team.event_name;
    document.getElementById('mClubName').innerText = team.nama_klub;
    document.getElementById('mRelayId').value = team.id;
    
    // Validasi & Filtering Data Atlet yang berhak tampil di Dropdown
    const cid = team.club_id;
    let swimmers = clubSwimmers[cid] || [];
    
    // Parameter Lomba
    const reqGender = (team.jenis_kelamin.toUpperCase() === 'L' || team.jenis_kelamin.toUpperCase() === 'MALE' || team.jenis_kelamin.toUpperCase() === 'PUTRA') ? 'L' : 
                      ((team.jenis_kelamin.toUpperCase() === 'P' || team.jenis_kelamin.toUpperCase() === 'FEMALE' || team.jenis_kelamin.toUpperCase() === 'PUTRI') ? 'P' : 'MIX');
    
    const minAge = parseInt(team.age_min) || 0;
    const maxAge = parseInt(team.age_max) || 99;
    const kuIds = team.selected_ku_ids ? team.selected_ku_ids.split(',') : [];
    
    // Ekstrak tahun lahir eksplisit jika ada (misal: "2010-2011")
    let allowedYears = [];
    const yearMatches = team.age_group.match(/\b(20\d{2})\b/g);
    if(yearMatches) {
        allowedYears = yearMatches.map(Number);
    }
    
    let validSwimmers = swimmers.filter(sw => {
        // 1. Cek Gender
        if (reqGender !== 'MIX' && sw.gender !== reqGender) return false;
        
        // 2. Cek Umur
        let isAgeFit = false;
        if (allowedYears.length > 0) {
            if (allowedYears.includes(sw.birth_year)) isAgeFit = true;
        } else {
            let passMinMax = (sw.age >= minAge && (maxAge === 0 || sw.age <= maxAge));
            if (kuIds.length > 0) {
                let passKu = false;
                kuIds.forEach(kid => {
                    let rule = ageRules[kid];
                    if (rule && sw.age >= parseInt(rule.min_age) && sw.age <= parseInt(rule.max_age)) {
                        passKu = true;
                    }
                });
                if (passKu && passMinMax) isAgeFit = true;
            } else {
                if (passMinMax) isAgeFit = true;
            }
        }
        return isAgeFit;
    });

    // Urutkan abjad
    validSwimmers.sort((a,b) => a.nama.localeCompare(b.nama));
    
    // Bangun HTML Dropdowns
    const container = document.getElementById('dropdownContainer');
    container.innerHTML = '';
    
    for(let i=1; i<=4; i++) {
        let currentId = team['swimmer_' + i + '_id'];
        let optionsHtml = `<option value="">-- KOSONGKAN (NULL) --</option>`;
        
        validSwimmers.forEach(sw => {
            let selected = (currentId && currentId == sw.id) ? 'selected' : '';
            optionsHtml += `<option value="${sw.id}" ${selected}>${sw.nama} (${sw.age} TH)</option>`;
        });
        
        container.innerHTML += `
            <div>
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Perenang ${i}</label>
                <select name="swimmer_${i}" class="w-full text-sm font-bold text-slate-700 bg-white border border-slate-300 rounded-lg px-4 py-3 outline-none focus:border-blue-500 shadow-sm">
                    ${optionsHtml}
                </select>
            </div>
        `;
    }
    
    document.getElementById('modalAssign').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('modalAssign').classList.add('hidden');
}
</script>
