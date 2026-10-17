<?php
/** Strona publiczna. $games – opublikowane gry z bazy. */
use App\Models\Game;
?>
<!-- ===== Sekcja powitalna ===== -->
<section class="hero" aria-labelledby="hero-title">
  <div class="container hero-content">
    <p class="eyebrow">Płock · gry terenowe · historia i przyroda</p>
    <h1 id="hero-title">Odkrywaj Płock <span class="text-gold">w grach miejskich</span></h1>
    <p class="hero-lead">
      Spacer, który zamienia się w przygodę. Płockie Gry Miejskie prowadzą przez zabytki,
      nadwiślańskie skarpy i mniej znane zakątki miasta – z mapą, zagadkami i zadaniami
      do wykonania w dowolnym momencie.
    </p>
    <div class="hero-actions">
      <a href="#gry" class="btn btn-gold">Zobacz dostępne gry</a>
      <a href="#jak-to-dziala" class="btn btn-ghost">Jak to działa?</a>
    </div>
  </div>

  <div class="hero-skyline" aria-hidden="true">
    <svg viewBox="0 0 1440 360" preserveAspectRatio="xMidYMax slice" focusable="false">
      <circle cx="1180" cy="78" r="60" fill="#e9b949" opacity=".08"/>
      <circle cx="1180" cy="78" r="26" fill="#fbf1d6" opacity=".9"/>
      <path d="M0 250C200 230 380 240 520 225c180-20 380-15 560 3 170 14 270 8 360 2v130H0Z" fill="#11305a"/>
      <g fill="#071a33">
        <path d="M0 292c160-10 300-18 430-30l110-12c100-10 420-10 500 2l120 18c100 12 200 16 280 20v70H0Z"/>
        <path d="M90 300v-36l20-16 20 16v-6h30v-16l18-14 18 14v18h26v-12l15-12 15 12v-4h28v-14l16-12 16 12v10h30v-10l14-10 14 10v8h30v60Z"/>
        <path d="M470 262v-84h6v-8h6v8h6v-8h5v8h6v-8h6v8v84Z"/>
        <rect x="610" y="120" width="42" height="140"/><path d="M610 120l21-58 21 58Z"/>
        <rect x="828" y="120" width="42" height="140"/><path d="M828 120l21-58 21 58Z"/>
        <path d="M640 260v-85l100-25 100 25v85Z"/>
        <rect x="715" y="158" width="50" height="22"/><path d="M715 160a25 25 0 0 1 50 0Z"/>
        <rect x="734" y="118" width="12" height="20"/><rect x="739" y="104" width="2" height="14"/><rect x="733" y="109" width="14" height="2"/>
        <rect x="920" y="140" width="38" height="120"/><path d="M914 140l25-34 25 34Z"/>
        <path d="M960 262v-40h70v40Z"/>
        <path d="M1050 280v-22l16-12 16 12v-4h26v-10l14-10 14 10v36Z"/>
      </g>
      <g fill="#0a2240">
        <circle cx="560" cy="250" r="22"/><circle cx="590" cy="256" r="16"/>
        <circle cx="895" cy="252" r="18"/><circle cx="1000" cy="246" r="20"/><circle cx="420" cy="268" r="18"/>
      </g>
      <g fill="#e9b949" opacity=".7">
        <rect x="626" y="140" width="10" height="16" rx="5"/><rect x="844" y="140" width="10" height="16" rx="5"/>
        <rect x="734" y="196" width="12" height="22" rx="6"/><circle cx="939" cy="165" r="7"/>
      </g>
      <rect x="0" y="300" width="1440" height="60" fill="#0d3260"/>
      <g fill="none" stroke-linecap="round" stroke-width="2">
        <path d="M40 318h120M260 330h180M520 344h140M980 322h160M1200 340h160" stroke="#5aa9e6" stroke-opacity=".3"/>
        <path d="M600 314h60M700 314h90M820 314h50M640 328h70M760 328h80M680 342h110" stroke="#e9b949" stroke-opacity=".35"/>
      </g>
      <g stroke="#071a33" fill="none">
        <path d="M1060 300h380" stroke-width="5"/><path d="M1250 300V150" stroke-width="8"/>
        <path d="M1250 158 1110 300M1250 158 1150 300M1250 158 1195 300M1250 158 1305 300M1250 158 1350 300M1250 158 1395 300" stroke-width="1.5"/>
      </g>
    </svg>
  </div>
</section>

