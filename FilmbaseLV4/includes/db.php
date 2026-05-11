<?php

$db_host = getenv('DB_HOST');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');
$db_name = getenv('DB_NAME');
$db_port = getenv('DB_PORT') ?: 3306;

// Provjera da su varijable postavljene
if (!$db_host || !$db_user || !$db_name) {
    error_log("Missing database environment variables.");
    http_response_code(500);
    exit("Database configuration error.");
}

// Spajanje na remote MySQL
$conn = new mysqli(
    $db_host,
    $db_user,
    $db_pass,
    $db_name,
    (int)$db_port
);

// Error handling
if ($conn->connect_error) {
    error_log("DB connection failed: " . $conn->connect_error);
    http_response_code(500);
    exit("Database connection failed.");
}

// UTF-8 za hrvatske znakove
$conn->set_charset("utf8mb4");
