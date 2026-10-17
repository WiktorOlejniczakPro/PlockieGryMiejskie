# PGM – API dla aplikacji mobilnej (Android)

Adres bazowy: `{APP_URL}/api/v1`, np. `http://localhost/pgm/public/api/v1`.
Z emulatora Androida komputer z XAMPP jest widoczny jako `http://10.0.2.2/pgm/public/api/v1`.

Wszystkie odpowiedzi są w JSON (UTF-8):

```json
{ "data": { ... } }                                   // sukces
{ "error": { "status": 422, "message": "Opis błędu dla gracza." } }   // błąd
```

Komunikat `message` jest po polsku i można go pokazać graczowi bez zmian.

## Gracz bez logowania

Aplikacja przy **pierwszym uruchomieniu** wywołuje `POST /players`, zapamiętuje otrzymany `token`
i dołącza go do każdego kolejnego żądania:

```
Authorization: Bearer 73a19ac82035d1d744a15a8f667f0a98befb794ce8d62c99335c5e1b0af04c30
```

Gracz nie widzi ekranu logowania – jest „zalogowany na stałe” na danym telefonie.
Token jest zwracany tylko raz (w bazie przechowywany jest wyłącznie jego skrót SHA-256).
Odinstalowanie aplikacji = nowe konto. API gracza nie używa ciasteczek ani tokenów CSRF.

## Endpointy

| Metoda | Adres | Token | Opis |
|---|---|---|---|
| GET | `/categories` | – | kategorie gier `[{slug, name}]` |
| GET | `/games?category=&difficulty=&q=` | – | lista opublikowanych gier |
| GET | `/games/{id}` | – | gra z punktami i zadaniami (bez poprawnych odpowiedzi) |
| GET | `/games/{id}/ranking` | – | ranking gry (najlepszy ukończony wynik gracza) |
| GET | `/ranking` | – | ranking ogólny (suma najlepszych wyników) |
| POST | `/players` | – | nowe konto `{nickname?}` → `{player, token}` (201) |
| GET | `/me` | ✔ | `{player, history}` |
| PUT | `/me` | ✔ | zmiana nicku `{nickname}` |
| POST | `/games/{id}/sessions` | ✔ | start gry lub wznowienie trwającej → stan rozgrywki (201) |
| GET | `/sessions/{id}` | ✔ | stan rozgrywki |
| POST | `/sessions/{id}/waypoints/{waypointId}/arrival` | ✔ | „jestem na miejscu” `{latitude, longitude, accuracy_m?}` |
| POST | `/sessions/{id}/tasks/{taskId}/answer` | ✔ | odpowiedź na zadanie (opis niżej) |

Filtry listy gier: `category` – slug kategorii (np. `historyczna`), `difficulty` – `easy` / `medium` / `hard`,
`q` – fragment tytułu lub krótkiego opisu.

## Gra – `GET /games/{id}`

```json
{
  "data": {
    "id": 1,
    "title": "Tajemnice Wzgórza Tumskiego",
    "short_description": "…",
    "description": "…",
    "difficulty": "easy",
    "estimated_duration_minutes": 60,
    "completion_points": 5,
    "max_score": 10,
    "is_sample": true,
    "categories": [{ "slug": "historyczna", "name": "Historyczna" }],
    "waypoint_count": 4,
    "route_length_m": 389,
    "start": { "latitude": 52.5466, "longitude": 19.6863 },
    "area": { "type": "Feature", "geometry": { "type": "Point", "coordinates": [19.687, 52.5462] },
              "properties": { "shape": "circle", "radius_m": 250 } },
    "waypoints": [
      {
        "id": 1, "position": 1, "name": "Bazylika Katedralna",
        "description": "…", "player_hint": "…",
        "latitude": 52.5466, "longitude": 19.6863,
        "arrival_radius_m": 40,
        "task": {
          "id": 1, "task_type": "single_choice", "title": "…", "description": "…", "hint": "…",
          "max_points": 2, "media_requirement": "", "media_max_seconds": null,
          "unlock_mode": "on_arrival",
          "options": [{ "id": 1, "text": "Władysław Herman i Bolesław Krzywousty" }, { "id": 2, "text": "…" }]
        }
      }
    ]
  }
}
```

