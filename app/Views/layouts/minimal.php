<?php
/** Layout stron błędów – działa niezależnie od stanu logowania. */
use App\Core\View;
?>
<!DOCTYPE html>
<html lang="pl">
<head>
  <?php View::partial('layouts/head', ['pageTitle' => ($pageTitle ?? 'Błąd') . ' – PGM', 'styles' => ['css/auth.css']]); ?>
  <meta name="robots" content="noindex">
</head>
<body class="page-auth">
  <main class="auth-shell" id="main">
    <a href="<?= e(url('/')) ?>" class="auth-brand">
      <?php View::partial('partials/logo'); ?>
      <span>Płockie <strong>Gry Miejskie</strong></span>
    </a>
    <?= $content ?>
  </main>
</body>
</html>
