<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

$prijavljen   = isLoggedIn();
$je_admin     = isAdmin();
$korisnik_ime = $_SESSION['korisnicko_ime'] ?? '';

// ─────────────────────────────────────────────
// DODAJ FILM (ADMIN)
// ─────────────────────────────────────────────
$poruka_forma = '';

if ($je_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['akcija'] ?? '') === 'dodaj_film') {

    $naslov   = trim($_POST['naslov'] ?? '');
    $zanr     = trim($_POST['zanr'] ?? '');
    $godina   = (int)($_POST['godina'] ?? 0);
    $trajanje = (int)($_POST['trajanje'] ?? 0);
    $ocjena   = (float)($_POST['ocjena'] ?? 0);
    $redatelj = trim($_POST['redatelj'] ?? '');
    $zemlja   = trim($_POST['zemlja'] ?? '');

    if (!$naslov || !$zanr || $godina < 1888 || $godina > 2030 || $trajanje < 1) {
        $poruka_forma = "<div class='alert alert-error'>Neispravni podaci.</div>";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO filmovi (naslov, zanr, godina, trajanje_min, ocjena, redatelj, zemlja)
            VALUES (?,?,?,?,?,?,?)
        ");
        $stmt->bind_param("ssiidss", $naslov, $zanr, $godina, $trajanje, $ocjena, $redatelj, $zemlja);
        $stmt->execute();
        $stmt->close();

        $poruka_forma = "<div class='alert alert-success'>Film dodan!</div>";
    }
}

// ─────────────────────────────────────────────
// BRISANJE FILMA
// ─────────────────────────────────────────────
if ($je_admin && isset($_GET['brisi'])) {
    $id = (int)$_GET['brisi'];

    $stmt = $conn->prepare("DELETE FROM filmovi WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: index.php");
    exit;
}

// ─────────────────────────────────────────────
// DODAJ U VIDEOTEKU
// ─────────────────────────────────────────────
if ($prijavljen && ($_POST['akcija'] ?? '') === 'dodaj_u_videoteku') {

    $film_id = (int)($_POST['film_id'] ?? 0);

    $stmt = $conn->prepare("
        INSERT IGNORE INTO zeljeni_filmovi (korisnik_id, film_id)
        VALUES (?, ?)
    ");
    $stmt->bind_param("ii", $_SESSION['user_id'], $film_id);
    $stmt->execute();
    $stmt->close();
}

// ─────────────────────────────────────────────
// UKLONI IZ VIDEOTEKE
// ─────────────────────────────────────────────
if ($prijavljen && isset($_GET['ukloni'])) {

    $film_id = (int)$_GET['ukloni'];

    $stmt = $conn->prepare("
        DELETE FROM zeljeni_filmovi
        WHERE korisnik_id=? AND film_id=?
    ");
    $stmt->bind_param("ii", $_SESSION['user_id'], $film_id);
    $stmt->execute();
    $stmt->close();

    header("Location: index.php#videoteka");
    exit;
}

// ─────────────────────────────────────────────
// FILTER FILMOVA (OSTAVLJENO JEDNOSTAVNO)
// ─────────────────────────────────────────────
$zanr   = $_GET['zanr'] ?? '';
$godina = (int)($_GET['godina'] ?? 0);
$ocjena = (float)($_GET['ocjena'] ?? 0);

$where = "WHERE 1=1";

if ($zanr)   $where .= " AND zanr LIKE '%$zanr%'";
if ($godina) $where .= " AND godina >= $godina";
if ($ocjena) $where .= " AND ocjena >= $ocjena";

$sql = "SELECT * FROM filmovi $where ORDER BY naslov ASC";
$filmovi = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// ─────────────────────────────────────────────
// VIDEOTEKA
// ─────────────────────────────────────────────
$moji_filmovi = [];

if ($prijavljen) {
    $stmt = $conn->prepare("
        SELECT f.*
        FROM filmovi f
        JOIN zeljeni_filmovi z ON f.id = z.film_id
        WHERE z.korisnik_id=?
    ");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $moji_filmovi = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="hr">
<head>
<meta charset="UTF-8">
<title>Filmbase</title>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/lv4.css">
</head>

<body>

<header id="Naslov">
    <h1>Dobrodosli na moju web stranicu</h1>
</header>

<nav class="navbar">
    <input type="checkbox" id="check">
    <label for="check" class="checkbtn">☰</label>

    <div class="nav-links">
        <a href="index.php">Pocetna</a>
        <a href="grafikon.html">Grafikon</a>
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

<h2>🎬 Filmovi iz baze</h2>

<?= $poruka_forma ?>

<form method="GET">
    <input type="text" name="zanr" placeholder="Žanr">
    <input type="number" name="godina" placeholder="Godina">
    <input type="number" step="0.1" name="ocjena" placeholder="Ocjena">
    <button>Filter</button>
</form>

<table border="1">
<tr>
    <th>Naslov</th><th>Žanr</th><th>Godina</th><th>Ocjena</th>
    <?php if ($prijavljen): ?><th>Videoteka</th><?php endif; ?>
    <?php if ($je_admin): ?><th>Akcija</th><?php endif; ?>
</tr>

<?php foreach ($filmovi as $f): ?>
<tr>
    <td><?= htmlspecialchars($f['naslov']) ?></td>
    <td><?= $f['zanr'] ?></td>
    <td><?= $f['godina'] ?></td>
    <td><?= $f['ocjena'] ?></td>

    <?php if ($prijavljen): ?>
    <td>
        <form method="POST">
            <input type="hidden" name="akcija" value="dodaj_u_videoteku">
            <input type="hidden" name="film_id" value="<?= $f['id'] ?>">
            <button>+</button>
        </form>
    </td>
    <?php endif; ?>

    <?php if ($je_admin): ?>
    <td>
        <a href="?brisi=<?= $f['id'] ?>">🗑</a>
    </td>
    <?php endif; ?>
</tr>
<?php endforeach; ?>
</table>

<?php if ($prijavljen): ?>
<h2>📚 Moja videoteka</h2>

<table>
<?php foreach ($moji_filmovi as $f): ?>
<tr>
    <td><?= $f['naslov'] ?></td>
    <td><a href="?ukloni=<?= $f['id'] ?>">✕</a></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

</main>

<footer>
<p>&copy; 2025 Filmbase</p>
</footer>

</body>
</html>
