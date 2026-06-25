<?php
// FILE: src/admin/entries/print_invoice.php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { die("Akses Ditolak"); }

$targetUserId = $_GET['id'] ?? 0;
$eventId      = $_GET['event_id'] ?? 0;

if ($targetUserId == 0 || $eventId == 0) die("Parameter Salah");

// --- 1. DATA EVENT ---
$stmtEvt = $pdo->prepare("SELECT * FROM events WHERE id = ? LIMIT 1");
$stmtEvt->execute([$eventId]);
$eventData = $stmtEvt->fetch();

// Ambil nama event dari berbagai kemungkinan kolom
$namaEvent = $eventData['nama_event'] 
             ?? $eventData['event_name'] 
             ?? $eventData['judul_event'] 
             ?? 'SWIMMING COMPETITION';

// --- 2. DATA KLUB ---
$stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->execute([$targetUserId]);
$userData = $stmtUser->fetch();
$clubName = !empty($userData['club_name']) ? $userData['club_name'] : ($userData['nama_lengkap'] ?? 'Tanpa Nama');

// --- 3. SPONSOR ---
try {
    $stmtSpon = $pdo->prepare("SELECT image_path FROM event_sponsors WHERE event_id = ?");
    $stmtSpon->execute([$eventId]);
    $sponsors = $stmtSpon->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) { $sponsors = []; }

// --- 4. ENTRIES ---
$groupedSwimmers = [];
$totalTagihan = 0;

$sqlEntries = "
    SELECT ent.id, ent.entry_time, s.id as sid, s.nama_atlet, s.jenis_kelamin, s.tanggal_lahir,
    en.distance, en.stroke, en.age_group, en.price
    FROM event_entries ent
    JOIN swimmers s ON ent.swimmer_id = s.id
    JOIN event_numbers en ON ent.category_id = en.id
    WHERE ent.user_id = ? AND ent.event_id = ?
    ORDER BY s.nama_atlet ASC, en.distance ASC
";
$stmt = $pdo->prepare($sqlEntries);
$stmt->execute([$targetUserId, $eventId]);
$raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($raw as $r) {
    if(!isset($groupedSwimmers[$r['sid']])) {
        $groupedSwimmers[$r['sid']] = ['info'=>$r, 'items'=>[], 'subtotal'=>0];
    }
    $groupedSwimmers[$r['sid']]['items'][] = $r;
}

$pricingMode = $eventData['pricing_mode'] ?? 'per_item';

