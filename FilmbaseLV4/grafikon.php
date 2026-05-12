<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

$prijavljen   = isLoggedIn();
$je_admin     = isAdmin();
$korisnik_ime = $_SESSION['korisnicko_ime'] ?? '';

// ── 1. DOHVAT STVARNIH PODATAKA IZ BAZE ───────────────────────────────────
// Grupiramo filmove po žanru i sortiramo od najzastupljenijeg
$query = "SELECT zanr, COUNT(*) AS broj FROM filmovi GROUP BY zanr ORDER BY broj DESC";
$rezultat = $conn->query($query);

$svi_zanrovi   = [];
$total_filmova = 0;

if ($rezultat) {
    while ($red = $rezultat->fetch_assoc()) {
        $zanr = trim($red['zanr']) ?: 'Nepoznato';
        $svi_zanrovi[] = [
            'zanr' => $zanr,
            'broj' => (int)$red['broj']
        ];
        $total_filmova += (int)$red['broj'];
    }
}

// ── 2. OGRANIČAVANJE NA MAX 6 KATEGORIJA (zbog 6 klasa u CSS-u) ───────────
$prikaz_zanrovi = [];
if (count($svi_zanrovi) > 6) {
    // Uzimamo top 5 žanrova
    $prikaz_zanrovi = array_slice($svi_zanrovi, 0, 5);
    
    // Sve preostale zbrajamo u zajedničku kategoriju "Ostalo"
    $ostalo_broj = 0;
    for ($i = 5; $i < count($svi_zanrovi); $i++) {
        $ostalo_broj += $svi_zanrovi[$i]['broj'];
    }
    $prikaz_zanrovi[] = [
        'zanr' => 'Ostalo',
        'broj' => $ostalo_broj
    ];
} else {
    $prikaz_zanrovi = $svi_zanrovi;
}

// ── 3. IZRAČUN KUMULATIVNIH POSTOTAKA ZA SLOJEVITI SVG ────────────────────
// Budući da se krugovi u SVG-u iscrtavaju jedan preko drugog,
// donji krug mora pokriti cijeli zbroj, a svaki gornji prekriva dio ispod sebe.
$krugovi_data  = [];
$trenutni_vrh  = 100.0;

// Točne boje iz tvog style_grafikon.css za savršeno usklađivanje s legendom
$boje_krugova = [
    'pie1' => '#36d8ff',
    'pie2' => '#1eaf60',
    'pie3' => '#c2540b',
    'pie4' => '#3d13b3',
    'pie5' => '#e056c2',
    'pie6' => 'chartreuse'
];

foreach ($prikaz_zanrovi as $index => $stavka) {
    $postotak = ($total_filmova > 0) ? ($stavka['broj'] / $total_filmova * 100) : 0;
    
    $klasa = 'pie' . ($index + 1);
    $krugovi_data[] = [
        'klasa'    => $klasa,
        'boja'     => $boje_krugova[$klasa] ?? '#ccc',
        'zanr'     => $stavka['zanr'],
        'broj'     => $stavka['broj'],
        'postotak' => round($postotak, 1),
        'target'   => round($trenutni_vrh, 2) // Ciljna točka animacije za ovaj sloj
    ];
    
    // Oduzimamo postotak za idući sloj koji ide preko trenutnog
    $trenutni_vrh -= $postotak;
    if ($trenutni_vrh < 0) $trenutni_vrh = 0;
}
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filmbase – Grafikon</title>

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/style_grafikon.css">
    
    <style>
    <?php foreach ($krugovi_data as $krug): ?>
    @keyframes <?= $krug['klasa'] ?> {
        100% {
            stroke-dasharray: <?= $krug['target'] ?> var(--circumference);
        }
    }
    <?php endforeach; ?>
    </style>
</head>

<body>

<header id="Naslov">
    <h1>Dobrodošli na moju web stranicu</h1>
</header>

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

<section class="grafikon-container">

    <div class="pie-chart">
        <svg viewBox="0 0 62 62">
            <?php if ($total_filmova > 0): ?>
                <?php foreach ($krugovi_data as $krug): ?>
                    <circle class="<?= $krug['klasa'] ?>" cx="30" cy="30" r="15.9"></circle>
                <?php endforeach; ?>
            <?php else: ?>
                <circle cx="30" cy="30" r="15.9" style="stroke: #eaeaea; stroke-dasharray: 100 100;"></circle>
            <?php endif; ?>
        </svg>
    </div>

    <div class="legend">
        <?php if ($total_filmova > 0): ?>
            <ul class="types" style="padding: 0;">
                <?php foreach ($krugovi_data as $krug): ?>
                    <li style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                        <span style="display: inline-block; width: 14px; height: 14px; background-color: <?= $krug['boja'] ?>; border-radius: 3px; flex-shrink: 0;"></span>
                        <strong style="color: #333;"><?= htmlspecialchars($krug['zanr']) ?></strong>
                        <span style="font-weight: normal; color: #666;">
                            (<?= $krug['broj'] ?> <?= $krug['broj'] === 1 ? 'film' : 'filmova' ?> – <?= $krug['postotak'] ?>%)
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p style="text-align: center; color: #888;">Baza filmova je trenutno prazna. Dodajte filmove za prikaz statistike.</p>
        <?php endif; ?>
    </div>

</section>

</main>

<footer>
    <p>&copy; 2025 Filmbase</p>
</footer>

</body>
</html>
