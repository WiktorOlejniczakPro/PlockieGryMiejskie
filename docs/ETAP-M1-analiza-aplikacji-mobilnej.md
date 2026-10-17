# PGM Mobile · ETAP 1 – analiza istniejącego systemu i plan integracji

Stan na 17.10.2026, gałąź `dane-miejskie` (commit z API miejsc). Analiza obejmuje **wyłącznie** pliki z tego repozytorium:
`database/schema.sql`, `database/seed.sql`, `routes/web.php`, `app/**` (kontrolery, modele, usługi, middleware, rdzeń),
`config/*.php`, `docs/*.md` oraz kod aplikacji Android przygotowany w poprzednich krokach (wersja 3, poza repozytorium).

Czego **nie** widziałem: wersji strony i bazy, które faktycznie działają na Twoim XAMPP (zakładam, że to ta z repozytorium),
zatwierdzonych tekstów regulaminu i polityki prywatności, listy prawdziwych nagród do sklepu.

---

## 1. Technologie istniejącego systemu

| Warstwa | Technologia (z kodu) |
|---|---|
| Backend | PHP 8.2+, własny mini-framework MVC (`app/Core`: Router, Request, Database, Session, Csrf, Auth), PDO z prawdziwymi prepared statements |
| Baza | MySQL 8 / MariaDB 10.4+, `utf8mb4_polish_ci`, 19 tabel |
| Panel WWW | widoki PHP + JS, mapa Leaflet + OpenStreetMap (`public/assets/js/editor.js`) |
| API | JSON, prefiks `/api/v1/`, odpowiedź `{"data": …}` albo `{"error": {"status", "message"}}` |
| Uwierzytelnianie panelu | sesja PHP (ciasteczko `PGMSESSID`) + CSRF |
| Uwierzytelnianie aplikacji | token Bearer gracza (w bazie SHA-256 w `players.api_token_hash`), bez sesji i CSRF |
| Pliki graczy | `storage/uploads/submissions/RRRR/MM/` poza `public/`, typ sprawdzany z zawartości (`finfo`) |

---

## 2. Przepływ danych

```
 Administrator ──(przeglądarka, sesja+CSRF)──► Panel PHP ──► /api/v1/admin/games ──┐
                                                                                    ▼
                                                     GameValidationService → GameSaveService
                                                                                    │ (1 transakcja)
                                                                                    ▼
                                               ┌──────────── MySQL (jedno źródło prawdy) ────────────┐
                                               │ games · game_categories · waypoints · tasks ·        │
                                               │ task_options · images · categories                   │
                                               │ players · game_sessions · session_waypoints ·        │
                                               │ task_submissions · submission_media                  │
                                               │ place_categories · places · streets                  │
                                               └──────────────────────────────────────────────────────┘
                                                                                    ▲
                     PlayerGameSerializer (tylko published, bez odpowiedzi) ────────┤
                     GamePlayService (GPS, odblokowanie, ocena, punkty) ────────────┘
                                                    ▲
 Gracz ──(Android, HTTPS, Authorization: Bearer)────┘  /api/v1/games, /sessions/…
```

Android **nigdy** nie łączy się z MySQL i nie zna żadnych haseł bazy.

---

## 3. Baza danych – tabele dotyczące gier i rozgrywki

