<?php
// Konekcija na bazu podataka
// Za lokalni razvoj (XAMPP): host=localhost, user=root, pass=""
// Za Render: koristiti environment varijable

$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';
$db_name = getenv('DB_NAME') ?: 'filmbase_lv4';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Greška pri spajanju na bazu: ' . $conn->connect_error]));
}

$conn->set_charset('utf8mb4');
