<?php
/** Komunikaty flash: success, error, info. */
$alertTypes = ['success' => 'Sukces', 'error' => 'Błąd', 'info' => 'Informacja'];
foreach ($alertTypes as $type => $label):
    $message = flash($type);
    if ($message === null) {
        continue;
    }
?>
  <div class="alert alert--<?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>">
    <span class="visually-hidden"><?= e($label) ?>:</span>
    <?= e($message) ?>
  </div>
<?php endforeach; ?>
