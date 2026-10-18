-- =====================================================================
--  PGM – dodanie GRACZY PRZYKŁADOWYCH do rankingu (bez kasowania bazy)
--  database/migracje/2026-10-18_gracze_przykladowi.sql
--  phpMyAdmin → baza pgm → Import → ten plik. Uruchom raz.
--  Potrzebne gry przykładowe z seed.sql (gra 1 i zarchiwizowana gra wakacyjna).
-- =====================================================================
USE pgm;
SET NAMES utf8mb4;

SET @g1 := (SELECT id FROM games WHERE title = '[PRZYKŁAD] Tajemnice Wzgórza Tumskiego' LIMIT 1);
SET @g4 := (SELECT id FROM games WHERE title = '[PRZYKŁAD] Wakacyjna gra na Starówce 2026' LIMIT 1);

-- =====================================================================
-- 3f. GRACZE PRZYKŁADOWE do rankingu (dane demonstracyjne)
--     Przykładowe konta (nie są to prawdziwi gracze). Tokeny są losowe przy
--     każdym imporcie, więc nikt nie może grać z tych kont.
--     Wyniki: gra 1 (maks. 10 pkt) i zarchiwizowana gra wakacyjna 4 (maks. 15 pkt).
--     Ranking ogólny = suma najlepszych wyników z gier.
-- =====================================================================
INSERT INTO players (nickname, api_token_hash, created_at) VALUES
  ('Wędrowiec', SHA2(CONCAT(UUID(), RAND()), 256), '2026-07-01 10:00:00'),
  ('Kasztan', SHA2(CONCAT(UUID(), RAND()), 256), '2026-07-02 10:00:00'),
  ('Tumska Ola', SHA2(CONCAT(UUID(), RAND()), 256), '2026-07-03 10:00:00'),
  ('Wiślany Rower', SHA2(CONCAT(UUID(), RAND()), 256), '2026-07-04 10:00:00'),
  ('Bulwarowy Marek', SHA2(CONCAT(UUID(), RAND()), 256), '2026-07-05 10:00:00'),
  ('Skarpa', SHA2(CONCAT(UUID(), RAND()), 256), '2026-07-06 10:00:00'),
  ('Mazowiecki Kot', SHA2(CONCAT(UUID(), RAND()), 256), '2026-07-07 10:00:00'),
  ('Gołąb z Rynku', SHA2(CONCAT(UUID(), RAND()), 256), '2026-07-08 10:00:00'),
  ('Radziwie Team', SHA2(CONCAT(UUID(), RAND()), 256), '2026-07-09 10:00:00'),
  ('Spacerowicz', SHA2(CONCAT(UUID(), RAND()), 256), '2026-07-10 10:00:00'),
  ('Zamkowa Ania', SHA2(CONCAT(UUID(), RAND()), 256), '2026-07-11 10:00:00'),
  ('Nowicjusz', SHA2(CONCAT(UUID(), RAND()), 256), '2026-07-12 10:00:00');

INSERT INTO game_sessions (game_id, player_id, status, score, started_at, finished_at) VALUES
  (@g1, (SELECT id FROM players WHERE nickname = 'Wędrowiec'), 'completed', 10, '2026-10-01 11:00:00', '2026-10-01 12:10:00'),
  (@g4, (SELECT id FROM players WHERE nickname = 'Wędrowiec'), 'completed', 15, '2026-08-03 15:00:00', '2026-08-03 15:20:00'),
  (@g1, (SELECT id FROM players WHERE nickname = 'Kasztan'), 'completed', 10, '2026-10-02 11:00:00', '2026-10-02 12:11:00'),
  (@g4, (SELECT id FROM players WHERE nickname = 'Kasztan'), 'completed', 13, '2026-08-04 15:00:00', '2026-08-04 15:21:00'),
  (@g1, (SELECT id FROM players WHERE nickname = 'Tumska Ola'), 'completed', 9, '2026-10-03 11:00:00', '2026-10-03 12:12:00'),
  (@g4, (SELECT id FROM players WHERE nickname = 'Tumska Ola'), 'completed', 14, '2026-08-05 15:00:00', '2026-08-05 15:22:00'),
  (@g1, (SELECT id FROM players WHERE nickname = 'Wiślany Rower'), 'completed', 10, '2026-10-04 11:00:00', '2026-10-04 12:13:00'),
  (@g1, (SELECT id FROM players WHERE nickname = 'Bulwarowy Marek'), 'completed', 8, '2026-10-05 11:00:00', '2026-10-05 12:14:00'),
  (@g4, (SELECT id FROM players WHERE nickname = 'Bulwarowy Marek'), 'completed', 12, '2026-08-07 15:00:00', '2026-08-07 15:24:00'),
  (@g1, (SELECT id FROM players WHERE nickname = 'Skarpa'), 'completed', 9, '2026-10-06 11:00:00', '2026-10-06 12:15:00'),
  (@g1, (SELECT id FROM players WHERE nickname = 'Mazowiecki Kot'), 'completed', 7, '2026-10-07 11:00:00', '2026-10-07 12:16:00'),
  (@g4, (SELECT id FROM players WHERE nickname = 'Mazowiecki Kot'), 'completed', 11, '2026-08-09 15:00:00', '2026-08-09 15:26:00'),
  (@g1, (SELECT id FROM players WHERE nickname = 'Gołąb z Rynku'), 'completed', 8, '2026-10-08 11:00:00', '2026-10-08 12:17:00'),
  (@g1, (SELECT id FROM players WHERE nickname = 'Radziwie Team'), 'completed', 6, '2026-10-09 11:00:00', '2026-10-09 12:18:00'),
  (@g4, (SELECT id FROM players WHERE nickname = 'Radziwie Team'), 'completed', 10, '2026-08-11 15:00:00', '2026-08-11 15:28:00'),
  (@g1, (SELECT id FROM players WHERE nickname = 'Spacerowicz'), 'completed', 7, '2026-10-10 11:00:00', '2026-10-10 12:19:00'),
  (@g1, (SELECT id FROM players WHERE nickname = 'Zamkowa Ania'), 'completed', 5, '2026-10-11 11:00:00', '2026-10-11 12:20:00'),
  (@g4, (SELECT id FROM players WHERE nickname = 'Zamkowa Ania'), 'completed', 8, '2026-08-13 15:00:00', '2026-08-13 15:30:00'),
  (@g1, (SELECT id FROM players WHERE nickname = 'Nowicjusz'), 'completed', 4, '2026-10-12 11:00:00', '2026-10-12 12:21:00');

