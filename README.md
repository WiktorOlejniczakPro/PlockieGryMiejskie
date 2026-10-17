# PGM – Płockie Gry Miejskie

Panel internetowy do tworzenia, edytowania i publikowania gier terenowych w Płocku.
Z panelu korzystają pracownicy Urzędu Miasta Płocka oraz upoważnione organizacje.
Gracze będą przechodzić gry w osobnej aplikacji mobilnej na Androida (przyszły etap).

## Stan prac

| Etap | Zakres | Stan |
|---|---|---|
| 1 | Architektura, schemat MySQL, dane przykładowe | ✅ gotowe |
| 2 | Konfiguracja, routing, layouty, strona publiczna, sesje, logowanie, CSRF, konto administratora | ✅ gotowe |
| 3 | Edytor gry: informacje podstawowe, mapa z punktami, kolejność, zadania, zapis do MySQL, lista gier | ✅ gotowe |
| 4+ | Wyszukiwarka adresów, obszar gry, zdjęcia, podgląd, publikacja, filtry listy, API dla Androida, konto, FAQ | ⏳ w kolejnych etapach |

Pozycje menu z przyszłych etapów są widoczne w panelu z etykietą „wkrótce” i nie da się ich kliknąć.

## Edytor gry – jak korzystać

1. **Utwórz nową grę** (menu boczne) → wpisz tytuł i pozostałe informacje.
2. Zakładka **Trasa, punkty i zadania**:
   - kliknięcie na mapie dodaje punkt („Lokalizacja 1”, „Lokalizacja 2”…),
   - marker można przeciągnąć, a współrzędne poprawić też ręcznie w polach formularza,
   - kolejność zmienisz, przeciągając punkt na liście albo strzałkami ↑ ↓ (numery na mapie i linia trasy aktualizują się od razu),
   - po wybraniu punktu po prawej stronie edytujesz nazwę, opis i wskazówkę oraz włączasz **zadanie** (6 typów).
3. **Zapisz szkic** (lub Ctrl+S). Cała gra zapisuje się w jednej transakcji. Przy próbie wyjścia bez zapisu przeglądarka ostrzega.

Minimum do zapisania szkicu: tytuł gry, nazwy punktów, poprawne współrzędne oraz tytuł i treść zadania (jeśli zadanie jest włączone).
Puste wiersze odpowiedzi w pytaniach zamkniętych są pomijane przy zapisie.

Linia na mapie łączy punkty w kolejności trasy. To schemat, a nie trasa piesza, dlatego długość jest podana „w linii prostej”.
Mapa wymaga internetu (kafelki OpenStreetMap). Biblioteka Leaflet jest dołączona do projektu, więc nie zależy od CDN.

## Wymagania

- PHP 8.2 lub nowszy z rozszerzeniami `pdo_mysql` i `mbstring` (oba są w XAMPP)
- MySQL 8 albo MariaDB 10.4+ (XAMPP zawiera MariaDB)
- Apache z `mod_rewrite` (w XAMPP włączony domyślnie)

## Uruchomienie w XAMPP (Windows)

### 1. Pliki

Pobierz projekt z GitHuba do folderu `C:\xampp\htdocs\pgm`:

```
cd C:\xampp\htdocs
git clone https://github.com/WiktorOlejniczakPro/PlockieGryMiejskie.git pgm
```

Bez gita: na stronie repozytorium kliknij **Code → Download ZIP**, rozpakuj i zmień nazwę folderu na `pgm`.
W `C:\xampp\htdocs\pgm` muszą być bezpośrednio foldery `app`, `public`, `database` itd.

### 2. Apache i MySQL

Otwórz **XAMPP Control Panel** i kliknij **Start** przy **Apache** i **MySQL**.

### 3. Baza danych

1. Wejdź na `http://localhost/phpmyadmin`.
2. **Import** → plik `database\schema.sql` → **Importuj**. Baza `pgm` utworzy się sama.
3. **Import** → plik `database\seed.sql` → **Importuj**.

> `schema.sql` usuwa i tworzy tabele od nowa – nie uruchamiaj go ponownie, gdy w bazie są już prawdziwe gry.

