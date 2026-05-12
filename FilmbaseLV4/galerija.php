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
        $url = trim($_POST['url_slike']);
        $naziv = basename(parse_url($url, PHP_URL_PATH)) ?: 'slika_' . time();
        $stmt = $conn->prepare("INSERT INTO slike (naziv_datoteke, opis, putanja, izvor) VALUES (?,?,?,'url')");
        $stmt->bind_param("sss", $naziv, $opis, $url);
        if ($stmt->execute()) {
            $poruka = '<div class="alert alert-success">Slika dodana!</div>';
        }
        $stmt->close();
    } elseif (isset($_FILES['slika']) && $_FILES['slika']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png'];
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
                if ($stmt->execute()) {
                    $poruka = '<div class="alert alert-success">Slika uploadana!</div>';
                }
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
    header('Location: galerija.php');
    exit;
}

// ── Ocjeni sliku (AJAX) ────────────────────────────────────────────────────
if ($prijavljen && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['akcija']) && $_POST['akcija'] === 'ocijeni') {
    $id_slika = (int)($_POST['id_slika'] ?? 0);
    $ocjena   = (int)($_POST['ocjena']   ?? 0);

    if ($id_slika > 0 && $ocjena >= 1 && $ocjena <= 5) {
        $stmt = $conn->prepare("
            INSERT INTO ocjene (id_korisnik, id_slika, ocjena)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE ocjena = VALUES(ocjena), vrijeme_ocjene = NOW()
        ");
        $stmt->bind_param("iii", $_SESSION['user_id'], $id_slika, $ocjena);
        $stmt->execute();
        $stmt->close();

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $res = $conn->query("SELECT AVG(ocjena) as avg_o, COUNT(*) as cnt FROM ocjene WHERE id_slika = $id_slika");
            $row = $res->fetch_assoc();
            echo json_encode(['avg' => round($row['avg_o'], 1), 'cnt' => $row['cnt']]);
            exit;
        }

        header('Location: galerija.php');
        exit;
    }
}

