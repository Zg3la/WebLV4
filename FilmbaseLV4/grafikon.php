<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

$prijavljen = isLoggedIn();
$je_admin   = isAdmin();
$korisnik_ime = $_SESSION['korisnicko_ime'] ?? '';
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filmbase – Grafikon</title>

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/style_grafikon.css">
</head>

<body>

<header id="Naslov">
    <h1>Dobrodošli na moju web stranicu</h1>
</header>

<!-- NAV -->
<nav class="navbar">
    <input type="checkbox" id="check">
    <label for="check" class="checkbtn">☰</label>

    <div class="nav-links">
        <a href="index.php">Početna</a>
        <a href="grafikon.php"><strong>Grafikon</strong></a>
        <a href="galerija.php">Galerija</a>

        <?php if ($prijavljen): ?>
            <a href="logout.php">Odjava (<?= htmlspecialchars($korisnik_ime) ?>)</a>
        <?php else: ?>
            <a href="login.php">Login</a>
            <a href="registracija.php">Registracija</a>
        <?php endif; ?>
    </div>
</nav>

<main>

<!-- PIE CHART -->
<section class="grafikon-container">

    <div class="pie-chart">
        <svg viewBox="0 0 62 62">

            <circle class="pie1" cx="30" cy="30" r="15.9"></circle>
            <circle class="pie2" cx="30" cy="30" r="15.9"></circle>
            <circle class="pie3" cx="30" cy="30" r="15.9"></circle>
            <circle class="pie4" cx="30" cy="30" r="15.9"></circle>
            <circle class="pie5" cx="30" cy="30" r="15.9"></circle>
            <circle class="pie6" cx="30" cy="30" r="15.9"></circle>

        </svg>
    </div>

    <!-- LEGENDA -->
    <div class="legend">
        <ul class="types">
            <li>Documentary</li>
            <li>Crime</li>
            <li>Children movie</li>
            <li>Drama</li>
            <li>Reality</li>
            <li>Comedy</li>
        </ul>
    </div>

</section>

</main>

<footer>
    <p>&copy; 2025 Filmbase</p>
</footer>

</body>
</html>
