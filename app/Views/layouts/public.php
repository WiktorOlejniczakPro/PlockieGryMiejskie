<?php
/** Layout strony publicznej. $content – treść widoku. */
use App\Core\Auth;
use App\Core\View;
?>
<!DOCTYPE html>
<html lang="pl">
<head>
  <?php View::partial('layouts/head', ['pageTitle' => $pageTitle ?? null, 'styles' => ['css/public.css']]); ?>
  <meta name="description" content="Płockie Gry Miejskie – gry terenowe, dzięki którym mieszkańcy i turyści poznają historię, zabytki i przyrodę Płocka.">
</head>
<body class="page-public">
  <a class="skip-link" href="#main">Przejdź do treści</a>

  <header class="site-header">
    <div class="container header-inner">
      <a href="<?= e(url('/')) ?>" class="brand" aria-label="Płockie Gry Miejskie – strona główna">
        <?php View::partial('partials/logo'); ?>
        <span class="brand-text">Płockie <strong>Gry Miejskie</strong></span>
      </a>

      <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="site-nav" data-nav-toggle>
        <span class="nav-toggle__bar" aria-hidden="true"></span>
        <span class="visually-hidden">Menu</span>
      </button>

      <nav class="site-nav" id="site-nav" aria-label="Nawigacja główna">
        <ul>
          <li><a href="<?= e(url('/')) ?>#top">Strona główna</a></li>
          <li><a href="<?= e(url('/')) ?>#o-projekcie">O projekcie</a></li>
          <li><a href="<?= e(url('/')) ?>#gry">Gry</a></li>
          <li><a href="<?= e(url('/')) ?>#aplikacja">Aplikacja</a></li>
          <li><a class="btn btn-gold btn-sm" href="<?= e(url('/panel')) ?>">Panel twórcy</a></li>
        </ul>
      </nav>
    </div>
  </header>

  <main id="main">
    <?= $content ?>
  </main>

  <footer class="site-footer">
    <div class="container footer-inner">
      <p class="footer-brand">Płockie <strong>Gry Miejskie</strong></p>
      <p>Projekt przygotowany dla Urzędu Miasta Płocka.</p>
      <p class="footer-small">Mapy: © OpenStreetMap · © <?= date('Y') ?> Stardust Crusaders Hackathon</p>
    </div>
  </footer>
</body>
</html>
