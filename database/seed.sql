-- =====================================================================
--  PGM – Płockie Gry Miejskie
--  database/seed.sql  –  dane startowe i DEMONSTRACYJNE
--
--  Importuj PO schema.sql, na świeżo utworzonej bazie.
--
--  Zawartość:
--    1. Słownik kategorii (dane docelowe).
--    2. Systemowe konto demonstracyjne – właściciel gier przykładowych.
--       NIE DA SIĘ na nie zalogować: zamiast hasha hasła ma znacznik
--       "!disabled", którego password_verify() nigdy nie zaakceptuje,
--       a status konta to "suspended". Pierwsze prawdziwe konto
--       administratora tworzy się skryptem CLI (ETAP 3).
--    3. Cztery gry DEMONSTRACYJNE w Płocku (is_sample = 1,
--       tytuł zaczyna się od "[PRZYKŁAD]") w różnych statusach.
--
--  Współrzędne są przybliżone i służą do pokazania działania mapy.
--  Treści zadań to przykłady – przed użyciem w prawdziwej grze
--  należy je zweryfikować.
-- =====================================================================

USE pgm;
SET NAMES utf8mb4;

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1. Kategorie
-- ---------------------------------------------------------------------
INSERT INTO categories (slug, name, position, is_other) VALUES
  ('historyczna',          'Historyczna',          1,  0),
  ('turystyczna',          'Turystyczna',          2,  0),
  ('naukowa',              'Naukowa',              3,  0),
  ('przyrodnicza',         'Przyrodnicza',         4,  0),
  ('sportowa',             'Sportowa',             5,  0),
  ('rodzinna',             'Rodzinna',             6,  0),
  ('ekologiczna',          'Ekologiczna',          7,  0),
  ('edukacyjna',           'Edukacyjna',           8,  0),
  ('kulturalna',           'Kulturalna',           9,  0),
  ('wydarzenie-miejskie',  'Wydarzenie miejskie',  10, 0),
  ('inne',                 'Inne',                 99, 1);

-- ---------------------------------------------------------------------
-- 2. Konto demonstracyjne (bez możliwości logowania)
-- ---------------------------------------------------------------------
INSERT INTO users (organization_name, first_name, last_name, email,
                   password_hash, role, account_status)
VALUES ('Dane demonstracyjne PGM', 'Konto', 'Demonstracyjne',
        'demo@pgm.invalid', '!disabled', 'creator', 'suspended');
SET @demo_owner := LAST_INSERT_ID();

-- =====================================================================
-- 3a. GRA 1 – opublikowana, łatwa, obszar: okrąg
-- =====================================================================
INSERT INTO games (owner_id, title, short_description, description,
                   estimated_duration_minutes, difficulty, status,
                   completion_points, area_geojson, is_sample, published_at)
VALUES (
  @demo_owner,
  '[PRZYKŁAD] Tajemnice Wzgórza Tumskiego',
  'Spacer po najstarszej części Płocka – katedra, zamek i widok na Wisłę.',
  'Gra demonstracyjna. Trasa prowadzi przez Wzgórze Tumskie, gdzie gracze poznają historię Płocka jako dawnej stolicy Polski, szukają detali architektonicznych i wykonują proste zadania.',
  60, 'easy', 'published', 5,
  '{"type":"Feature","geometry":{"type":"Point","coordinates":[19.6866,52.5462]},"properties":{"shape":"circle","radius_m":300}}',
  1, NOW()
);
SET @g1 := LAST_INSERT_ID();

INSERT INTO game_categories (game_id, category_id)
SELECT @g1, id FROM categories WHERE slug IN ('historyczna','turystyczna','rodzinna');

INSERT INTO waypoints (game_id, name, description, player_hint, latitude, longitude, position) VALUES
  (@g1, 'Bazylika Katedralna', 'Romańska katedra na Wzgórzu Tumskim.', 'Szukaj najwyższych wież na wzgórzu.', 52.5466000, 19.6863000, 1);
