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

-- ---------------------------------------------------------------------
-- 20. REWARDS – nagrody dodawane przez administratora w panelu.
--     stock NULL = bez limitu sztuk. is_sample = nagroda demonstracyjna
--     (bez wartości handlowej – pokazywana z dopiskiem „przykład”).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rewards (
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
CREATE TABLE IF NOT EXISTS reward_redemptions (
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

-- nagrody PRZYKŁADOWE (bez wartości handlowej)
INSERT INTO rewards (title, description, reward_type, partner_name, cost_points, stock,
                     redemption_instructions, is_active, is_sample)
VALUES
  ('[PRZYKŁAD] Naklejka PGM', 'Pamiątkowa naklejka Płockich Gier Miejskich.', 'item',
   'Dane demonstracyjne PGM', 10, 50, 'Przykład – nagroda testowa, nie do odbioru.', 1, 1),
  ('[PRZYKŁAD] Kupon 5%', 'Przykładowy kupon zniżkowy do testów sklepu.', 'discount',
   'Dane demonstracyjne PGM', 100, NULL, 'Przykład – kod nie ma wartości handlowej.', 1, 1),
  ('[PRZYKŁAD] Kupon 10%', 'Przykładowy kupon zniżkowy do testów sklepu.', 'discount',
   'Dane demonstracyjne PGM', 200, NULL, 'Przykład – kod nie ma wartości handlowej.', 1, 1),
  ('[PRZYKŁAD] Kupon 15%', 'Przykładowy kupon zniżkowy do testów sklepu.', 'discount',
   'Dane demonstracyjne PGM', 300, NULL, 'Przykład – kod nie ma wartości handlowej.', 1, 1),
  ('[PRZYKŁAD] Kupon 30%', 'Przykładowy kupon zniżkowy do testów sklepu.', 'discount',
   'Dane demonstracyjne PGM', 600, 10, 'Przykład – kod nie ma wartości handlowej.', 1, 1);
