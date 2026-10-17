<?php
/**
 * Edytor gry. Zmienne: $game (null dla nowej gry), $categories, $bootstrap.
 * Całą interakcję obsługuje public/assets/js/editor.js; dane startowe
 * trafiają do skryptu jako JSON w <script type="application/json">.
 */
$isNew = $game === null;
$status = $game['status'] ?? 'draft';
?>
<div class="editor" data-editor>

  <!-- ===== Pasek edytora ===== -->
  <div class="editor-bar">
    <div class="editor-bar__title">
      <a class="editor-back" href="<?= e(url('/panel/gry')) ?>">
        <svg class="icon icon--sm" viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        Twoje gry
      </a>
      <div class="editor-bar__heading">
        <h1 id="editor-heading"><?= e($isNew ? 'Nowa gra' : $game['title']) ?></h1>
        <span class="status status--<?= e($status) ?>" id="editor-status"><?= e(label('status', $status)) ?></span>
      </div>
    </div>

    <div class="editor-bar__actions">
      <p class="save-state" id="save-state" role="status" aria-live="polite" data-state="<?= $isNew ? 'new' : 'saved' ?>">
        <?= $isNew ? 'Gra nie jest jeszcze zapisana' : 'Wszystkie zmiany zapisane' ?>
      </p>
      <a class="btn btn-outline btn-sm" href="<?= e(url('/panel/gry')) ?>" id="btn-cancel">Anuluj</a>
      <button type="button" class="btn btn-primary btn-sm" id="btn-save">
        <?= $status === 'draft' ? 'Zapisz szkic' : 'Zapisz zmiany' ?>
      </button>
      <button type="button" class="btn btn-gold btn-sm" id="btn-publish" hidden>Opublikuj grę</button>
    </div>
  </div>

  <div class="lock-note draft-note" id="draft-note" hidden>
    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v5h1"/></svg>
    <p><strong>To jest szkic.</strong> Gracze go nie widzą – gra pojawi się na stronie głównej i w aplikacji
      dopiero po kliknięciu „Opublikuj grę”.</p>
  </div>

  <div class="lock-note" id="lock-note" hidden>
    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
    <p><strong id="lock-note-title">Edycja zablokowana.</strong> <span id="lock-note-text"></span></p>
    <button type="button" class="btn btn-outline btn-sm" data-goto-tab="preview">Przejdź do statusu gry</button>
  </div>

  <div class="save-errors" id="save-errors" role="alert" hidden>
    <p class="save-errors__title" id="save-errors-title"></p>
    <ul id="save-errors-list"></ul>
  </div>

  <!-- ===== Zakładki ===== -->
  <div class="editor-tabs" role="tablist" aria-label="Części edytora">
    <button type="button" role="tab" class="editor-tab" id="tab-info" aria-controls="panel-info" data-tab="info">
      <span class="editor-tab__num">1</span> Informacje podstawowe
    </button>
    <button type="button" role="tab" class="editor-tab" id="tab-route" aria-controls="panel-route" data-tab="route">
      <span class="editor-tab__num">2</span> Trasa, punkty i zadania
      <span class="editor-tab__count" id="tab-route-count">0</span>
    </button>
    <button type="button" role="tab" class="editor-tab" id="tab-preview" aria-controls="panel-preview" data-tab="preview">
      <span class="editor-tab__num">3</span> Podgląd i publikacja
    </button>
  </div>

  <div class="editor-workspace" id="editor-workspace">

    <!-- ===== 1. Informacje podstawowe ===== -->
    <section class="editor-panel" id="panel-info" role="tabpanel" aria-labelledby="tab-info" hidden>
      <div class="info-grid">
        <div class="card info-card">
          <h2>Opis gry</h2>
          <div class="field">
            <label for="f-title">Tytuł gry <span class="req" aria-hidden="true">*</span></label>
            <input type="text" id="f-title" data-game="title" maxlength="150" required
                   placeholder="np. Tajemnice Wzgórza Tumskiego">
            <p class="field__hint"><span data-count-for="f-title">0</span>/150 znaków</p>
          </div>
          <div class="field">
            <label for="f-short">Krótki opis</label>
            <textarea id="f-short" data-game="short_description" maxlength="300" rows="2"
                      placeholder="Jedno–dwa zdania widoczne na liście gier"></textarea>
            <p class="field__hint"><span data-count-for="f-short">0</span>/300 znaków</p>
          </div>
          <div class="field">
            <label for="f-desc">Pełny opis</label>
            <textarea id="f-desc" data-game="description" maxlength="10000" rows="7"
                      placeholder="Wprowadzenie do gry, fabuła, wskazówki dla uczestników"></textarea>
          </div>
        </div>

        <div class="info-side">
          <div class="card info-card">
            <h2>Parametry</h2>
            <div class="field-row">
              <div class="field">
                <label for="f-duration">Czas gry (minuty)</label>
                <input type="number" id="f-duration" data-game="estimated_duration_minutes" data-type="int"
                       min="1" max="1440" step="5" inputmode="numeric">
              </div>
              <div class="field">
                <label for="f-difficulty">Poziom trudności</label>
                <select id="f-difficulty" data-game="difficulty">
                  <option value="easy">Łatwy</option>
                  <option value="medium">Średni</option>
                  <option value="hard">Trudny</option>
                </select>
              </div>
            </div>
            <div class="field">
              <label for="f-points">Punkty za ukończenie całej trasy</label>
              <input type="number" id="f-points" data-game="completion_points" data-type="int"
                     min="0" max="1000" step="1" inputmode="numeric">
              <p class="field__hint">Domyślnie 5. Panel zapisuje tylko ustawienie – punkty naliczy przyszła aplikacja mobilna.</p>
            </div>
          </div>

          <div class="card info-card">
            <h2>Kategorie</h2>
            <fieldset class="chips" aria-describedby="cat-hint">
              <legend class="visually-hidden">Kategorie gry</legend>
              <?php foreach ($categories as $category): ?>
                <label class="chip">
                  <input type="checkbox" value="<?= e($category['id']) ?>" data-category
                         <?= $category['is_other'] ? 'data-other' : '' ?>>
                  <span><?= e($category['name']) ?></span>
                </label>
              <?php endforeach; ?>
            </fieldset>
            <p class="field__hint" id="cat-hint">Możesz wybrać kilka kategorii.</p>
            <div class="field" id="custom-category-field" hidden>
              <label for="f-custom">Nazwa własnej kategorii</label>
              <input type="text" id="f-custom" data-game="custom_category" maxlength="60" placeholder="np. Gra dla seniorów">
            </div>
          </div>

          <div class="card info-card">
            <h2>Termin dostępności <span class="optional">(opcjonalnie)</span></h2>
            <div class="field-row">
              <div class="field">
                <label for="f-from">Od</label>
                <input type="datetime-local" id="f-from" data-game="available_from">
              </div>
              <div class="field">
                <label for="f-until">Do</label>
                <input type="datetime-local" id="f-until" data-game="available_until">
              </div>
            </div>
            <p class="field__hint">Zostaw puste, jeśli gra ma być dostępna bez ograniczeń.</p>
          </div>
        </div>
      </div>
      <div class="info-next">
        <button type="button" class="btn btn-primary" data-goto-tab="route">Dalej: trasa na mapie →</button>
      </div>
    </section>

    <!-- ===== 2. Trasa, punkty i zadania ===== -->
    <section class="editor-panel editor-panel--route" id="panel-route" role="tabpanel" aria-labelledby="tab-route" hidden>
      <div class="route-layout">

        <aside class="route-list" aria-labelledby="points-title">
          <div class="route-list__head">
            <h2 id="points-title">Punkty trasy</h2>
            <p class="route-summary" id="route-summary">Brak punktów</p>
          </div>
          <ol class="point-list" id="point-list" aria-describedby="points-help"></ol>
          <div class="point-list-empty" id="point-list-empty">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
            <p><strong>Kliknij na mapie</strong>, aby dodać pierwszy punkt trasy.</p>
          </div>
          <p class="route-list__help" id="points-help">Kolejność zmienisz, przeciągając punkt lub strzałkami ↑ ↓.</p>
          <button type="button" class="btn btn-outline btn-sm btn-block" id="btn-add-center">
            <svg class="icon icon--sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            Dodaj punkt w środku mapy
          </button>
        </aside>

        <div class="route-map">
          <div class="map-toolbar" id="map-toolbar">
            <span class="route-status" id="route-status" role="status" aria-live="polite"></span>
          </div>
          <div id="map" class="map" role="region" aria-label="Mapa trasy. Kliknij, aby dodać punkt. Punkty można przeciągać."></div>
          <p class="map-note" id="map-note">
            Linia łączy punkty w kolejności trasy – to schemat, nie trasa piesza.
            Długość podana jest w linii prostej.
          </p>
        </div>

        <aside class="route-details" aria-label="Szczegóły punktu">
          <div id="point-details"></div>
          <div class="details-empty" id="details-empty">
            <p><strong>Nie wybrano punktu.</strong></p>
            <p>Kliknij punkt na liście lub na mapie, aby edytować jego opis i zadanie.</p>
          </div>
        </aside>
      </div>
    </section>

    <!-- ===== 3. Podgląd i publikacja ===== -->
    <section class="editor-panel" id="panel-preview" role="tabpanel" aria-labelledby="tab-preview" hidden>
      <p class="preview-unsaved" id="preview-unsaved" hidden>
        Masz niezapisane zmiany – podgląd je pokazuje, ale gracze zobaczą je dopiero po zapisaniu.
        Zmiana statusu najpierw zapisze grę.
      </p>
      <div class="preview-grid">
        <div class="preview-col">
          <div class="card status-card">
            <h2>Status gry</h2>
            <div class="status-card__now">
              <span class="status" id="pv-status"></span>
              <p id="pv-status-desc"></p>
            </div>
            <p class="visibility" id="pv-visibility" data-ok="false"></p>
            <div class="status-actions" id="pv-actions"></div>
            <details class="status-help">
              <summary>Co oznaczają statusy?</summary>
              <dl>
                <div><dt><span class="status status--draft">Szkic</span></dt>
                  <dd>Gra w przygotowaniu, niewidoczna dla graczy. Tylko szkic można edytować; można go zapisywać niekompletnym.</dd></div>
                <div><dt><span class="status status--published">Opublikowana</span></dt>
                  <dd>Widoczna na stronie głównej (a później w aplikacji), jeśli trwa termin dostępności. Zablokowana do edycji – aby coś zmienić, cofnij ją do szkicu.</dd></div>
                <div><dt><span class="status status--archived">Zarchiwizowana</span></dt>
                  <dd>Gra zakończona, tylko do odczytu i niewidoczna. Można ją przywrócić jako szkic.</dd></div>
              </dl>
            </details>
          </div>

          <div class="card">
            <h2 class="preview-h2">Gotowość do publikacji</h2>
            <ul class="checklist" id="pv-checklist"></ul>
          </div>
        </div>

        <div class="preview-col">
          <div class="card">
            <h2 class="preview-h2">Tak gra wygląda na stronie głównej</h2>
            <article class="pv-card" id="pv-card">
              <h3 id="pv-title"></h3>
              <p class="pv-card__desc" id="pv-short"></p>
              <dl class="pv-card__meta">
                <div><dt>Poziom</dt><dd id="pv-difficulty"></dd></div>
                <div><dt>Czas gry</dt><dd id="pv-duration"></dd></div>
                <div><dt>Punkty trasy</dt><dd id="pv-count"></dd></div>
              </dl>
              <p class="pv-card__cats" id="pv-cats"></p>
            </article>
          </div>

          <div class="card">
            <h2 class="preview-h2">Przebieg gry</h2>
            <p class="pv-summary" id="pv-summary"></p>
            <ol class="pv-route" id="pv-route"></ol>
          </div>
        </div>
      </div>
    </section>
  </div>

  <div class="visually-hidden" id="editor-live" aria-live="polite"></div>
