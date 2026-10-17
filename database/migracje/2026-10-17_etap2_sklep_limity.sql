-- =====================================================================
--  PGM – migracja bazy do wersji „etap 2” (sklep, limit dzienny, ochrona
--  postępu graczy). Dla bazy zaimportowanej WCZEŚNIEJ ze schema.sql –
--  dane (gry, gracze, rozgrywki) zostają.
--
--  phpMyAdmin: wybierz bazę pgm po lewej → Import → ten plik → Importuj.
--  Uruchom JEDEN raz. Na nowej instalacji wystarczy aktualny schema.sql.
-- =====================================================================

USE pgm;
SET NAMES utf8mb4;

-- domyślny promień odblokowania zadania: 50 m (istniejące zadania bez zmian)
ALTER TABLE tasks ALTER unlock_radius_m SET DEFAULT 50;

-- zmiana nicku raz w roku
ALTER TABLE players ADD COLUMN nickname_changed_at DATETIME NULL AFTER api_token_hash;

-- wersja gry w chwili startu rozgrywki
ALTER TABLE game_sessions ADD COLUMN game_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER player_id;

-- ochrona historii i postępu graczy: zamiast kaskadowego usuwania – blokada
ALTER TABLE game_sessions DROP FOREIGN KEY fk_sessions_game;
ALTER TABLE game_sessions ADD CONSTRAINT fk_sessions_game
  FOREIGN KEY (game_id) REFERENCES games (id) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE session_waypoints DROP FOREIGN KEY fk_sw_waypoint;
ALTER TABLE session_waypoints ADD CONSTRAINT fk_sw_waypoint
  FOREIGN KEY (waypoint_id) REFERENCES waypoints (id) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE task_submissions DROP FOREIGN KEY fk_submissions_task;
ALTER TABLE task_submissions ADD CONSTRAINT fk_submissions_task
  FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE RESTRICT ON UPDATE CASCADE;

-- Tabele nagród i sklepu punktów: migracja 2026-10-18_kreator_nagrod_sklep.sql
-- (uruchom ją PO tym pliku).