foreach($groupedSwimmers as &$d) {
    $cnt = count($d['items']);
    if ($pricingMode === 'package') {
        $l = (int)($eventData['package_limit'] ?? 0);
        $b = (float)($eventData['package_price'] ?? 0);
        $e = (float)($eventData['extra_price'] ?? 0);
        $d['subtotal'] = ($cnt <= $l) ? $b : ($b + (($cnt - $l) * $e));
    } else {
        $def = (float)($eventData['price_per_item'] ?? 0);
        $sub = 0; foreach($d['items'] as $i) $sub += ($i['price']>0 ? $i['price'] : $def);
        $d['subtotal'] = $sub;
    }
    $totalTagihan += $d['subtotal'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Invoice - <?= htmlspecialchars($clubName) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* --- RESET & COLOR SETTINGS --- */
        * { box-sizing: border-box; }
        
        body {
            margin: 0; padding: 0;
            background-color: #525659;
            font-family: 'Arial Narrow', Arial, sans-serif;
            font-size: 10pt;
            /* PAKSA WARNA KELUAR */
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
        }

        /* --- STRUKTUR KERTAS (A4) --- */
        .sheet {
            width: 210mm;
            height: 297mm;
            background: white;
            margin: 30px auto;
            padding: 10mm 10mm 0 10mm; 
            position: relative;
            box-shadow: 0 0 15px rgba(0,0,0,0.5);
            overflow: hidden; 
            display: flex;
            flex-direction: column;
        }

        /* --- HEADER --- */
        .invoice-header {
            width: 100%;
            border-bottom: 2px solid #000;
            margin-bottom: 10px;
            padding-bottom: 5px;
            display: flex; justify-content: space-between; align-items: flex-end;
            flex-shrink: 0;
        }
        .invoice-header h1 { margin: 0; font-size: 18pt; font-weight: 900; font-style: italic; text-transform: uppercase; }
        .invoice-header p { margin: 2px 0 0; font-size: 11pt; font-weight: bold; color: #444; text-transform: uppercase; }
        .header-total { text-align: right; }
        .header-total span { display: block; font-size: 8pt; font-weight: bold; color: #666; }
        .header-total strong { display: block; font-size: 16pt; font-family: monospace; font-weight: 900; color: #000; }

        /* --- BODY --- */
        .page-body {
            width: 100%;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
        }

        /* --- FOOTER FIXED (FULL COLOR) --- */
        .sheet-footer {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 18mm;
            background: white;
            border-top: 1px double #aaa;
            display: flex; justify-content: center; align-items: center; gap: 30px;
            padding: 5px 0;
            z-index: 50;
        }
        
        .sheet-footer img { 
            height: 45px; 
            width: auto; 
            /* HAPUS FILTER GRAYSCALE AGAR BERWARNA */
            object-fit: contain;
            opacity: 1; /* Full Opaque */
        }

        /* --- KARTU ATLET --- */
        .swimmer-block {
            border: 1px solid #000;
            margin-bottom: 5px;
            border-radius: 4px;
            overflow: hidden;
            background: #fff;
            width: 100%;
        }

        .sb-header {
            padding: 4px 8px;
            border-bottom: 1px solid #000;
            display: flex; justify-content: space-between; align-items: center;
        }
        
        /* WARNA BACKGROUND & BORDER HEADER */
        .sb-header.male { 
            background-color: #dbeafe !important; 
            border-color: #1e40af !important;
            -webkit-print-color-adjust: exact !important; 
            print-color-adjust: exact !important;
        }
        .sb-header.female { 
            background-color: #fce7f3 !important; 
            border-color: #9d174d !important;
            -webkit-print-color-adjust: exact !important; 
            print-color-adjust: exact !important;
        }
        
        .name-text { font-weight: 800; font-size: 10pt; text-transform: uppercase; font-style: italic; }
        .meta-text { font-size: 8pt; color: #444; margin-left: 6px; font-weight: bold; }
        .price-text { font-family: monospace; font-weight: bold; font-size: 11pt; }
        
        .gender-badge {
            display: inline-block; width: 16px; height: 16px;
            text-align: center; line-height: 16px;
            border-radius: 50%; color: white !important; font-weight: bold; font-size: 8pt; margin-right: 5px;
            -webkit-print-color-adjust: exact !important; 
            print-color-adjust: exact !important;
        }
        .male .gender-badge { background-color: #2563eb !important; }
        .female .gender-badge { background-color: #db2777 !important; }

        .event-row {
            display: flex; justify-content: space-between;
            padding: 2px 8px;
            border-bottom: 1px dotted #ccc;
            font-size: 9pt;
        }
        .event-row:last-child { border-bottom: none; }
        .evt-name { font-weight: bold; font-size: 9pt; text-transform: uppercase; }
        .evt-ku { font-size: 7pt; color: #666; margin-left: 3px; }
        .evt-time { font-family: monospace; font-weight: bold; color: #444; }

        .btn-print {
            position: fixed; top: 20px; right: 20px; z-index: 9999;
            background: #0f172a; color: white; border: none; padding: 12px 24px;
            border-radius: 8px; font-weight: bold; cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3); text-transform: uppercase;
        }
        .btn-print:hover { transform: scale(1.05); }

        @media print {
            body { background: white; margin: 0; }
            .sheet {
                margin: 0; box-shadow: none; border: none;
                page-break-after: always;
                min-height: 297mm; height: 297mm;
            }
            .btn-print { display: none; }
            @page { margin: 0; size: A4; }
            
            /* PASTIKAN WARNA TERCETAK */
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style>
</head>
<body>

    <button onclick="window.print()" class="btn-print"><i class="fas fa-print"></i> CETAK PDF</button>

    <div id="source-data" style="display: none;">
        
        <div id="tpl-header">
            <div class="invoice-header">
                <div>
                    <h1><?= htmlspecialchars($namaEvent) ?></h1>
                    <p>KLUB: <?= htmlspecialchars($clubName) ?></p>
                </div>
                <div class="header-total">
                    <span>TOTAL TAGIHAN</span>
                    <strong>Rp <?= number_format($totalTagihan, 0, ',', '.') ?></strong>
                </div>
            </div>
        </div>

        <div id="tpl-footer">
            <div class="sheet-footer">
                <?php if(!empty($sponsors)): ?>
                    <?php foreach($sponsors as $img): ?>
                        <img src="<?= BASE_URL . '/public/' . ltrim($img, '/') ?>" alt="Sponsor">
                    <?php endforeach; ?>
                <?php else: ?>
                    <small style="color:#ccc; font-weight:bold;">TIRTA AMANDA SWIMMING CLUB</small>
                <?php endif; ?>
            </div>
        </div>

        <div id="tpl-cards">
            <?php foreach($groupedSwimmers as $swimmer): 
                $inf = $swimmer['info'];
                $isMale = ($inf['jenis_kelamin'] == 'L');
                $gClass = $isMale ? 'male' : 'female';
                $gLabel = $isMale ? 'P' : 'W';
            ?>
            <div class="swimmer-block">
                <div class="sb-header <?= $gClass ?>">
                    <div style="display:flex; align-items:center;">
                        <span class="gender-badge"><?= $gLabel ?></span>
                        <div>
                            <span class="name-text"><?= htmlspecialchars($inf['nama_atlet']) ?></span>
                            <span class="meta-text"><?= date('Y', strtotime($inf['tanggal_lahir'])) ?></span>
                        </div>
                    </div>
                    <div class="price-text">Rp <?= number_format($swimmer['subtotal'], 0, ',', '.') ?></div>
                </div>
                <div>
                    <?php foreach($swimmer['items'] as $item): ?>
                    <div class="event-row">
                        <div>
                            <span class="evt-name"><?= $item['distance'] ?>M <?= strtoupper($item['stroke']) ?></span>
                            <span class="evt-ku">(KU <?= $item['age_group'] ?>)</span>
                        </div>
                        <span class="evt-time"><?= $item['entry_time'] ? htmlspecialchars($item['entry_time']) : 'NT' ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="output-books"></div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const sourceCards = Array.from(document.querySelectorAll('#tpl-cards .swimmer-block'));
            const headerHTML  = document.getElementById('tpl-header').innerHTML;
            const footerHTML  = document.getElementById('tpl-footer').innerHTML;
            const output      = document.getElementById('output-books');

            const PAGE_HEIGHT_LIMIT = 1060; 
            
            let currentBody = null;
            let currentHeight = 0;

            function createPage(isFirst) {
                const sheet = document.createElement('div');
                sheet.className = 'sheet';
                
                const footDiv = document.createElement('div');
                footDiv.innerHTML = footerHTML;
                sheet.appendChild(footDiv);

                const bodyDiv = document.createElement('div');
                bodyDiv.className = 'page-body';

                let usedHeight = 38;
                if (isFirst) {
                    const headDiv = document.createElement('div');
                    headDiv.innerHTML = headerHTML;
                    bodyDiv.appendChild(headDiv);
                    usedHeight += 120; 
                } else {
                    bodyDiv.style.marginTop = '0px'; 
                    usedHeight = 38;
                }

                sheet.appendChild(bodyDiv);
                output.appendChild(sheet);

                currentBody = bodyDiv;
                currentHeight = usedHeight;
            }

            createPage(true);

            sourceCards.forEach(card => {
                const clone = card.cloneNode(true);
                currentBody.appendChild(clone);
                const itemHeight = clone.offsetHeight + 5; 

                if ((currentHeight + itemHeight) > PAGE_HEIGHT_LIMIT) {
                    currentBody.removeChild(clone);
                    createPage(false);
                    currentBody.appendChild(clone);
                    currentHeight += itemHeight;
                } else {
                    currentHeight += itemHeight;
                }
            });

            if (sourceCards.length === 0) {
                output.innerHTML = '<div class="sheet" style="justify-content:center; align-items:center; display:flex;">Data Kosong</div>';
            }
        });
    </script>
</body>
</html>