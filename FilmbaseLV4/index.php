<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

$prijavljen   = isLoggedIn();
$je_admin     = isAdmin();
$korisnik_ime = $_SESSION['korisnicko_ime'] ?? '';

// ── Dodaj film u bazu (admin) ─────────────────────────────────────────────
$poruka_forma = '';
if ($je_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['akcija']) && $_POST['akcija'] === 'dodaj_film') {
    $naslov   = trim($_POST['naslov']    ?? '');
    $zanr     = trim($_POST['zanr']      ?? '');
    $godina   = (int)($_POST['godina']   ?? 0);
    $trajanje = (int)($_POST['trajanje'] ?? 0);
    $ocjena   = (float)($_POST['ocjena'] ?? 0);
    $redatelj = trim($_POST['redatelj']  ?? '');
    $zemlja   = trim($_POST['zemlja']    ?? '');

    if (!$naslov || !$zanr || $godina < 1888 || $godina > 2030 || $trajanje < 1 || $trajanje > 600) {
        $poruka_forma = '<div class="alert alert-error">Neispravni podaci. Provjeri godinu (1888–2030) i trajanje (1–600 min).</div>';
    } else {
        $stmt = $conn->prepare("INSERT INTO filmovi (naslov, zanr, godina, trajanje_min, ocjena, redatelj, zemlja) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("ssiidss", $naslov, $zanr, $godina, $trajanje, $ocjena, $redatelj, $zemlja);
        $poruka_forma = $stmt->execute()
            ? '<div class="alert alert-success">Film uspješno dodan!</div>'
            : '<div class="alert alert-error">Greška pri dodavanju filma.</div>';
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

// ── Osobna videoteka (trajno u bazu) + Upozorenje za nisku ocjenu ─────────
$upozorenje_niskocjena = '';
$poruka_videoteka      = '';

if ($prijavljen && isset($_GET['dodaj_videoteka']) && is_numeric($_GET['dodaj_videoteka'])) {
    $film_id   = (int)$_GET['dodaj_videoteka'];
    $potvrdeno = isset($_GET['potvrdeno']) ? (int)$_GET['potvrdeno'] : 0;

    // Dohvati podatke o odabranom filmu
    $stmt = $conn->prepare("SELECT naslov, ocjena FROM filmovi WHERE id = ?");
    $stmt->bind_param("i", $film_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $odabrani_film = $res->fetch_assoc();
    $stmt->close();

    if ($odabrani_film) {
        // Provjeri je li film već dodan u videoteku korisnika
        $stmt_provjera = $conn->prepare("SELECT id FROM zeljeni_filmovi WHERE korisnik_id = ? AND film_id = ?");
        $stmt_provjera->bind_param("ii", $_SESSION['user_id'], $film_id);
        $stmt_provjera->execute();
        $vec_u_bazi = $stmt_provjera->get_result()->num_rows > 0;
        $stmt_provjera->close();

        if ($vec_u_bazi) {
            $poruka_videoteka = '<div class="alert alert-error">Ovaj film je već u vašoj osobnoj videoteci!</div>';
        } else {
            // Ako je ocjena ispod 5.0 i korisnik još nije potvrdio unos, ispiši crveno upozorenje
            if ($odabrani_film['ocjena'] < 5.0 && $potvrdeno === 0) {
                $upozorenje_niskocjena = '
                <div style="border: 2px solid #c0392b; background-color: #fadbd8; padding: 20px; margin-bottom: 20px; border-radius: 8px; color: #78281f; text-align: center;">
                    <h3 style="margin-top: 0; color: #c0392b;">⚠️ Upozorenje: Film ima nisku ocjenu!</h3>
                    <p style="font-size: 16px;">Film <strong>' . htmlspecialchars($odabrani_film['naslov']) . '</strong> ima prosječnu ocjenu ispod 5.0 (' . number_format($odabrani_film['ocjena'], 1) . ').</p>
                    <p style="font-size: 15px; margin-bottom: 15px;">Jeste li sigurni da ga želite dodati u svoju osobnu videoteku?</p>
                    <div>
                        <a href="index.php?dodaj_videoteka=' . $film_id . '&potvrdeno=1" style="background-color: #c0392b; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;">Da, siguran sam</a>
                        <a href="index.php" style="background-color: #7f8c8d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block; margin-left: 10px;">Odustani</a>
                    </div>
                </div>';
            } else {
                // Trajno spremanje u bazu
                $stmt_ins = $conn->prepare("INSERT INTO zeljeni_filmovi (korisnik_id, film_id) VALUES (?, ?)");
                $stmt_ins->bind_param("ii", $_SESSION['user_id'], $film_id);
                if ($stmt_ins->execute()) {
                    header('Location: index.php?otvori_videoteku=1&poruka=uspjeh');
                    exit;
                } else {
                    $poruka_videoteka = '<div class="alert alert-error">Greška pri spremanju u videoteku.</div>';
                }
                $stmt_ins->close();
            }
        }
    }
}

// ── Uklanjanje filma iz osobne videoteke ──────────────────────────────────
if ($prijavljen && isset($_GET['ukloni_videoteka']) && is_numeric($_GET['ukloni_videoteka'])) {
    $zeljeni_id = (int)$_GET['ukloni_videoteka'];
    $stmt = $conn->prepare("DELETE FROM zeljeni_filmovi WHERE id = ? AND korisnik_id = ?");
    $stmt->bind_param("ii", $zeljeni_id, $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();
    header('Location: index.php?otvori_videoteku=1');
    exit;
}

// Povratna informacija o uspješnom dodavanju
if (isset($_GET['poruka']) && $_GET['poruka'] === 'uspjeh') {
    $poruka_videoteka = '<div class="alert alert-success">Film uspješno dodan u osobnu videoteku!</div>';
}

// ── Dohvat stavaka osobne videoteke za prijavljenog korisnika ─────────────
$moja_videoteka = [];
$videoteka_ids  = []; // Za brzu provjeru u tablici kako bismo onemogućili duple unose
if ($prijavljen) {
    $stmt = $conn->prepare("
        SELECT z.id AS zeljeni_id, f.id AS film_id, f.naslov, f.zanr, f.godina 
        FROM zeljeni_filmovi z 
        JOIN filmovi f ON z.film_id = f.id 
        WHERE z.korisnik_id = ?
        ORDER BY z.vrijeme_dodavanja DESC
    ");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $moja_videoteka = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($moja_videoteka as $s) {
        $videoteka_ids[] = $s['film_id'];
    }
}

// ── Filtriranje ─────────────────────────────────────────────────────────
$zanr_filter   = trim($_GET['zanr']    ?? '');
$godina_filter = (int)($_GET['godina'] ?? 0);
$ocjena_filter = isset($_GET['ocjena']) ? (float)$_GET['ocjena'] : 0;
$sort          = in_array($_GET['sort'] ?? '', ['naslov','godina','ocjena']) ? $_GET['sort'] : 'naslov';

$where = ["1=1"]; $params = []; $types = "";
if ($zanr_filter)   { $where[] = "zanr LIKE ?";   $params[] = "%$zanr_filter%"; $types .= "s"; }
if ($godina_filter) { $where[] = "godina >= ?";    $params[] = $godina_filter;  $types .= "i"; }
if ($ocjena_filter) { $where[] = "ocjena >= ?";    $params[] = $ocjena_filter;  $types .= "d"; }

$sql  = "SELECT * FROM filmovi WHERE " . implode(" AND ", $where) . " ORDER BY $sort ASC";
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$filmovi = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
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
    <style>
    /* ── FLOATING OSOBNA VIDEOTEKA ───────────────────────────────────────── */
    #kosarica-aside {
        position: fixed;
        right: 0;
        top: 180px;
        width: 290px;
        background: #fff;
        border: 2px solid lightcoral;
        border-right: none;
        border-radius: 12px 0 0 12px;
        box-shadow: -4px 4px 20px rgba(0,0,0,0.18);
        z-index: 9999;
        transform: translateX(100%);
        transition: transform 0.28s cubic-bezier(.4,0,.2,1);
    }
    #kosarica-aside.otvoren {
        transform: translateX(0);
    }
    #kosarica-tab {
        position: absolute;
        right: 100%;
        top: 18px;
        background: lightcoral;
        color: white;
        border: none;
        border-radius: 10px 0 0 10px;
        width: 48px;
        padding: 10px 0;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
        font-size: 20px;
        box-shadow: -3px 2px 8px rgba(0,0,0,0.2);
        user-select: none;
    }
    #kosarica-badge {
        background: white;
        color: lightcoral;
        border-radius: 50%;
        width: 22px;
        height: 22px;
        font-size: 12px;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    #kosarica-body {
        padding: 14px 14px 16px;
        max-height: 65vh;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
    }
    #kosarica-body h3 {
        margin: 0 0 10px;
        color: lightcoral;
        font-size: 15px;
        border-bottom: 1px solid #eee;
        padding-bottom: 8px;
    }
    #lista-kosarice {
        list-style: none;
        padding: 0;
        margin: 0 0 10px;
        flex: 1;
    }
    #lista-kosarice li {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 7px 0;
        border-bottom: 1px solid #f2f2f2;
        gap: 6px;
        font-size: 13px;
    }
    #lista-kosarice li:last-child { border-bottom: none; }
    .k-film-naslov { font-weight: bold; display: block; }
    .k-film-meta   { color: #888; font-size: 12px; }
    .k-ukloni {
        background: none;
        border: none;
        color: #c0392b;
        cursor: pointer;
        font-size: 15px;
        padding: 0 2px;
        flex-shrink: 0;
        line-height: 1;
        font-weight: bold;
    }
    .k-ukloni:hover { color: #922b21; }
    #k-prazna {
        text-align: center;
        color: #bbb;
        font-size: 13px;
        padding: 16px 0;
    }
    /* Gumbi u tablici */
    .k-dodaj-btn {
        background: lightcoral;
        color: white;
        border: none;
        padding: 5px 10px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        white-space: nowrap;
        font-weight: bold;
    }
    .k-dodaj-btn:hover { background: #c0392b; }
    .k-dodaj-btn.u-kosarici {
        background: #27ae60;
        cursor: default;
    }
    </style>
</head>
<body>

<aside id="kosarica-aside" class="<?= isset($_GET['otvori_videoteku']) ? 'otvoren' : '' ?>">
    <button id="kosarica-tab" title="Otvori osobnu videoteku">
        🎬
        <span id="kosarica-badge"><?= count($moja_videoteka) ?></span>
    </button>
    <div id="kosarica-body">
        <h3>📂 Moja osobna videoteka</h3>
        <?php if (!$prijavljen): ?>
            <p id="k-prazna" style="display:block;">Morate se <a href="login.php" style="color:lightcoral;">prijaviti</a> za spremanje filmova.</p>
        <?php else: ?>
            <ul id="lista-kosarice">
                <?php foreach ($moja_videoteka as $film): ?>
                    <li>
                        <div>
                            <span class="k-film-naslov"><?= htmlspecialchars($film['naslov']) ?></span>
                            <span class="k-film-meta"><?= htmlspecialchars($film['zanr']) ?> · <?= $film['godina'] ?></span>
                        </div>
                        <a href="index.php?ukloni_videoteka=<?= $film['zeljeni_id'] ?>" class="k-ukloni" title="Ukloni iz videoteke" style="text-decoration:none;">✕</a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (empty($moja_videoteka)): ?>
                <p id="k-prazna" style="display:block;">Vaša videoteka je prazna.<br>Dodajte filmove iz tablice.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</aside>

<header id="Naslov"><h1>Dobrodosli na moju web stranicu</h1></header>
<nav class="navbar">
    <input type="checkbox" id="check">
    <label for="check" class="checkbtn">☰</label>
    <div class="nav-links">
        <a href="index.php"><strong>Pocetna</strong></a>
        <a href="grafikon.php">Grafikon</a>
        <a href="galerija.php">Galerija</a>
        <?php if ($prijavljen): ?>
            <a href="logout.php">Odjava (<?= htmlspecialchars($korisnik_ime) ?>)</a>
        <?php else: ?>
            <a href="login.php">Prijava</a>
            <a href="registracija.php">Registracija</a>
        <?php endif; ?>
    </div>
</nav>

<main>

<section id="filmovi-baza">
    <h1 id="tablicatekst">🎬 FILMOVI IZ BAZE PODATAKA (LV4)</h1>

    <?= $upozorenje_niskocjena ?>
    <?= $poruka_forma ?>
    <?= $poruka_videoteka ?>

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
                <option value="naslov" <?= $sort==='naslov' ? 'selected':'' ?>>Naslovu</option>
                <option value="godina" <?= $sort==='godina' ? 'selected':'' ?>>Godini</option>
                <option value="ocjena" <?= $sort==='ocjena' ? 'selected':'' ?>>Ocjeni</option>
            </select>
            <button type="submit">Filtriraj</button>
            <a href="index.php" style="margin-left:8px;"><button type="button">Poništi</button></a>
        </div>
    </form>

    <div class="container">
        <table id="filtriranje-tablica" style="width:100%">
            <thead>
                <tr>
                    <th>Naslov</th><th>Žanr</th><th>Godina</th>
                    <th>Trajanje</th><th>Ocjena</th><th>Redatelj</th><th>Zemlja</th>
                    <th>Videoteka</th>
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
                    <td style="text-align:center;">
                        <?php if ($prijavljen): ?>
                            <?php if (in_array($film['id'], $videoteka_ids)): ?>
                                <span class="k-dodaj-btn u-kosarici" style="display:inline-block;">✓ Dodano</span>
                            <?php else: ?>
                                <a href="index.php?dodaj_videoteka=<?= $film['id'] ?>" class="k-dodaj-btn" style="text-decoration:none; display:inline-block;">📂 Dodaj</a>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="login.php" class="k-dodaj-btn" style="text-decoration:none; display:inline-block; background:#aaa;" title="Prijavite se za spremanje">🔒 Dodaj</a>
                        <?php endif; ?>
                    </td>
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
        <a href="login.php">Prijavite se</a> za spremanje filmova u osobnu videoteku.
    </p>
    <?php endif; ?>
</section>

</main>
<footer><p>&copy; 2025. Web Programiranje. Sva prava pridrzana.</p></footer>
<script src="js/script.js"></script>
<script>
// ════════════════════════════════════════════════════
//  OTVARANJE I ZATVARANJE SIDEBARA VIDEOTEKE
// ════════════════════════════════════════════════════
(function () {
    const aside = document.getElementById('kosarica-aside');
    const tab   = document.getElementById('kosarica-tab');

    if (tab && aside) {
        tab.addEventListener('click', function (e) {
            e.stopPropagation();
            aside.classList.toggle('otvoren');
        });

        // Klik izvan videoteke je zatvara
        document.addEventListener('click', function (e) {
            if (aside.classList.contains('otvoren') && !aside.contains(e.target)) {
                aside.classList.remove('otvoren');
            }
        });
    }
})();
</script>
</body>
</html>
