<?php
$files = [
    'public/live_result.php',
    'src/user/live_result.php'
];

foreach ($files as $file) {
    $content = file_get_contents($file);

    // 1. Add timeToMs function after session_start or includes
    if (strpos($content, 'function timeToMs') === false) {
        $insertStr = "
function timeToMs(\$time) {
    \$time = trim(\$time);
    if (empty(\$time) || \$time == 'NT' || \$time == '99:99.99' || \$time == '-') return 9999999999; 
    \$parts = preg_split('/[:.]/', \$time);
    \$menit = 0; \$detik = 0; \$ms = 0;
    if (count(\$parts) == 3) { \$menit = (int)\$parts[0]; \$detik = (int)\$parts[1]; \$ms = (int)\$parts[2]; } 
    elseif (count(\$parts) == 2) { \$detik = (int)\$parts[0]; \$ms = (int)\$parts[1]; } 
    elseif (count(\$parts) == 1) { \$detik = (int)\$parts[0]; }
    return (\$menit * 60000) + (\$detik * 1000) + (\$ms * 10);
}
";
        // insert right before // 4. Kelompokkan (or // Kelompokkan)
        $content = preg_replace('/(\/\/\s*4\.\s*Kelompokkan.*|\/\/\s*Kelompokkan.*)/', $insertStr . '$1', $content);
    }

    // 2. Modify Grouping Logic to add ms_sort and usort
    $pattern = '/(foreach \(\$results as \$r\) \{)(.*?)(^\s*\$groupedResults\[\$judulAcara\]\[\] = \$r;\s*\})/sm';
    $replacement = '$1
    $r[\'ms_sort\'] = 9999999999;
    if ($r[\'is_dq_final\'] == 1) { $r[\'ms_sort\'] = 9999999999 + 100; }
    elseif (!empty($r[\'time_final\']) && $r[\'time_final\'] != \'NT\') { $r[\'ms_sort\'] = timeToMs($r[\'time_final\']); }
$2$3';
    $content = preg_replace($pattern, $replacement, $content);
    
    // Add usort after grouping
    $usortStr = '
foreach ($groupedResults as &$rows) {
    usort($rows, function($a, $b) {
        if ($a[\'ms_sort\'] == $b[\'ms_sort\']) return 0;
        return ($a[\'ms_sort\'] < $b[\'ms_sort\']) ? -1 : 1;
    });
}
unset($rows);
';
    $content = preg_replace('/(\$groupedResults\[\$judulAcara\]\[\] = \$r;\s*\})/', '$1' . $usortStr, $content);

    // 3. Update the loop for dynamic rank
    $loopPattern = '/foreach \(\$atletList as \$atlet\):.*?\$isDQ = \(\$atlet\[\'is_dq_final\'\] == 1\);/sm';
    if (strpos($file, 'public/live_result.php') !== false) {
        $loopRep = '$rank = 1; $real_rank = 1; $prev_time = null;
                                        foreach ($atletList as $atlet): 
                                            $isDQ = ($atlet[\'is_dq_final\'] == 1);
                                            $isValid = (!$isDQ && !empty($atlet[\'time_final\']) && $atlet[\'time_final\'] != \'NT\');
                                            ';
        $content = preg_replace('/foreach \(\$atletList as \$atlet\):\s*\$isDQ = \(\$atlet\[\'is_dq_final\'\] == 1\);/sm', $loopRep, $content);
    } else {
        $loopRep = '$rank = 1; $real_rank = 1; $prev_time = null;
                                    foreach ($atletList as $atlet): 
                                        $isMyTeam = ($atlet[\'swimmer_owner_id\'] == $user_id);
                                        $isDQ = ($atlet[\'is_dq_final\'] == 1);
                                        $isValid = (!$isDQ && !empty($atlet[\'time_final\']) && $atlet[\'time_final\'] != \'NT\');
                                        ';
        $content = preg_replace('/foreach \(\$atletList as \$atlet\):\s*\$isMyTeam = \(\$atlet\[\'swimmer_owner_id\'\] == \$user_id\);\s*\$isDQ = \(\$atlet\[\'is_dq_final\'\] == 1\);/sm', $loopRep, $content);
    }

    // Replace old static rank logic
    $oldRankLogic = '/\$rankBadge = \'-\';\s*if \(!\$isDQ.*?\}/sm';
    $newRankLogic = '$rankBadge = \'-\';
                                            $rankClass = \'text-slate-500\'; // For public
                                            if ($isValid) {
                                                if ($atlet[\'ms_sort\'] !== $prev_time) { $real_rank = $rank; }
                                                $rankBadge = $real_rank;
                                                $prev_time = $atlet[\'ms_sort\'];
                                                $rank++;
                                                if($rankBadge == 1) { $rankBadge = \'🥇 1\'; $rankClass = \'text-amber-400\'; }
                                                elseif($rankBadge == 2) { $rankBadge = \'🥈 2\'; $rankClass = \'text-slate-300\'; }
                                                elseif($rankBadge == 3) { $rankBadge = \'🥉 3\'; $rankClass = \'text-orange-400\'; }
                                            }';
    
    // Actually just a simple string replace for the old block
    // Let's use preg_replace carefully
    $content = preg_replace('/\$rankBadge = \'-\';\s*.*?if\(\$rankBadge == 3\).*?\}/sm', $newRankLogic, $content);

    file_put_contents($file, $content);
    echo "Patched $file\n";
}
