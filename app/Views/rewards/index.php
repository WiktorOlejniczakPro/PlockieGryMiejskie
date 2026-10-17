<?php
/** Lista nagród. Zmienne: $rewards, $stats, $canManage. */
use App\Core\View;
use App\Models\Reward;
?>
<div class="page-head">
  <div>
    <h1>Kreator nagród</h1>
    <p class="page-head__lead">Kody rabatowe i karty podarunkowe, które gracze wymieniają na punkty zdobyte w grach.</p>
  </div>
  <?php if ($canManage): ?>
    <a class="btn btn-primary" href="<?= e(url('/panel/nagrody/nowa')) ?>">
      <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
      Dodaj nagrodę
    </a>
  <?php endif; ?>
</div>

<p class="rewards-note" role="note">
  <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v5h1"/></svg>
  <span>Tutaj przygotowujesz katalog nagród i pule kodów. Wymianę punktów na nagrody obsłuży przyszła aplikacja mobilna –
  gracz zobaczy aktywne nagrody, a po wymianie dostanie jeden kod z puli.</span>
</p>

<ul class="stat-grid stat-grid--3">
  <li class="stat-card"><span class="stat-card__value"><?= e($stats['total']) ?></span><span class="stat-card__label">Wszystkie nagrody</span></li>
  <li class="stat-card stat-card--green"><span class="stat-card__value"><?= e($stats['active']) ?></span><span class="stat-card__label">Aktywne (widoczne dla graczy)</span></li>
  <li class="stat-card stat-card--blue"><span class="stat-card__value"><?= e($stats['codes_available']) ?></span><span class="stat-card__label">Dostępne kody</span></li>
</ul>

<?php if (!$rewards): ?>
  <div class="card empty-state empty-state--big">
    <p><strong>Nie ma jeszcze żadnych nagród.</strong></p>
    <p>Dodaj pierwszy kod rabatowy albo kartę podarunkową.</p>
    <?php if ($canManage): ?><a class="btn btn-primary" href="<?= e(url('/panel/nagrody/nowa')) ?>">Dodaj nagrodę</a><?php endif; ?>
  </div>
<?php else: ?>
  <ul class="reward-grid">
    <?php foreach ($rewards as $r): ?>
      <li class="reward-item<?= $r['is_active'] ? '' : ' is-inactive' ?>">
        <?php View::partial('rewards/card', ['r' => $r]); ?>
        <div class="reward-item__meta">
          <div class="reward-item__badges">
            <span class="status <?= $r['is_active'] ? 'status--published' : 'status--archived' ?>"><?= $r['is_active'] ? 'Aktywna' : 'Nieaktywna' ?></span>
          </div>
          <p class="reward-item__codes<?= (int) $r['codes_available'] === 0 ? ' is-empty' : '' ?>">
            Kody: <strong><?= e($r['codes_available']) ?></strong> dostępnych · <?= e($r['codes_redeemed']) ?> wydanych
            <?php if ((int) $r['codes_available'] === 0): ?><br><span>Brak kodów – gracze nie odbiorą tej nagrody</span><?php endif; ?>
          </p>
          <?php if ($canManage): ?>
            <div class="reward-item__actions">
              <a class="btn btn-outline btn-sm" href="<?= e(url('/panel/nagrody/' . (int) $r['id'] . '/edycja')) ?>"
                 aria-label="Edytuj nagrodę: <?= e($r['title']) ?>">Edytuj</a>
              <form method="post" action="<?= e(url('/panel/nagrody/' . (int) $r['id'] . '/status')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline btn-sm"><?= $r['is_active'] ? 'Dezaktywuj' : 'Aktywuj' ?></button>
              </form>
            </div>
          <?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
