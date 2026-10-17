<?php
/** Layout ekranów logowania – wyśrodkowana karta na granatowym tle. */
use App\Core\View;
?>
<!DOCTYPE html>
<html lang="pl">
<head>
  <?php View::partial('layouts/head', ['pageTitle' => $pageTitle ?? null, 'styles' => ['css/auth.css']]); ?>
  <meta name="robots" content="noindex">
</head>
<body class="page-auth">
  <main class="auth-shell" id="main">
    <a href="<?= e(url('/')) ?>" class="auth-brand">
      <?php View::partial('partials/logo'); ?>
      <span>Płockie <strong>Gry Miejskie</strong></span>
    </a>
    <?= $content ?>
    <p class="auth-back"><a href="<?= e(url('/')) ?>">← Wróć na stronę główną</a></p>
  </main>
</body>
</html>
