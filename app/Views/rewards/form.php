<?php
/**
 * Kreator nagrody (tworzenie i edycja).
 * Zmienne: $reward (null = nowa), $values (wartości pól), $errors ['pole' => komunikat], $codes.
 */
use App\Core\View;
use App\Models\Reward;

$isNew = $reward === null;
$v = static function (string $key) use ($values): string {
    $val = $values[$key] ?? '';
    if (is_string($val) && preg_match('/^\d+\.00$/', $val)) {
        $val = substr($val, 0, -3); // 15.00 -> 15
    }
    return (string) $val;
};
$err = static fn (string $key): ?string => $errors[$key] ?? null;
$type = $v('reward_type') === 'gift_card' ? 'gift_card' : 'discount_code';
$kind = $v('discount_kind') === 'amount' ? 'amount' : 'percent';
$action = $isNew ? url('/panel/nagrody') : url('/panel/nagrody/' . (int) $reward['id']);

/** Pole z etykietą, podpowiedzią i komunikatem błędu. */
$fieldError = static function (string $key) use ($err): string {
    return $err($key) ? '<p class="field__error" id="err-' . e($key) . '">' . e($err($key)) . '</p>' : '';
};
$aria = static fn (string $key): string => $err($key) ? ' aria-invalid="true" aria-describedby="err-' . e($key) . '"' : '';
?>
<div class="page-head">
  <div>
    <a class="editor-back" href="<?= e(url('/panel/nagrody')) ?>">← Kreator nagród</a>
    <h1><?= $isNew ? 'Nowa nagroda' : 'Edycja nagrody' ?></h1>
    <?php if (!$isNew): $app = Reward::appVisibility($reward); ?>
      <p class="reward-item__app<?= $app['visible'] ? '' : ' is-hidden' ?>">📱 <?= e($app['label']) ?></p>
    <?php endif; ?>
  </div>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error" role="alert">
    Popraw zaznaczone pola (<?= count($errors) ?>) i zapisz ponownie.
  </div>
<?php endif; ?>

