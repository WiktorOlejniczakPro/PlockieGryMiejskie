@echo off
rem PGM - polaczenie telefonu na kablu USB z XAMPP na komputerze.
rem Uruchom dwuklikiem PO zainstalowaniu aplikacji z Android Studio
rem (Android Studio przy instalacji potrafi skasowac przekierowanie).
setlocal
set "ADB=%LOCALAPPDATA%\Android\Sdk\platform-tools\adb.exe"
if not exist "%ADB%" (
  echo Nie znaleziono adb: %ADB%
  pause
  exit /b 1
)

echo === 1. XAMPP na komputerze ===
curl -s -o nul -w "localhost/pgm/public/api/v1/categories -> HTTP %%{http_code}  (ma byc 200)\n" http://localhost/pgm/public/api/v1/categories
echo.

echo === 2. Urzadzenia ===
"%ADB%" devices
set FOUND=0
for /f "skip=1 tokens=1,2" %%a in ('"%ADB%" devices') do (
  if "%%b"=="device" (
    echo %%a | findstr /b "emulator-" >nul || (
      set FOUND=1
      echo.
      echo === 3. Telefon %%a: przekierowanie tcp:8080 -^> tcp:80 ===
      "%ADB%" -s %%a reverse tcp:8080 tcp:80
      "%ADB%" -s %%a reverse --list
      echo Otwieram test na telefonie - ma sie pokazac tekst JSON z kategoriami.
      "%ADB%" -s %%a shell am start -a android.intent.action.VIEW -d http://127.0.0.1:8080/pgm/public/api/v1/categories >nul
    )
  )
)
if "%FOUND%"=="0" echo Nie widze telefonu. Wlacz debugowanie USB i zezwol na nie na telefonie.
echo.
echo Teraz w aplikacji kliknij "Szukaj ponownie".
pause
