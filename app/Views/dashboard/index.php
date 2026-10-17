<?php
/** Dashboard. Zmienne: $user, $counts, $recent. */
use App\Models\Game;

$isAdmin = $user['role'] === 'admin';
$stats = [
    ['key' => 'total',     'label' => $isAdmin ? 'Wszystkie gry' : 'Twoje gry', 'tone' => 'navy'],
    ['key' => 'published', 'label' => 'Opublikowane',                           'tone' => 'green'],
    ['key' => 'draft',     'label' => 'Szkice',                                 'tone' => 'blue'],
    ['key' => 'archived',  'label' => 'Zarchiwizowane',                         'tone' => 'gray'],
];
?>
<div class="page-head">
  <div>
    <h1>Dzień dobry, <?= e($user['first_name']) ?>!</h1>
    <p class="page-head__lead">
      <?= $isAdmin
          ? 'Jako administrator widzisz wszystkie gry w systemie.'
          : 'Tu znajdziesz podsumowanie swoich gier.' ?>
    </p>
  </div>
</div>

<section aria-labelledby="stats-title">
  <h2 id="stats-title" class="visually-hidden">Podsumowanie</h2>
  <ul class="stat-grid">
    <?php foreach ($stats as $stat): ?>
      <li class="stat-card stat-card--<?= e($stat['tone']) ?>">
        <span class="stat-card__value"><?= e($counts[$stat['key']]) ?></span>
        <span class="stat-card__label"><?= e($stat['label']) ?></span>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php if ($counts['hidden'] > 0): ?>
    <p class="muted">Ukryte: <?= e($counts['hidden']) ?></p>
  <?php endif; ?>
</section>

<div class="dash-grid">
  <section class="card" aria-labelledby="recent-title">
    <div class="card__head">
      <h2 id="recent-title">Ostatnio edytowane gry</h2>
    </div>

    <?php if (!$recent): ?>
      <p class="empty-state">Nie ma jeszcze żadnych gier.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th scope="col">Tytuł</th>
              <th scope="col">Status</th>
              <th scope="col">Punkty</th>
              <?php if ($isAdmin): ?><th scope="col">Właściciel</th><?php endif; ?>
              <th scope="col">Ostatnia edycja</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent as $game): ?>
              <tr>
                <td>
                  <?php if ($game['status'] !== 'archived'): ?>
                    <a class="table__title" href="<?= e(url('/panel/gry/' . (int) $game['id'] . '/edycja')) ?>"><?= e(Game::displayTitle($game)) ?></a>
                  <?php else: ?>
                    <span class="table__title"><?= e(Game::displayTitle($game)) ?></span>
                  <?php endif; ?>
                  <?php if ($game['is_sample']): ?><span class="badge badge--sample">Przykład</span><?php endif; ?>
                </td>
                <td><span class="status status--<?= e($game['status']) ?>"><?= e(label('status', $game['status'])) ?></span></td>
                <td><?= e($game['waypoint_count']) ?></td>
                <?php if ($isAdmin): ?><td><?= e($game['owner_name']) ?></td><?php endif; ?>
                <td><?= e(format_datetime($game['updated_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <section class="card" aria-labelledby="shortcuts-title">
    <div class="card__head">
      <h2 id="shortcuts-title">Skróty</h2>
    </div>
    <ul class="shortcut-list">
      <li>
        <a class="shortcut shortcut--primary" href="<?= e(url('/panel/gry/nowa')) ?>">
          <strong>Utwórz nową grę</strong>
          <span>Zaprojektuj trasę na mapie Płocka</span>
        </a>
      </li>
      <li>
        <a class="shortcut" href="<?= e(url('/panel/gry')) ?>">
          <strong>Lista gier</strong>
          <span>Otwórz i edytuj zapisane gry</span>
        </a>
      </li>
      <li>
        <a class="shortcut" href="<?= e(url('/')) ?>">
          <strong>Strona publiczna</strong>
          <span>Zobacz, jak opublikowane gry widzą mieszkańcy</span>
        </a>
      </li>
    </ul>
  </section>
</div>