<div class="reward-layout">
  <form method="post" action="<?= e($action) ?>" class="reward-form" id="reward-form" novalidate>
    <?= csrf_field() ?>
    <?php if (!$isNew): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>

    <!-- Krok 1: rodzaj -->
    <section class="card wizard-step" aria-labelledby="step1">
      <h2 id="step1"><span class="step-num">1</span> Rodzaj nagrody</h2>
      <fieldset class="type-choice">
        <legend class="visually-hidden">Rodzaj nagrody</legend>
        <label class="type-option">
          <input type="radio" name="reward_type" value="discount_code" <?= $type === 'discount_code' ? 'checked' : '' ?> data-reward-type>
          <span class="type-option__box">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 15 15 9M9.5 9.5h.01M14.5 14.5h.01M4 7V5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v2a2 2 0 0 0 0 4v2a2 2 0 0 0 0 4v2a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-2a2 2 0 0 0 0-4V11a2 2 0 0 0 0-4Z"/></svg>
            <strong>Kod rabatowy</strong>
            <span>Zniżka procentowa lub kwotowa w sklepie</span>
          </span>
        </label>
        <label class="type-option">
          <input type="radio" name="reward_type" value="gift_card" <?= $type === 'gift_card' ? 'checked' : '' ?> data-reward-type>
          <span class="type-option__box">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 12v9H4v-9M2 7h20v5H2zM12 22V7M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7Zm0 0h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7Z"/></svg>
            <strong>Karta podarunkowa</strong>
            <span>Bon o określonej wartości w złotówkach</span>
          </span>
        </label>
      </fieldset>
      <?= $fieldError('reward_type') ?>
    </section>

    <!-- Krok 2: sklep i opis -->
    <section class="card wizard-step" aria-labelledby="step2">
      <h2 id="step2"><span class="step-num">2</span> Sklep i opis</h2>
      <div class="field<?= $err('partner_name') ? ' has-error' : '' ?>">
        <label for="f-partner">Sklep / partner <span class="req" aria-hidden="true">*</span></label>
        <input type="text" id="f-partner" name="partner_name" maxlength="120" required value="<?= e($v('partner_name')) ?>"
               placeholder="np. Księgarnia przy Tumskiej" data-preview-input="partner_name"<?= $aria('partner_name') ?>>
        <?= $fieldError('partner_name') ?>
      </div>
      <div class="field<?= $err('title') ? ' has-error' : '' ?>">
        <label for="f-title">Nazwa nagrody <span class="req" aria-hidden="true">*</span></label>
        <input type="text" id="f-title" name="title" maxlength="150" required value="<?= e($v('title')) ?>"
               placeholder="np. Rabat 10% na książki" data-preview-input="title"<?= $aria('title') ?>>
        <?= $fieldError('title') ?>
      </div>
      <div class="field<?= $err('description') ? ' has-error' : '' ?>">
        <label for="f-desc">Opis dla gracza</label>
        <textarea id="f-desc" name="description" rows="3" maxlength="2000"
                  placeholder="Na co można wykorzystać nagrodę?"<?= $aria('description') ?>><?= e($v('description')) ?></textarea>
        <?= $fieldError('description') ?>
      </div>
      <div class="field<?= $err('partner_url') ? ' has-error' : '' ?>">
        <label for="f-url">Strona sklepu <span class="optional">(opcjonalnie)</span></label>
        <input type="url" id="f-url" name="partner_url" maxlength="255" value="<?= e($v('partner_url')) ?>"
               placeholder="https://"<?= $aria('partner_url') ?>>
        <?= $fieldError('partner_url') ?>
      </div>
    </section>

    <!-- Krok 3: wartość -->
    <section class="card wizard-step" aria-labelledby="step3">
      <h2 id="step3"><span class="step-num">3</span> Wartość nagrody</h2>

      <fieldset class="segmented" data-only-for="discount_code">
        <legend>Rodzaj rabatu</legend>
        <label><input type="radio" name="discount_kind" value="percent" <?= $kind === 'percent' ? 'checked' : '' ?> data-discount-kind> Procentowy (%)</label>
        <label><input type="radio" name="discount_kind" value="amount" <?= $kind === 'amount' ? 'checked' : '' ?> data-discount-kind> Kwotowy (zł)</label>
      </fieldset>
      <?= $fieldError('discount_kind') ?>

      <div class="field-row">
        <div class="field<?= $err('value_amount') ? ' has-error' : '' ?>">
          <label for="f-value" data-value-label>
            <?= $type === 'gift_card' ? 'Wartość karty (zł)' : ($kind === 'percent' ? 'Wysokość rabatu (%)' : 'Kwota rabatu (zł)') ?>
            <span class="req" aria-hidden="true">*</span>
          </label>
          <input type="text" id="f-value" name="value_amount" inputmode="decimal" required value="<?= e($v('value_amount')) ?>"
                 placeholder="np. 10" data-preview-input="value_amount"<?= $aria('value_amount') ?>>
          <?= $fieldError('value_amount') ?>
        </div>
        <div class="field<?= $err('min_order_amount') ? ' has-error' : '' ?>" data-only-for="discount_code">
          <label for="f-min">Minimalne zakupy (zł) <span class="optional">(opcjonalnie)</span></label>
          <input type="text" id="f-min" name="min_order_amount" inputmode="decimal" value="<?= e($v('min_order_amount')) ?>"
                 placeholder="np. 50"<?= $aria('min_order_amount') ?>>
          <?= $fieldError('min_order_amount') ?>
        </div>
      </div>
    </section>

    <!-- Krok 4: koszt i warunki -->
    <section class="card wizard-step" aria-labelledby="step4">
      <h2 id="step4"><span class="step-num">4</span> Koszt w punktach i warunki</h2>
      <div class="field-row">
        <div class="field<?= $err('cost_points') ? ' has-error' : '' ?>">
          <label for="f-cost">Koszt (punkty) <span class="req" aria-hidden="true">*</span></label>
          <input type="number" id="f-cost" name="cost_points" min="1" max="100000" required value="<?= e($v('cost_points')) ?>"
                 inputmode="numeric" data-preview-input="cost_points"<?= $aria('cost_points') ?>>
          <p class="field__hint">Za ukończenie gry gracz dostaje zwykle 5–10 punktów plus punkty za zadania.</p>
          <?= $fieldError('cost_points') ?>
        </div>
        <div class="field<?= $err('per_player_limit') ? ' has-error' : '' ?>">
          <label for="f-limit">Limit na gracza</label>
          <input type="number" id="f-limit" name="per_player_limit" min="1" max="100" value="<?= e($v('per_player_limit')) ?>"
                 inputmode="numeric"<?= $aria('per_player_limit') ?>>
          <p class="field__hint">Ile razy jeden gracz może odebrać tę nagrodę.</p>
          <?= $fieldError('per_player_limit') ?>
        </div>
      </div>
      <div class="field-row">
        <div class="field<?= $err('valid_from') ? ' has-error' : '' ?>">
          <label for="f-from">Ważna od <span class="optional">(opcjonalnie)</span></label>
          <input type="date" id="f-from" name="valid_from" value="<?= e($v('valid_from')) ?>"<?= $aria('valid_from') ?>>
          <?= $fieldError('valid_from') ?>
        </div>
        <div class="field<?= $err('valid_until') ? ' has-error' : '' ?>">
          <label for="f-until">Ważna do <span class="optional">(opcjonalnie)</span></label>
          <input type="date" id="f-until" name="valid_until" value="<?= e($v('valid_until')) ?>"
                 data-preview-input="valid_until"<?= $aria('valid_until') ?>>
          <?= $fieldError('valid_until') ?>
        </div>
      </div>
      <div class="field<?= $err('terms') ? ' has-error' : '' ?>">
        <label for="f-terms">Warunki wykorzystania</label>
        <textarea id="f-terms" name="terms" rows="2" maxlength="2000"
                  placeholder="np. Kod jednorazowy, nie łączy się z innymi promocjami."<?= $aria('terms') ?>><?= e($v('terms')) ?></textarea>
        <?= $fieldError('terms') ?>
      </div>
      <label class="check">
        <input type="checkbox" name="is_active" value="1" <?= !empty($values['is_active']) ? 'checked' : '' ?>>
        <span>Nagroda aktywna – widoczna dla graczy w katalogu</span>
      </label>
    </section>

    <?php if ($isNew): ?>
      <!-- Krok 5: kody (przy tworzeniu) -->
      <section class="card wizard-step" aria-labelledby="step5">
        <h2 id="step5"><span class="step-num">5</span> Kody <span class="optional">(możesz dodać później)</span></h2>
        <div class="field<?= $err('codes') ? ' has-error' : '' ?>">
          <label for="f-codes">Kody rabatowe lub numery kart – każdy w osobnej linii</label>
          <textarea id="f-codes" name="codes" rows="5" class="mono" placeholder="ABC-123-XYZ&#10;ABC-124-XYZ"<?= $aria('codes') ?>><?= e($v('codes')) ?></textarea>
          <p class="field__hint">Każdy kod zostanie wydany jednemu graczowi. Dozwolone: litery, cyfry, myślnik, podkreślnik i kropka.</p>
          <?= $fieldError('codes') ?>
        </div>
      </section>
    <?php endif; ?>

    <div class="form-actions">
      <a class="btn btn-outline" href="<?= e(url('/panel/nagrody')) ?>">Anuluj</a>
      <button type="submit" class="btn btn-primary"><?= $isNew ? 'Utwórz nagrodę' : 'Zapisz zmiany' ?></button>
    </div>
  </form>

  <aside class="reward-preview" aria-label="Podgląd nagrody">
    <p class="reward-preview__title">Podgląd – tak zobaczy to gracz</p>
    <?php View::partial('rewards/card', ['r' => $values]); ?>
  </aside>
