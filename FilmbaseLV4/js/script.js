// ============================================================
//  script.js  –  LV3 JavaScript
//  Zadatak 1: Dohvat i prikaz podataka iz CSV-a
//  Zadatak 2: Filtriranje (zanr, godina, ocjena)
//  Zadatak 3: Kosaricia za posudbu
// ============================================================

// Globalna varijabla - dostupna svim funkcijama
let sviFilmovi = [];

// ============================================================
// ZADATAK 1 – Dohvat i parsiranje CSV datoteke
// ============================================================
fetch('data/movies.csv')
    .then(res => res.text())
    .then(csv => {
        // PapaParse parsira CSV u niz objekata
        const rezultat = Papa.parse(csv, {
            header: true,        // prvi redak je zaglavlje
            skipEmptyLines: true // preskoči prazne retke
        });

        // Obradi podatke – pretvori stringove u ispravne tipove
        sviFilmovi = rezultat.data.map(film => ({
            naslov:  film['Naslov']  ? film['Naslov'].trim()  : '',
            zanr:    film['Zanr']   ? film['Zanr'].trim()    : '',
            godina:  Number(film['Godina']),
            trajanje: Number(film['Trajanje_min']),
            ocjena:  Number(film['Ocjena']),
            redatelj: film['Rezisery'] ? film['Rezisery'].trim() : '',
            zemlja:  film['Zemlja_porijekla'] ? film['Zemlja_porijekla'].trim() : ''
        }));

        // Prikazi prvih 15 filmova u gornjoj tablici (Zadatak 1)
        prikaziTablicu(sviFilmovi.slice(0, 15));
    })
    .catch(err => {
        console.error('Greška pri dohvaćanju CSV-a:', err);
    });


// ============================================================
// ZADATAK 1 – Funkcija za prikaz filmova u tablici
// ============================================================
function prikaziTablicu(filmovi) {
    const tbody = document.querySelector('#filmovi-tablica tbody');
    tbody.innerHTML = ''; // ocisti prethodni sadrzaj

    if (filmovi.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7">Nema filmova za prikaz.</td></tr>';
        return;
    }

    for (const film of filmovi) {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${film.naslov}</td>
            <td>${film.zanr}</td>
            <td>${film.godina}</td>
            <td>${film.trajanje} min</td>
            <td>${film.ocjena}</td>
            <td>${film.redatelj}</td>
            <td>${film.zemlja}</td>
        `;
        tbody.appendChild(row);
    }
}


// ============================================================
// ZADATAK 2 – Filtriranje filmova
// ============================================================

// Ažuriraj prikaz vrijednosti slidera dok korisnik povlači
const sliderOcjena = document.getElementById('filter-ocjena');
const prikazOcjene = document.getElementById('ocjena-vrijednost');

sliderOcjena.addEventListener('input', () => {
    prikazOcjene.textContent = parseFloat(sliderOcjena.value).toFixed(1);
});

// Funkcija za filtriranje
function filtriraj() {
    // Dohvati vrijednosti filtera
    const zanr    = document.getElementById('filter-zanr').value.trim().toLowerCase();
    const godinaOd = parseInt(document.getElementById('filter-godina').value) || 0;
    const minOcjena = parseFloat(document.getElementById('filter-ocjena').value);

    // Filtriraj niz sviFilmovi prema svim kriterijima
    const filtrirani = sviFilmovi.filter(film => {
        const zanrMatch    = !zanr    || film.zanr.toLowerCase().includes(zanr);
        const godinaMatch  = !godinaOd || film.godina >= godinaOd;
        const ocjenaMatch  = film.ocjena >= minOcjena;
        return zanrMatch && godinaMatch && ocjenaMatch;
    });

    prikaziFiltriraneFilmove(filtrirani);

    // Prikazi tablicu s rezultatima
    document.getElementById('filtrirani-container').style.display = 'block';
}

// Pokretanje filtriranja klikom na gumb
document.getElementById('primijeni-filtere').addEventListener('click', filtriraj);

// Reset – poništi sve filtere
document.getElementById('reset-filtere').addEventListener('click', () => {
    document.getElementById('filter-zanr').value   = '';
    document.getElementById('filter-godina').value  = '';
    document.getElementById('filter-ocjena').value  = 7;
    prikazOcjene.textContent = '7.0';
    document.getElementById('filtrirani-container').style.display = 'none';
});


// ============================================================
// ZADATAK 2 + 3 – Prikaz filtriranih filmova s gumbom "Dodaj"
// ============================================================
function prikaziFiltriraneFilmove(filmovi) {
    const tbody = document.getElementById('filtrirani-tbody');
    tbody.innerHTML = '';

    if (filmovi.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7">Nema filmova za odabrane filtere.</td></tr>';
        return;
    }

    for (const film of filmovi) {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${film.naslov}</td>
            <td>${film.zanr}</td>
            <td>${film.godina}</td>
            <td>${film.trajanje} min</td>
            <td>${film.ocjena}</td>
            <td>${film.zemlja}</td>
            <td><button class="dodaj-btn" data-naslov="${film.naslov}">+ Dodaj</button></td>
        `;

        // Gumb "Dodaj u košaricu" za svaki red
        row.querySelector('.dodaj-btn').addEventListener('click', () => {
            dodajUKosaricu(film);
        });

        tbody.appendChild(row);
    }
}


// ============================================================
// ZADATAK 3 – Košarica za posudbu
// ============================================================
let kosarica = []; // niz filmova u košarici

// Dodaj film u košaricu
function dodajUKosaricu(film) {
    // Provjeri je li film već u košarici
    const vecPostoji = kosarica.some(f => f.naslov === film.naslov);
    if (vecPostoji) {
        alert(`"${film.naslov}" je već u košarici!`);
        return;
    }
    kosarica.push(film);
    osvjeziKosaricu();
}

// Ukloni film iz košarice prema indeksu
function ukloniIzKosarice(index) {
    kosarica.splice(index, 1);
    osvjeziKosaricu();
}

// Osvježi prikaz košarice
function osvjeziKosaricu() {
    const lista       = document.getElementById('lista-kosarice');
    const praznaTekst = document.getElementById('kosarica-prazna');
    const brojEl      = document.getElementById('broj-u-kosarica');

    lista.innerHTML = '';
    brojEl.textContent = `(${kosarica.length})`;

    if (kosarica.length === 0) {
        praznaTekst.style.display = 'block';
        return;
    }

    praznaTekst.style.display = 'none';

    // Kreiraj stavku za svaki film u košarici
    kosarica.forEach((film, index) => {
        const li = document.createElement('li');
        li.innerHTML = `
            <span>${film.naslov} (${film.godina})</span>
            <button class="ukloni-btn" title="Ukloni">✕</button>
        `;
        li.querySelector('.ukloni-btn').addEventListener('click', () => {
            ukloniIzKosarice(index);
        });
        lista.appendChild(li);
    });
}

// Potvrdi posudbu
document.getElementById('potvrdi-kosaricu').addEventListener('click', () => {
    if (kosarica.length === 0) {
        alert('Košarica je prazna! Dodajte filmove prije potvrde.');
        return;
    }
    const broj = kosarica.length;
    alert(`✅ Uspješno ste dodali ${broj} ${broj === 1 ? 'film' : 'filma'} u svoju košaricu za vikend maraton!`);
    // Isprazni košaricu nakon potvrde
    kosarica = [];
    osvjeziKosaricu();
});
