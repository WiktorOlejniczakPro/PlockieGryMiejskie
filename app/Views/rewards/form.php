<?php
/**
 * Formularz nagrody (nowa / edycja). Zmienne: $user, $reward (null = nowa), $types.
 */
$isNew = $reward === null;
$val = static function (string $key, string $default = '') use ($reward): string {
    $old = old($key, "\0");
    if ($old !== "\0") {
        return $old;
    }
    return $reward !== null && $reward[$key] !== null ? (string) $reward[$key] : $default;
};
$validUntil = $val('valid_until');
if ($validUntil !== '' && str_contains($validUntil, ' ')) {
    $validUntil = str_replace(' ', 'T', substr($validUntil, 0, 16));
}
$checked = static function (string $key, bool $default) use ($reward): bool {
    $old = old($key, "\0");
    if ($old !== "\0") {
        return $old !== '';
    }
    return $reward !== null ? (bool) $reward[$key] : $default;
};
$action = $isNew ? url('/panel/nagrody') : url('/panel/nagrody/' . (int) $reward['id']);
?>
<div class="page-head">
  <div>
    <h1><?= $isNew ? 'Nowa nagroda' : 'Edycja nagrody' ?></h1>
    <p class="page-head__lead">Nagroda pojawi się w sklepie punktów w aplikacji mobilnej, gdy jest włączona.</p>
  </div>
  <a class="btn btn-outline" href="<?= e(url('/panel/nagrody')) ?>">Wróć do listy</a>
</div>

<section class="card" style="max-width:760px">
  <form method="post" action="<?= e($action) ?>" class="form" novalidate>
    <?= csrf_field() ?>

    <div class="field">
      <label for="title">Nazwa nagrody *</label>
      <input id="title" name="title" maxlength="150" required value="<?= e($val('title')) ?>"
             placeholder="np. Kupon 10% do kawiarni przy Starym Rynku">
    </div>

    <div class="field">
      <label for="description">Opis</label>
      <textarea id="description" name="description" rows="3" maxlength="2000"><?= e($val('description')) ?></textarea>
    </div>

    <div class="field">
      <label for="reward_type">Rodzaj *</label>
      <select id="reward_type" name="reward_type">
        <?php foreach ($types as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $val('reward_type', 'coupon') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="partner_name">Partner / fundator</label>
      <input id="partner_name" name="partner_name" maxlength="150" value="<?= e($val('partner_name')) ?>"
             placeholder="np. Urząd Miasta Płocka">
    </div>

    <div class="field">
      <label for="cost_points">Koszt w punktach *</label>
      <input id="cost_points" name="cost_points" type="number" min="1" max="1000000" required value="<?= e($val('cost_points', '100')) ?>">
    </div>

    <div class="field">
      <label for="stock">Liczba sztuk</label>
      <input id="stock" name="stock" type="number" min="0" max="1000000" value="<?= e($val('stock')) ?>"
             aria-describedby="stock-hint">
      <small id="stock-hint">Puste = bez limitu.</small>
    </div>

    <div class="field">
      <label for="valid_until">Ważna do</label>
      <input id="valid_until" name="valid_until" type="datetime-local" value="<?= e($validUntil) ?>" aria-describedby="valid-hint">
      <small id="valid-hint">Puste = bezterminowo. Po tej dacie nagroda znika ze sklepu.</small>
    </div>

    <div class="field">
      <label for="redemption_instructions">Jak odebrać nagrodę</label>
      <textarea id="redemption_instructions" name="redemption_instructions" rows="3" maxlength="2000"
                placeholder="np. Pokaż kod przy kasie. Ważny jednorazowo."><?= e($val('redemption_instructions')) ?></textarea>
    </div>

    <label class="check">
      <input type="checkbox" name="is_active" value="1" <?= $checked('is_active', true) ? 'checked' : '' ?>>
      Nagroda dostępna w sklepie
    </label>
    <label class="check">
      <input type="checkbox" name="is_sample" value="1" <?= $checked('is_sample', false) ? 'checked' : '' ?>>
      Nagroda przykładowa (demonstracyjna, bez wartości handlowej)
    </label>

    <div>
      <button type="submit" class="btn btn-primary"><?= $isNew ? 'Dodaj nagrodę' : 'Zapisz zmiany' ?></button>
    </div>
  </form>
</section>
