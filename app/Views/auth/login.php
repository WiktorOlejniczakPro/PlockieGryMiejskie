<?php
use App\Core\View;
?>
<section class="auth-card" aria-labelledby="login-title">
  <h1 id="login-title">Logowanie</h1>
  <p class="auth-lead">Panel dla pracowników Urzędu Miasta Płocka i upoważnionych organizacji tworzących gry miejskie.</p>

  <?php View::partial('partials/alerts'); ?>

  <form method="post" action="<?= e(url('/logowanie')) ?>" class="form" novalidate data-validate>
    <?= csrf_field() ?>

    <div class="field">
      <label for="email">Adres e-mail</label>
      <input type="email" id="email" name="email" required maxlength="190"
             autocomplete="username" inputmode="email" value="<?= e(old('email')) ?>"
             <?= old('email') === '' ? 'autofocus' : '' ?>>
      <p class="field__error" data-error-for="email" hidden>Podaj poprawny adres e-mail.</p>
    </div>

    <div class="field">
      <label for="password">Hasło</label>
      <div class="password-field">
        <input type="password" id="password" name="password" required maxlength="1024"
               autocomplete="current-password" <?= old('email') !== '' ? 'autofocus' : '' ?>>
        <button type="button" class="password-toggle" data-password-toggle="password"
                aria-controls="password" aria-pressed="false">Pokaż</button>
      </div>
      <p class="field__error" data-error-for="password" hidden>Wpisz hasło.</p>
    </div>

    <button type="submit" class="btn btn-primary btn-block">Zaloguj się</button>
  </form>

  <p class="auth-note">
    Nie masz konta? Dostęp przyznaje administrator systemu w Urzędzie Miasta Płocka.
  </p>
</section>
