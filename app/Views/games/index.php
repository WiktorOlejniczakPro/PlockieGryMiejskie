<?php
/**
 * Lista gier. Zmienne: $user, $games.
 * Wyszukiwarka, filtry i akcje (publikacja, duplikowanie, archiwizacja)
 * zostaną dodane w kolejnym etapie.
 */
use App\Models\Game;

$isAdmin = $user['role'] === 'admin';
?>
<div class="page-head">
  <div>
    <h1><?= $isAdmin ? 'Wszystkie gry' : 'Twoje gry' ?></h1>
    <p class="page-head__lead">
      <?= e(count($games)) ?> <?= e(plural_pl(count($games), 'gra', 'gry', 'gier')) ?>
      <?= $isAdmin ? 'w systemie' : '' ?>
    </p>
  </div>
  <a class="btn btn-primary" href="<?= e(url('/panel/gry/nowa')) ?>">
    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
    Utwórz nową grę
  </a>
</div>

<section class="card">
  <?php if (!$games): ?>
    <div class="empty-state empty-state--big">
      <p><strong>Nie masz jeszcze żadnej gry.</strong></p>
      <p>Utwórz pierwszą grę i zaznacz jej trasę na mapie Płocka.</p>
      <a class="btn btn-primary" href="<?= e(url('/panel/gry/nowa')) ?>">Utwórz nową grę</a>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <caption class="visually-hidden">Lista gier</caption>
        <thead>
          <tr>
            <th scope="col">Tytuł</th>
            <th scope="col">Status</th>
            <th scope="col">Kategorie</th>
            <th scope="col">Poziom</th>
            <th scope="col">Punkty</th>
            <th scope="col">Czas</th>
            <?php if ($isAdmin): ?><th scope="col">Właściciel</th><?php endif; ?>
            <th scope="col">Ostatnia edycja</th>
            <th scope="col"><span class="visually-hidden">Akcje</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($games as $game): ?>
            <tr>
              <td>
                <span class="table__title"><?= e(Game::displayTitle($game)) ?></span>
                <?php if ($game['is_sample']): ?><span class="badge badge--sample">Przykład</span><?php endif; ?>
                <?php if ($game['short_description']): ?>
                  <span class="table__sub"><?= e($game['short_description']) ?></span>
                <?php endif; ?>
              </td>
              <td><span class="status status--<?= e($game['status']) ?>"><?= e(label('status', $game['status'])) ?></span></td>
              <td class="table__muted"><?= e($game['categories'] ?: '—') ?></td>
              <td><?= e(label('difficulty', $game['difficulty'])) ?></td>
              <td><?= e($game['waypoint_count']) ?></td>
              <td class="nowrap"><?= e(format_duration($game['estimated_duration_minutes'])) ?></td>
              <?php if ($isAdmin): ?><td><?= e($game['owner_name']) ?></td><?php endif; ?>
              <td class="nowrap"><?= e(format_datetime($game['updated_at'])) ?></td>
              <td class="table__actions">
                <?php if ($game['status'] === 'archived'): ?>
                  <span class="table__muted">Tylko do odczytu</span>
                <?php else: ?>
                  <a class="btn btn-outline btn-sm" href="<?= e(url('/panel/gry/' . (int) $game['id'] . '/edycja')) ?>"
                     aria-label="Edytuj grę: <?= e(Game::displayTitle($game)) ?>">Edytuj</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
