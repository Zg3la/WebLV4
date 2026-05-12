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

// ── Osobna videoteka (Library - Trajno u bazu) ───────────────────────────
$upozorenje_niskocjena = '';
$poruka_videoteka      = '';

if ($prijavljen && isset($_GET['dodaj_videoteka']) && is_numeric($_GET['dodaj_videoteka'])) {
    $film_id   = (int)$_GET['dodaj_videoteka'];
    $potvrdeno = isset($_GET['potvrdeno']) ? (int)$_GET['potvrdeno'] : 0;

    $stmt = $conn->prepare("SELECT naslov, ocjena FROM filmovi WHERE id = ?");
    $stmt->bind_param("i", $film_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $odabrani_film = $res->fetch_assoc();
    $stmt->close();

    if ($odabrani_film) {
        $stmt_provjera = $conn->prepare("SELECT id FROM zeljeni_filmovi WHERE korisnik_id = ? AND film_id = ?");
        $stmt_provjera->bind_param("ii", $_SESSION['user_id'], $film_id);
        $stmt_provjera->execute();
        $vec_u_bazi = $stmt_provjera->get_result()->num_rows > 0;
        $stmt_provjera->close();

        if ($vec_u_bazi) {
            $poruka_videoteka = '<div class="alert alert-error">Ovaj film je već u vašoj Library videoteci!</div>';
        } else {
            if ($odabrani_film['ocjena'] < 5.0 && $potvrdeno === 0) {
                $upozorenje_niskocjena = '
                <div style="border: 2px solid #c0392b; background-color: #fadbd8; padding: 20px; margin-bottom: 20px; border-radius: 8px; color: #78281f; text-align: center;">
                    <h3 style="margin-top: 0; color: #c0392b;">⚠️ Upozorenje: Film ima nisku ocjenu!</h3>
                    <p>Želite li ipak dodati <strong>' . htmlspecialchars($odabrani_film['naslov']) . '</strong> u Library?</p>
                    <a href="index.php?dodaj_videoteka=' . $film_id . '&potvrdeno=1" style="background:#c0392b; color:white; padding:8px 15px; text-decoration:none; border-radius:5px;">Da, dodaj</a>
                    <a href="index.php" style="background:#7f8c8d; color:white; padding:8px 15px; text-decoration:none; border-radius:5px; margin-left:10px;">Odustani</a>
                </div>';
            } else {
                $stmt_ins = $conn->prepare("INSERT INTO zeljeni_filmovi (korisnik_id, film_id) VALUES (?, ?)");
                $stmt_ins->bind_param("ii", $_SESSION['user_id'], $film_id);
                if ($stmt_ins->execute()) {
                    header('Location: index.php?otvori_library=1');
                    exit;
                }
                $stmt_ins->close();
            }
        }
    }
}

// Uklanjanje iz Library-a
if ($prijavljen && isset($_GET['ukloni_videoteka']) && is_numeric($_GET['ukloni_videoteka'])) {
    $zeljeni_id = (int)$_GET['ukloni_videoteka'];
    $stmt = $conn->prepare("DELETE FROM zeljeni_filmovi WHERE id = ? AND korisnik_id = ?");
    $stmt->bind_param("ii", $zeljeni_id, $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();
    header('Location: index.php?otvori_library=1');
    exit;
}

// Dohvat Library stavaka (Ispravljen ORDER BY da nema Errora)
$moja_videoteka = [];
$videoteka_ids  = [];
if ($prijavljen) {
    $stmt = $conn->prepare("
        SELECT z.id AS zeljeni_id, f.id AS film_id, f.naslov, f.zanr, f.godina 
        FROM zeljeni_filmovi z 
        JOIN filmovi f ON z.film_id = f.id 
        WHERE z.korisnik_id = ?
        ORDER BY z.id DESC
    ");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $moja_videoteka = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($moja_videoteka as $s) { $videoteka_ids[] = $s['film_id']; }
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
    <style>
    /* ── LIBRARY (LIJEVO) ── */
    #videoteka-aside {
        position: fixed; left: 0; top: 180px; width: 280px; background: #fff;
        border: 2px solid #2ecc71; border-left: none; border-radius: 0 12px 12px 0;
        box-shadow: 4px 4px 20px rgba(0,0,0,0.1); z-index: 9999;
        transform: translateX(-100%); transition: transform 0.3s ease;
    }
    #videoteka-aside.otvoren { transform: translateX(0); }
    #videoteka-tab {
        position: absolute; left: 100%; top: 20px; background: #2ecc71; color: white;
        border: none; border-radius: 0 8px 8px 0; width: 45px; padding: 10px 0; cursor: pointer;
    }

    /* ── KOŠARICA (DESNO) ── */
    #kosarica-aside {
        position: fixed; right: 0; top: 180px; width: 280px; background: #fff;
        border: 2px solid lightcoral; border-right: none; border-radius: 12px 0 0 12px;
        box-shadow: -4px 4px 20px rgba(0,0,0,0.1); z-index: 9999;
        transform: translateX(100%); transition: transform 0.3s ease;
    }
    #kosarica-aside.otvoren { transform: translateX(0); }
    #kosarica-tab {
        position: absolute; right: 100%; top: 20px; background: lightcoral; color: white;
        border: none; border-radius: 8px 0 0 8px; width: 45px; padding: 10px 0; cursor: pointer;
    }

    .aside-body { padding: 15px; max-height: 60vh; overflow-y: auto; font-size: 13px; }
    .k-ukloni { color: red; cursor: pointer; font-weight: bold; text-decoration:none; float:right; }
    .btn-lib { background: #2ecc71; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; text-decoration:none; font-size: 11px; }
    .btn-cart { background: lightcoral; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 11px; }
    </style>
</head>
<body>

<aside id="videoteka-aside" class="<?= isset($_GET['otvori_library']) ? 'otvoren' : '' ?>">
    <button id="videoteka-tab">📂<br><small><?= count($moja_videoteka) ?></small></button>
    <div class="aside-body">
        <h3>📂 Moj Library</h3>
        <ul style="list-style:none; padding:0;">
            <?php foreach ($moja_videoteka as $f): ?>
                <li style="border-bottom:1px solid #eee; padding:5px 0;">
                    <strong><?= htmlspecialchars($f['naslov']) ?></strong>
                    <a href="index.php?ukloni_videoteka=<?= $f['zeljeni_id'] ?>" class="k-ukloni">✕</a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if(empty($moja_videoteka)): ?><p>Library je prazan.</p><?php endif; ?>
    </div>
</aside>

<aside id="kosarica-aside">
    <button id="kosarica-tab">🛒<br><small id="cart-badge">0</small></button>
    <div class="aside-body">
        <h3>🛒 Košarica (Posudba)</h3>
        <ul id="cart-list" style="list-style:none; padding:0;"></ul>
        <button id="clear-cart" style="width:100%; margin-top:10px;">Isprazni</button>
    </div>
</aside>

<header id="Naslov"><h1>Dobrodosli na Filmbase</h1></header>

<main>
    <section id="filmovi-baza">
        <?= $upozorenje_niskocjena ?>
        <?= $poruka_forma ?>
        <?= $poruka_videoteka ?>

        <div class="container">
            <table style="width:100%; border-collapse:collapse;" border="1">
                <thead>
                    <tr>
                        <th>Naslov</th><th>Ocjena</th><th>Library (Baza)</th><th>Posudba (Cart)</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($filmovi as $film): ?>
                    <tr>
                        <td><?= htmlspecialchars($film['naslov']) ?></td>
                        <td><?= number_format($film['ocjena'], 1) ?></td>
                        <td>
                            <?php if ($prijavljen): ?>
                                <?php if (in_array($film['id'], $videoteka_ids)): ?>
                                    <span style="color:green;">✓ Spremljeno</span>
                                <?php else: ?>
                                    <a href="index.php?dodaj_videoteka=<?= $film['id'] ?>" class="btn-lib">📂 U Library</a>
                                <?php endif; ?>
                            <?php else: ?>
                                <small>Prijavi se</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn-cart add-to-cart" 
                                data-id="<?= $film['id'] ?>" 
                                data-naslov="<?= htmlspecialchars($film['naslov']) ?>">🛒 U košaricu</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<script>
// ── LOGIKA ZA SIDEBARE ──
document.getElementById('videoteka-tab').onclick = () => document.getElementById('videoteka-aside').classList.toggle('otvoren');
document.getElementById('kosarica-tab').onclick = () => document.getElementById('kosarica-aside').classList.toggle('otvoren');

// ── LOGIKA ZA KOŠARICU (LocalStorage) ──
let cart = JSON.parse(localStorage.getItem('posudba_cart') || '[]');
const updateUI = () => {
    const list = document.getElementById('cart-list');
    const badge = document.getElementById('cart-badge');
    list.innerHTML = '';
    badge.innerText = cart.length;
    cart.forEach((item, index) => {
        list.innerHTML += `<li style="border-bottom:1px solid #eee; padding:5px 0;">
            ${item.naslov} <span onclick="removeItem(${index})" style="color:red; cursor:pointer; float:right;">✕</span>
        </li>`;
    });
    localStorage.setItem('posudba_cart', JSON.stringify(cart));
};

document.querySelectorAll('.add-to-cart').forEach(btn => {
    btn.onclick = () => {
        const id = btn.dataset.id;
        if(!cart.find(i => i.id === id)) {
            cart.push({id: id, naslov: btn.dataset.naslov});
            updateUI();
            document.getElementById('kosarica-aside').classList.add('otvoren');
        }
    };
});

window.removeItem = (index) => { cart.splice(index, 1); updateUI(); };
document.getElementById('clear-cart').onclick = () => { cart = []; updateUI(); };

updateUI();
</script>
</body>
</html>
