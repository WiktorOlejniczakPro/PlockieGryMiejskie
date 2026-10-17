<?php
/** Strona błędu. Zmienne: $status, $message, $pageTitle, $debug (wyjątek tylko w trybie deweloperskim). */
use App\Core\Auth;

$hasUser = false;
try {
    $hasUser = Auth::check();
} catch (\Throwable) {
    // np. brak połączenia z bazą – pokazujemy stronę bez linku do panelu
}
?>
<section class="auth-card auth-card--center" aria-labelledby="error-title">
  <p class="error-code"><?= e($status) ?></p>
  <h1 id="error-title"><?= e($pageTitle) ?></h1>
  <p class="auth-lead"><?= e($message) ?></p>

  <div class="error-actions">
    <?php if ($status === 419): ?>
      <button type="button" class="btn btn-primary" data-history-back>Wróć do formularza</button>
    <?php endif; ?>
    <a class="btn <?= $status === 419 ? 'btn-outline' : 'btn-primary' ?>" href="<?= e(url($hasUser ? '/panel' : '/')) ?>">
      <?= $hasUser ? 'Przejdź do panelu' : 'Strona główna' ?>
    </a>
  </div>

  <?php if ($debug instanceof \Throwable): ?>
    <details class="debug-box" open>
      <summary>Szczegóły (widoczne tylko w trybie deweloperskim)</summary>
      <p><strong><?= e(get_class($debug)) ?>:</strong> <?= e($debug->getMessage()) ?></p>
      <p><?= e($debug->getFile()) ?>:<?= e($debug->getLine()) ?></p>
      <?php if (str_contains($debug->getMessage(), 'bazą danych')): ?>
        <p>Sprawdź: czy MySQL działa w XAMPP, czy baza <code>pgm</code> została zaimportowana
           i czy dane w pliku <code>.env</code> (DB_HOST, DB_NAME, DB_USER, DB_PASS) są poprawne.
           Pełny komunikat jest w <code>storage/logs/php-error.log</code>.</p>
      <?php endif; ?>
    </details>
  <?php endif; ?>
</section>
