<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

$prijavljen   = isLoggedIn();
$je_admin     = isAdmin();
$korisnik_ime = $_SESSION['korisnicko_ime'] ?? '';

// ── Dodaj film u bazu (admin) ──────────────────────────────────────────────
$poruka_forma = '';
if ($je_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['akcija']) && $_POST['akcija'] === 'dodaj_film') {
    $naslov    = trim($_POST['naslov']    ?? '');
    $zanr      = trim($_POST['zanr']      ?? '');
    $godina    = (int)($_POST['godina']   ?? 0);
    $trajanje  = (int)($_POST['trajanje'] ?? 0);
    $ocjena    = (float)($_POST['ocjena'] ?? 0);
    $redatelj  = trim($_POST['redatelj']  ?? '');
    $zemlja    = trim($_POST['zemlja']    ?? '');

    if (!$naslov || !$zanr || $godina < 1888 || $godina > 2030 || $trajanje < 1 || $trajanje > 600) {
        $poruka_forma = '<div class="alert alert-error">Neispravni podaci. Provjeri godinu (1888–2030) i trajanje (1–600 min).</div>';
    } else {
        $stmt = $conn->prepare("INSERT INTO filmovi (naslov, zanr, godina, trajanje_min, ocjena, redatelj, zemlja) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("ssiiidss", $naslov, $zanr, $godina, $trajanje, $ocjena, $redatelj, $zemlja);
        // fix bind: ocjena is decimal
        $stmt = $conn->prepare("INSERT INTO filmovi (naslov, zanr, godina, trajanje_min, ocjena, redatelj, zemlja) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("ssiidss", $naslov, $zanr, $godina, $trajanje, $ocjena, $redatelj, $zemlja);
        if ($stmt->execute()) {
            $poruka_forma = '<div class="alert alert-success">Film uspješno dodan!</div>';
        } else {
            $poruka_forma = '<div class="alert alert-error">Greška pri dodavanju filma.</div>';
        }
        $stmt->close();
    }
}

// ── Briši film (admin) ────────────────────────────────────────────────────
if ($je_admin && isset($_GET['brisi']) && is_numeric($_GET['brisi'])) {
    $id = (int)$_GET['brisi'];
    $stmt = $conn->prepare("DELETE FROM filmovi WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header('Location: index.php');
    exit;
}

// ── Dodaj u videoteku (korisnik) ──────────────────────────────────────────
$poruka_videoteka = '';
if ($prijavljen && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['akcija']) && $_POST['akcija'] === 'dodaj_u_videoteku') {
    $film_id = (int)($_POST['film_id'] ?? 0);
    if ($film_id > 0) {
        // Provjeri prosječnu ocjenu filma
        $res = $conn->query("SELECT ocjena FROM filmovi WHERE id = $film_id");
        $film_row = $res->fetch_assoc();
        $upozorenje = ($film_row && $film_row['ocjena'] < 5.0);

        $stmt = $conn->prepare("INSERT IGNORE INTO zeljeni_filmovi (korisnik_id, film_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $_SESSION['user_id'], $film_id);
        $stmt->execute();
        $stmt->close();

        if ($upozorenje) {
            $poruka_videoteka = '<div class="alert alert-warn">⚠️ Ovaj film ima nisku ocjenu – jeste li sigurni da ga želite dodati?</div>';
        } else {
            $poruka_videoteka = '<div class="alert alert-success">Film dodan u vašu videoteku!</div>';
        }
    }
}

// ── Ukloni iz videoteke ───────────────────────────────────────────────────
if ($prijavljen && isset($_GET['ukloni']) && is_numeric($_GET['ukloni'])) {
    $film_id = (int)$_GET['ukloni'];
    $stmt = $conn->prepare("DELETE FROM zeljeni_filmovi WHERE korisnik_id = ? AND film_id = ?");
    $stmt->bind_param("ii", $_SESSION['user_id'], $film_id);
    $stmt->execute();
    $stmt->close();
    header('Location: index.php#videoteka');
    exit;
}

// ── Filtriranje i prikaz filmova ──────────────────────────────────────────
$zanr_filter    = trim($_GET['zanr']    ?? '');
$godina_filter  = (int)($_GET['godina'] ?? 0);
$ocjena_filter  = isset($_GET['ocjena']) ? (float)$_GET['ocjena'] : 0;
$sort           = in_array($_GET['sort'] ?? '', ['naslov','godina','ocjena']) ? $_GET['sort'] : 'naslov';

$where = ["1=1"];
$params = [];
$types  = "";

if ($zanr_filter) {
    $where[] = "zanr LIKE ?";
    $params[] = "%$zanr_filter%";
    $types .= "s";
}
if ($godina_filter > 0) {
    $where[] = "godina >= ?";
    $params[] = $godina_filter;
    $types .= "i";
}
if ($ocjena_filter > 0) {
    $where[] = "ocjena >= ?";
    $params[] = $ocjena_filter;
    $types .= "d";
}

$sql = "SELECT * FROM filmovi WHERE " . implode(" AND ", $where) . " ORDER BY $sort ASC";
$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$filmovi = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Moja videoteka ────────────────────────────────────────────────────────
$moji_filmovi = [];
$moji_ids = [];
if ($prijavljen) {
    $stmt = $conn->prepare("
        SELECT f.* FROM filmovi f
        JOIN zeljeni_filmovi zf ON f.id = zf.film_id
        WHERE zf.korisnik_id = ?
        ORDER BY zf.datum_dodavanja DESC
    ");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $moji_filmovi = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $moji_ids = array_column($moji_filmovi, 'id');
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filmbase</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/lv4.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.4.1/papaparse.min.js"></script>
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
        <?php if ($prijavljen): ?>
            <?php if ($je_admin): ?>
                <a href="admin/dashboard.php">Admin</a>
            <?php endif; ?>
            <a href="logout.php">Odjava (<?= htmlspecialchars($korisnik_ime) ?>)</a>
        <?php else: ?>
            <a href="login.php">Prijava</a>
            <a href="registracija.php">Registracija</a>
        <?php endif; ?>
    </div>
</nav>

<main>

<!-- ==================== ZADATAK 1 (LV1/LV2): CSV tablica ==================== -->
<h1 id="tablicatekst">POPIS FILMOVA (iz CSV datoteke)</h1>
<div class="container">
    <table id="filmovi-tablica">
        <thead>
            <tr>
                <th>Naslov</th><th>Žanr</th><th>Godina</th>
                <th>Trajanje (min)</th><th>Ocjena</th><th>Redatelj</th><th>Zemlja</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>

<!-- ==================== ZADATAK 2 (LV2): Filtriranje (JS) ==================== -->
<h1 id="tablicatekst">FILTRIRANJE FILMOVA</h1>
<div id="filteri">
    <label for="filter-zanr">Žanr:</label>
    <select id="filter-zanr">
        <option value="">-- Svi žanrovi --</option>
        <option value="Drama">Drama</option><option value="Crime">Crime</option>
        <option value="Action">Action</option><option value="Adventure">Adventure</option>
        <option value="Comedy">Comedy</option><option value="Biography">Biography</option>
        <option value="Sci-Fi">Sci-Fi</option><option value="Horror">Horror</option>
        <option value="Animation">Animation</option><option value="Western">Western</option>
        <option value="Thriller">Thriller</option><option value="War">War</option>
    </select>
    <label for="filter-godina">Godina od:</label>
    <input type="number" id="filter-godina" placeholder="npr. 1990" min="1900" max="2025">
    <label for="filter-ocjena">Min. ocjena: <span id="ocjena-vrijednost">7.0</span></label>
    <input type="range" id="filter-ocjena" min="0" max="10" step="0.1" value="7">
    <button id="primijeni-filtere">Filtriraj</button>
    <button id="reset-filtere">Poništi filtere</button>
</div>
<div class="container" id="filtrirani-container" style="display:none;">
    <table id="filtriranje-tablica">
        <thead>
            <tr>
                <th>Naslov</th><th>Žanr</th><th>Godina</th>
                <th>Trajanje (min)</th><th>Ocjena</th><th>Zemlja</th><th>Dodaj</th>
            </tr>
        </thead>
        <tbody id="filtrirani-tbody"></tbody>
    </table>
</div>
<aside id="kosarica">
    <h2>🎬 Moja košarica <span id="broj-u-kosarica">(0)</span></h2>
    <ul id="lista-kosarice"></ul>
    <p id="kosarica-prazna">Košarica je prazna.</p>
    <button id="potvrdi-kosaricu">✅ Potvrdi posudbu</button>
</aside>

<!-- ==================== LV4 ZADATAK 1a: Filmovi iz baze ==================== -->
<section id="filmovi-baza">
    <h1 id="tablicatekst">🎬 FILMOVI IZ BAZE PODATAKA (LV4)</h1>

    <?= $poruka_forma ?>
    <?= $poruka_videoteka ?>

    <!-- Filter forma (server-side) -->
    <form method="GET" action="index.php" id="db-filteri">
        <div id="filteri">
            <label>Žanr:</label>
            <select name="zanr">
                <option value="">-- Svi žanrovi --</option>
                <?php foreach (['Drama','Crime','Action','Adventure','Comedy','Biography','Sci-Fi','Horror','Animation','Western','Thriller','War'] as $z): ?>
                    <option value="<?= $z ?>" <?= $zanr_filter === $z ? 'selected' : '' ?>><?= $z ?></option>
                <?php endforeach; ?>
            </select>
            <label>Godina od:</label>
            <input type="number" name="godina" value="<?= $godina_filter ?: '' ?>" placeholder="npr. 1990" min="1900" max="2030">
            <label>Min. ocjena:</label>
            <input type="number" name="ocjena" value="<?= $ocjena_filter ?: '' ?>" step="0.1" min="0" max="10" placeholder="0–10">
            <label>Sortiraj po:</label>
            <select name="sort">
                <option value="naslov"  <?= $sort==='naslov'  ? 'selected':'' ?>>Naslovu</option>
                <option value="godina"  <?= $sort==='godina'  ? 'selected':'' ?>>Godini</option>
                <option value="ocjena"  <?= $sort==='ocjena'  ? 'selected':'' ?>>Ocjeni</option>
            </select>
            <button type="submit" id="primijeni-filtere">Filtriraj</button>
            <a href="index.php" style="margin-left:8px;"><button type="button" id="reset-filtere">Poništi</button></a>
        </div>
    </form>

    <div class="container">
        <table id="filtriranje-tablica" style="width:100%">
            <thead>
                <tr>
                    <th>Naslov</th><th>Žanr</th><th>Godina</th>
                    <th>Trajanje</th><th>Ocjena</th><th>Redatelj</th><th>Zemlja</th>
                    <?php if ($prijavljen): ?><th>Videoteka</th><?php endif; ?>
                    <?php if ($je_admin): ?><th>Akcije</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($filmovi as $film): ?>
                <tr>
                    <td><?= htmlspecialchars($film['naslov']) ?></td>
                    <td><?= htmlspecialchars($film['zanr']) ?></td>
                    <td><?= $film['godina'] ?></td>
                    <td><?= $film['trajanje_min'] ?> min</td>
                    <td><?= number_format($film['ocjena'], 1) ?></td>
                    <td><?= htmlspecialchars($film['redatelj'] ?? '–') ?></td>
                    <td><?= htmlspecialchars($film['zemlja'] ?? '–') ?></td>
                    <?php if ($prijavljen): ?>
                    <td>
                        <?php if (in_array($film['id'], $moji_ids)): ?>
                            <span style="color:green">✓ U videoteci</span>
                        <?php else: ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="akcija" value="dodaj_u_videoteku">
                                <input type="hidden" name="film_id" value="<?= $film['id'] ?>">
                                <button type="submit" class="dodaj-btn">+ Dodaj</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <?php if ($je_admin): ?>
                    <td>
                        <a href="?brisi=<?= $film['id'] ?>" class="btn-delete"
                           onclick="return confirm('Obrisati film?')">🗑 Briši</a>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($filmovi)): ?>
                <tr><td colspan="9" style="text-align:center">Nema rezultata.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Admin: Dodaj novi film -->
    <?php if ($je_admin): ?>
    <div class="admin-forma">
        <h3>➕ Dodaj novi film</h3>
        <form method="POST" action="index.php">
            <input type="hidden" name="akcija" value="dodaj_film">
            <div class="forma-grid">
                <div><label>Naslov *</label><input type="text" name="naslov" required maxlength="255"></div>
                <div><label>Žanr *</label><input type="text" name="zanr" required maxlength="100"></div>
                <div><label>Godina * (1888–2030)</label><input type="number" name="godina" required min="1888" max="2030"></div>
                <div><label>Trajanje (min) *</label><input type="number" name="trajanje" required min="1" max="600"></div>
                <div><label>Ocjena (0–10)</label><input type="number" name="ocjena" step="0.1" min="0" max="10" value="0"></div>
                <div><label>Redatelj</label><input type="text" name="redatelj" maxlength="150"></div>
                <div><label>Zemlja</label><input type="text" name="zemlja" maxlength="100"></div>
            </div>
            <button type="submit" class="btn-primary">Dodaj film</button>
        </form>
    </div>
    <?php elseif (!$prijavljen): ?>
    <p style="text-align:center;color:#888;margin:20px">
        <a href="login.php">Prijavite se</a> kako biste dodali filmove u svoju videoteku.
    </p>
    <?php endif; ?>
</section>

<!-- ==================== LV4: Moja videoteka ==================== -->
<?php if ($prijavljen): ?>
<section id="videoteka">
    <h1 id="tablicatekst">📚 MOJA VIDEOTEKA</h1>
    <?php if (empty($moji_filmovi)): ?>
        <p style="text-align:center;color:#888">Vaša videoteka je prazna. Dodajte filmove iz tablice iznad.</p>
    <?php else: ?>
    <div class="container">
        <table id="filtriranje-tablica" style="width:100%">
            <thead>
                <tr><th>Naslov</th><th>Žanr</th><th>Godina</th><th>Ocjena</th><th>Ukloni</th></tr>
            </thead>
            <tbody>
            <?php foreach ($moji_filmovi as $film): ?>
                <tr>
                    <td><?= htmlspecialchars($film['naslov']) ?></td>
                    <td><?= htmlspecialchars($film['zanr']) ?></td>
                    <td><?= $film['godina'] ?></td>
                    <td><?= number_format($film['ocjena'], 1) ?></td>
                    <td>
                        <a href="?ukloni=<?= $film['id'] ?>#videoteka" class="btn-delete"
                           onclick="return confirm('Ukloniti film iz videoteke?')">✕ Ukloni</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>

</main>

<footer><p>&copy; 2025. Web Programiranje. Sva prava pridrzana.</p></footer>
<script src="js/script.js"></script>
</body>
</html>
