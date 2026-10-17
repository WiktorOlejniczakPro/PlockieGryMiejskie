-- =====================================================================
-- PGM – migracja: usunięcie statusu „Ukryta” (hidden)
-- Gry ukryte stają się szkicami (szkic też jest niewidoczny dla graczy).
-- Uruchom raz w phpMyAdmin na istniejącej bazie (zakładka SQL).
-- =====================================================================
SET NAMES utf8mb4;

UPDATE games SET status = 'draft' WHERE status = 'hidden';
UPDATE game_status_history SET from_status = 'draft' WHERE from_status = 'hidden';
UPDATE game_status_history SET to_status   = 'draft' WHERE to_status   = 'hidden';

ALTER TABLE games
  MODIFY status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft';
ALTER TABLE game_status_history
  MODIFY from_status ENUM('draft','published','archived') NULL,
  MODIFY to_status   ENUM('draft','published','archived') NOT NULL;
