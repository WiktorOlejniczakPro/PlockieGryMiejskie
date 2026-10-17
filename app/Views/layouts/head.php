<?php
/**
 * Wspólna sekcja <head>.
 * Zmienne: $pageTitle, $styles i $scripts – listy ścieżek względem public/assets/
 * (np. 'css/panel.css', 'vendor/leaflet/leaflet.js').
 */
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? config('app.name')) ?></title>
<meta name="theme-color" content="#0b2545">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<link rel="icon" href="<?= e(asset('images/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Sora:wght@600;700;800&display=swap">
<link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
<?php foreach (($styles ?? []) as $style): ?>
<link rel="stylesheet" href="<?= e(asset($style)) ?>">
<?php endforeach; ?>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php foreach (($scripts ?? []) as $script): ?>
<script src="<?= e(asset($script)) ?>" defer></script>
<?php endforeach; ?>