</div>

<?php if (!$isNew): ?>
  <!-- Pula kodów -->
  <section class="card codes-section" id="kody" aria-labelledby="codes-title">
    <div class="card__head">
      <h2 id="codes-title">Pula kodów</h2>
      <p class="muted"><?= e($reward['codes_available']) ?> dostępnych · <?= e($reward['codes_redeemed']) ?> wydanych</p>
    </div>

    <form method="post" action="<?= e(url('/panel/nagrody/' . (int) $reward['id'] . '/kody')) ?>" class="codes-add">
      <?= csrf_field() ?>
      <label for="f-newcodes">Dodaj kody – każdy w osobnej linii</label>
      <textarea id="f-newcodes" name="codes" rows="3" class="mono" placeholder="ABC-123-XYZ&#10;ABC-124-XYZ"></textarea>
      <button type="submit" class="btn btn-primary btn-sm">Dodaj kody</button>
    </form>

    <?php if (!$codes): ?>
      <p class="empty-state">Pula jest pusta. Dodaj kody otrzymane od sklepu, żeby gracze mogli odebrać nagrodę.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th scope="col">Kod</th><th scope="col">Status</th><th scope="col">Dodano</th><th scope="col"><span class="visually-hidden">Akcje</span></th></tr></thead>
          <tbody>
            <?php foreach ($codes as $c): ?>
              <tr>
                <td>
                  <code class="code-mask" data-code="<?= e($c['code']) ?>"><?= e(Reward::maskCode($c['code'])) ?></code>
                  <button type="button" class="link-btn code-reveal" data-reveal>Pokaż</button>
                </td>
                <td><span class="status <?= $c['status'] === 'available' ? 'status--published' : 'status--archived' ?>"><?= $c['status'] === 'available' ? 'Dostępny' : 'Wydany' ?></span>
                  <?php if ($c['status'] === 'redeemed' && !empty($c['player_nickname'])): ?>
                    <div class="muted" style="font-size:.8rem">graczowi <?= e($c['player_nickname']) ?><?= $c['redeemed_at'] ? ', ' . e(format_datetime($c['redeemed_at'])) : '' ?></div>
                  <?php endif; ?>
                </td>
                <td class="nowrap"><?= e(format_datetime($c['created_at'])) ?></td>
                <td class="table__actions">
                  <?php if ($c['status'] === 'available'): ?>
                    <form method="post" action="<?= e(url('/panel/nagrody/' . (int) $reward['id'] . '/kody/' . (int) $c['id'])) ?>"
                          data-confirm="Usunąć ten kod z puli?">
                      <?= csrf_field() ?>
                      <input type="hidden" name="_method" value="DELETE">
                      <button type="submit" class="btn btn-outline btn-sm">Usuń</button>
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

  <section class="card danger-zone" aria-labelledby="danger-title">
    <h2 id="danger-title">Usuwanie nagrody</h2>
    <?php if ((int) $reward['codes_redeemed'] > 0): ?>
      <p>Gracze odebrali już kody tej nagrody, więc nie można jej usunąć. Zamiast tego odznacz „Nagroda aktywna” i zapisz.</p>
    <?php else: ?>
      <p>Usunięcie jest nieodwracalne i usuwa także wszystkie kody z puli.</p>
      <form method="post" action="<?= e(url('/panel/nagrody/' . (int) $reward['id'])) ?>"
            data-confirm="Na pewno usunąć nagrodę „<?= e($reward['title']) ?>” razem z kodami?">
        <?= csrf_field() ?>
        <input type="hidden" name="_method" value="DELETE">
        <button type="submit" class="btn btn-danger btn-sm">Usuń nagrodę</button>
      </form>
    <?php endif; ?>
  </section>
<?php endif; ?>
