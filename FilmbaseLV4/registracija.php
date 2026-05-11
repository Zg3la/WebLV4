<?php
session_start();
require_once 'includes/db.php';

$greska = '';
$uspjeh = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $korisnicko_ime = trim($_POST['korisnicko_ime'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $lozinka        = $_POST['lozinka'] ?? '';
    $potvrda        = $_POST['potvrda_lozinke'] ?? '';

    // Validacija
    if (empty($korisnicko_ime) || empty($email) || empty($lozinka)) {
        $greska = 'Sva polja su obavezna.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $greska = 'Neispravna email adresa.';
    } elseif (strlen($lozinka) < 6) {
        $greska = 'Lozinka mora imati najmanje 6 znakova.';
    } elseif ($lozinka !== $potvrda) {
        $greska = 'Lozinke se ne podudaraju.';
    } else {
        // Provjera postoji li korisnik
        $stmt = $conn->prepare("SELECT id FROM korisnici WHERE korisnicko_ime = ? OR email = ?");
        $stmt->bind_param("ss", $korisnicko_ime, $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $greska = 'Korisničko ime ili email već postoje.';
        } else {
            $hash = password_hash($lozinka, PASSWORD_DEFAULT);
            $stmt2 = $conn->prepare("INSERT INTO korisnici (korisnicko_ime, email, lozinka) VALUES (?, ?, ?)");
            $stmt2->bind_param("sss", $korisnicko_ime, $email, $hash);
            if ($stmt2->execute()) {
                $uspjeh = 'Registracija uspješna! Možete se <a href="login.php">prijaviti</a>.';
            } else {
                $greska = 'Greška pri registraciji.';
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filmbase – Registracija</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/auth.css">
</head>
<body>
<header id="Naslov"><h1>Dobrodosli na moju web stranicu</h1></header>
<nav class="navbar">
    <input type="checkbox" id="check">
    <label for="check" class="checkbtn">☰</label>
    <div class="nav-links">
        <a href="index.php">Pocetna</a>
        <a href="grafikon.html">Grafikon</a>
        <a href="galerija.php">Galerija</a>
        <a href="login.php">Prijava</a>
        <a href="registracija.php"><strong>Registracija</strong></a>
    </div>
</nav>

<main>
    <div class="auth-box">
        <h2>📝 Registracija</h2>
        <?php if ($greska): ?>
            <div class="alert alert-error"><?= htmlspecialchars($greska) ?></div>
        <?php endif; ?>
        <?php if ($uspjeh): ?>
            <div class="alert alert-success"><?= $uspjeh ?></div>
        <?php else: ?>
        <form method="POST" action="registracija.php">
            <label>Korisničko ime</label>
            <input type="text" name="korisnicko_ime" required maxlength="50"
                   value="<?= htmlspecialchars($_POST['korisnicko_ime'] ?? '') ?>">

            <label>Email</label>
            <input type="email" name="email" required
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

            <label>Lozinka (min. 6 znakova)</label>
            <input type="password" name="lozinka" required minlength="6">

            <label>Potvrda lozinke</label>
            <input type="password" name="potvrda_lozinke" required minlength="6">

            <button type="submit" class="btn-primary">Registriraj se</button>
        </form>
        <p class="auth-link">Već imate račun? <a href="login.php">Prijavite se</a></p>
        <?php endif; ?>
    </div>
</main>

<footer><p>&copy; 2025. Web Programiranje. Sva prava pridrzana.</p></footer>
</body>
</html>