<!-- ===== Czym są gry miejskie ===== -->
<section class="section" id="o-projekcie" aria-labelledby="about-title">
  <div class="container">
    <header class="section-header">
      <p class="eyebrow eyebrow--dark">O projekcie</p>
      <h2 id="about-title">Czym są gry miejskie?</h2>
      <p class="section-lead">
        To trasy po mieście z punktami na mapie. W każdym punkcie czeka opis miejsca, zagadka
        albo zadanie. Uczestnicy przechodzą je samodzielnie, w swoim tempie – bez zapisów
        i bez umawiania się na konkretną godzinę.
      </p>
    </header>

    <div class="feature-grid">
      <article class="feature-card">
        <span class="feature-card__icon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M3 21h18M5 21V10l7-5 7 5v11M9 21v-6h6v6"/></svg>
        </span>
        <h3>Historia i zabytki</h3>
        <p>Wzgórze Tumskie, katedra, zamek książąt mazowieckich i Stary Rynek – dzieje dawnej stolicy Polski opowiedziane w terenie.</p>
      </article>
      <article class="feature-card">
        <span class="feature-card__icon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M12 22V12m0 0C12 7 8 4 4 4c0 5 3 8 8 8Zm0 0c0-5 4-8 8-8 0 5-3 8-8 8Z"/></svg>
        </span>
        <h3>Przyroda i ekologia</h3>
        <p>Nadwiślańskie skarpy, parki i bulwary. Gry pomagają poznać lokalną przyrodę i uczą, jak o nią dbać.</p>
      </article>
      <article class="feature-card">
        <span class="feature-card__icon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M17 20v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2M10 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm11 10v-2a4 4 0 0 0-3-3.9M16 2.1a4 4 0 0 1 0 7.8"/></svg>
        </span>
        <h3>Dla mieszkańców i turystów</h3>
        <p>Rodzinny spacer, wycieczka szkolna, zwiedzanie miasta albo wydarzenie miejskie – każdy wybiera grę dla siebie.</p>
      </article>
    </div>

    <div class="benefits-panel">
      <h3>Co zyskują uczestnicy?</h3>
      <ul class="benefits">
        <li>Poznają historię i ciekawostki o Płocku w atrakcyjnej formie.</li>
        <li>Aktywnie spędzają czas na świeżym powietrzu.</li>
        <li>Grają, kiedy chcą – bez zbierania grupy na konkretny termin.</li>
        <li>Rywalizują w przyjaznej atmosferze z rodziną i znajomymi.</li>
        <li>Odkrywają miejsca, które łatwo przeoczyć na co dzień.</li>
      </ul>
    </div>
  </div>
</section>

<!-- ===== Jak to działa ===== -->
<section class="section section--alt" id="jak-to-dziala" aria-labelledby="how-title">
  <div class="container">
    <header class="section-header">
      <p class="eyebrow eyebrow--dark">Jak to działa</p>
      <h2 id="how-title">Od pomysłu do gry w trzech krokach</h2>
    </header>

    <ol class="steps">
      <li class="step">
        <span class="step__num" aria-hidden="true">1</span>
        <h3>Twórca projektuje trasę</h3>
        <p>Pracownik urzędu lub organizacji partnerskiej zaznacza punkty na mapie Płocka, dodaje opisy, zdjęcia i zadania.</p>
      </li>
      <li class="step">
        <span class="step__num" aria-hidden="true">2</span>
        <h3>Gra zostaje opublikowana</h3>
        <p>System sprawdza, czy gra jest kompletna. Po publikacji trafia do listy dostępnych gier.</p>
      </li>
      <li class="step">
        <span class="step__num" aria-hidden="true">3</span>
        <h3>Gracze ruszają w miasto</h3>
        <p>Uczestnicy przechodzą trasę w aplikacji mobilnej, wykonują zadania i zbierają punkty – kiedy im wygodnie.</p>
      </li>
    </ol>
  </div>
</section>

<!-- ===== Opublikowane gry (dane z bazy) ===== -->
<section class="section" id="gry" aria-labelledby="games-title">
  <div class="container">
    <header class="section-header">
      <p class="eyebrow eyebrow--dark">Gry</p>
      <h2 id="games-title">Opublikowane gry</h2>
      <p class="section-lead">Gry przygotowane przez twórców i opublikowane w systemie.</p>
    </header>

    <?php if (!$games): ?>
      <p class="empty-state">Pierwsze gry pojawią się tu wkrótce.</p>
    <?php else: ?>
      <ul class="game-grid">
        <?php foreach ($games as $game): ?>
          <li>
            <article class="game-card" data-difficulty="<?= e($game['difficulty']) ?>">
              <?php if ($game['is_sample']): ?>
                <span class="badge badge--sample">Gra przykładowa</span>
              <?php endif; ?>
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
      <p class="eyebrow eyebrow--dark">Wkrótce</p>
      <h2 id="app-title">Aplikacja mobilna na Androida</h2>
      <p>
        Gry będzie można przechodzić w aplikacji mobilnej z mapą, nawigacją GPS, zadaniami,
        przesyłaniem zdjęć i filmów oraz rankingami graczy. Aplikacja jest w przygotowaniu –
        link do pobrania pojawi się na tej stronie po jej udostępnieniu.
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
