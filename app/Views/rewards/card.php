<?php
/**
 * Karta nagrody – tak nagrodę zobaczy gracz w aplikacji.
 * Używana na liście nagród i jako podgląd w kreatorze (JS aktualizuje pola data-preview).
 * Zmienne: $r – tablica z polami nagrody.
 */
use App\Models\Reward;

$isGift = ($r['reward_type'] ?? '') === 'gift_card';
$hasValue = is_numeric(str_replace(',', '.', (string) ($r['value_amount'] ?? '')))
    && (float) str_replace(',', '.', (string) $r['value_amount']) > 0;
$value = $hasValue ? Reward::valueLabel([
    'reward_type'   => $r['reward_type'] ?? 'discount_code',
    'discount_kind' => $r['discount_kind'] ?? 'percent',
    'value_amount'  => (float) str_replace(',', '.', (string) $r['value_amount']),
]) : '—';
?>
<article class="reward-card reward-card--<?= $isGift ? 'gift' : 'discount' ?>" data-preview-card>
  <div class="reward-card__ticket">
    <span class="reward-card__type" data-preview="type"><?= e($isGift ? 'Karta podarunkowa' : 'Kod rabatowy') ?></span>
    <span class="reward-card__value" data-preview="value"><?= e($value) ?></span>
  </div>
  <div class="reward-card__body">
    <p class="reward-card__partner" data-preview="partner_name"><?= e(($r['partner_name'] ?? '') !== '' ? $r['partner_name'] : 'Nazwa sklepu') ?></p>
    <h3 class="reward-card__title" data-preview="title"><?= e(($r['title'] ?? '') !== '' ? $r['title'] : 'Nazwa nagrody') ?></h3>
    <p class="reward-card__cost">
      <svg class="icon icon--sm" viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9Z"/></svg>
      <span data-preview="cost_points"><?= e(($r['cost_points'] ?? '') !== '' ? $r['cost_points'] : '0') ?></span> pkt
    </p>
    <p class="reward-card__valid" data-preview="valid_until"><?= !empty($r['valid_until']) ? 'Ważna do ' . e(date('d.m.Y', strtotime((string) $r['valid_until']))) : 'Bez terminu ważności' ?></p>
  </div>
</article>
