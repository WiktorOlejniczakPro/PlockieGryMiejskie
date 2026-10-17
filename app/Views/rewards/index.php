<?php
/**
 * Sklep punktów – nagrody i ostatnie wymiany. Zmienne: $user, $rewards, $redemptions.
 */
use App\Controllers\RewardController;

$statusLabels = ['issued' => 'Wydany', 'used' => 'Wykorzystany', 'cancelled' => 'Anulowany'];
?>
<div class="page-head">
  <div>
    <h1>Nagrody</h1>
    <p class="page-head__lead">
      Sklep punktów w aplikacji mobilnej – gracze wymieniają tu punkty zdobyte w grach.
    </p>
  </div>
  <a class="btn btn-primary" href="<?= e(url('/panel/nagrody/nowa')) ?>">
    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
    Dodaj nagrodę
  </a>
</div>

<section class="card">
  <?php if (!$rewards): ?>
    <div class="empty-state empty-state--big">
      <p><strong>Sklep jest pusty.</strong></p>
      <p>Dodaj pierwszą nagrodę – pojawi się w aplikacji graczy.</p>
      <a class="btn btn-primary" href="<?= e(url('/panel/nagrody/nowa')) ?>">Dodaj nagrodę</a>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <caption class="visually-hidden">Nagrody</caption>
        <thead>
          <tr>
            <th scope="col">Nagroda</th>
            <th scope="col">Rodzaj</th>
            <th scope="col">Koszt</th>
            <th scope="col">Wydano / sztuk</th>
            <th scope="col">Ważna do</th>
            <th scope="col">Stan</th>
            <th scope="col"><span class="visually-hidden">Akcje</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rewards as $r): ?>
            <tr>
              <td>
                <span class="table__title"><?= e($r['title']) ?></span>
                <?php if ($r['is_sample']): ?><span class="badge badge--sample">Przykład</span><?php endif; ?>
                <?php if ($r['partner_name']): ?><span class="table__sub"><?= e($r['partner_name']) ?></span><?php endif; ?>
              </td>
              <td><?= e(RewardController::TYPES[$r['reward_type']] ?? $r['reward_type']) ?></td>
              <td class="nowrap"><strong><?= e($r['cost_points']) ?></strong> pkt</td>
              <td><?= e($r['issued']) ?> / <?= $r['stock'] === null ? 'bez limitu' : e($r['stock']) ?></td>
              <td class="nowrap"><?= e($r['valid_until'] ? format_datetime($r['valid_until']) : 'bezterminowo') ?></td>
              <td>
                <span class="status status--<?= $r['is_active'] ? 'published' : 'hidden' ?>">
                  <?= $r['is_active'] ? 'W sklepie' : 'Wyłączona' ?>
                </span>
              </td>
              <td class="table__actions">
                <a class="btn btn-outline btn-sm" href="<?= e(url('/panel/nagrody/' . (int) $r['id'] . '/edycja')) ?>"
                   aria-label="Edytuj nagrodę: <?= e($r['title']) ?>">Edytuj</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="card" style="margin-top:1.5rem">
  <div class="card__head"><h2>Ostatnie wymiany</h2></div>
  <?php if (!$redemptions): ?>
    <p class="table__muted">Gracze nie wymienili jeszcze żadnych punktów.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <caption class="visually-hidden">Ostatnie wymiany punktów</caption>
        <thead>
          <tr>
            <th scope="col">Kod</th>
            <th scope="col">Nagroda</th>
            <th scope="col">Gracz</th>
            <th scope="col">Koszt</th>
            <th scope="col">Data</th>
            <th scope="col">Status</th>
            <th scope="col"><span class="visually-hidden">Akcje</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($redemptions as $x): ?>
            <tr>
              <td class="nowrap"><code><?= e($x['code']) ?></code></td>
              <td><?= e($x['title']) ?></td>
              <td><?= e($x['nickname']) ?></td>
              <td class="nowrap"><?= e($x['cost_points']) ?> pkt</td>
              <td class="nowrap"><?= e(format_datetime($x['created_at'])) ?></td>
              <td><?= e($statusLabels[$x['status']] ?? $x['status']) ?></td>
              <td class="table__actions">
                <?php if ($x['status'] === 'issued'): ?>
                  <form method="post" action="<?= e(url('/panel/nagrody/wymiany/' . (int) $x['id'] . '/wykorzystana')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline btn-sm">Oznacz jako wykorzystany</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