| Tabela | Najważniejsze pola | Uwagi |
|---|---|---|
| `games` | `id`, `title`, `short_description`, `description`, `cover_image_id`, `estimated_duration_minutes`, `difficulty` (easy/medium/hard), `status` (draft/published/hidden/archived), `completion_points` (domyślnie **5**), `area_geojson`, `available_from/until`, `lock_version`, `published_at` | gracz widzi tylko `published` w terminie dostępności |
| `categories`, `game_categories` | `slug`, `name`, `is_other` / N:M | 11 kategorii z `seed.sql`; własna kategoria w `games.custom_category` |
| `waypoints` | `id`, `game_id`, `name`, `description`, `player_hint`, `latitude`/`longitude` DECIMAL(10,7), `position` (1..N, unikalna w grze), `image_id` | kolejność = `position` ustawiona w edytorze |
| `tasks` | 0..1 na punkt: `task_type` (single_choice, multiple_choice, open_answer, photo, video, info), `title`, `description`, `hint`, `max_points`, `expected_answer`, `evaluation_instructions`, `media_requirement`, `media_max_seconds`, `unlock_mode` (always/on_arrival/after_previous), `unlock_radius_m` (domyślnie **30**) | `expected_answer` i `is_correct` nigdy nie idą do gracza |
| `task_options` | `id`, `task_id`, `option_text`, `is_correct`, `position` | |
| `images` | metadane plików z panelu | **brak** endpointu wydającego obrazy |
| `players` | `id`, `nickname`, `api_token_hash`, `created_ip`, `created_at`, `last_seen_at` | **brak** e-maila i hasła – konto „anonimowe na urządzeniu” |
| `game_sessions` | `id`, `game_id`, `player_id`, `status` (in_progress/completed), `score`, `started_at`, `finished_at` | jedna trwająca rozgrywka gracza na grę |
| `session_waypoints` | dotarcie: `latitude`, `longitude`, `accuracy_m`, `distance_m`, `reached_at` | PK (session, waypoint) = brak duplikatów |
| `task_submissions` | `answer_text`, `selected_option_ids` (JSON), `is_correct`, `points_awarded`, `review_status` (auto/unverified/rejected) | UNIQUE (session, task) = jedna odpowiedź |
| `submission_media` | plik zdjęcia/filmu gracza | |
| `places`, `place_categories`, `streets` | otwarte dane miasta (1319 miejsc, 504 ulice) | zakładka „Odkrywaj” |

---

## 4. Istniejące endpointy dla aplikacji

| Metoda | Ścieżka | Auth | Co robi |
|---|---|---|---|
| GET | `/api/v1/categories` | – | kategorie `{slug, name}` |
| GET | `/api/v1/games?category=&difficulty=&q=` | – | opublikowane gry (1 kategoria naraz) |
| GET | `/api/v1/games/{id}` | – | gra + punkty (wg `position`) + zadania + opcje **bez** odpowiedzi |
| GET | `/api/v1/games/{id}/ranking` | – | najlepszy ukończony wynik każdego gracza |
| GET | `/api/v1/ranking` | – | suma najlepszych wyników ze wszystkich gier |
| GET | `/api/v1/place-categories`, `/api/v1/places` | – | miejsca z danych miasta |
| POST | `/api/v1/players` | – | nowe konto bez hasła → token (limit 30/h z IP) |
| GET / PUT | `/api/v1/me` | Bearer | profil + historia / zmiana nicku |
| POST | `/api/v1/games/{id}/sessions` | Bearer | start lub wznowienie rozgrywki |
| GET | `/api/v1/sessions/{id}` | Bearer | stan rozgrywki (tylko własnej) |
| POST | `/api/v1/sessions/{id}/waypoints/{waypointId}/arrival` | Bearer | dotarcie – serwer liczy odległość (promień + min(dokładność, 30 m)) |
| POST | `/api/v1/sessions/{id}/tasks/{taskId}/answer` | Bearer | odpowiedź (JSON lub multipart `media`), ocena po stronie serwera |

Szczegóły formatów: `docs/API-android.md`. Wszystkie powyższe przetestowałem na MariaDB (pełne przejście gry przykładowej, błędy 401/403/404/409/422).

---

## 5. Co już spełnia wymagania promptu

- Jedno źródło prawdy: gra z panelu → te same tabele → API gracza.
- Kolejność punktów z `waypoints.position`, zadania przypięte do właściwych punktów (`tasks.waypoint_id`).
- Gracz dostaje wyłącznie gry `published` w terminie dostępności; brak poprawnych odpowiedzi w JSON.
- Ocena odpowiedzi, przyznawanie punktów i bonus `completion_points` liczone **wyłącznie na serwerze**.
- Ochrona przed duplikatami: jedna odpowiedź na zadanie (UNIQUE), dotarcie `INSERT IGNORE`, bonus raz (warunek `status = 'in_progress'`).
- Cudza rozgrywka → 404; punkt/zadanie spoza gry → 404.
- Ranking i historia z bazy, nie z telefonu.

