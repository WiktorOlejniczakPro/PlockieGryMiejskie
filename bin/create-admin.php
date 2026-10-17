<?php
declare(strict_types=1);

/**
 * Tworzy konto w panelu PGM z wiersza poleceń.
 * Hasło jest wpisywane w konsoli (nie trafia do historii poleceń ani do kodu)
 * i zapisywane w bazie wyłącznie jako hash.
 *
 * Użycie (Windows, XAMPP – w katalogu pgm):
 *   C:\xampp\php\php.exe bin\create-admin.php
 *   C:\xampp\php\php.exe bin\create-admin.php --email=jan.kowalski@plock.eu --first=Jan --last=Kowalski --org="Urząd Miasta Płocka"
 *
 * Konto twórcy gier zamiast administratora:
 *   php bin/create-admin.php --role=creator
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Models\User;
use App\Services\AuditLog;

$options = getopt('', ['email:', 'first:', 'last:', 'org:', 'role:', 'help']);

if (isset($options['help'])) {
    echo "Opcje: --email= --first= --last= --org= --role=admin|creator\n";
    echo "Brakujące dane skrypt zapyta w konsoli. Hasło zawsze wpisuje się ręcznie.\n";
    exit(0);
}

function out(string $text): void
{
    fwrite(STDOUT, $text . PHP_EOL);
}

function fail(string $text): void
{
    fwrite(STDERR, 'BŁĄD: ' . $text . PHP_EOL);
    exit(1);
}

function ask(string $label, ?string $preset = null): string
{
    if ($preset !== null && trim($preset) !== '') {
        return trim($preset);
    }
    fwrite(STDOUT, $label . ': ');
    $line = fgets(STDIN);
    return $line === false ? '' : trim($line);
}

/** Wpisywanie hasła bez wyświetlania znaków (Linux/macOS; w Windows z ostrzeżeniem). */
function askPassword(string $label): string
{
    $isWindows = DIRECTORY_SEPARATOR === '\\';
    $interactive = function_exists('stream_isatty') && stream_isatty(STDIN);

    if ($interactive && !$isWindows) {
        fwrite(STDOUT, $label . ': ');
        shell_exec('stty -echo');
        $line = fgets(STDIN);
        shell_exec('stty echo');
        fwrite(STDOUT, PHP_EOL);
    } else {
        if ($interactive && $isWindows) {
            fwrite(STDOUT, '(uwaga: w konsoli Windows wpisywane hasło będzie widoczne)' . PHP_EOL);
        }
        fwrite(STDOUT, $label . ': ');
        $line = fgets(STDIN);
    }
    return $line === false ? '' : rtrim($line, "\r\n");
}

out('=== PGM – tworzenie konta ===');

$role = $options['role'] ?? 'admin';
if (!in_array($role, ['admin', 'creator'], true)) {
    fail('Rola musi mieć wartość admin albo creator.');
}

try {
    $email = User::normalizeEmail(ask('Adres e-mail', $options['email'] ?? null));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        fail('Nieprawidłowy adres e-mail.');
    }
    if (User::emailExists($email)) {
        fail('Konto z tym adresem e-mail już istnieje.');
    }

    $first = ask('Imię', $options['first'] ?? null);
    $last = ask('Nazwisko', $options['last'] ?? null);
    $org = ask('Organizacja (np. Urząd Miasta Płocka)', $options['org'] ?? null);
    foreach (['Imię' => [$first, 80], 'Nazwisko' => [$last, 80], 'Organizacja' => [$org, 150]] as $name => [$value, $max]) {
        if ($value === '' || mb_strlen($value) > $max) {
            fail("{$name}: pole wymagane, maksymalnie {$max} znaków.");
        }
    }

    $min = (int) config('app.auth.min_password_length', 12);
    $password = askPassword("Hasło (min. {$min} znaków)");
    if (mb_strlen($password) < $min) {
        fail("Hasło musi mieć co najmniej {$min} znaków.");
    }
    if (strlen($password) > 1024) {
        fail('Hasło jest zbyt długie.');
    }
    if ($password !== askPassword('Powtórz hasło')) {
        fail('Hasła nie są identyczne.');
    }

    $id = User::create([
        'organization_name' => $org,
        'first_name'        => $first,
        'last_name'         => $last,
        'email'             => $email,
        'password'          => $password,
        'role'              => $role,
        'account_status'    => 'active',
    ]);
    AuditLog::record('user.created_cli', null, 'user', $id, ['role' => $role]);
} catch (RuntimeException $e) {
    fail($e->getMessage() . ' Sprawdź, czy MySQL działa i czy dane w pliku .env są poprawne.');
}

out('');
out(sprintf('Gotowe! Utworzono konto „%s” (%s) z rolą: %s.', "{$first} {$last}", $email, label('role', $role)));
out('Wpisz ten adres e-mail w pliku .env jako AUTO_LOGIN_EMAIL i otwórz /panel w przeglądarce.');
