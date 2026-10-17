-- =====================================================================
--  PGM – Płockie Gry Miejskie
--  database/schema.sql  –  pełny schemat bazy danych
--
--  Zgodność: MySQL 8.0.16+  oraz  MariaDB 10.4+ (XAMPP)
--  Kodowanie: utf8mb4, porównywanie: utf8mb4_polish_ci
--
--  UWAGA: skrypt USUWA istniejące tabele PGM i tworzy je od nowa.
--  Uruchamiaj go tylko na pustej bazie lub gdy świadomie chcesz
--  wyczyścić dane (np. w środowisku deweloperskim).
-- =====================================================================

CREATE DATABASE IF NOT EXISTS pgm
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_polish_ci;

USE pgm;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS reward_redemptions;
DROP TABLE IF EXISTS rewards;
DROP TABLE IF EXISTS submission_media;
DROP TABLE IF EXISTS task_submissions;
DROP TABLE IF EXISTS session_waypoints;
DROP TABLE IF EXISTS game_sessions;
DROP TABLE IF EXISTS players;
DROP TABLE IF EXISTS streets;
DROP TABLE IF EXISTS places;
DROP TABLE IF EXISTS place_categories;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS game_status_history;
DROP TABLE IF EXISTS task_options;
DROP TABLE IF EXISTS tasks;
DROP TABLE IF EXISTS waypoints;
DROP TABLE IF EXISTS game_categories;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS games;
DROP TABLE IF EXISTS images;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 1. USERS – konta twórców gier i administratorów systemu
--    role:
--      admin   – administrator systemu: zarządza kontami, widzi
--                i może edytować wszystkie gry
--      creator – twórca gier: pracownik UM Płocka lub upoważniony
--                przedstawiciel organizacji; zarządza tylko swoimi grami
--    account_status:
--      pending   – konto zarejestrowane, czeka na zatwierdzenie
--      active    – może się logować
--      suspended – zablokowane (logowanie niemożliwe)
-- ---------------------------------------------------------------------
CREATE TABLE users (
  id                   INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  organization_name    VARCHAR(150)      NOT NULL,
  first_name           VARCHAR(80)       NOT NULL,
  last_name            VARCHAR(80)       NOT NULL,
  email                VARCHAR(190)      NOT NULL,
  password_hash        VARCHAR(255)      NOT NULL,
  role                 ENUM('admin','creator')               NOT NULL DEFAULT 'creator',
  account_status       ENUM('pending','active','suspended')  NOT NULL DEFAULT 'pending',
  approved_by          INT UNSIGNED      NULL,
  approved_at          DATETIME          NULL,
  last_login_at        DATETIME          NULL,
  password_changed_at  DATETIME          NULL,
  created_at           DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_status_role (account_status, role),
  CONSTRAINT fk_users_approved_by
    FOREIGN KEY (approved_by) REFERENCES users (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- ---------------------------------------------------------------------
-- 2. IMAGES – metadane przesłanych plików
--    Same pliki leżą w storage/uploads (poza katalogiem public/),
--    pod losowymi nazwami. storage_path jest ścieżką względną
--    wewnątrz storage/uploads, np. "2026/10/3f9c...e1.webp".
--    Plik jest wydawany wyłącznie przez kontroler sprawdzający prawa.
-- ---------------------------------------------------------------------
CREATE TABLE images (
  id             INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  uploader_id    INT UNSIGNED      NULL,
  storage_path   VARCHAR(255)      NOT NULL,
  original_name  VARCHAR(255)      NOT NULL,
  mime_type      ENUM('image/jpeg','image/png','image/webp') NOT NULL,
  file_size      INT UNSIGNED      NOT NULL,
  width          SMALLINT UNSIGNED NULL,
  height         SMALLINT UNSIGNED NULL,
  sha256         CHAR(64)          NOT NULL,
  created_at     DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_images_storage_path (storage_path),
  KEY idx_images_uploader (uploader_id),
  KEY idx_images_sha256 (sha256),
  CONSTRAINT chk_images_size CHECK (file_size > 0 AND file_size <= 10485760),
  CONSTRAINT fk_images_uploader
    FOREIGN KEY (uploader_id) REFERENCES users (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- ---------------------------------------------------------------------
-- 3. GAMES – gry terenowe
--    status:
--      draft     – szkic, widoczny tylko dla właściciela i adminów; tylko szkic można edytować
--      published – opublikowana, dostępna w publicznym API; zablokowana do edycji
--      archived  – zarchiwizowana, tylko do odczytu w panelu
--    area_geojson – opcjonalny obszar gry jako GeoJSON Feature:
--      wielokąt: {"type":"Feature","geometry":{"type":"Polygon",...},
--                 "properties":{"shape":"polygon"}}
--      okrąg:    {"type":"Feature","geometry":{"type":"Point",
--                 "coordinates":[lng,lat]},
--                 "properties":{"shape":"circle","radius_m":300}}
--      (GeoJSON nie ma typu "okrąg", stąd punkt środka + promień)
--    lock_version – licznik do wykrywania konfliktów zapisu
--      (optymistyczne blokowanie przy autozapisie z dwóch kart)
--    custom_category – własna nazwa, gdy wybrano kategorię „Inne”
--    is_sample – 1 = gra demonstracyjna z seed.sql
-- ---------------------------------------------------------------------
CREATE TABLE games (
  id                          INT UNSIGNED       NOT NULL AUTO_INCREMENT,
  owner_id                    INT UNSIGNED       NOT NULL,
  title                       VARCHAR(150)       NOT NULL,
  short_description           VARCHAR(300)       NULL,
  description                 TEXT               NULL,
  cover_image_id              INT UNSIGNED       NULL,
  estimated_duration_minutes  SMALLINT UNSIGNED  NULL,
  difficulty                  ENUM('easy','medium','hard')                      NOT NULL DEFAULT 'medium',
  status                      ENUM('draft','published','archived')              NOT NULL DEFAULT 'draft',
  completion_points           SMALLINT UNSIGNED  NOT NULL DEFAULT 5,
  custom_category             VARCHAR(60)        NULL,
  area_geojson                JSON               NULL,
  available_from              DATETIME           NULL,
  available_until             DATETIME           NULL,
  is_sample                   TINYINT(1)         NOT NULL DEFAULT 0,
  lock_version                INT UNSIGNED       NOT NULL DEFAULT 1,
  published_at                DATETIME           NULL,
  archived_at                 DATETIME           NULL,
  created_at                  DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at                  DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_games_owner_status   (owner_id, status, updated_at),
  KEY idx_games_status_pub     (status, published_at),
  KEY idx_games_difficulty     (difficulty),
  KEY idx_games_cover          (cover_image_id),
  FULLTEXT KEY ft_games_search (title, short_description),

  CONSTRAINT chk_games_title_not_blank CHECK (CHAR_LENGTH(TRIM(title)) > 0),
  CONSTRAINT chk_games_duration        CHECK (estimated_duration_minutes IS NULL
                                              OR estimated_duration_minutes BETWEEN 1 AND 1440),
  CONSTRAINT chk_games_points          CHECK (completion_points <= 1000),
  CONSTRAINT chk_games_availability    CHECK (available_from IS NULL OR available_until IS NULL
                                              OR available_until > available_from),
  CONSTRAINT chk_games_is_sample       CHECK (is_sample IN (0,1)),

  CONSTRAINT fk_games_owner
    FOREIGN KEY (owner_id) REFERENCES users (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_games_cover
    FOREIGN KEY (cover_image_id) REFERENCES images (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- ---------------------------------------------------------------------
-- 4. CATEGORIES – słownik kategorii gier
-- ---------------------------------------------------------------------
CREATE TABLE categories (
  id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug        VARCHAR(60)       NOT NULL,
  name        VARCHAR(60)       NOT NULL,
  position    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_other    TINYINT(1)        NOT NULL DEFAULT 0,
  is_active   TINYINT(1)        NOT NULL DEFAULT 1,

  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_slug (slug),
  UNIQUE KEY uq_categories_name (name),
  KEY idx_categories_active_pos (is_active, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- ---------------------------------------------------------------------
-- 5. GAME_CATEGORIES – relacja wiele-do-wielu gry ↔ kategorie
-- ---------------------------------------------------------------------
CREATE TABLE game_categories (
  game_id      INT UNSIGNED      NOT NULL,
  category_id  SMALLINT UNSIGNED NOT NULL,

  PRIMARY KEY (game_id, category_id),
  KEY idx_game_categories_category (category_id, game_id),
  CONSTRAINT fk_gc_game
    FOREIGN KEY (game_id) REFERENCES games (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_gc_category
    FOREIGN KEY (category_id) REFERENCES categories (id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- ---------------------------------------------------------------------
-- 6. WAYPOINTS – punkty trasy
--    DECIMAL(10,7) = dokładność ok. 1 cm, bez błędów zaokrągleń FLOAT.
--    position zaczyna się od 1 i jest unikalna w obrębie gry.
--    Zmiana kolejności odbywa się w transakcji w dwóch krokach
--    (przesunięcie o +10000, potem właściwe numery), żeby nie
--    naruszyć klucza unikalnego i nie zmieniać identyfikatorów punktów.
--    player_hint – wskazówka dla gracza, jak znaleźć miejsce.
-- ---------------------------------------------------------------------
CREATE TABLE waypoints (
  id           INT UNSIGNED       NOT NULL AUTO_INCREMENT,
  game_id      INT UNSIGNED       NOT NULL,
  name         VARCHAR(120)       NOT NULL,
  description  TEXT               NULL,
  player_hint  VARCHAR(500)       NULL,
  latitude     DECIMAL(10,7)      NOT NULL,
  longitude    DECIMAL(10,7)      NOT NULL,
  position     SMALLINT UNSIGNED  NOT NULL,
  image_id     INT UNSIGNED       NULL,
  created_at   DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_waypoints_game_position (game_id, position),
  KEY idx_waypoints_image (image_id),
  CONSTRAINT chk_waypoints_lat      CHECK (latitude  BETWEEN -90  AND 90),
  CONSTRAINT chk_waypoints_lng      CHECK (longitude BETWEEN -180 AND 180),
  CONSTRAINT chk_waypoints_position CHECK (position >= 1),
  CONSTRAINT chk_waypoints_name     CHECK (CHAR_LENGTH(TRIM(name)) > 0),
  CONSTRAINT fk_waypoints_game
    FOREIGN KEY (game_id) REFERENCES games (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_waypoints_image
    FOREIGN KEY (image_id) REFERENCES images (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- ---------------------------------------------------------------------
-- 7. TASKS – zadanie przypisane do punktu (0 lub 1 na punkt)
--    task_type:
--      single_choice   – jednokrotny wybór  (opcje w task_options)
--      multiple_choice – wielokrotny wybór  (opcje w task_options)
--      open_answer     – odpowiedź otwarta (expected_answer /
--                        evaluation_instructions)
--      photo           – wykonanie zdjęcia  (media_requirement)
--      video           – nagranie filmu     (media_requirement,
--                        media_max_seconds)
--      info            – treść informacyjna bez odpowiedzi
--    unlock_mode – USTAWIENIE dla przyszłej aplikacji mobilnej
--      (panel tylko je zapisuje, nie sprawdza GPS):
--      always         – zadanie widoczne od razu
--      on_arrival     – po dotarciu w promień unlock_radius_m
--      after_previous – po ukończeniu poprzedniego punktu
--    expected_answer i is_correct NIGDY nie trafiają do publicznego API.
-- ---------------------------------------------------------------------
CREATE TABLE tasks (
  id                       INT UNSIGNED       NOT NULL AUTO_INCREMENT,
  waypoint_id              INT UNSIGNED       NOT NULL,
  title                    VARCHAR(150)       NOT NULL,
  description              TEXT               NOT NULL,
  task_type                ENUM('single_choice','multiple_choice','open_answer',
                                'photo','video','info')              NOT NULL,
  hint                     VARCHAR(500)       NULL,
  image_id                 INT UNSIGNED       NULL,
  max_points               SMALLINT UNSIGNED  NOT NULL DEFAULT 1,
  expected_answer          TEXT               NULL,
  evaluation_instructions  TEXT               NULL,
  media_requirement        VARCHAR(500)       NULL,
  media_max_seconds        SMALLINT UNSIGNED  NULL,
  unlock_mode              ENUM('always','on_arrival','after_previous') NOT NULL DEFAULT 'on_arrival',
  unlock_radius_m          SMALLINT UNSIGNED  NOT NULL DEFAULT 50,
  created_at               DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at               DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_tasks_waypoint (waypoint_id),
  KEY idx_tasks_type (task_type),
  KEY idx_tasks_image (image_id),
  CONSTRAINT chk_tasks_title         CHECK (CHAR_LENGTH(TRIM(title)) > 0),
  CONSTRAINT chk_tasks_points        CHECK (max_points <= 1000),
  CONSTRAINT chk_tasks_info_points   CHECK (task_type <> 'info' OR max_points = 0),
  CONSTRAINT chk_tasks_radius        CHECK (unlock_radius_m BETWEEN 5 AND 1000),
  CONSTRAINT chk_tasks_video_len     CHECK (media_max_seconds IS NULL
                                            OR (task_type = 'video' AND media_max_seconds BETWEEN 1 AND 600)),
  CONSTRAINT fk_tasks_waypoint
    FOREIGN KEY (waypoint_id) REFERENCES waypoints (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_tasks_image
    FOREIGN KEY (image_id) REFERENCES images (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- ---------------------------------------------------------------------
-- 8. TASK_OPTIONS – warianty odpowiedzi w pytaniach zamkniętych
--    Reguły zależne od typu zadania (np. dokładnie jedna poprawna
--    odpowiedź w single_choice, min. 2 opcje) sprawdza
--    GameValidationService w PHP – SQL nie wyrazi ich prostym CHECK.
-- ---------------------------------------------------------------------
CREATE TABLE task_options (
  id           INT UNSIGNED       NOT NULL AUTO_INCREMENT,
  task_id      INT UNSIGNED       NOT NULL,
  option_text  VARCHAR(300)       NOT NULL,
  is_correct   TINYINT(1)         NOT NULL DEFAULT 0,
  position     SMALLINT UNSIGNED  NOT NULL,

  PRIMARY KEY (id),
  UNIQUE KEY uq_task_options_position (task_id, position),
  CONSTRAINT chk_task_options_text    CHECK (CHAR_LENGTH(TRIM(option_text)) > 0),
  CONSTRAINT chk_task_options_correct CHECK (is_correct IN (0,1)),
  CONSTRAINT chk_task_options_pos     CHECK (position >= 1),
  CONSTRAINT fk_task_options_task
    FOREIGN KEY (task_id) REFERENCES tasks (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- ---------------------------------------------------------------------
-- 9. GAME_STATUS_HISTORY – historia publikacji, ukrywania, archiwizacji
-- ---------------------------------------------------------------------
CREATE TABLE game_status_history (
  id           BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  game_id      INT UNSIGNED      NOT NULL,
  changed_by   INT UNSIGNED      NULL,
  from_status  ENUM('draft','published','archived') NULL,
  to_status    ENUM('draft','published','archived') NOT NULL,
  note         VARCHAR(255)      NULL,
  created_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_gsh_game_time (game_id, created_at),
  KEY idx_gsh_user (changed_by),
  CONSTRAINT fk_gsh_game
    FOREIGN KEY (game_id) REFERENCES games (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_gsh_user
    FOREIGN KEY (changed_by) REFERENCES users (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- ---------------------------------------------------------------------
-- 10. LOGIN_ATTEMPTS – ograniczanie liczby prób logowania
--     email_hash = SHA-256 adresu (małe litery), bez przechowywania
--     wpisanego adresu w postaci jawnej. ip_address przez INET6_ATON().
--     Stare wpisy można okresowo usuwać (np. starsze niż 30 dni).
-- ---------------------------------------------------------------------
CREATE TABLE login_attempts (
  id            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  email_hash    CHAR(64)         NOT NULL,
  ip_address    VARBINARY(16)    NOT NULL,
  succeeded     TINYINT(1)       NOT NULL DEFAULT 0,
  attempted_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_login_attempts_email (email_hash, attempted_at),
  KEY idx_login_attempts_ip    (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- ---------------------------------------------------------------------
-- 11. AUDIT_LOGS – dziennik ważnych operacji (logowanie, publikacja,
--     usunięcie gry, zmiana roli itp.). details – dodatkowy kontekst
--     w JSON, bez haseł i tokenów.
-- ---------------------------------------------------------------------
CREATE TABLE audit_logs (
  id           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id      INT UNSIGNED     NULL,
  action       VARCHAR(64)      NOT NULL,
  entity_type  VARCHAR(32)      NULL,
  entity_id    INT UNSIGNED     NULL,
  ip_address   VARBINARY(16)    NULL,
  user_agent   VARCHAR(255)     NULL,
  details      JSON             NULL,
  created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_audit_user_time   (user_id, created_at),
  KEY idx_audit_entity      (entity_type, entity_id),
  KEY idx_audit_action_time (action, created_at),
  CONSTRAINT fk_audit_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- ---------------------------------------------------------------------
-- 12. PLACE_CATEGORIES – rodzaje miejsc referencyjnych (zabytek,
--     przystanek, plac zabaw...). source_name – skąd pochodzą dane.
-- ---------------------------------------------------------------------
CREATE TABLE place_categories (
  id           SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug         VARCHAR(60)       NOT NULL,
  name         VARCHAR(80)       NOT NULL,
  source_name  VARCHAR(120)      NULL,
  position     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active    TINYINT(1)        NOT NULL DEFAULT 1,

  PRIMARY KEY (id),
  UNIQUE KEY uq_place_categories_slug (slug),
  KEY idx_place_categories_active_pos (is_active, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- ---------------------------------------------------------------------
-- 13. PLACES – miejsca w Płocku z otwartych danych miasta (zabytki,
--     szkoły, przystanki, stacje roweru miejskiego, obiekty komunalne,
--     osiedla). Twórca gry może je wykorzystać jako gotowe punkty trasy;
--     punkt w grze (waypoints) jest wtedy KOPIĄ – zmiana lub usunięcie
--     miejsca nie zmienia istniejących gier.
--     area_geojson – obrys obszaru (np. osiedla) jako GeoJSON Feature;
--     latitude/longitude to wtedy przybliżony środek obszaru.
--     source_ref – identyfikator obiektu w pliku źródłowym.
--     Dane generuje bin/build-places-seed.php (sekcja w seed.sql).
-- ---------------------------------------------------------------------
CREATE TABLE places (
  id            INT UNSIGNED       NOT NULL AUTO_INCREMENT,
  category_id   SMALLINT UNSIGNED  NOT NULL,
  name          VARCHAR(255)       NOT NULL,
  address       VARCHAR(255)       NULL,
  description   TEXT               NULL,
  latitude      DECIMAL(10,7)      NOT NULL,
  longitude     DECIMAL(10,7)      NOT NULL,
  area_geojson  JSON               NULL,
  source_ref    VARCHAR(100)       NULL,
  is_active     TINYINT(1)         NOT NULL DEFAULT 1,
  created_at    DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_places_category (category_id, is_active),
  KEY idx_places_location (latitude, longitude),
  FULLTEXT KEY ft_places_search (name, address),
  CONSTRAINT chk_places_lat  CHECK (latitude  BETWEEN -90  AND 90),
  CONSTRAINT chk_places_lng  CHECK (longitude BETWEEN -180 AND 180),
  CONSTRAINT chk_places_name CHECK (CHAR_LENGTH(TRIM(name)) > 0),
  CONSTRAINT fk_places_category
    FOREIGN KEY (category_id) REFERENCES place_categories (id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- ---------------------------------------------------------------------
-- 14. STREETS – słownik ulic Płocka (TERYT, kod ULIC) do przyszłej
--     wyszukiwarki adresów. latitude/longitude – punkt leżący na ulicy
--     (najbliższy środka jej zasięgu), min_/max_ – prostokąt zasięgu.
-- ---------------------------------------------------------------------
CREATE TABLE streets (
  id             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  ulic_code      CHAR(5)        NOT NULL,
  name           VARCHAR(150)   NOT NULL,
  latitude       DECIMAL(10,7)  NOT NULL,
  longitude      DECIMAL(10,7)  NOT NULL,
  min_latitude   DECIMAL(10,7)  NOT NULL,
  min_longitude  DECIMAL(10,7)  NOT NULL,
  max_latitude   DECIMAL(10,7)  NOT NULL,
  max_longitude  DECIMAL(10,7)  NOT NULL,

  PRIMARY KEY (id),
  UNIQUE KEY uq_streets_ulic (ulic_code),
  KEY idx_streets_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- =====================================================================
--  APLIKACJA MOBILNA – gracze i rozgrywki
-- =====================================================================

-- ---------------------------------------------------------------------
-- 15. PLAYERS – konta graczy aplikacji mobilnej (osobne od users).
--     Aplikacja przy pierwszym uruchomieniu sama zakłada konto i dostaje
--     token API – gracz nie musi się logować. W bazie trzymamy wyłącznie
--     skrót SHA-256 tokenu. created_ip służy do limitu zakładania kont.
-- ---------------------------------------------------------------------
CREATE TABLE players (
  id              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  nickname        VARCHAR(40)    NOT NULL,
  api_token_hash  CHAR(64)       NOT NULL,
  nickname_changed_at DATETIME   NULL,
  created_ip      VARBINARY(16)  NULL,
  created_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at    DATETIME       NULL,

  PRIMARY KEY (id),
  UNIQUE KEY uq_players_token (api_token_hash),
  KEY idx_players_created_ip (created_ip, created_at),
  CONSTRAINT chk_players_nickname CHECK (CHAR_LENGTH(TRIM(nickname)) >= 3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- ---------------------------------------------------------------------
-- 16. GAME_SESSIONS – jedno przejście gry przez gracza.
--     score – suma przyznanych punktów (+ completion_points gry po
--     ukończeniu). Gracz może mieć tylko jedną rozgrywkę „w trakcie”
--     danej gry; po ukończeniu może zagrać ponownie (ranking bierze
--     najlepszy wynik).
--     game_version – games.lock_version w chwili startu (która wersja
--     gry była rozgrywana). Gry z rozgrywkami nie można usunąć (RESTRICT) –
--     historia graczy zostaje; grę się archiwizuje.
-- ---------------------------------------------------------------------
CREATE TABLE game_sessions (
  id            INT UNSIGNED       NOT NULL AUTO_INCREMENT,
  game_id       INT UNSIGNED       NOT NULL,
  player_id     INT UNSIGNED       NOT NULL,
  game_version  INT UNSIGNED       NOT NULL DEFAULT 1,
  status        ENUM('in_progress','completed') NOT NULL DEFAULT 'in_progress',
  score         SMALLINT UNSIGNED  NOT NULL DEFAULT 0,
  started_at    DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at   DATETIME           NULL,

  PRIMARY KEY (id),
  KEY idx_sessions_player (player_id, status, started_at),
  KEY idx_sessions_ranking (game_id, status, score),
  CONSTRAINT fk_sessions_game
    FOREIGN KEY (game_id) REFERENCES games (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_sessions_player
    FOREIGN KEY (player_id) REFERENCES players (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- ---------------------------------------------------------------------
-- 17. SESSION_WAYPOINTS – dotarcie gracza do punktu trasy (pozycja GPS
--     zgłoszona przez aplikację i sprawdzona przez serwer).
-- ---------------------------------------------------------------------
CREATE TABLE session_waypoints (
  session_id   INT UNSIGNED       NOT NULL,
  waypoint_id  INT UNSIGNED       NOT NULL,
  latitude     DECIMAL(10,7)      NOT NULL,
  longitude    DECIMAL(10,7)      NOT NULL,
  accuracy_m   SMALLINT UNSIGNED  NULL,
  distance_m   SMALLINT UNSIGNED  NOT NULL,
  reached_at   DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (session_id, waypoint_id),
  KEY idx_session_waypoints_wp (waypoint_id),
  CONSTRAINT fk_sw_session
    FOREIGN KEY (session_id) REFERENCES game_sessions (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_sw_waypoint
    FOREIGN KEY (waypoint_id) REFERENCES waypoints (id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- ---------------------------------------------------------------------
-- 18. TASK_SUBMISSIONS – odpowiedź gracza na zadanie (jedna na zadanie
--     w danej rozgrywce). review_status:
--       auto       – oceniona automatycznie (pytania zamknięte, odpowiedź
--                    otwarta z wpisaną oczekiwaną odpowiedzią, info)
--       unverified – zaliczona bez sprawdzania (zdjęcie, film, odpowiedź
--                    otwarta bez wzorca); organizator może ją zweryfikować
--       rejected   – odrzucona przez organizatora (przyszła funkcja panelu)
-- ---------------------------------------------------------------------
CREATE TABLE task_submissions (
  id                   INT UNSIGNED       NOT NULL AUTO_INCREMENT,
  session_id           INT UNSIGNED       NOT NULL,
  task_id              INT UNSIGNED       NOT NULL,
  answer_text          TEXT               NULL,
  selected_option_ids  JSON               NULL,
  is_correct           TINYINT(1)         NULL,
  points_awarded       SMALLINT UNSIGNED  NOT NULL DEFAULT 0,
  review_status        ENUM('auto','unverified','rejected') NOT NULL DEFAULT 'auto',
  submitted_at         DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_submissions_session_task (session_id, task_id),
  KEY idx_submissions_task (task_id, review_status),
  CONSTRAINT fk_submissions_session
    FOREIGN KEY (session_id) REFERENCES game_sessions (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_submissions_task
    FOREIGN KEY (task_id) REFERENCES tasks (id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- ---------------------------------------------------------------------
-- 19. SUBMISSION_MEDIA – zdjęcia i filmy przesłane przez graczy.
--     Pliki leżą w storage/uploads/submissions/ (poza katalogiem public).
-- ---------------------------------------------------------------------
CREATE TABLE submission_media (
  id             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  submission_id  INT UNSIGNED  NOT NULL,
  storage_path   VARCHAR(255)  NOT NULL,
  mime_type      VARCHAR(50)   NOT NULL,
  file_size      INT UNSIGNED  NOT NULL,
  sha256         CHAR(64)      NOT NULL,
  created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_submission_media_path (storage_path),
  KEY idx_submission_media_submission (submission_id),
  CONSTRAINT fk_submission_media_submission
    FOREIGN KEY (submission_id) REFERENCES task_submissions (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- =====================================================================
--  SKLEP PUNKTÓW
--  Saldo gracza NIE jest przechowywane – liczy je serwer:
--    suma najlepszych wyników z każdej gry (ponowne granie tej samej gry
--    nie „dodrukowuje” punktów)  −  koszt wymian (bez anulowanych).
-- =====================================================================

-- ---------------------------------------------------------------------
-- 20. REWARDS – nagrody dodawane przez administratora w panelu.
--     stock NULL = bez limitu sztuk. is_sample = nagroda demonstracyjna
--     (bez wartości handlowej – pokazywana z dopiskiem „przykład”).
-- ---------------------------------------------------------------------
CREATE TABLE rewards (
  id                       INT UNSIGNED       NOT NULL AUTO_INCREMENT,
  title                    VARCHAR(150)       NOT NULL,
  description              TEXT               NULL,
  reward_type              ENUM('discount','coupon','item','other') NOT NULL DEFAULT 'other',
  partner_name             VARCHAR(150)       NULL,
  cost_points              INT UNSIGNED       NOT NULL,
  stock                    INT UNSIGNED       NULL,
  valid_until              DATETIME           NULL,
  redemption_instructions  TEXT               NULL,
  is_active                TINYINT(1)         NOT NULL DEFAULT 1,
  is_sample                TINYINT(1)         NOT NULL DEFAULT 0,
  created_by               INT UNSIGNED       NULL,
  created_at               DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at               DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_rewards_active (is_active, cost_points),
  CONSTRAINT chk_rewards_title CHECK (CHAR_LENGTH(TRIM(title)) > 0),
  CONSTRAINT chk_rewards_cost  CHECK (cost_points BETWEEN 1 AND 1000000),
  CONSTRAINT fk_rewards_user
    FOREIGN KEY (created_by) REFERENCES users (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- ---------------------------------------------------------------------
-- 21. REWARD_REDEMPTIONS – wymiany punktów na nagrody.
--     cost_points – koszt w chwili wymiany (zmiana ceny nie zmienia historii).
--     code – kod do okazania przy odbiorze (generuje serwer).
--     idempotency_key – ten sam klucz od aplikacji = ta sama wymiana
--     (ponowione żądanie nie pobierze punktów drugi raz).
--     status: issued – wydana, used – zrealizowana, cancelled – anulowana
--     (punkty wracają do salda).
-- ---------------------------------------------------------------------
CREATE TABLE reward_redemptions (
  id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  reward_id        INT UNSIGNED  NOT NULL,
  player_id        INT UNSIGNED  NOT NULL,
  cost_points      INT UNSIGNED  NOT NULL,
  code             CHAR(14)      NOT NULL,
  status           ENUM('issued','used','cancelled') NOT NULL DEFAULT 'issued',
  idempotency_key  CHAR(36)      NULL,
  created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  used_at          DATETIME      NULL,

  PRIMARY KEY (id),
  UNIQUE KEY uq_redemptions_code (code),
  UNIQUE KEY uq_redemptions_idempotency (player_id, idempotency_key),
  KEY idx_redemptions_player (player_id, created_at),
  KEY idx_redemptions_reward (reward_id, status),
  CONSTRAINT fk_redemptions_reward
    FOREIGN KEY (reward_id) REFERENCES rewards (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_redemptions_player
    FOREIGN KEY (player_id) REFERENCES players (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;
