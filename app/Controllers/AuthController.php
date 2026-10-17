<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Models\User;
use App\Services\AuditLog;
use App\Services\RateLimiter;

/**
 * Logowanie i wylogowanie twórców gier.
 * Zasady bezpieczeństwa:
 *  - jeden ogólny komunikat przy złym e-mailu LUB haśle (nie zdradzamy, czy konto istnieje),
 *  - weryfikacja hasła trwa podobnie długo także dla nieistniejącego konta,
 *  - limit nieudanych prób na e-mail i na adres IP,
 *  - nowy identyfikator sesji i nowy token CSRF po zalogowaniu.
 */
final class AuthController
{
    private const GENERIC_ERROR = 'Nieprawidłowy adres e-mail lub hasło.';

    public function showLogin(Request $request): void
    {
        View::render('auth/login', ['pageTitle' => 'Logowanie – PGM'], 'layouts/auth');
    }

    public function login(Request $request): void
    {
        $email = $request->string('email');
        $password = (string) $request->input('password', '');

        Session::flashInput(['email' => $email]);

        if ($email === '' || $password === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || mb_strlen($email) > 190 || strlen($password) > 1024) {
            Session::flash('error', 'Podaj poprawny adres e-mail i hasło.');
            redirect('/logowanie');
        }

        $emailHash = RateLimiter::emailHash($email);
        $ip = Request::ip();

        if (RateLimiter::tooManyAttempts($emailHash, $ip)) {
            Session::flash('error', 'Zbyt wiele nieudanych prób logowania. Spróbuj ponownie za kilkanaście minut.');
            redirect('/logowanie');
        }

        $user = User::findForLogin($email);
        // Dla nieistniejącego konta też liczymy hash, żeby czas odpowiedzi nie zdradzał, czy konto istnieje.
        // (hash losowego, nieznanego hasła – służy wyłącznie do wyrównania czasu)
        $hash = $user['password_hash'] ?? '$2y$10$azCraCJRTzTAPClUt.QgJeakDh.FlAyqZnydit.MUIQz8c4RrnqYa';
        $valid = password_verify($password, $hash) && $user !== null;

        if (!$valid) {
            RateLimiter::record($emailHash, $ip, false);
            AuditLog::record('auth.login_failed', $user['id'] ?? null, 'user', $user['id'] ?? null);
            Session::flash('error', self::GENERIC_ERROR);
            redirect('/logowanie');
        }

        // Hasło poprawne – dopiero teraz można bezpiecznie powiedzieć o stanie konta.
        if ($user['account_status'] !== 'active') {
            RateLimiter::record($emailHash, $ip, false);
            Session::flash('error', $user['account_status'] === 'pending'
                ? 'Twoje konto czeka na zatwierdzenie przez administratora.'
                : 'Konto jest zablokowane. Skontaktuj się z administratorem systemu.');
            redirect('/logowanie');
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            User::updatePasswordHash((int) $user['id'], $password);
        }

        RateLimiter::record($emailHash, $ip, true);
        User::recordLogin((int) $user['id']);
        Auth::login(User::findById((int) $user['id']) ?? $user);
        AuditLog::record('auth.login', (int) $user['id'], 'user', (int) $user['id']);

        $intended = Session::get('_intended');
        Session::forget('_intended');
        Session::flashInput([]);

        // Przekierowanie tylko na ścieżki wewnątrz panelu (ochrona przed open redirect).
        $target = (is_string($intended) && preg_match('#^/panel(/[\w\-/]*)?$#', $intended)) ? $intended : '/panel';
        redirect($target);
    }

    public function logout(Request $request): void
    {
        $userId = Auth::id();
        Auth::logout();
        AuditLog::record('auth.logout', $userId, 'user', $userId);
        Session::flash('success', 'Wylogowano pomyślnie.');
        redirect('/logowanie');
    }
}