SET @g1w1 := LAST_INSERT_ID();
INSERT INTO waypoints (game_id, name, description, player_hint, latitude, longitude, position) VALUES
  (@g1, 'Zamek Książąt Mazowieckich', 'Zespół zamkowy z Wieżą Zegarową.', 'Stań przy wieży z zegarem.', 52.5462000, 19.6852000, 2);
SET @g1w2 := LAST_INSERT_ID();
INSERT INTO waypoints (game_id, name, description, player_hint, latitude, longitude, position) VALUES
  (@g1, 'Skarpa nad Wisłą', 'Punkt widokowy na Wisłę.', 'Podejdź do barierki z widokiem na rzekę.', 52.5456000, 19.6874000, 3);
SET @g1w3 := LAST_INSERT_ID();
INSERT INTO waypoints (game_id, name, description, player_hint, latitude, longitude, position) VALUES
  (@g1, 'Wzgórze – punkt końcowy', 'Zakończenie trasy przy wejściu na wzgórze.', NULL, 52.5468000, 19.6880000, 4);
SET @g1w4 := LAST_INSERT_ID();

-- zadanie jednokrotnego wyboru
INSERT INTO tasks (waypoint_id, title, description, task_type, hint, max_points, unlock_mode, unlock_radius_m)
VALUES (@g1w1, 'Królewskie groby',
        'W płockiej katedrze pochowano dwóch władców Polski. Którzy to byli?',
        'single_choice', 'Rządzili, gdy Płock był stolicą kraju.', 2, 'on_arrival', 40);
SET @t := LAST_INSERT_ID();
INSERT INTO task_options (task_id, option_text, is_correct, position) VALUES
  (@t, 'Władysław Herman i Bolesław Krzywousty', 1, 1),
  (@t, 'Mieszko I i Bolesław Chrobry',           0, 2),
  (@t, 'Kazimierz Wielki i Władysław Łokietek',  0, 3);

-- zadanie otwarte
INSERT INTO tasks (waypoint_id, title, description, task_type, hint, max_points,
                   expected_answer, evaluation_instructions, unlock_mode, unlock_radius_m)
VALUES (@g1w2, 'Godzina na wieży',
        'Odczytaj godzinę z zegara na wieży zamkowej i wpisz ją w formacie GG:MM.',
        'open_answer', 'Zegar jest na górnej części wieży.', 1,
        NULL, 'Zaakceptuj każdą godzinę zgodną z czasem przesłania odpowiedzi (±5 minut).',
        'on_arrival', 40);

-- zadanie fotograficzne
INSERT INTO tasks (waypoint_id, title, description, task_type, max_points,
                   media_requirement, evaluation_instructions, unlock_mode, unlock_radius_m)
VALUES (@g1w3, 'Panorama Wisły',
        'Zrób zdjęcie panoramy Wisły ze skarpy.',
        'photo', 2,
        'Jedno zdjęcie w orientacji poziomej, widoczna rzeka.',
        'Zdjęcie musi przedstawiać Wisłę widzianą ze skarpy.',
        'on_arrival', 50);

-- zadanie informacyjne (0 punktów)
INSERT INTO tasks (waypoint_id, title, description, task_type, max_points, unlock_mode)
VALUES (@g1w4, 'Gratulacje!',
        'To koniec trasy. Płock był siedzibą władców Polski w latach 1079–1138.',
        'info', 0, 'after_previous');

INSERT INTO game_status_history (game_id, changed_by, from_status, to_status, note)
VALUES (@g1, NULL, NULL,    'draft',     'Dane demonstracyjne'),
       (@g1, NULL, 'draft', 'published', 'Dane demonstracyjne');

-- =====================================================================
-- 3b. GRA 2 – szkic, średnia, obszar: wielokąt
-- =====================================================================
INSERT INTO games (owner_id, title, short_description, description,
                   estimated_duration_minutes, difficulty, status,
                   completion_points, area_geojson, is_sample)