</div>

<!-- ===== Szablon: szczegóły punktu ===== -->
<template id="tpl-point-details">
  <form class="details-form" novalidate data-details-form>
    <div class="details-head">
      <span class="point-num point-num--lg" data-point-number>1</span>
      <h2 class="details-title">Punkt trasy</h2>
      <button type="button" class="icon-btn" data-action="center" title="Pokaż na mapie" aria-label="Pokaż punkt na mapie">
        <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3"/></svg>
      </button>
      <button type="button" class="icon-btn icon-btn--danger" data-action="delete" title="Usuń punkt" aria-label="Usuń punkt">
        <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M10 11v6m4-6v6M6 7l1 13h10l1-13M9 7V4h6v3"/></svg>
      </button>
    </div>

    <div class="field">
      <label data-for="name">Nazwa punktu <span class="req" aria-hidden="true">*</span></label>
      <input type="text" data-wp="name" maxlength="120" required>
    </div>
    <div class="field">
      <label data-for="description">Opis lokalizacji</label>
      <textarea data-wp="description" rows="3" maxlength="5000" placeholder="Co to za miejsce? Co warto tu zobaczyć?"></textarea>
    </div>
    <div class="field">
      <label data-for="player_hint">Wskazówka dla gracza</label>
      <input type="text" data-wp="player_hint" maxlength="500" placeholder="np. Szukaj wieży z zegarem">
    </div>
    <div class="field-row">
      <div class="field">
        <label data-for="latitude">Szerokość geogr.</label>
        <input type="number" data-wp="latitude" step="0.0000001" min="-90" max="90" inputmode="decimal">
      </div>
      <div class="field">
        <label data-for="longitude">Długość geogr.</label>
        <input type="number" data-wp="longitude" step="0.0000001" min="-180" max="180" inputmode="decimal">
      </div>
    </div>
    <p class="field__error" data-coord-error hidden>Podaj poprawne współrzędne (szerokość −90…90, długość −180…180).</p>

    <!-- Zadanie -->
    <div class="task-box">
      <label class="switch">
        <input type="checkbox" data-task-toggle>
        <span class="switch__track" aria-hidden="true"></span>
        <span class="switch__label">Dodaj zadanie w tym punkcie</span>
      </label>

      <div class="task-fields" data-task-fields hidden>
        <div class="field">
          <label data-for="task_type">Typ zadania</label>
          <select data-task="task_type">
            <option value="single_choice">Pytanie jednokrotnego wyboru</option>
            <option value="multiple_choice">Pytanie wielokrotnego wyboru</option>
            <option value="open_answer">Odpowiedź otwarta</option>
            <option value="photo">Wykonanie zdjęcia</option>
            <option value="video">Nagranie filmu</option>
            <option value="info">Informacja (bez odpowiedzi)</option>
          </select>
        </div>
        <div class="field">
          <label data-for="title">Tytuł zadania <span class="req" aria-hidden="true">*</span></label>
          <input type="text" data-task="title" maxlength="150" required>
        </div>
        <div class="field">
          <label data-for="description">Treść polecenia <span class="req" aria-hidden="true">*</span></label>
          <textarea data-task="description" rows="3" maxlength="5000" required></textarea>
        </div>

        <!-- Odpowiedzi w pytaniach zamkniętych -->
        <div class="options" data-show-for="single_choice multiple_choice">
          <p class="options__title">Odpowiedzi <span class="options__mode" data-options-mode></span></p>
          <ul class="options__list" data-options></ul>
          <button type="button" class="btn btn-outline btn-sm" data-action="add-option">+ Dodaj odpowiedź</button>
        </div>

        <div class="field" data-show-for="open_answer">
          <label data-for="expected_answer">Oczekiwana odpowiedź</label>
          <textarea data-task="expected_answer" rows="2" maxlength="5000"></textarea>
        </div>
        <div class="field" data-show-for="photo video">
          <label data-for="media_requirement">Wymagania dotyczące materiału</label>
          <input type="text" data-task="media_requirement" maxlength="500" placeholder="np. Zdjęcie poziome, widoczna wieża zegarowa">
        </div>
        <div class="field" data-show-for="video">
          <label data-for="media_max_seconds">Maksymalna długość filmu (sekundy)</label>
          <input type="number" data-task="media_max_seconds" data-type="int" min="1" max="600" inputmode="numeric">
        </div>
        <div class="field" data-show-for="open_answer photo video">
          <label data-for="evaluation_instructions">Instrukcja oceny</label>
          <textarea data-task="evaluation_instructions" rows="2" maxlength="5000" placeholder="Jak sprawdzić odpowiedź gracza?"></textarea>
        </div>
        <div class="field">
          <label data-for="hint">Podpowiedź dla gracza</label>
          <input type="text" data-task="hint" maxlength="500">
        </div>

        <div class="field-row">
          <div class="field" data-hide-for="info">
            <label data-for="max_points">Maks. liczba punktów</label>
            <input type="number" data-task="max_points" data-type="int" min="0" max="1000" inputmode="numeric">
          </div>
          <div class="field">
            <label data-for="unlock_mode">Dostępność zadania</label>
            <select data-task="unlock_mode">
              <option value="on_arrival">Po dotarciu na miejsce</option>
              <option value="always">Od razu</option>
              <option value="after_previous">Po ukończeniu poprzedniego punktu</option>
            </select>
          </div>
        </div>
        <div class="field" data-show-unlock="on_arrival">
          <label data-for="unlock_radius_m">Promień odblokowania (metry)</label>
          <input type="number" data-task="unlock_radius_m" data-type="int" min="5" max="1000" inputmode="numeric">
        </div>
        <p class="field__hint">Dostępność i punktację wykorzysta przyszła aplikacja mobilna – panel tylko je zapisuje.</p>
      </div>
    </div>
  </form>
</template>

<!-- ===== Szablon: wiersz odpowiedzi ===== -->
<template id="tpl-option">
  <li class="option-row">
    <label class="option-row__correct">
      <input type="checkbox" data-opt-correct>
      <span class="visually-hidden">Poprawna odpowiedź</span>
    </label>
    <input type="text" class="option-row__text" data-opt-text maxlength="300" placeholder="Treść odpowiedzi">
    <button type="button" class="icon-btn icon-btn--danger" data-opt-remove aria-label="Usuń odpowiedź">
      <svg class="icon icon--sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
    </button>
  </li>
</template>

<script type="application/json" id="editor-data"><?= json_encode($bootstrap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