`seed.sql` zawiera też **miejsca i ulice Płocka** z otwartych danych miasta (tabele `place_categories`, `places`, `streets`) – patrz sekcja [Dane miejskie](#dane-miejskie).

### 4. Plik konfiguracyjny `.env`

W folderze `C:\xampp\htdocs\pgm` skopiuj plik `.env.example` i nazwij kopię `.env`.

W domyślnym XAMPP nic nie trzeba zmieniać (`DB_USER=root`, puste `DB_PASS`).
Jeśli Twój MySQL ma hasło albo osobnego użytkownika, wpisz je w `DB_USER` i `DB_PASS`.

> W Eksploratorze Windows plik bez nazwy przed kropką bywa kłopotliwy. Najprościej
> zrobić kopię w wierszu poleceń: `copy .env.example .env`

### 5. Pierwsze konto administratora

Otwórz wiersz poleceń (`cmd`) i wpisz:

```
cd C:\xampp\htdocs\pgm
C:\xampp\php\php.exe bin\create-admin.php
```

Skrypt zapyta o e-mail, imię, nazwisko, organizację i hasło (min. 12 znaków).
Hasło trafia do bazy wyłącznie jako hash. W kodzie projektu nie ma żadnych domyślnych haseł.

Konto twórcy gier (bez uprawnień administratora) tworzy się tak samo, z dopiskiem `--role=creator`.

### 6. Otwórz aplikację

- Strona publiczna: **http://localhost/pgm/** (przekierowuje do `/pgm/public/`)
- Logowanie: **http://localhost/pgm/public/logowanie**

Po zalogowaniu zobaczysz dashboard z liczbą gier pobraną z bazy (administrator widzi też 4 gry przykładowe).

### Alternatywa bez Apache

```
C:\xampp\php\php.exe -S localhost:8000 -t public public\index.php
```

Potem otwórz `http://localhost:8000`.

## Tryb demonstracyjny – bez ekranu logowania

Na pokaz (np. dla jury) panel może być stale zalogowany na jedno konto:

1. Utwórz konto skryptem `bin/create-admin.php` (jeśli jeszcze go nie masz).
2. W pliku `.env` wpisz jego adres: `AUTO_LOGIN_EMAIL=admin@example.org`
3. Odśwież stronę – panel otworzy się od razu, a `/logowanie` przekieruje do panelu.

- W panelu widać żółty pasek „Tryb demonstracyjny”, żeby nikt nie zapomniał go wyłączyć.
- Kliknięcie „Wyloguj” wyłącza automatyczne logowanie do zamknięcia przeglądarki.
- Przy `APP_ENV=production` ustawienie jest ignorowane.
- **Wyłączenie:** wyczyść wartość (`AUTO_LOGIN_EMAIL=`). Każdy, kto otworzy stronę w trybie demo, ma pełny dostęp do tego konta – nie zostawiaj go na serwerze dostępnym z internetu.

## Najczęstsze problemy

| Objaw | Rozwiązanie |
|---|---|
| „Brak pliku .env” | Skopiuj `.env.example` jako `.env` (krok 4). |
| „Nie można połączyć się z bazą danych” | Sprawdź, czy MySQL działa w XAMPP i czy baza `pgm` jest zaimportowana. Sprawdź `DB_USER` / `DB_PASS` w `.env`. Szczegóły są w `storage\logs\php-error.log`. |
| Każda strona poza główną daje 404 | Apache nie czyta plików `.htaccess`. W `C:\xampp\apache\conf\httpd.conf` dla katalogu `htdocs` ustaw `AllowOverride All` i sprawdź, czy linia `LoadModule rewrite_module` nie jest zakomentowana. Zrestartuj Apache. |
| „Sesja formularza wygasła” po kliknięciu „Zaloguj” | Strona była długo otwarta albo przeglądarka blokuje ciasteczka. Odśwież stronę logowania i spróbuj ponownie. |
| „Zbyt wiele nieudanych prób logowania” | Po 5 błędnych hasłach konto jest blokowane na 15 minut. Poczekaj albo (tylko lokalnie) wyczyść tabelę `login_attempts` w phpMyAdmin. |
| Apache nie startuje (port 80) | Port zajmuje inny program (np. Skype, IIS). Zamknij go albo zmień port Apache na 8080 w XAMPP (Config → httpd.conf, `Listen 8080`). |
| Krzaczki zamiast polskich liter w konsoli | Dotyczy tylko wiersza poleceń MySQL – dodaj `--default-character-set=utf8mb4`. |

## Dane miejskie

Pliki źródłowe (otwarte dane UM Płock, BDOT10k, TERYT/PRG) leżą w `database/dane-zrodlowe/`.
Skrypt `bin/build-places-seed.php` zamienia je na sekcję „MIEJSCA” w `database/seed.sql`:

| Rodzaj miejsc | Plik źródłowy | Liczba |
|---|---|---|
| Zabytki – rejestr i gminna ewidencja | `zabytki-rejestr.csv`, `zabytki-ewidencja.csv` | 535 |
| Przedszkola, szkoły, poradnie, internaty | `placowki-oswiatowe.csv` | 73 |
| Szkoły niepubliczne i uczelnie (uzupełnienie) | `budynki-szkol-*.csv` (BDOT10k) | 41 |
| Przystanki komunikacji miejskiej | `przystanki.csv` | 387 |
| Stacje roweru miejskiego | `rower-miejski.csv` | 56 |
| Place zabaw, siłownie, boiska, parki, fontanny, toalety, parkingi | `wsk-obiekty-komunalne.kml` | 206 |
| Osiedla (obrys obszaru w GeoJSON) | `wsk-obiekty-komunalne.kml` | 21 |
| Ulice Płocka (kod TERYT ULIC, punkt na ulicy, zasięg) | `ulice-plock-2023.gml` | 504 |

Skrypt przy okazji:
- przelicza współrzędne z układu PUWG 1992 (EPSG:2180) na WGS84,
- naprawia uszkodzone polskie litery w źródłach („P3ocku” → „Płocku”, „Sob?tka” → „Sobótka”, „Dluga” → „Długa”) na podstawie poprawnie zapisanych nazw ulic i szkół,
- poprawia dwa błędne wiersze w pliku roweru miejskiego i usuwa duplikaty.

Celowo **pomijane** są dane techniczne: stacje transformatorowe, przepompownie i punkty pomiarowe liczników (numery PPE).
Pliki `ulice-linie-bdot.csv` i `prg-ulice.gml` są w repozytorium jako materiał źródłowy (PRG służy tylko do słownika nazw).

Po zmianie plików źródłowych:

```
C:\xampp\php\php.exe bin\build-places-seed.php
C:\xampp\php\php.exe bin\build-places-seed.php --report   (lista wszystkich poprawionych nazw)
```

Na istniejącej bazie z prawdziwymi grami nie importuj całego `seed.sql` ponownie – wystarczy utworzyć trzy nowe tabele (koniec `schema.sql`) i wykonać sekcję „MIEJSCA” z `seed.sql`.

## Struktura projektu

```
pgm/
├── app/
│   ├── Controllers/   HomeController, AuthController, DashboardController
│   ├── Core/          App, Router, Request, View, Session, Csrf, Auth, Database, Env, Config
│   ├── Middleware/    AuthMiddleware, GuestMiddleware, CsrfMiddleware
│   ├── Models/        User, Game
│   ├── Services/      RateLimiter, AuditLog
│   ├── Views/         layouts/, public/, auth/, dashboard/, errors/, partials/
│   ├── bootstrap.php  wspólny start aplikacji
│   └── helpers.php    e(), url(), asset(), csrf_field(), redirect() …
├── bin/               create-admin.php, build-places-seed.php
├── config/            app.php, database.php
├── database/          schema.sql, seed.sql, dane-zrodlowe/ (otwarte dane miasta)
├── docs/              dokumentacja etapów
├── public/            ← jedyny katalog dostępny z przeglądarki
│   ├── index.php
│   └── assets/        css/, js/, images/
├── routes/web.php     wszystkie adresy aplikacji
├── storage/           logs/, uploads/ (zablokowane dla przeglądarki)
├── .env.example
└── .htaccess          przekierowanie do public/ i blokada reszty plików
```

## Bezpieczeństwo (zrealizowane w ETAPIE 2)

- Wszystkie zapytania SQL przez prepared statements PDO.
- Escapowanie danych w widokach funkcją `e()`, nagłówek Content-Security-Policy.
- Token CSRF sprawdzany automatycznie dla każdego żądania POST/PUT/DELETE.
- Hasła: `password_hash()` / `password_verify()`, minimum 12 znaków.
- Sesja: ciasteczko HttpOnly + SameSite=Lax (+ Secure przy HTTPS), nowy identyfikator po zalogowaniu, wylogowanie po 120 minutach bezczynności.
- Logowanie: jeden komunikat przy złym e-mailu i złym haśle, jednakowy czas odpowiedzi, limit 5 prób na konto i 20 na adres IP w ciągu 15 minut.
- Zablokowane lub niezatwierdzone konto zostaje wylogowane przy następnym żądaniu.
- Pliki `.env`, `config/`, `app/`, `database/` i `storage/` są niedostępne z przeglądarki, nawet gdy cały projekt leży w `htdocs`.
- `APP_ENV=production` wyłącza wyświetlanie szczegółów błędów. Są wtedy zapisywane tylko w `storage/logs/`.
- Ważne operacje (logowanie, wylogowanie, utworzenie konta) trafiają do tabeli `audit_logs`.