- `route_length_m` – długość **w linii prostej** między punktami (nie trasa piesza).
- `area` – opcjonalny obszar gry (GeoJSON `Feature`; okrąg = `Point` + `properties.radius_m`).
- `task` = `null`, gdy punkt nie ma zadania (wystarczy do niego dotrzeć).
- `task_type`: `single_choice`, `multiple_choice`, `open_answer`, `photo`, `video`, `info`.
- `unlock_mode`: `always` (od razu), `on_arrival` (po dotarciu), `after_previous` (po ukończeniu poprzedniego punktu).

## Stan rozgrywki (odpowiedź start / sessions / arrival / answer)

```json
{
  "data": {
    "id": 7, "game_id": 1, "status": "in_progress",
    "score": 3, "task_points": 3, "completion_points": 5, "max_score": 10,
    "completed_waypoints": 2, "total_waypoints": 4, "next_waypoint_id": 3,
    "started_at": "2026-10-17 14:17:52", "finished_at": null,
    "waypoints": [
      {
        "waypoint_id": 1, "position": 1, "reached": true, "reached_at": "…", "completed": true,
        "task": {
          "task_id": 1, "available": true,
          "submission": {
            "is_correct": true, "points_awarded": 2, "max_points": 2, "review_status": "auto",
            "submitted_at": "…", "correct_option_ids": [1], "selected_option_ids": [1]
          }
        }
      }
    ]
  }
}
```

- Punkt jest **ukończony** (`completed`), gdy gracz do niego dotarł (punkt bez zadania) albo odpowiedział na zadanie.
- Po ukończeniu wszystkich punktów `status` = `completed`, a do wyniku dochodzi `completion_points`.
- `review_status`: `auto` – oceniono automatycznie; `unverified` – zaliczone bez sprawdzania
  (zdjęcie, film, odpowiedź otwarta bez wzorca); `rejected` – odrzucone przez organizatora (przyszła funkcja panelu).

## Dotarcie do punktu

`POST /sessions/{id}/waypoints/{waypointId}/arrival`

```json
{ "latitude": 52.54680, "longitude": 19.68650, "accuracy_m": 8 }
```

Serwer liczy odległość od punktu i przyjmuje zgłoszenie, gdy
`odległość ≤ arrival_radius_m + min(accuracy_m, PLAYER_GPS_TOLERANCE_MAX_M)`.
W przeciwnym razie 422 z komunikatem, np. „Jesteś jeszcze za daleko od punktu „Bazylika Katedralna” (ok. 120 m, potrzeba najwyżej 50 m)”.
Ponowne zgłoszenie tego samego punktu nie jest błędem.

## Odpowiedź na zadanie

`POST /sessions/{id}/tasks/{taskId}/answer` – jedna próba na zadanie (ponowna → 409).

| Typ | Treść żądania | Ocena |
|---|---|---|
| `single_choice` | JSON `{"option_ids": [2]}` – dokładnie jedna opcja | automatyczna |
| `multiple_choice` | JSON `{"option_ids": [1, 3]}` | automatyczna, wszystkie poprawne = punkty |
| `open_answer` | JSON `{"answer_text": "…"}` | porównanie z oczekiwaną odpowiedzią (bez wielkości liter i polskich znaków) albo zaliczenie bez wzorca |
| `photo` | `multipart/form-data`: plik `media` (JPEG/PNG/WebP), opcjonalnie `answer_text` | zaliczone po przesłaniu |
| `video` | `multipart/form-data`: plik `media` (MP4/3GP/WebM/MOV), opcjonalnie `answer_text` | zaliczone po przesłaniu |
| `info` | JSON `{}` – potwierdzenie przeczytania | 0 punktów |

Rozmiar pliku: najwyżej `PLAYER_UPLOAD_MAX_MB` (domyślnie 30 MB). Wartość musi być mniejsza niż
`upload_max_filesize` i `post_max_size` w `php.ini` (XAMPP: 40M).

## Kody błędów

| Kod | Kiedy |
|---|---|
| 401 | brak / zły token gracza |
| 403 | zadanie jeszcze zablokowane (nie dotarłeś / poprzedni punkt nieukończony) |
| 404 | gra nieopublikowana lub nie istnieje, cudza rozgrywka, punkt / zadanie z innej gry |
| 409 | zadanie już wykonane, rozgrywka zakończona |
| 413 | za duży plik |
| 422 | błędne dane (za daleko od punktu, brak odpowiedzi, zły typ pliku, zły nick) |
| 429 | za dużo nowych kont z jednego adresu IP |
