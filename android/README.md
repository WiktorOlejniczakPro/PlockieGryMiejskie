# PGM – aplikacja Android

Aplikacja dla graczy Płockich Gier Miejskich: Kotlin + Jetpack Compose (Material 3),
mapa OpenStreetMap (osmdroid), GPS z wbudowanego `LocationManager` (bez usług Google),
połączenie z serwerem PHP przez REST API (`docs/API-android.md`). Pakiet `pl.plock.pgm`, minSdk 26.

## Zakładki

- **Gry** – opublikowane gry z panelu, filtr kategorii, wyszukiwarka.
- **Odkrywaj** – miejsca Płocka z otwartych danych miasta (zabytki, rower miejski, place zabaw…).
- **Sklep** – nagrody z Kreatora nagród (panel); wymiana punktów na kod z puli, „Moje kupony”.
- **Ranking** – suma najlepszych wyników.
- **Profil** – saldo, dzienny limit gier (2), historia, zmiana nicku (raz w roku).

Rozgrywka: punkty trasy po kolei, automatyczne dotarcie po 3 s w promieniu punktu (GPS ≤ 50 m dokładności),
6 typów zadań (wybór, odpowiedź, zdjęcie, film, informacja), prowadzenie do celu w aplikacji po drogach
i ścieżkach rowerowych (OSRM na danych OpenStreetMap, pieszo / rowerem).

## Uruchomienie

1. Serwer: XAMPP z projektem w `C:\xampp\htdocs\pgm` i bazą z `database/schema.sql` + `database/seed.sql`.
2. Android Studio → **Open** → folder `android`. Poczekaj na synchronizację Gradle i uruchom ▶.
   - Alternatywa: w projekcie z szablonu „Empty Activity” (pakiet `pl.plock.pgm`) podmień folder `app/src`
     i dopisz biblioteki z `gradle/libs.versions.toml` (Retrofit, OkHttp, osmdroid, lifecycle-viewmodel-compose).
3. Adres serwera wybiera się sam (`Config.kt`):
   - emulator → `http://10.0.2.2/pgm/public/api/v1/`,
   - telefon na kablu USB → `http://127.0.0.1:8080/...` po poleceniu `adb reverse tcp:8080 tcp:80`
     (najprościej: dwuklik `polacz-telefon.bat` po zainstalowaniu aplikacji),
   - gdy żaden adres nie odpowiada, aplikacja pokazuje ekran pomocy z polem na adres (np. IP komputera w Wi-Fi).

Tryb demo (pozycja ustawiana przytrzymaniem palca na mapie) działa tylko na emulatorze.

## Bezpieczeństwo

Aplikacja nie łączy się z bazą danych i nie zawiera haseł do bazy. Gracz dostaje token przy pierwszym
uruchomieniu (bez logowania). Punkty, limity, poprawność odpowiedzi i wymiany w sklepie liczy wyłącznie serwer.