---

## 6. Braki i niezgodności (wynik analizy)

### 6.1. Krytyczne – mogą zepsuć dane lub demo

| # | Problem | Skutek | Propozycja |
|---|---|---|---|
| K1 | Panel **nie ma publikacji** (brak endpointu/przycisku; etap 6 panelu niezrobiony) | nowa gra z panelu **nie pojawi się** w aplikacji – jedyna opublikowana to przykład z `seed.sql` | `POST /api/v1/admin/games/{id}/publish` i `/hide` z `GameValidationService::forPublish()` + przycisk w edytorze |
| K2 | Edycja opublikowanej gry przez `GameSaveService` **usuwa** punkty i zadania nieobecne w zapisie oraz **odtwarza** opcje odpowiedzi | `ON DELETE CASCADE` kasuje postęp graczy (`session_waypoints`, `task_submissions`); nowe `task_options.id` rozjeżdżają zapisane `selected_option_ids` | zapis migawki gry przy starcie rozgrywki (`game_sessions.game_version` + `snapshot_json`) **albo** blokada usuwania punktów/zadań w grze z aktywnymi rozgrywkami; opcje aktualizować po `id` zamiast DELETE+INSERT |
| K3 | Usunięcie gry kasuje historię graczy (`game_sessions` → `games` ON DELETE CASCADE) | utrata historii i rankingu | w panelu tylko archiwizacja; FK zmienić na RESTRICT |
| K4 | Brak wydawania obrazów (`images`, `cover_image_id`, `waypoints.image_id`, `tasks.image_id`) i brak uploadu w panelu | karty gier bez zdjęć | `GET /api/v1/images/{id}` (tylko obrazy opublikowanych gier) + `cover_url` w JSON gry |

### 6.2. Wymagane przez nowy prompt, a nieobecne w backendzie

