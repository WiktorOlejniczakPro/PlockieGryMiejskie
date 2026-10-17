<?php
/**
 * Layout panelu twórcy: górny pasek + menu boczne + treść.
 * Zmienne: $content, $activeNav, $pageTitle.
 * Pozycje oznaczone 'soon' => true są widoczne, ale nieaktywne – trafią
 * do panelu w kolejnych etapach (nie udajemy, że działają).
 */
use App\Core\Auth;
use App\Core\View;
use App\Models\User;

$user = Auth::user();
$activeNav = $activeNav ?? '';
$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard',          'href' => '/panel', 'icon' => 'M4 13h6V4H4v9Zm0 7h6v-5H4v5Zm10 0h6v-9h-6v9Zm0-16v5h6V4h-6Z'],
    ['key' => 'games',     'label' => 'Twoje gry',          'href' => '/panel/gry',      'icon' => 'M4 6h16M4 12h16M4 18h10'],
    ['key' => 'create',    'label' => 'Utwórz nową grę',    'href' => '/panel/gry/nowa', 'icon' => 'M12 5v14M5 12h14'],
    ['key' => 'rewards',   'label' => 'Kreator nagród',     'href' => '/panel/nagrody',  'icon' => 'M20 12v9H4v-9M2 7h20v5H2zM12 22V7M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7Zm0 0h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7Z'],
    ['key' => 'account',   'label' => 'Konto i ustawienia', 'soon' => true,     'icon' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 9a7 7 0 0 1 14 0'],
];
?>
<!DOCTYPE html>
<html lang="pl">
<head>
  <?php View::partial('layouts/head', [
      'pageTitle' => $pageTitle ?? null,
      'styles'    => array_merge(['css/panel.css'], $extraStyles ?? []),
      'scripts'   => $extraScripts ?? [],
  ]); ?>
  <meta name="robots" content="noindex">
</head>
<body class="page-panel">
  <a class="skip-link" href="#main">Przejdź do treści</a>

  <header class="topbar">
    <button type="button" class="topbar__menu" aria-expanded="false" aria-controls="sidebar" data-sidebar-toggle>
      <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
      <span class="visually-hidden">Pokaż menu</span>
    </button>

    <a href="<?= e(url('/panel')) ?>" class="topbar__brand">
      <?php View::partial('partials/logo'); ?>
      <span>PGM <span class="topbar__brand-sub">Panel twórcy</span></span>
    </a>

    <details class="account-menu">
      <summary aria-label="Menu konta: <?= e(User::fullName($user)) ?>">
        <span class="avatar" aria-hidden="true"><?= e(User::initials($user)) ?></span>
        <span class="account-menu__name"><?= e(User::fullName($user)) ?></span>
        <svg class="icon icon--sm" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
      </summary>
      <div class="account-menu__panel">
        <p class="account-menu__who">
          <strong><?= e(User::fullName($user)) ?></strong>
          <span><?= e($user['email']) ?></span>
          <span class="badge badge--role"><?= e(label('role', $user['role'])) ?></span>
        </p>
        <p class="account-menu__org"><?= e($user['organization_name']) ?></p>
        <form method="post" action="<?= e(url('/wyloguj')) ?>">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-outline btn-sm btn-block">Wyloguj</button>
        </form>
      </div>
    </details>
  </header>

  <div class="panel-shell<?= !empty($compactSidebar) ? ' panel-shell--compact' : '' ?>">
    <aside class="sidebar" id="sidebar">
      <nav aria-label="Menu panelu">
        <ul class="sidebar__nav">
          <?php foreach ($navItems as $item): ?>
            <li>
              <?php if (!empty($item['soon'])): ?>
                <span class="sidebar__link is-disabled" aria-disabled="true" title="<?= e($item['label']) ?> – wkrótce">
                  <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="<?= e($item['icon']) ?>"/></svg>
                  <span class="sidebar__label"><?= e($item['label']) ?></span>
                  <span class="soon-tag">wkrótce</span>
                </span>
              <?php else: ?>
                <a class="sidebar__link<?= $activeNav === $item['key'] ? ' is-active' : '' ?>"
                   href="<?= e(url($item['href'])) ?>"
                   title="<?= e($item['label']) ?>"
                   <?= $activeNav === $item['key'] ? 'aria-current="page"' : '' ?>>
                  <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="<?= e($item['icon']) ?>"/></svg>
                  <span class="sidebar__label"><?= e($item['label']) ?></span>
                </a>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <form method="post" action="<?= e(url('/wyloguj')) ?>" class="sidebar__logout">
          <?= csrf_field() ?>
          <button type="submit" class="sidebar__link" title="Wyloguj">
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10"/></svg>
            <span class="sidebar__label">Wyloguj</span>
          </button>
        </form>
      </nav>
    </aside>
    <div class="sidebar-backdrop" data-sidebar-close hidden></div>

    <main class="panel-main <?= e($mainClass ?? '') ?>" id="main">
      <?php if (Auth::autoLoginEnabled()): ?>
        <p class="demo-banner" role="note">
          <strong>Tryb demonstracyjny:</strong> automatyczne logowanie jest włączone (AUTO_LOGIN_EMAIL w pliku .env).
          Wyłącz je przed udostępnieniem panelu innym osobom.
        </p>
      <?php endif; ?>
      <?php View::partial('partials/alerts'); ?>
      <?= $content ?>
    </main>
  </div>
</body>
</html>
