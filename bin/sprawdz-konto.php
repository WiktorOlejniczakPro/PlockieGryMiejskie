<?php
// Diagnostyka: jakie konto panel próbuje załadować i jakie konta są w bazie.
// Uruchom w folderze pgm:  C:\xampp\php\php.exe bin\sprawdz-konto.php
require dirname(__DIR__) . '/app/bootstrap.php';

echo "Plik .env wczytany: " . (App\Core\Env::isLoaded() ? "TAK" : "NIE (brak pliku .env w folderze pgm!)") . PHP_EOL;

$email = config('app.auth.auto_login_email');
if ($email === null) {
    echo "Ustawienie auto_login_email: BRAK w config/app.php (masz starszą wersję pliku config/app.php)" . PHP_EOL;
} else {
    echo "AUTO_LOGIN_EMAIL z .env: [" . $email . "]" . PHP_EOL;
}

echo PHP_EOL . "Konta w bazie '" . config('database.database') . "':" . PHP_EOL;
foreach (App\Core\Database::query('SELECT email, role, account_status FROM users ORDER BY id')->fetchAll() as $u) {
    $match = ($email !== null && mb_strtolower(trim((string) $email)) === $u['email']) ? '   <-- TO KONTO' : '';
    echo " - {$u['email']}  (rola: {$u['role']}, status: {$u['account_status']}){$match}" . PHP_EOL;
}
