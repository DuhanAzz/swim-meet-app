<?php
// FILE: src/admin/seeding/set_print_config.php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simpan konfigurasi ke SESSION
    $_SESSION['print_config'] = [
        'show_event_no' => isset($_POST['show_event_no']),
        'show_date'     => isset($_POST['show_date']),
        'show_event_name'=> isset($_POST['show_event_name']),
        'show_group'    => isset($_POST['show_group']),
        'show_gender'   => isset($_POST['show_gender']),
        'show_pool'     => isset($_POST['show_pool']),
        'show_round'    => isset($_POST['show_round'])
    ];
    
    // Redirect kembali ke index
    header("Location: index.php");
    exit;
}