| # | Wymaganie | Stan | Propozycja (tabele / endpointy) |
|---|---|---|---|
| B1 | Rejestracja i logowanie e-mail + hasło | `players` bez e-maila i hasła; dziś konto zakładane automatycznie | `players`: `username` UNIQUE, `email` UNIQUE, `password_hash`, `account_status`, `email_verified_at`, `username_changed_at`, `terms_accepted_at`; tabela `player_tokens` (wiele urządzeń, unieważnianie przy wylogowaniu); `POST /auth/register`, `POST /auth/login`, `POST /auth/logout`; limit prób – istniejące `login_attempts` |
| B2 | Limit 2 rozpoczęte gry dziennie (Europe/Warsaw) | brak | liczenie `game_sessions.started_at` w strefie Warszawy w tej samej transakcji z blokadą wiersza gracza (już jest `SELECT … FOR UPDATE` w `GamePlayService::start`); `GET /me/limits` → `{limit, used, remaining, resets_at}`; 429 z komunikatem przy przekroczeniu. **Wznowienie** trwającej gry nie zużywa limitu |
| B3 | Ścisła kolejność punktów | dziś dotarcie do **dowolnego** punktu jest przyjmowane; tylko `unlock_mode = after_previous` blokuje zadanie | serwer przyjmuje dotarcie tylko do pierwszego nieukończonego punktu (domyślnie dla wszystkich gier) – zmiana w `GamePlayService::arrive` |
| B4 | Domyślny promień 50 m | schemat: `unlock_radius_m` DEFAULT 30, punkt bez zadania 30 m | zmiana domyślnej wartości w schemacie i stałej `DEFAULT_RADIUS_M` na 50 (istniejące gry bez zmian) |
| B5 | Ręczna ocena („oczekuje na ocenę”) | zdjęcia/filmy/otwarte bez wzorca są **od razu zaliczane** (`unverified`, pełne punkty) | nowy `review_status = 'pending'` z 0 pkt + ekran oceny w panelu; do czasu jego powstania zostawić obecne zachowanie (inaczej punkty nigdy nie zostaną przyznane) |
| B6 | Ponowna próba odpowiedzi („jeśli zasady pozwalają”) | jedna próba na zadanie | ewentualnie `tasks.max_attempts` (domyślnie 1) – **decyzja zespołu** |
| B7 | Saldo punktów i sklep nagród | brak tabel | `rewards` (nazwa, opis, typ, koszt, stan, ważność, aktywność), `reward_redemptions` (gracz, nagroda, koszt, status, kod, `idempotency_key` UNIQUE), `point_ledger` (każdy wpis +/−, UNIQUE na źródło → brak podwójnych punktów); `GET /me/balance`, `GET /rewards`, `POST /rewards/{id}/redeem` (atomowo w transakcji), `GET /me/redemptions`; **zarządzanie nagrodami w panelu** (bez tego sklep będzie pusty – nie wpisujemy fikcyjnych ofert jako prawdziwych) |
| B8 | Zmiana nazwy raz w roku, zmiana hasła i e-maila | jest tylko zmiana nicku bez limitu | `PUT /me/username` (sprawdza `username_changed_at`, zwraca `next_change_at`), `PUT /me/password` (stare hasło), `PUT /me/email` |
| B9 | Filtry: wiele kategorii, czas, liczba punktów trasy, sortowanie | API: 1 kategoria, trudność, tekst | katalog jest mały (≤200 gier na odpowiedź) → filtrowanie po stronie aplikacji jest wystarczające; opcjonalnie `category=a,b`, `sort=` w API |
| B10 | Historia z liczbą odwiedzonych punktów, szczegóły zakończonej rozgrywki | `/me` zwraca tytuł, status, wynik, daty | dodać `completed_waypoints`/`total_waypoints` do historii; `GET /sessions/{id}` działa także dla zakończonych |
| B11 | Trasa piesza | tylko linia prosta (uczciwie opisana jako „w linii prostej”) | bez usługi trasowania: punkty + odległość + **nawigacja zewnętrzna** (już jest). Opcjonalnie OSRM / openrouteservice po stronie serwera |
| B12 | Regulamin, polityka prywatności | brak treści | potrzebne zatwierdzone teksty od zespołu/UM – nie wymyślam dokumentów prawnych |

---

## 7. Decyzje do podjęcia przez zespół

1. **Konto gracza**: e-mail + hasło (prompt) zamiast obecnego „zawsze zalogowany”? Proponuję: rejestracja/logowanie jak w prompcie, a obecne konta anonimowe można połączyć z nowym kontem przy rejestracji (historia zostaje).
2. **Logowanie e-mailem czy nazwą użytkownika?** Proponuję e-mail (unikalny) + osobna, publiczna nazwa użytkownika.
3. **Potwierdzanie e-maila**: na hackathonie zwykle bez wysyłki maili (brak serwera SMTP w XAMPP) – konto aktywne od razu.
4. **Limit dzienny**: liczy każdą rozpoczętą grę (także przerwaną), wznowienie się nie liczy – zgodnie z promptem.
5. **Mapa**: zostaję przy **OpenStreetMap** (osmdroid) – zgodnie z panelem (Leaflet/OSM), bez klucza API i opłat. Google Maps wymaga klucza i konta rozliczeniowego.
6. **Zdjęcia/filmy**: zaliczać od razu (jak teraz) czy czekać na ocenę w panelu (wymaga ekranu oceny – nowa praca w panelu)?
7. **Sklep**: kto i gdzie dodaje nagrody? Proponuję prosty ekran „Nagrody” w panelu admina; do demo – nagrody wyraźnie oznaczone jako **przykładowe** (`is_sample`), jak gry przykładowe.

---

## 8. Mapowanie pól bazy → modele Kotlin

Konwencja: JSON w `snake_case` (tak zwraca API), w Kotlinie `camelCase` z jawnymi adnotacjami serializacji.

