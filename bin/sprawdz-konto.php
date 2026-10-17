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

// Tabele potrzebne aplikacji mobilnej i sklepowi (Kreator nagród -> Sklep w aplikacji)
echo PHP_EOL . "Tabele aplikacji i sklepu:" . PHP_EOL;
$missing = 0;
foreach (['players', 'game_sessions', 'places', 'rewards', 'reward_codes', 'reward_redemptions'] as $table) {
    $ok = App\Core\Database::query(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
        [$table]
    )->fetchColumn() > 0;
    $missing += $ok ? 0 : 1;
    echo " - {$table}: " . ($ok ? 'OK' : 'BRAK') . PHP_EOL;
}
if ($missing === 0) {
    $hasCodeColumn = App\Core\Database::query(
        "SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = 'reward_redemptions' AND column_name = 'reward_code_id'"
    )->fetchColumn() > 0;
    if (!$hasCodeColumn) {
        echo "UWAGA: stara wersja tabel nagród – zaimportuj database/migracje/2026-10-18_kreator_nagrod_sklep.sql" . PHP_EOL;
    } else {
        $today = date('Y-m-d');
        $n = App\Core\Database::query(
            'SELECT COUNT(*) FROM rewards WHERE is_active = 1
               AND (valid_from IS NULL OR valid_from <= ?) AND (valid_until IS NULL OR valid_until >= ?)',
            [$today, $today]
        )->fetchColumn();
        echo "Nagrody widoczne teraz w aplikacji: {$n}" . PHP_EOL;
    }
} else {
    echo "UWAGA: brakuje tabel – zaimportuj database/schema.sql i database/seed.sql (albo migracje z database/migracje/)." . PHP_EOL;
}
