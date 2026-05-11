<?php

$db_host = getenv('DB_HOST');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');
$db_name = getenv('DB_NAME');
$db_port = getenv('DB_PORT') ?: 3306;

// fallback samo za lokalni dev
if (!$db_host) {
    $db_host = '127.0.0.1';
    $db_user = 'root';
    $db_pass = '';
    $db_name = 'filmbase_lv4';
    $db_port = 3306;
}

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

if ($conn->connect_error) {
    error_log("DB ERROR: " . $conn->connect_error);
    http_response_code(500);
    die("Database connection failed");
}

$conn->set_charset("utf8mb4");