VALUES (
  @demo_owner,
  '[PRZYKŁAD] Śladami dawnej stolicy',
  'Trasa przez Stary Rynek i ulicę Tumską z zagadkami o historii miasta.',
  'Gra demonstracyjna w wersji roboczej. Pokazuje pytanie wielokrotnego wyboru i zadanie filmowe.',
  90, 'medium', 'draft', 8,
  '{"type":"Feature","geometry":{"type":"Polygon","coordinates":[[[19.6840,52.5475],[19.6975,52.5480],[19.6980,52.5450],[19.6845,52.5448],[19.6840,52.5475]]]},"properties":{"shape":"polygon"}}',
  1
);
SET @g2 := LAST_INSERT_ID();

INSERT INTO game_categories (game_id, category_id)
SELECT @g2, id FROM categories WHERE slug IN ('historyczna','edukacyjna');

INSERT INTO waypoints (game_id, name, description, latitude, longitude, position) VALUES
  (@g2, 'Stary Rynek', 'Plac z ratuszem w centrum Starego Miasta.', 52.5470500, 19.6906000, 1);
SET @g2w1 := LAST_INSERT_ID();
INSERT INTO waypoints (game_id, name, description, latitude, longitude, position) VALUES
  (@g2, 'Ulica Tumska', 'Główny deptak miasta.', 52.5463000, 19.6949000, 2);
SET @g2w2 := LAST_INSERT_ID();
INSERT INTO waypoints (game_id, name, description, latitude, longitude, position) VALUES
  (@g2, 'Lokalizacja 3', NULL, 52.5458000, 19.6915000, 3);

-- zadanie wielokrotnego wyboru
INSERT INTO tasks (waypoint_id, title, description, task_type, hint, max_points, unlock_mode, unlock_radius_m)
VALUES (@g2w1, 'Obiekty Wzgórza Tumskiego',
        'Które z wymienionych obiektów znajdują się na płockim Wzgórzu Tumskim? Zaznacz wszystkie poprawne.',
        'multiple_choice', 'Odwiedzisz je w grze „Tajemnice Wzgórza Tumskiego”.', 3, 'always', 30);
SET @t := LAST_INSERT_ID();
INSERT INTO task_options (task_id, option_text, is_correct, position) VALUES
  (@t, 'Bazylika Katedralna',         1, 1),
  (@t, 'Zamek Książąt Mazowieckich',  1, 2),
  (@t, 'Zamek Królewski na Wawelu',   0, 3),
  (@t, 'Pałac Kultury i Nauki',       0, 4);

-- zadanie filmowe
INSERT INTO tasks (waypoint_id, title, description, task_type, max_points,
                   media_requirement, media_max_seconds, evaluation_instructions, unlock_mode, unlock_radius_m)
VALUES (@g2w2, 'Reportaż z deptaka',
        'Nagraj krótki film, w którym opowiadasz, co najbardziej podoba Ci się na ulicy Tumskiej.',
        'video', 3,
        'Film w orientacji poziomej, bez wizerunku przypadkowych osób na pierwszym planie.', 30,
        'Oceń, czy film powstał na ulicy Tumskiej i zawiera wypowiedź gracza.',
        'on_arrival', 40);

INSERT INTO game_status_history (game_id, changed_by, from_status, to_status, note)
VALUES (@g2, NULL, NULL, 'draft', 'Dane demonstracyjne');

-- =====================================================================
-- 3c. GRA 3 – ukryta, rodzinna/ekologiczna, kategoria „Inne”
-- =====================================================================
INSERT INTO games (owner_id, title, short_description, description,
                   estimated_duration_minutes, difficulty, status,
                   completion_points, custom_category, is_sample, published_at)
VALUES (
  @demo_owner,
  '[PRZYKŁAD] Nad Wisłą – spacer ekologiczny',
  'Rodzinna trasa wzdłuż nadwiślańskich bulwarów z zadaniami przyrodniczymi.',
  'Gra demonstracyjna, tymczasowo ukryta. Przykład użycia własnej kategorii.',
  45, 'easy', 'hidden', 5, 'Spacer z dziećmi', 1, NOW()
);
SET @g3 := LAST_INSERT_ID();

