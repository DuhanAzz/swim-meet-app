<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_role($roles)
{
    if (!isset($_SESSION['user'])) {
        header("Location: /public/login.php");
        exit;
    }

    if (!in_array($_SESSION['user']['role'], (array)$roles)) {
        echo "ACCESS DENIED: Anda tidak punya izin";
        exit;
    }
}
