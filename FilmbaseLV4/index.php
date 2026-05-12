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

// ── Filtriranje ───────────────────────────────────────────────────────────
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
    /* ── FLOATING KOŠARICA ───────────────────────────────────────────────── */
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
        /* skrivena – samo tab visi van s desne strane */
        transform: translateX(100%);
        transition: transform 0.28s cubic-bezier(.4,0,.2,1);
    }
    #kosarica-aside.otvoren {
        transform: translateX(0);
    }
    /* Tab koji uvijek viri s desne strane */
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
        font-size: 17px;
        padding: 0 2px;
        flex-shrink: 0;
        line-height: 1;
    }
    .k-ukloni:hover { color: #922b21; }
    #k-prazna {
        text-align: center;
        color: #bbb;
        font-size: 13px;
        padding: 16px 0;
    }
    #k-potvrdi {
        width: 100%;
        background: lightcoral;
        color: white;
        border: none;
        padding: 10px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: bold;
    }
    #k-potvrdi:hover { background: #c0392b; }

    /* gumb u tablici */
    .k-dodaj-btn {
        background: lightcoral;
        color: white;
        border: none;
        padding: 4px 10px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        white-space: nowrap;
    }
    .k-dodaj-btn:hover   { background: #c0392b; }
    .k-dodaj-btn.u-kosarici {
        background: #aaa;
        cursor: default;
    }
    </style>
</head>
<body>

<!-- ── FLOATING KOŠARICA ─────────────────────────────────────────────────── -->
<aside id="kosarica-aside">
    <button id="kosarica-tab" title="Otvori košaricu">
        🎬
        <span id="kosarica-badge">0</span>
    </button>
    <div id="kosarica-body">
        <h3>🛒 Košarica za posudbu</h3>
        <ul id="lista-kosarice"></ul>
        <p id="k-prazna">Košarica je prazna.<br>Dodajte filmove iz tablice.</p>
        <button id="k-potvrdi"><?= $prijavljen ? "✅ Potvrdi posudbu" : "🔒 Potvrdi posudbu" ?></button>
    </div>
</aside>

<header id="Naslov"><h1>Dobrodosli na moju web stranicu</h1></header>
<nav class="navbar">
    <input type="checkbox" id="check">
    <label for="check" class="checkbtn">☰</label>
    <div class="nav-links">
        <a href="index.php">Pocetna</a>
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

    <?= $poruka_forma ?>

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
                    <th>Košarica</th>
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
                    <td>
                        <button class="k-dodaj-btn"
                            data-id="<?= $film['id'] ?>"
                            data-naslov="<?= htmlspecialchars($film['naslov'], ENT_QUOTES) ?>"
                            data-zanr="<?= htmlspecialchars($film['zanr'], ENT_QUOTES) ?>"
                            data-godina="<?= $film['godina'] ?>">
                            🛒 Dodaj
                        </button>
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
        <a href="login.php">Prijavite se</a> za više mogućnosti.
    </p>
    <?php endif; ?>
</section>

</main>
<footer><p>&copy; 2025. Web Programiranje. Sva prava pridrzana.</p></footer>
<script src="js/script.js"></script>
<script>
const KORISNIK_PRIJAVLJEN = <?= $prijavljen ? 'true' : 'false' ?>;

// ════════════════════════════════════════════════════
//  KOŠARICA – floating aside
// ════════════════════════════════════════════════════
(function () {
    const aside  = document.getElementById('kosarica-aside');
    const tab    = document.getElementById('kosarica-tab');
    const lista  = document.getElementById('lista-kosarice');
    const prazna = document.getElementById('k-prazna');
    const badge  = document.getElementById('kosarica-badge');
    const potvrdi = document.getElementById('k-potvrdi');

    let kosarica = JSON.parse(localStorage.getItem('kosarica') || '[]');

    // ── Otvori / zatvori ────────────────────────────
    tab.addEventListener('click', function (e) {
        e.stopPropagation();
        aside.classList.toggle('otvoren');
    });

    // Klik izvan košarice zatvori je
    document.addEventListener('click', function (e) {
        if (aside.classList.contains('otvoren') && !aside.contains(e.target)) {
            aside.classList.remove('otvoren');
        }
    });

    // ── Render košarice ──────────────────────────────
    function render() {
        badge.textContent = kosarica.length;
        lista.innerHTML   = '';

        if (kosarica.length === 0) {
            prazna.style.display = 'block';
        } else {
            prazna.style.display = 'none';
            kosarica.forEach(function (film, i) {
                const li = document.createElement('li');
                li.innerHTML =
                    '<div><span class="k-film-naslov">' + film.naslov + '</span>' +
                    '<span class="k-film-meta">' + film.zanr + ' · ' + film.godina + '</span></div>' +
                    '<button class="k-ukloni" data-i="' + i + '" title="Ukloni">✕</button>';
                lista.appendChild(li);
            });
        }

        // Osvježi stanje gumba u tablici
        document.querySelectorAll('.k-dodaj-btn').forEach(function (btn) {
            const uKosarici = kosarica.some(function (f) { return f.id === parseInt(btn.dataset.id); });
            btn.textContent = uKosarici ? '✓ Dodano' : '🛒 Dodaj';
            btn.classList.toggle('u-kosarici', uKosarici);
            btn.disabled = uKosarici;
        });
    }

    // ── Ukloni klik (event delegation na listi) ───────
    lista.addEventListener('click', function (e) {
        const btn = e.target.closest('.k-ukloni');
        if (!btn) return;
        kosarica.splice(parseInt(btn.dataset.i), 1);
        localStorage.setItem('kosarica', JSON.stringify(kosarica));
        render();
    });

    // ── Dodaj iz tablice ─────────────────────────────
    document.querySelectorAll('.k-dodaj-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = parseInt(btn.dataset.id);
            if (kosarica.some(function (f) { return f.id === id; })) return;
            kosarica.push({ id: id, naslov: btn.dataset.naslov, zanr: btn.dataset.zanr, godina: btn.dataset.godina });
            localStorage.setItem('kosarica', JSON.stringify(kosarica));
            render();
            aside.classList.add('otvoren');
        });
    });

    // ── Potvrdi posudbu ──────────────────────────────
    potvrdi.addEventListener('click', function () {
        if (!KORISNIK_PRIJAVLJEN) {
            if (confirm('Morate biti prijavljeni za posudbu.\nŽelite li se prijaviti?')) {
                window.location.href = 'login.php';
            }
            return;
        }
        if (kosarica.length === 0) {
            alert('Košarica je prazna!');
            return;
        }
        const broj = kosarica.length;
        alert('✅ Uspješno ste dodali ' + broj + ' ' + (broj === 1 ? 'film' : 'filma') + ' u svoju košaricu za vikend maraton!');
        kosarica = [];
        localStorage.setItem('kosarica', JSON.stringify(kosarica));
        render();
        aside.classList.remove('otvoren');
    });

    render();
})();
</script>
</body>
</html>