// ── Dohvati sve slike s prosječnim ocjenama ───────────────────────────────
$slike_result = $conn->query("
    SELECT s.*, 
           ROUND(AVG(o.ocjena), 1) AS avg_ocjena,
           COUNT(o.id) AS broj_ocjena
    FROM slike s
    LEFT JOIN ocjene o ON s.id = o.id_slika
    GROUP BY s.id
    ORDER BY s.datum_dodavanja DESC
");
$slike = $slike_result->fetch_all(MYSQLI_ASSOC);

$moje_ocjene = [];
if ($prijavljen) {
    $res = $conn->query("SELECT id_slika, ocjena FROM ocjene WHERE id_korisnik = {$_SESSION['user_id']}");
    while ($row = $res->fetch_assoc()) {
        $moje_ocjene[$row['id_slika']] = $row['ocjena'];
    }
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
    <h1 style="text-align:center;margin-top:40px">⭐ OCJENJIVANJE FOTOGRAFIJA (LV4)</h1>

    <?php if (!$prijavljen): ?>
        <p style="text-align:center;color:#888">
            <a href="login.php">Prijavite se</a> kako biste mogli ocjenjivati fotografije.
        </p>
    <?php endif; ?>

    <div class="galerija-grid">
        <?php foreach ($slike as $slika): ?>
        <div class="galerija-kartica" id="slika-<?= $slika['id'] ?>">
            <img src="<?= htmlspecialchars($slika['putanja']) ?>"
                 alt="<?= htmlspecialchars($slika['opis'] ?? '') ?>"
                 loading="lazy">

            <?php if ($slika['opis']): ?>
                <p class="slika-opis"><?= htmlspecialchars($slika['opis']) ?></p>
            <?php endif; ?>

            <!-- Prosječna ocjena -->
            <div class="avg-ocjena">
                <?php
                $avg = $slika['avg_ocjena'] ?? 0;
                $cnt = $slika['broj_ocjena'];
                for ($i = 1; $i <= 5; $i++):
                    $filled = $i <= round($avg);
                ?>
                    <span class="zvjezdica <?= $filled ? 'puna' : '' ?>">★</span>
                <?php endfor; ?>
                <span class="avg-tekst" id="avg-<?= $slika['id'] ?>">
                    <?= $avg ? $avg . ' / 5' : 'Nema ocjena' ?> 
                    (<?= $cnt ?> <?= $cnt === 1 ? 'ocjena' : 'ocjena' ?>)
                </span>
            </div>

            <!-- Forma za ocjenjivanje (samo prijavljeni) -->
            <?php if ($prijavljen): ?>
            <div class="ocjeni-forma">
                <span>Vaša ocjena:</span>
                <div class="zvjezdice-wrapper" data-slika="<?= $slika['id'] ?>" data-odabrana="<?= $moje_ocjene[$slika['id']] ?? 0 ?>">
                    <?php $moja = $moje_ocjene[$slika['id']] ?? 0; ?>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="zvjezdica-input <?= $i <= $moja ? 'odabrana' : '' ?>"
                              data-slika="<?= $slika['id'] ?>"
                              data-ocjena="<?= $i ?>"
                              title="<?= $i ?> zvjezdic<?= $i === 1 ? 'a' : 'e' ?>">★</span>
                    <?php endfor; ?>
                </div>
                <?php if ($moja): ?>
                    <span class="moja-ocjena-tekst" id="moja-ocjena-<?= $slika['id'] ?>">Vaša: <?= $moja ?>/5</span>
                <?php else: ?>
                    <span class="moja-ocjena-tekst" id="moja-ocjena-<?= $slika['id'] ?>"></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Admin: briši sliku -->
            <?php if ($je_admin): ?>
            <div style="text-align:center;margin-top:6px">
                <a href="galerija.php?brisi_sliku=<?= $slika['id'] ?>"
                   class="btn-delete" onclick="return confirm('Obrisati sliku?')">🗑 Briši</a>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <?php if (empty($slike)): ?>
            <p style="grid-column:1/-1;text-align:center;color:#888">Nema slika u galeriji.</p>
        <?php endif; ?>
    </div>

    <!-- Admin: dodaj sliku -->
    <?php if ($je_admin): ?>
    <div class="admin-forma" style="max-width:600px;margin:30px auto">
        <h3>➕ Dodaj sliku u galeriju</h3>
        <form method="POST" action="galerija.php" enctype="multipart/form-data">
            <input type="hidden" name="akcija" value="dodaj_sliku">
            <div class="forma-grid" style="grid-template-columns:1fr">
                <div>
                    <label>URL slike (ili uploadaj ispod)</label>
                    <input type="url" name="url_slike" placeholder="https://...">
                </div>
                <div>
                    <label>Upload slike (JPEG/PNG, max 5MB)</label>
                    <input type="file" name="slika" accept="image/jpeg,image/png">
                </div>
                <div>
                    <label>Opis</label>
                    <input type="text" name="opis" maxlength="500">
                </div>
            </div>
            <button type="submit" class="btn-primary">Dodaj sliku</button>
        </form>
    </div>
    <?php endif; ?>
</section>

</main>
<footer><p>&copy; 2025. Web Programiranje. Sva prava pridrzana.</p></footer>

<script>
// ── Zvjezdice – hover i klik za ocjenjivanje ─────────────────────────────
document.querySelectorAll('.zvjezdice-wrapper').forEach(wrapper => {
    const zvjezdice = wrapper.querySelectorAll('.zvjezdica-input');
    const idSlike   = parseInt(wrapper.dataset.slika);
    let odabrana    = parseInt(wrapper.dataset.odabrana) || 0;

    function obojiDo(n, cssClass) {
        zvjezdice.forEach((z, i) => {
            z.classList.toggle(cssClass, i < n);
        });
    }

    zvjezdice.forEach((zvj, idx) => {
        // Hover – privremeno oboji do hovered zvjezdice
        zvj.addEventListener('mouseenter', () => {
            // ukloni odabrana privremeno da hover bude čist
            obojiDo(0, 'odabrana');
            obojiDo(idx + 1, 'hover');
        });

        // Kad miš izađe – vrati na odabranu
        zvj.addEventListener('mouseleave', () => {
            obojiDo(0, 'hover');
            obojiDo(odabrana, 'odabrana');
        });

        // Klik – spremi ocjenu
        zvj.addEventListener('click', async () => {
            const novaOcjena = idx + 1;
            odabrana = novaOcjena;
            wrapper.dataset.odabrana = novaOcjena;

            // Odmah vizualno oboji
            obojiDo(0, 'hover');
            obojiDo(odabrana, 'odabrana');

            // Pošalji AJAX
            const fd = new FormData();
            fd.append('akcija', 'ocijeni');
            fd.append('id_slika', idSlike);
            fd.append('ocjena', novaOcjena);

            try {
                const res  = await fetch('galerija.php', {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();

                // Ažuriraj prikaz prosječne ocjene
                const avgEl = document.getElementById('avg-' + idSlike);
                if (avgEl) avgEl.textContent = data.avg + ' / 5 (' + data.cnt + ' ocjena)';

                // Ažuriraj avg zvjezdice (readonly prikaz)
                const kartica = document.getElementById('slika-' + idSlike);
                const avgZvj  = kartica.querySelectorAll('.avg-ocjena .zvjezdica');
                const rounded = Math.round(data.avg);
                avgZvj.forEach((z, i) => {
                    z.classList.toggle('puna', i < rounded);
                });

                // Prikaži tekst ocjene
                const mojaEl = document.getElementById('moja-ocjena-' + idSlike);
                if (mojaEl) mojaEl.textContent = 'Vaša: ' + novaOcjena + '/5';
            } catch(e) {
                console.error('Greška pri ocjenjivanju:', e);
            }
        });
    });
});
</script>
</body>
</html>
