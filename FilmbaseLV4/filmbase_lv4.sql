-- LV4 Filmbase baza podataka
-- Pokrenuti u phpMyAdmin ili MySQL konzoli

CREATE DATABASE IF NOT EXISTS filmbase_lv4 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE filmbase_lv4;

-- Tablica korisnika
CREATE TABLE IF NOT EXISTS korisnici (
    id INT AUTO_INCREMENT PRIMARY KEY,
    korisnicko_ime VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    lozinka VARCHAR(255) NOT NULL,
    uloga ENUM('korisnik', 'admin') DEFAULT 'korisnik',
    datum_registracije TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tablica filmova
CREATE TABLE IF NOT EXISTS filmovi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    naslov VARCHAR(255) NOT NULL,
    zanr VARCHAR(100) NOT NULL,
    godina INT NOT NULL,
    trajanje_min INT NOT NULL,
    ocjena DECIMAL(3,1) DEFAULT 0.0,
    redatelj VARCHAR(150),
    zemlja VARCHAR(100),
    opis TEXT,
    datum_dodavanja TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Osobna videoteka (željeni filmovi)
CREATE TABLE IF NOT EXISTS zeljeni_filmovi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    korisnik_id INT NOT NULL,
    film_id INT NOT NULL,
    datum_dodavanja TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (korisnik_id) REFERENCES korisnici(id) ON DELETE CASCADE,
    FOREIGN KEY (film_id) REFERENCES filmovi(id) ON DELETE CASCADE,
    UNIQUE KEY jedinstven_unos (korisnik_id, film_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tablica slika
CREATE TABLE IF NOT EXISTS slike (
    id INT AUTO_INCREMENT PRIMARY KEY,
    naziv_datoteke VARCHAR(255) NOT NULL,
    opis VARCHAR(500),
    putanja VARCHAR(500) NOT NULL,
    izvor ENUM('lokalno', 'url') DEFAULT 'url',
    datum_dodavanja TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tablica ocjena slika
CREATE TABLE IF NOT EXISTS ocjene (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_korisnik INT NOT NULL,
    id_slika INT NOT NULL,
    ocjena TINYINT NOT NULL CHECK (ocjena BETWEEN 1 AND 5),
    komentar TEXT,
    vrijeme_ocjene TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_korisnik) REFERENCES korisnici(id) ON DELETE CASCADE,
    FOREIGN KEY (id_slika) REFERENCES slike(id) ON DELETE CASCADE,
    UNIQUE KEY jedna_ocjena (id_korisnik, id_slika)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Demo admin korisnik (lozinka: admin123)
INSERT IGNORE INTO korisnici (korisnicko_ime, email, lozinka, uloga)
VALUES ('admin', 'admin@filmbase.hr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Demo filmovi iz CSV-a
INSERT IGNORE INTO filmovi (naslov, zanr, godina, trajanje_min, ocjena, redatelj, zemlja) VALUES
('The Shawshank Redemption', 'Drama', 1994, 142, 9.3, 'Frank Darabont', 'USA'),
('The Godfather', 'Crime, Drama', 1972, 175, 9.2, 'Francis Ford Coppola', 'USA'),
('The Dark Knight', 'Action, Crime', 2008, 152, 9.0, 'Christopher Nolan', 'UK/USA'),
('Schindler''s List', 'Biography, Drama', 1993, 195, 9.0, 'Steven Spielberg', 'USA'),
('Pulp Fiction', 'Crime, Drama', 1994, 154, 8.9, 'Quentin Tarantino', 'USA'),
('Inception', 'Action, Adventure', 2010, 148, 8.8, 'Christopher Nolan', 'USA/UK'),
('The Matrix', 'Action, Sci-Fi', 1999, 136, 8.7, 'Lana Wachowski', 'USA'),
('Goodfellas', 'Biography, Crime', 1990, 145, 8.7, 'Martin Scorsese', 'USA'),
('Interstellar', 'Adventure, Drama', 2014, 169, 8.7, 'Christopher Nolan', 'USA/UK'),
('Parasite', 'Drama, Thriller', 2019, 132, 8.5, 'Bong Joon Ho', 'South Korea'),
('Fight Club', 'Drama', 1999, 139, 8.8, 'David Fincher', 'USA'),
('Gladiator', 'Action, Adventure', 2000, 155, 8.5, 'Ridley Scott', 'USA/UK'),
('The Silence of the Lambs', 'Crime, Drama', 1991, 118, 8.6, 'Jonathan Demme', 'USA'),
('Se7en', 'Crime, Drama', 1995, 127, 8.6, 'David Fincher', 'USA'),
('The Lion King', 'Animation, Adventure', 1994, 88, 8.5, 'Roger Allers', 'USA');

-- Demo slike za galeriju (javni URL-ovi)
INSERT IGNORE INTO slike (naziv_datoteke, opis, putanja, izvor) VALUES
('kino_valli', 'Kino Valli, Pula', 'https://pulainfo.hr/wp-content/uploads/2024/03/Kino_Valli_K.jpg', 'url'),
('european_film', 'European Film Academy', 'https://www.europeanfilmacademy.org/app/uploads/2024/10/img-3489.jpg', 'url'),
('cinema_hall', 'Kino dvorana', 'https://dynamic-media-cdn.tripadvisor.com/media/photo-o/12/58/9b/17/photo1jpg.jpg?w=900&h=500&s=1', 'url'),
('film_reel', 'Film projekcija', 'https://esquire.com.au/wp-content/uploads/2025/01/1500x1000-Template-12.jpg', 'url'),
('movie_premiere', 'Filmska premijera', 'https://cdn.moviefone.com/image-assets/83533/bRBeSHfGHwkEpImlhxPmOcUsaeg.jpg?d=360x540&q=80', 'url'),
('animated_movies', 'Animirani filmovi 2026', 'https://static0.srcdn.com/wordpress/wp-content/uploads/2024/08/2026-big-year-for-animated-movies.jpg', 'url');
