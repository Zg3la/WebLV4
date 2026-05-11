<?php

$db_host = getenv('DB_HOST');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');
$db_name = getenv('DB_NAME');
$db_port = (int)getenv('DB_PORT');

$conn = mysqli_init();

// Putanja do Aiven certifikata
$ca_cert = __DIR__ . '/../certs/ca.pem';

mysqli_ssl_set(
    $conn,
    null,
    null,
    $ca_cert,
    null,
    null
);

$conn->real_connect(
    $db_host,
    $db_user,
    $db_pass,
    $db_name,
    $db_port,
    null,
    MYSQLI_CLIENT_SSL
);

if ($conn->connect_error) {
    error_log("DB error: " . $conn->connect_error);
    exit("Database connection failed.");
}

$conn->set_charset("utf8mb4");
