<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

$prijavljen = isLoggedIn();
$je_admin   = isAdmin();
$poruka     = '';

// ── Admin: upload slike ───────────────────────────────────────────────────
if ($je_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['akcija']) && $_POST['akcija'] === 'dodaj_sliku') {
    $opis = trim($_POST['opis'] ?? '');
    if (!empty($_POST['url_slike'])) {
        $url   = trim($_POST['url_slike']);
        $naziv = basename(parse_url($url, PHP_URL_PATH)) ?: 'slika_' . time();
        $stmt  = $conn->prepare("INSERT INTO slike (naziv_datoteke, opis, putanja, izvor) VALUES (?,?,?,'url')");
        $stmt->bind_param("sss", $naziv, $opis, $url);
        $poruka = $stmt->execute() ? '<div class="alert alert-success">Slika dodana!</div>' : '<div class="alert alert-error">Greška.</div>';
        $stmt->close();
    } elseif (isset($_FILES['slika']) && $_FILES['slika']['error'] === UPLOAD_ERR_OK) {
        $allowed  = ['image/jpeg','image/png'];
        $max_size = 5 * 1024 * 1024;
        if (!in_array($_FILES['slika']['type'], $allowed)) {
            $poruka = '<div class="alert alert-error">Dopušteni su samo JPEG i PNG formati.</div>';
        } elseif ($_FILES['slika']['size'] > $max_size) {
            $poruka = '<div class="alert alert-error">Slika ne smije biti veća od 5MB.</div>';
        } else {
            $naziv = time() . '_' . basename($_FILES['slika']['name']);
            $dest  = __DIR__ . '/slike/' . $naziv;
            if (move_uploaded_file($_FILES['slika']['tmp_name'], $dest)) {
                $putanja = 'slike/' . $naziv;
                $stmt = $conn->prepare("INSERT INTO slike (naziv_datoteke, opis, putanja, izvor) VALUES (?,?,?,'lokalno')");
                $stmt->bind_param("sss", $naziv, $opis, $putanja);
                $poruka = $stmt->execute() ? '<div class="alert alert-success">Slika uploadana!</div>' : '<div class="alert alert-error">Greška.</div>';
                $stmt->close();
            }
        }
    } else {
        $poruka = '<div class="alert alert-error">Dodajte URL ili odaberite datoteku.</div>';
    }
}

// ── Admin: briši sliku ────────────────────────────────────────────────────
if ($je_admin && isset($_GET['brisi_sliku']) && is_numeric($_GET['brisi_sliku'])) {
    $id = (int)$_GET['brisi_sliku'];
    $conn->query("DELETE FROM ocjene WHERE id_slika = $id");
    $conn->query("DELETE FROM slike WHERE id = $id");
    header('Location: galerija.php'); exit;
}

// ── AJAX: ocijeni / makni ocjenu ──────────────────────────────────────────
if ($prijavljen && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $akcija   = $_POST['akcija']   ?? '';
    $id_slika = (int)($_POST['id_slika'] ?? 0);

    if ($akcija === 'ocijeni' && $id_slika > 0) {
        $ocjena = (int)($_POST['ocjena'] ?? 0);
        if ($ocjena >= 1 && $ocjena <= 5) {
            $stmt = $conn->prepare("INSERT INTO ocjene (id_korisnik, id_slika, ocjena) VALUES (?,?,?) ON DUPLICATE KEY UPDATE ocjena=VALUES(ocjena), vrijeme_ocjene=NOW()");
            $stmt->bind_param("iii", $_SESSION['user_id'], $id_slika, $ocjena);
            $stmt->execute(); $stmt->close();
        }
    } elseif ($akcija === 'makni_ocjenu' && $id_slika > 0) {
        $stmt = $conn->prepare("DELETE FROM ocjene WHERE id_korisnik=? AND id_slika=?");
        $stmt->bind_param("ii", $_SESSION['user_id'], $id_slika);
        $stmt->execute(); $stmt->close();
    }

    // Vrati ažurirane podatke
    $res = $conn->query("SELECT ROUND(AVG(ocjena),1) AS avg_o, COUNT(*) AS cnt FROM ocjene WHERE id_slika=$id_slika");
    $row = $res->fetch_assoc();
    // Provjeri ima li user još ocjenu
    $res2 = $conn->query("SELECT ocjena FROM ocjene WHERE id_korisnik={$_SESSION['user_id']} AND id_slika=$id_slika");
    $moja = $res2->num_rows ? (int)$res2->fetch_assoc()['ocjena'] : 0;
    echo json_encode(['avg' => (float)($row['avg_o'] ?? 0), 'cnt' => (int)$row['cnt'], 'moja' => $moja]);
    exit;
}

