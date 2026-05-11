<?php
// ================================
// DATABASE CONFIG (SAFE & PORTABLE)
// ================================

// Environment variables (Render / Docker / hosting)
$db_host = getenv('DB_HOST');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');
$db_name = getenv('DB_NAME');

// Fallback za lokalni razvoj (XAMPP / Linux)
if (!$db_host) {
    $db_host = '127.0.0.1'; // BITNO: ne "localhost"
}
if (!$db_user) {
    $db_user = 'root';
}
if ($db_pass === false) {
    $db_pass = '';
}
if (!$db_name) {
    $db_name = 'filmbase_lv4';
}

// ================================
// MYSQL CONNECTION
// ================================

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Connection check
if ($conn->connect_error) {
    error_log("DB connection failed: " . $conn->connect_error);

    http_response_code(500);
    die("Greška pri spajanju na bazu podataka.");
}

// Charset (obavezno za hrvatske znakove)
$conn->set_charset("utf8mb4");