| Baza / JSON | Kotlin | Typ | Uwagi |
|---|---|---|---|
| `games.id` → `id` | `Game.id` | Int | API zwraca `id` (nie `game_id`) |
| `games.title` | `title` | String | API usuwa prefiks `[PRZYKŁAD]`, flaga `is_sample` osobno |
| `short_description`, `description` | `shortDescription`, `description` | String? | |
| `estimated_duration_minutes` | `estimatedDurationMinutes` | Int? | filtr czasu: ≤30, 31–60, >60 |
| `difficulty` | `difficulty` | enum `Difficulty` (easy/medium/hard) + `UNKNOWN` | odporność na nowe wartości |
| `completion_points` | `completionPoints` | Int | bonus za ukończenie (z bazy, nie stała 5) |
| suma `tasks.max_points` + bonus | `maxScore` | Int | liczone przez API |
| `game_categories` → `categories[]` | `List<Category>` | `{slug, name}` | |
| (liczone) `waypoint_count` | `waypointCount` | Int | filtr <5, =5, >5 |
| `route_length_m` | `routeLengthM` | Int | w linii prostej |
| `area_geojson` → `area` | `GeoJsonFeature?` | | okrąg = Point + `radius_m` |
| `waypoints.id/position/name/description/player_hint/latitude/longitude` | `Waypoint` | Int/Int/String/String?/String?/Double/Double | `latitude` z DECIMAL → Double |
| `tasks.unlock_radius_m` → `arrival_radius_m` | `arrivalRadiusM` | Int | dla punktu bez zadania 30 (→50) |
| `tasks.*` (bez `expected_answer`, `evaluation_instructions`) | `GameTask` | | `taskType` → enum `TaskType` + `UNKNOWN` |
| `task_options.id/option_text` | `TaskOption(id, text)` | | bez `is_correct` |
| `game_sessions.*` + stan | `SessionState` | | `status` → enum |
| `task_submissions.*` | `Submission` | | `reviewStatus` → enum (auto/unverified/rejected/pending) |
| `players.id/nickname` | `Player` | | po B1: `username`, `email` |

---

## 9. Stan obecnej aplikacji Android (v3) wobec promptu

| Obszar | Teraz | Wymaganie promptu | Plan |
|---|---|---|---|
| Nawigacja | ręczny stan w ViewModelu | Navigation Compose | migracja na Navigation Compose |
| JSON | Gson + `FieldNamingPolicy` | kotlinx.serialization lub Moshi | migracja na **kotlinx.serialization** (bezpieczne null-e, enumy z wartością domyślną) |
| Token | SharedPreferences | szyfrowany magazyn (Keystore) | **DataStore + szyfrowanie kluczem z Android Keystore** (EncryptedSharedPreferences jest wycofane przez Google) |
| Konto | automatyczne, bez logowania | rejestracja/logowanie | ekrany auth po B1 |
| Architektura | 1 ViewModel, pakiety `data/ui` | moduły `feature/*`, `core/*`, repozytoria | podział jak w prompcie, ViewModel na ekran, StateFlow |
| Lokalizacja | LocationManager | Fused lub mechanizm Androida | zostaje LocationManager (działa bez Google Play Services, np. na emulatorach bez GMS) + stabilizacja pomiarów |
| Mapa | osmdroid | Google Maps lub zgodne z ustaleniami | osmdroid (decyzja 5) |
| Sklep, limit, historia rozszerzona, ustawienia konta, motyw | brak | wymagane | po rozszerzeniach B2, B7, B8, B10 |

Kod v3 jest dobrym punktem wyjścia dla rozgrywki (mapa, GPS, zadania, aparat działają na tym samym API); nie piszę go od zera, tylko przebudowuję do docelowej struktury.

---

## 10. Proponowana struktura aplikacji

