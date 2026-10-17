<?php
/** Strona publiczna. $games – opublikowane gry z bazy. */
use App\Models\Game;
?>
<!-- ===== Sekcja powitalna ===== -->
<section class="hero" aria-labelledby="hero-title">
  <div class="container hero-content">
    <h1 id="hero-title">Witaj na stronie <span class="text-gold">Płockich gier miejskich</span></h1>
    <p class="hero-lead">
      Spacer, który zamienia się w przygodę. Płockie Gry Miejskie prowadzą przez zabytki,
      nadwiślańskie skarpy i mniej znane zakątki miasta – z mapą i zadaniami
      do wykonania w każdej chwili.
    </p>
  </div>
</section>

<!-- ===== Czym są gry miejskie ===== -->
<section class="section section--alt" id="o-projekcie" aria-labelledby="about-title">
  <div class="container">
    <header class="section-header">
      <h2 id="about-title">O projekcie</h2>
    </header>

    <div class="feature-grid">
      <article class="feature-card">
        <span class="feature-card__icon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M3 21h18M5 21V10l7-5 7 5v11M9 21v-6h6v6"/></svg>
        </span>
        <h3>Czym są gry miejskie?</h3>
        <p>Gry miejskie polegają na podróżowaniu wytoczonymi w aplikacji ścieżkami i zwiedzaniu wyjątkowych lub mało znanych miejsc w mieście.</p>
      </article>
      <article class="feature-card">
        <span class="feature-card__icon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M12 22V12m0 0C12 7 8 4 4 4c0 5 3 8 8 8Zm0 0c0-5 4-8 8-8 0 5-3 8-8 8Z"/></svg>
        </span>
        <h3>Aktywne spędzanie czasu</h3>
        <p>Dzięki graniu w gry miejskie można zwiedzać miasto w aktywny i przyjemny sposób.</p>
      </article>
      <article class="feature-card">
        <span class="feature-card__icon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M17 20v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2M10 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm11 10v-2a4 4 0 0 0-3-3.9M16 2.1a4 4 0 0 1 0 7.8"/></svg>
        </span>
        <h3>Dostępność</h3>
        <p>W gry miejskie tworzone przez miasto Płock mogą grać zarówno mieszkańcy jak i turyści zwiedzający interesujące miejsca w mieście.</p>
      </article>
    </div>

    <div class="benefits-panel">
      <h3>Co zyskują uczestnicy?</h3>
      <ul class="benefits">
        <li>Za granie w gry przyznawane są punkty, które można wymienić na atrakcyjne nagrody.</li>
        <li>Aktywnie spędzają czas na świeżym powietrzu.</li>
        <li>Możliwość grania w dowolnym momencie.</li>
        <li>Odkrywają miejsca, które łatwo przeoczyć na co dzień.</li>
        <li>Poznają Płock w angażujący i interesujący sposób.</li>
      </ul>
    </div>
  </div>
</section>

<!-- ===== Opublikowane gry (dane z bazy) ===== -->
<section class="section" id="gry" aria-labelledby="games-title">
  <div class="container">
    <header class="section-header">
      <h2 id="games-title">Obecnie dostępne gry</h2>
    </header>

    <?php if (!$games): ?>
      <p class="empty-state">Pierwsze gry pojawią się tu wkrótce.</p>
    <?php else: ?>
      <ul class="game-grid">
        <?php foreach ($games as $game): ?>
          <li>
            <article class="game-card" data-difficulty="<?= e($game['difficulty']) ?>">
              <h3><?= e(Game::displayTitle($game)) ?></h3>
              <?php if ($game['short_description']): ?>
                <p class="game-card__desc"><?= e($game['short_description']) ?></p>
              <?php endif; ?>
              <dl class="game-card__meta">
                <div><dt>Poziom</dt><dd><?= e(label('difficulty', $game['difficulty'])) ?></dd></div>
                <div><dt>Czas gry</dt><dd><?= e(format_duration($game['estimated_duration_minutes'])) ?></dd></div>
                <div><dt>Punkty trasy</dt><dd><?= e($game['waypoint_count']) ?></dd></div>
              </dl>
              <?php if ($game['categories']): ?>
                <p class="game-card__cats"><?= e($game['categories']) ?></p>
              <?php endif; ?>
            </article>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</section>

<!-- ===== Planowana aplikacja mobilna ===== -->
<section class="section section--alt" id="aplikacja" aria-labelledby="app-title">
  <div class="container app-teaser">
    <div>
      <h2 id="app-title">Aplikacja mobilna na Androida</h2>
      <p>
        Pobierz bezpłatną aplikację na swój telefon z androidem, gdzie będziesz mógł założyć konto i rozpocząć swoją przygodę z grami miejskimi.
      </p>
    </div>
    <div class="app-teaser__card" aria-hidden="true">
      <svg viewBox="0 0 120 200">
        <rect x="8" y="4" width="104" height="192" rx="18" fill="#0b2545"/>
        <rect x="16" y="22" width="88" height="156" rx="6" fill="#e3f0fb"/>
        <path d="M30 140 52 96l20 22 18-40" fill="none" stroke="#2f80c4" stroke-width="3" stroke-dasharray="5 5"/>
        <circle cx="30" cy="140" r="7" fill="#e9b949"/><circle cx="52" cy="96" r="7" fill="#0b2545"/>
        <circle cx="72" cy="118" r="7" fill="#0b2545"/><circle cx="90" cy="78" r="7" fill="#e9b949"/>
        <rect x="46" y="184" width="28" height="4" rx="2" fill="#5aa9e6"/>
      </svg>
    </div>
  </div>
</section>