INSERT INTO game_categories (game_id, category_id)
SELECT @g3, id FROM categories WHERE slug IN ('ekologiczna','przyrodnicza','rodzinna','inne');

INSERT INTO waypoints (game_id, name, description, latitude, longitude, position) VALUES
  (@g3, 'Amfiteatr', 'Start przy amfiteatrze nad Wisłą.', 52.5443000, 19.7001000, 1);
SET @g3w1 := LAST_INSERT_ID();
INSERT INTO waypoints (game_id, name, description, latitude, longitude, position) VALUES
  (@g3, 'Molo', 'Molo nad Wisłą.', 52.5433000, 19.7015000, 2);
SET @g3w2 := LAST_INSERT_ID();

INSERT INTO tasks (waypoint_id, title, description, task_type, max_points,
                   expected_answer, evaluation_instructions, unlock_mode, unlock_radius_m)
VALUES (@g3w1, 'Ptasi obserwator',
        'Rozejrzyj się nad rzeką. Wpisz nazwę jednego gatunku ptaka, którego widzisz.',
        'open_answer', 1, NULL,
        'Zaakceptuj każdą rozsądną nazwę gatunku ptaka występującego nad Wisłą.',
        'on_arrival', 50);

INSERT INTO tasks (waypoint_id, title, description, task_type, max_points, unlock_mode)
VALUES (@g3w2, 'Dbajmy o rzekę',
        'Śmieci zostawione nad brzegiem trafiają do Wisły. Zabierz swoje odpady ze sobą.',
        'info', 0, 'on_arrival');

INSERT INTO game_status_history (game_id, changed_by, from_status, to_status, note)
VALUES (@g3, NULL, NULL,        'draft',     'Dane demonstracyjne'),
       (@g3, NULL, 'draft',     'published', 'Dane demonstracyjne'),
       (@g3, NULL, 'published', 'hidden',    'Dane demonstracyjne');

-- =====================================================================
-- 3d. GRA 4 – zarchiwizowana, wydarzenie z terminem dostępności
-- =====================================================================
INSERT INTO games (owner_id, title, short_description, description,
                   estimated_duration_minutes, difficulty, status,
                   completion_points, available_from, available_until,
                   is_sample, published_at, archived_at)
VALUES (
  @demo_owner,
  '[PRZYKŁAD] Wakacyjna gra na Starówce 2026',
  'Krótka gra przygotowana na wakacyjne wydarzenie miejskie.',
  'Gra demonstracyjna pokazująca termin dostępności i status archiwalny.',
  30, 'hard', 'archived', 10,
  '2026-07-01 00:00:00', '2026-08-31 23:59:59',
  1, '2026-07-01 00:00:00', '2026-09-01 00:00:00'
);
SET @g4 := LAST_INSERT_ID();

INSERT INTO game_categories (game_id, category_id)
SELECT @g4, id FROM categories WHERE slug IN ('wydarzenie-miejskie','sportowa');

INSERT INTO waypoints (game_id, name, description, latitude, longitude, position) VALUES
  (@g4, 'Start – Stary Rynek', 'Punkt zbiórki.', 52.5471000, 19.6904000, 1);
SET @g4w1 := LAST_INSERT_ID();

INSERT INTO tasks (waypoint_id, title, description, task_type, max_points, media_requirement, unlock_mode)
VALUES (@g4w1, 'Zdjęcie drużyny',
        'Zrób wspólne zdjęcie drużyny przy ratuszu.',
        'photo', 5, 'Jedno zdjęcie, widoczni wszyscy członkowie drużyny.', 'always');

INSERT INTO game_status_history (game_id, changed_by, from_status, to_status, note)
VALUES (@g4, NULL, NULL,        'draft',     'Dane demonstracyjne'),
       (@g4, NULL, 'draft',     'published', 'Dane demonstracyjne'),
       (@g4, NULL, 'published', 'archived',  'Dane demonstracyjne');

COMMIT;
