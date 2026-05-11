<?php
// Provjera je li korisnik prijavljen
function requireLogin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

// Provjera je li admin
function requireAdmin() {
    requireLogin();
    if ($_SESSION['uloga'] !== 'admin') {
        header('Location: index.php');
        exit;
    }
}

// Vraća true ako je korisnik prijavljen
function isLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return !empty($_SESSION['user_id']);
}

// Vraća true ako je admin
function isAdmin() {
    return isLoggedIn() && $_SESSION['uloga'] === 'admin';
}