// ── Dohvati slike ─────────────────────────────────────────────────────────
$slike = $conn->query("
    SELECT s.*, ROUND(AVG(o.ocjena),1) AS avg_ocjena, COUNT(o.id) AS broj_ocjena
    FROM slike s LEFT JOIN ocjene o ON s.id=o.id_slika
    GROUP BY s.id ORDER BY s.datum_dodavanja DESC
")->fetch_all(MYSQLI_ASSOC);

$moje_ocjene = [];
if ($prijavljen) {
    $res = $conn->query("SELECT id_slika, ocjena FROM ocjene WHERE id_korisnik={$_SESSION['user_id']}");
    while ($r = $res->fetch_assoc()) $moje_ocjene[$r['id_slika']] = (int)$r['ocjena'];
}
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filmbase – Galerija</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/style_slike.css">
</head>
<body>
<header id="Naslov"><h1>Dobrodosli na moju web stranicu</h1></header>
<nav class="navbar">
    <input type="checkbox" id="check">
    <label for="check" class="checkbtn">☰</label>
    <div class="nav-links">
        <a href="index.php">Pocetna</a>
        <a href="grafikon.php">Grafikon</a>
        <a href="galerija.php"><strong>Galerija</strong></a>
        <?php if ($prijavljen): ?>
            <a href="logout.php">Odjava (<?= htmlspecialchars($_SESSION['korisnicko_ime']) ?>)</a>
        <?php else: ?>
            <a href="login.php">Prijava</a>
            <a href="registracija.php">Registracija</a>
        <?php endif; ?>
    </div>
</nav>

<main>
<?= $poruka ?>

<section id="ocjenjivanje">
    <h1 class="section-title"></h1>

    <?php if (!$prijavljen): ?>
        <p class="info-tekst"><a href="login.php">Prijavite se</a> kako biste mogli ocjenjivati fotografije.</p>
    <?php endif; ?>

    <div class="galerija-grid">
    <?php foreach ($slike as $slika):
        $avg  = (float)($slika['avg_ocjena'] ?? 0);
        $cnt  = (int)$slika['broj_ocjena'];
        $moja = $moje_ocjene[$slika['id']] ?? 0;
    ?>
        <div class="galerija-kartica" id="kartica-<?= $slika['id'] ?>">
            <img src="<?= htmlspecialchars($slika['putanja']) ?>"
                 alt="<?= htmlspecialchars($slika['opis'] ?? '') ?>"
                 loading="lazy">

            <?php if ($slika['opis']): ?>
                <p class="slika-opis"><?= htmlspecialchars($slika['opis']) ?></p>
            <?php endif; ?>

            <!-- Prosječna ocjena (readonly) -->
            <div class="avg-blok">
                <div class="avg-zvjezdice" id="avg-zvjezdice-<?= $slika['id'] ?>">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="zvj <?= $i <= round($avg) ? 'puna' : '' ?>">★</span>
                    <?php endfor; ?>
                </div>
                <span class="avg-tekst" id="avg-tekst-<?= $slika['id'] ?>">
                    <?= $avg ? $avg . ' / 5' : 'Nema ocjena' ?> (<?= $cnt ?>)
                </span>
            </div>

            <!-- Interaktivne zvjezdice (samo prijavljeni) -->
            <?php if ($prijavljen): ?>
            <div class="ocjeni-blok">
                <span class="ocjeni-label">Vaša ocjena</span>
                <div class="zvjezdice-row" id="zvjezdice-<?= $slika['id'] ?>" data-slika="<?= $slika['id'] ?>" data-moja="<?= $moja ?>">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="zvj-input <?= $i <= $moja ? 'aktivna' : '' ?>" data-val="<?= $i ?>">★</span>
                    <?php endfor; ?>
                </div>
                <span class="ocjeni-hint" id="hint-<?= $slika['id'] ?>"><?= $moja ? '' : 'Klikni za ocjenu' ?></span>
                <div class="ocjena-status" id="status-<?= $slika['id'] ?>">
                    <?php if ($moja): ?>
                        <span class="ocjena-broj"><?= $moja ?></span>
                        <span>od 5 zvjezdica</span>
                        <button class="makni-btn" id="makni-<?= $slika['id'] ?>" data-slika="<?= $slika['id'] ?>">ukloni</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($je_admin): ?>
            <div class="admin-akcije">
                <a href="galerija.php?brisi_sliku=<?= $slika['id'] ?>" class="btn-delete"
                   onclick="return confirm('Obrisati sliku?')">🗑 Briši</a>
            </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <?php if (empty($slike)): ?>
        <p style="grid-column:1/-1;text-align:center;color:#888">Nema slika u galeriji.</p>
    <?php endif; ?>
    </div>

    <?php if ($je_admin): ?>
    <div class="admin-forma">
        <h3>➕ Dodaj sliku u galeriju</h3>
        <form method="POST" action="galerija.php" enctype="multipart/form-data">
            <input type="hidden" name="akcija" value="dodaj_sliku">
            <div class="forma-grid">
                <div><label>URL slike</label><input type="url" name="url_slike" placeholder="https://..."></div>
                <div><label>Upload (JPEG/PNG, max 5MB)</label><input type="file" name="slika" accept="image/jpeg,image/png"></div>
                <div><label>Opis</label><input type="text" name="opis" maxlength="500"></div>
            </div>
            <button type="submit" class="btn-primary">Dodaj sliku</button>
        </form>
    </div>
    <?php endif; ?>
</section>
</main>
<footer><p>&copy; 2025. Web Programiranje. Sva prava pridrzana.</p></footer>

<script>
document.querySelectorAll('.zvjezdice-row').forEach(function(row) {
    var zvjezdice = Array.from(row.querySelectorAll('.zvj-input'));
    var idSlike   = parseInt(row.dataset.slika);
    var trenutna  = parseInt(row.dataset.moja) || 0;
    var hintEl    = document.getElementById('hint-' + idSlike);
    var statusEl  = document.getElementById('status-' + idSlike);

    var hintTeksti = ['', 'Loše', 'Osrednje', 'Dobro', 'Vrlo dobro', 'Odlično'];

    function bojaj(n, cssClass) {
        zvjezdice.forEach(function(z, i) {
            z.classList.toggle(cssClass, i < n);
        });
    }

    // Hover – highlight do hovered zvjezdice
    zvjezdice.forEach(function(z) {
        z.addEventListener('mouseenter', function() {
            // ukloni aktivna privremeno da hover bude čist
            bojaj(0, 'aktivna');
            bojaj(parseInt(z.dataset.val), 'hover-active');
            if (hintEl) hintEl.textContent = hintTeksti[parseInt(z.dataset.val)];
        });
        z.addEventListener('mouseleave', function() {
            bojaj(0, 'hover-active');
            bojaj(trenutna, 'aktivna');
            if (hintEl) hintEl.textContent = trenutna ? '' : 'Klikni za ocjenu';
        });
    });

    // Klik – spremi
    zvjezdice.forEach(function(z) {
        z.addEventListener('click', async function() {
            var nova = parseInt(z.dataset.val);
            trenutna = nova;
            row.dataset.moja = nova;
            bojaj(0, 'hover-active');
            bojaj(nova, 'aktivna');
            if (hintEl) hintEl.textContent = '';

            var fd = new FormData();
            fd.append('akcija', 'ocijeni');
            fd.append('id_slika', idSlike);
            fd.append('ocjena', nova);
            var data = await posalji(fd);
            azurirajAvg(idSlike, data);
            renderStatus(idSlike, nova, statusEl);
        });
    });

    // Makni – event delegation na statusEl jer se gumb dynamički renderira
    statusEl.addEventListener('click', async function(e) {
        if (!e.target.classList.contains('makni-btn')) return;
        trenutna = 0;
        row.dataset.moja = 0;
        bojaj(0, 'aktivna');
        bojaj(0, 'hover-active');
        if (hintEl) hintEl.textContent = 'Klikni za ocjenu';

        var fd = new FormData();
        fd.append('akcija', 'makni_ocjenu');
        fd.append('id_slika', idSlike);
        var data = await posalji(fd);
        azurirajAvg(idSlike, data);
        statusEl.innerHTML = '';
    });
});

function renderStatus(idSlike, ocjena, statusEl) {
    statusEl.innerHTML =
        '<span class="ocjena-broj">' + ocjena + '</span>' +
        '<span>od 5 zvjezdica</span>' +
        '<button class="makni-btn" data-slika="' + idSlike + '">ukloni</button>';
}

async function posalji(fd) {
    var res = await fetch('galerija.php', { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} });
    return res.json();
}

function azurirajAvg(idSlike, data) {
    var avgTekst = document.getElementById('avg-tekst-' + idSlike);
    if (avgTekst) avgTekst.textContent = (data.avg ? data.avg + ' / 5' : 'Nema ocjena') + ' (' + data.cnt + ')';
    var avgZvj = document.querySelectorAll('#avg-zvjezdice-' + idSlike + ' .zvj');
    var rounded = Math.round(data.avg);
    avgZvj.forEach(function(z, i) { z.classList.toggle('puna', i < rounded); });
}
</script>
</body>
</html>
