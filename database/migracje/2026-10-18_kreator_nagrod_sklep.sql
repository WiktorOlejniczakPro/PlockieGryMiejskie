-- =====================================================================
--  PGM – migracja: KREATOR NAGRÓD połączony ze sklepem w aplikacji
--  database/migracje/2026-10-18_kreator_nagrod_sklep.sql
--
--  Dla bazy, która ma już tabele aplikacji mobilnej (players, game_sessions…)
--  z wersji „Etap 2”. Zastępuje proste nagrody sklepu nagrodami z Kreatora
--  (kody rabatowe / karty podarunkowe z pulą kodów).
--  UWAGA: usuwa dotychczasowe nagrody i wymiany graczy (dane testowe)
--  i dodaje 4 nagrody przykładowe. Gier, graczy i wyników nie rusza.
--  Uruchom raz (phpMyAdmin → baza pgm → Import).
-- =====================================================================

USE pgm;
SET NAMES utf8mb4;

DROP TABLE IF EXISTS reward_redemptions;
DROP TABLE IF EXISTS reward_codes;
DROP TABLE IF EXISTS rewards;

-- ---------------------------------------------------------------------
-- 20. REWARDS – katalog nagród wymienianych przez graczy na punkty
--     reward_type:
--       discount_code – kod rabatowy (discount_kind: percent | amount)
--       gift_card     – karta podarunkowa o wartości value_amount (PLN)
--     value_amount    – procent rabatu (1–100), kwota rabatu albo wartość karty w PLN
--     cost_points     – ile punktów gracz musi wydać na nagrodę
--     per_player_limit– ile razy jeden gracz może odebrać tę nagrodę
--     is_sample       – nagroda przykładowa (fikcyjny sklep, kody DEMO-, bez wartości handlowej)
-- ---------------------------------------------------------------------
CREATE TABLE rewards (
  id                INT UNSIGNED       NOT NULL AUTO_INCREMENT,
  created_by        INT UNSIGNED       NULL,
  reward_type       ENUM('discount_code','gift_card') NOT NULL,
  title             VARCHAR(150)       NOT NULL,
  partner_name      VARCHAR(120)       NOT NULL,
  partner_url       VARCHAR(255)       NULL,
  description       TEXT               NULL,
  discount_kind     ENUM('percent','amount') NULL,
  value_amount      DECIMAL(10,2)      NOT NULL,
  min_order_amount  DECIMAL(10,2)      NULL,
  cost_points       INT UNSIGNED       NOT NULL,
  per_player_limit  SMALLINT UNSIGNED  NOT NULL DEFAULT 1,
  terms             TEXT               NULL,
  valid_from        DATE               NULL,
  valid_until       DATE               NULL,
  is_active         TINYINT(1)         NOT NULL DEFAULT 1,
  is_sample         TINYINT(1)         NOT NULL DEFAULT 0,
  created_at        DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_rewards_active (is_active, reward_type, cost_points),
  KEY idx_rewards_created_by (created_by),
  CONSTRAINT chk_rewards_title    CHECK (CHAR_LENGTH(TRIM(title)) > 0),
  CONSTRAINT chk_rewards_partner  CHECK (CHAR_LENGTH(TRIM(partner_name)) > 0),
  CONSTRAINT chk_rewards_cost     CHECK (cost_points BETWEEN 1 AND 100000),
  CONSTRAINT chk_rewards_value    CHECK (value_amount > 0),
  CONSTRAINT chk_rewards_kind     CHECK ((reward_type = 'gift_card' AND discount_kind IS NULL)
                                         OR (reward_type = 'discount_code' AND discount_kind IS NOT NULL)),
  CONSTRAINT chk_rewards_percent  CHECK (discount_kind IS NULL OR discount_kind <> 'percent' OR value_amount <= 100),
  CONSTRAINT chk_rewards_limit    CHECK (per_player_limit BETWEEN 1 AND 100),
  CONSTRAINT chk_rewards_dates    CHECK (valid_from IS NULL OR valid_until IS NULL OR valid_until >= valid_from),
  CONSTRAINT chk_rewards_flags    CHECK (is_active IN (0,1) AND is_sample IN (0,1)),
  CONSTRAINT fk_rewards_created_by
    FOREIGN KEY (created_by) REFERENCES users (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- ---------------------------------------------------------------------
-- 21. REWARD_CODES – pula kodów (rabatowych lub numerów kart) do nagrody
--     Każdy kod trafia do jednego gracza: przy wymianie w aplikacji serwer
--     zmienia status na "redeemed". Kody NIGDY nie trafiają do publicznego
--     API – gracz widzi tylko kod, który sam odebrał.
-- ---------------------------------------------------------------------
CREATE TABLE reward_codes (
  id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  reward_id    INT UNSIGNED  NOT NULL,
  code         VARCHAR(64)   NOT NULL,
  status       ENUM('available','redeemed') NOT NULL DEFAULT 'available',
  redeemed_at  DATETIME      NULL,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_reward_codes (reward_id, code),
  KEY idx_reward_codes_status (reward_id, status),
  CONSTRAINT chk_reward_codes_code CHECK (CHAR_LENGTH(TRIM(code)) > 0),
  CONSTRAINT fk_reward_codes_reward
    FOREIGN KEY (reward_id) REFERENCES rewards (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- ---------------------------------------------------------------------
-- 22. REWARD_REDEMPTIONS – wymiany punktów na nagrody (z aplikacji).
--     reward_code_id – kod z puli wydany graczowi (jeden kod = jedna wymiana).
--     cost_points – koszt w chwili wymiany (zmiana ceny nie zmienia historii).
--     idempotency_key – ten sam klucz od aplikacji = ta sama wymiana
--     (ponowione żądanie nie pobierze punktów drugi raz).
--     status: issued – wydana, used – oznaczona w panelu jako wykorzystana,
--     cancelled – anulowana (punkty wracają do salda).
-- ---------------------------------------------------------------------
CREATE TABLE reward_redemptions (
  id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  reward_id        INT UNSIGNED  NOT NULL,
  reward_code_id   INT UNSIGNED  NOT NULL,
  player_id        INT UNSIGNED  NOT NULL,
  cost_points      INT UNSIGNED  NOT NULL,
  status           ENUM('issued','used','cancelled') NOT NULL DEFAULT 'issued',
  idempotency_key  CHAR(36)      NULL,
  created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  used_at          DATETIME      NULL,

  PRIMARY KEY (id),
  UNIQUE KEY uq_redemptions_code (reward_code_id),
  UNIQUE KEY uq_redemptions_idempotency (player_id, idempotency_key),
  KEY idx_redemptions_player (player_id, created_at),
  KEY idx_redemptions_reward (reward_id, player_id, status),
  CONSTRAINT fk_redemptions_reward
    FOREIGN KEY (reward_id) REFERENCES rewards (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_redemptions_code
    FOREIGN KEY (reward_code_id) REFERENCES reward_codes (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_redemptions_player
    FOREIGN KEY (player_id) REFERENCES players (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

START TRANSACTION;

-- Przykładowe nagrody (is_sample = 1). Sklepy są FIKCYJNE – oznaczone „(przykład)”.
-- Kody zaczynają się od „DEMO-” i nie są prawdziwymi kodami rabatowymi.
INSERT INTO rewards (reward_type, title, partner_name, description, discount_kind, value_amount,
                     min_order_amount, cost_points, per_player_limit, terms, valid_until, is_active, is_sample)
VALUES ('discount_code', 'Rabat 10% na książki', 'Księgarnia przy Tumskiej (przykład)',
        'Zniżka na wszystkie książki o historii Płocka i Mazowsza.', 'percent', 10.00,
        50.00, 20, 1, 'Kod jednorazowy. Nie łączy się z innymi promocjami.', '2026-12-31', 1, 1);
SET @r1 := LAST_INSERT_ID();
INSERT INTO reward_codes (reward_id, code) VALUES
  (@r1, 'DEMO-KSIAZKI-0001'), (@r1, 'DEMO-KSIAZKI-0002'), (@r1, 'DEMO-KSIAZKI-0003'),
  (@r1, 'DEMO-KSIAZKI-0004'), (@r1, 'DEMO-KSIAZKI-0005');

INSERT INTO rewards (reward_type, title, partner_name, description, discount_kind, value_amount,
                     min_order_amount, cost_points, per_player_limit, terms, is_active, is_sample)
VALUES ('discount_code', '15 zł zniżki na sprzęt sportowy', 'Sklep sportowy Nad Wisłą (przykład)',
        'Rabat kwotowy na rowery, akcesoria i odzież sportową.', 'amount', 15.00,
        100.00, 40, 1, 'Minimalna wartość zakupów: 100 zł.', 1, 1);
SET @r2 := LAST_INSERT_ID();
INSERT INTO reward_codes (reward_id, code) VALUES
  (@r2, 'DEMO-SPORT-A1'), (@r2, 'DEMO-SPORT-A2'), (@r2, 'DEMO-SPORT-A3');

INSERT INTO rewards (reward_type, title, partner_name, description, value_amount,
                     cost_points, per_player_limit, terms, is_active, is_sample)
VALUES ('gift_card', 'Karta podarunkowa 50 zł', 'Kawiarnia Na Skarpie (przykład)',
        'Karta do wykorzystania na kawę, ciasta i lody z widokiem na Wisłę.', 50.00,
        60, 2, 'Karta ważna 6 miesięcy od wydania.', 1, 1);
SET @r3 := LAST_INSERT_ID();
INSERT INTO reward_codes (reward_id, code) VALUES (@r3, 'DEMO-KAWA-5001'), (@r3, 'DEMO-KAWA-5002');

INSERT INTO rewards (reward_type, title, partner_name, description, value_amount,
                     cost_points, per_player_limit, terms, is_active, is_sample)
VALUES ('gift_card', 'Karta podarunkowa 100 zł do kina', 'Kino Przykładowe (przykład)',
        'Bilety i przekąski w kinie. Nieaktywna – czeka na dostarczenie kodów.', 100.00,
        120, 1, NULL, 0, 1);

COMMIT;