```
pl.plock.pgm
├── PgmApplication.kt, MainActivity.kt
├── navigation/        PgmNavHost, trasy (Start, Login, Register, Home, Play, GameDetails, Gameplay/{sessionId},
│                      History, Shop, Settings, Ranking)
├── core/
│   ├── network/       Retrofit + OkHttp (timeouty, nagłówek Bearer, obsługa 401 → wylogowanie), ApiResult, mapowanie błędów
│   ├── security/      TokenStore (DataStore + Keystore)
│   ├── location/      LocationTracker (stabilizacja: ≥2 pomiary w promieniu, dokładność ≤ 50 m)
│   ├── ui/            motyw PGM, PgmIcons, przyciski, karty, stany Loading/Empty/Error, okna potwierdzeń
│   └── util/          odległość, formatowanie dat (Europe/Warsaw), odmiana liczebników
├── data/
│   ├── remote/        PgmApi, DTO (kotlinx.serialization)
│   ├── local/         UserPreferences (motyw, „nie pokazuj zarchiwizowanych”, tryb demo)
│   └── repository/    AuthRepository, GameRepository, SessionRepository, ShopRepository, RankingRepository
├── domain/            modele i reguły (filtry katalogu, stan etapu, dostępność zadania)
└── feature/
    ├── auth/  home/  games/  game_details/  gameplay/  archive/  shop/  settings/  profile/  explore/
```

---

## 11. Plan wdrożenia rozgrywki GPS (najwyższy priorytet)

1. **Backend** (przed aplikacją): B3 ścisła kolejność, B4 promień 50 m, K2 migawka/ochrona postępu, B2 limit przy starcie.
2. **Start gry**: ekran podsumowania (czas, liczba punktów, wymaganie GPS, zadania foto/wideo, pozostały limit z `GET /me/limits`) → zgoda na lokalizację „podczas używania” → `POST /games/{id}/sessions` (429 = limit) → `GET /sessions/{id}`.
3. **Ekran gry**: mapa OSM z aktywnym punktem wyróżnionym, ukończone zielone, przyszłe przygaszone; linia trasy opisana jako schematyczna; przyciski: centruj na mnie / na celu / lista / nawigacja zewnętrzna; panel: „Punkt 2 z 7”, nazwa, opis, odległość, stan zasięgu, przycisk zadania; pasek postępu i punkty.
4. **Zaliczenie**: aplikacja zgłasza dotarcie dopiero, gdy **2 kolejne pomiary** (≥3 s odstępu) z dokładnością ≤ 50 m leżą w promieniu; przy słabym GPS komunikat „Nie możemy jeszcze dokładnie określić Twojej lokalizacji…”; ostateczną decyzję podejmuje serwer.
5. **Zadania**: 6 typów jak w bazie; odpowiedź wysyłana raz (przycisk blokowany, ponowienie tylko po błędzie sieci, 409 = już wykonane → odświeżenie stanu); upload zdjęć/filmów z paskiem postępu.
6. **Koniec**: ekran podsumowania z punktami za zadania i osobno bonusem, czasem gry, nowym saldem; wpis w historii.
7. **Wznowienie**: wyjście z gry nie kasuje rozgrywki; „Kontynuuj” w szczegółach i w historii.

---

## 12. Proponowana kolejność dalszych etapów

| Etap | Zakres | Zależy od |
|---|---|---|
| 2 | Backend: K1 publikacja, K2–K3 ochrona danych, B1 konta, B2 limit, B3–B4 GPS, B10 historia | decyzje 1–4 |
| 3 | Android: konfiguracja (Gradle pod Twoją wersję AGP), architektura, motyw, sieć, TokenStore | – |
| 4 | Auth (rejestracja, logowanie, sesja, wylogowanie) | etap 2 (B1) |
| 5–6 | Główna, katalog z filtrami, szczegóły gry | – |
| 7–8 | Rozgrywka GPS + zadania (przeniesienie i poprawa kodu v3) | etap 2 (B2–B4) |
| 9 | Punkty, historia, archiwum | B10 |
| 10 | Sklep i rankingi | B7 + ekran nagród w panelu |
| 11 | Ustawienia (hasło, e-mail, nazwa raz w roku, motyw, preferencje) | B8 |
| 12 | Testy, dokumentacja, instrukcja demo | – |
