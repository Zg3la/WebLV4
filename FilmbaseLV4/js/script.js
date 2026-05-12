// ============================================================
//  script.js  –  Klijentska obrada posudbe i provjere ocjena
// ============================================================

(function () {
    const aside   = document.getElementById('kosarica-aside');
    const tab     = document.getElementById('kosarica-tab');
    const lista   = document.getElementById('lista-kosarice');
    const prazna  = document.getElementById('k-prazna');
    const badge   = document.getElementById('kosarica-badge');
    const potvrdi = document.getElementById('k-potvrdi');

    // Ako se skripta učita na stranici koja nema posudbenu košaricu, prekini izvršavanje
    if (!aside || !tab) return;

    let kosarica = JSON.parse(localStorage.getItem('kosarica') || '[]');

    // ── Otvaranje / zatvaranje desne posudbene ladice ───────────
    tab.addEventListener('click', function (e) {
        e.stopPropagation();
        aside.classList.toggle('otvoren');
    });

    document.addEventListener('click', function (e) {
        if (aside.classList.contains('otvoren') && !aside.contains(e.target)) {
            aside.classList.remove('otvoren');
        }
    });

    // ── Funkcija za iscrtavanje sadržaja košarice ────────────────
    function render() {
        if (badge) badge.textContent = kosarica.length;
        if (lista) lista.innerHTML   = '';

        if (kosarica.length === 0) {
            if (prazna) prazna.style.display = 'block';
        } else {
            if (prazna) prazna.style.display = 'none';
            kosarica.forEach(function (film, i) {
                const li = document.createElement('li');
                li.innerHTML =
                    '<div><span class="k-film-naslov">' + film.naslov + '</span>' +
                    '<span class="k-film-meta">' + film.zanr + ' · ' + film.godina + '</span></div>' +
                    '<button class="k-ukloni" data-i="' + i + '" title="Ukloni">✕</button>';
                lista.appendChild(li);
            });
        }

        // Osvježavanje stanja gumba u tablici
        document.querySelectorAll('.k-dodaj-btn').forEach(function (btn) {
            const uKosarici = kosarica.some(function (f) { return f.id === parseInt(btn.dataset.id); });
            btn.textContent = uKosarici ? '✓ Dodano' : '🛒 Dodaj';
            btn.classList.toggle('u-kosarici', uKosarici);
            btn.disabled = uKosarici;
        });
    }

    // ── Brisanje filma iz posudbene košarice ─────────────────────
    if (lista) {
        lista.addEventListener('click', function (e) {
            const btn = e.target.closest('.k-ukloni');
            if (!btn) return;
            kosarica.splice(parseInt(btn.dataset.i), 1);
            localStorage.setItem('kosarica', JSON.stringify(kosarica));
            render();
        });
    }

    // Pomoćna funkcija za konačno dodavanje u posudbu
    function izvrsiDodavanjeUPosudbu(btn) {
        const id = parseInt(btn.dataset.id);
        kosarica.push({ 
            id: id, 
            naslov: btn.dataset.naslov, 
            zanr: btn.dataset.zanr, 
            godina: btn.dataset.godina 
        });
        localStorage.setItem('kosarica', JSON.stringify(kosarica));
        render();
        aside.classList.add('otvoren');
    }

    // ── Dodavanje filma iz tablice uz provjeru ocjene ────────────
    document.querySelectorAll('.k-dodaj-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = parseInt(btn.dataset.id);
            const ocjena = parseFloat(btn.dataset.ocjena || 0);

            // Provjera je li film već dodan
            if (kosarica.some(function (f) { return f.id === id; })) return;

            // Ako je prosječna ocjena ispod 5.0, generira se iskačuće upozorenje
            if (ocjena < 5.0) {
                const kontejner = document.getElementById('upozorenje-posudba-kontejner');
                if (!kontejner) return;

                kontejner.innerHTML = `
                    <div style="border: 2px solid #c0392b; background-color: #fadbd8; padding: 20px; margin-bottom: 20px; border-radius: 8px; color: #78281f; text-align: center;">
                        <h3 style="margin-top: 0; color: #c0392b;">⚠️ Upozorenje: Film ima nisku ocjenu!</h3>
                        <p style="font-size: 16px;">Film <strong>${btn.dataset.naslov}</strong> ima prosječnu ocjenu ispod 5.0 (${ocjena.toFixed(1)}).</p>
                        <p style="font-size: 15px; margin-bottom: 15px;">Ovaj film ima nisku ocjenu – jeste li sigurni da ga želite dodati u košaricu za posudbu?</p>
                        <div>
                            <button id="potvrdi-posudbu-btn" style="background-color: #c0392b; color: white; padding: 8px 16px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">Da, siguran sam</button>
                            <button id="odustani-posudbu-btn" style="background-color: #7f8c8d; color: white; padding: 8px 16px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; margin-left: 10px;">Odustani</button>
                        </div>
                    </div>
                `;
                
                // Automatsko pomicanje ekrana do okvira upozorenja
                kontejner.scrollIntoView({ behavior: 'smooth' });

                document.getElementById('potvrdi-posudbu-btn').onclick = function() {
                    izvrsiDodavanjeUPosudbu(btn);
                    kontejner.innerHTML = ''; // Uklanjanje okvira nakon potvrde
                };
                
                document.getElementById('odustani-posudbu-btn').onclick = function() {
                    kontejner.innerHTML = ''; // Uklanjanje okvira kod odustajanja
                };
            } else {
                // Ako je ocjena 5.0 ili viša, film se sprema bez odgode
                izvrsiDodavanjeUPosudbu(btn);
            }
        });
    });

    // ── Potvrda konačne posudbe ──────────────────────────────────
    if (potvrdi) {
        potvrdi.addEventListener('click', function () {
            if (typeof KORISNIK_PRIJAVLJEN !== 'undefined' && !KORISNIK_PRIJAVLJEN) {
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
    }

    // Inicijalno iscrtavanje stanja košarice prilikom učitavanja stranice
    render();
})();
