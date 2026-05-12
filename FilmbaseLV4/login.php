<?php
session_start();
require_once 'includes/db.php';

// Već prijavljen → preusmjeri
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$greska = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $korisnicko_ime = trim($_POST['korisnicko_ime'] ?? '');
    $lozinka        = $_POST['lozinka'] ?? '';

    if (empty($korisnicko_ime) || empty($lozinka)) {
        $greska = 'Unesite korisničko ime i lozinku.';
    } else {
        $stmt = $conn->prepare("SELECT id, korisnicko_ime, lozinka, uloga FROM korisnici WHERE korisnicko_ime = ?");
        $stmt->bind_param("s", $korisnicko_ime);
        $stmt->execute();
        $result = $stmt->get_result();
        $korisnik = $result->fetch_assoc();

        if ($korisnik && password_verify($lozinka, $korisnik['lozinka'])) {
            $_SESSION['user_id']         = $korisnik['id'];
            $_SESSION['korisnicko_ime']  = $korisnik['korisnicko_ime'];
            $_SESSION['uloga']           = $korisnik['uloga'];

            $redirect = $_GET['redirect'] ?? 'index.php';
            header('Location: ' . $redirect);
            exit;
        } else {
            $greska = 'Pogrešno korisničko ime ili lozinka.';
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
    <title>Filmbase – Prijava</title>
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
        <a href="grafikon.php">Grafikon</a>
        <a href="galerija.php">Galerija</a>
        <a href="login.php"><strong>Prijava</strong></a>
        <a href="registracija.php">Registracija</a>
    </div>
</nav>

<main>
    <div class="auth-box">
        <h2>🔐 Prijava</h2>
        <?php if ($greska): ?>
            <div class="alert alert-error"><?= htmlspecialchars($greska) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <label>Korisničko ime</label>
            <input type="text" name="korisnicko_ime" required
                   value="<?= htmlspecialchars($_POST['korisnicko_ime'] ?? '') ?>">

            <label>Lozinka</label>
            <input type="password" name="lozinka" required>

            <button type="submit" class="btn-primary">Prijavi se</button>
        </form>

        <p class="auth-link">Nemate račun? <a href="registracija.php">Registrirajte se</a></p>
        <div class="demo-info">
            <strong>Demo pristup:</strong><br>
            Korisničko ime: <code>admin</code><br>
            Lozinka: <code>password</code>
        </div>
    </div>
</main>

<footer><p>&copy; 2025. Web Programiranje. Sva prava pridrzana.</p></footer>
</body>
</html>
