<?php
/** Các thẻ <head> dùng chung. Biến: $title */
$shades = color_shades((string) setting('primary_color', '#2563eb'));
$pageTitle = (isset($title) && $title !== '' ? $title . ' · ' : '') . setting('site_name');
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="base-uri" content="<?= e(base_uri()) ?>">
<meta name="server-time" content="<?= time() ?>">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="<?= e($shades['base']) ?>">
<title><?= e($pageTitle) ?></title>
<link rel="icon" href="<?= e(favicon_url()) ?>">
<link rel="stylesheet" href="<?= asset('css/fonts.css') ?>">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<style>
:root{--primary:<?= e($shades['base']) ?>;--primary-hover:<?= e($shades['hover']) ?>;--primary-dark:<?= e($shades['dark']) ?>;--primary-rgb:<?= e($shades['rgb']) ?>}
:root:not([data-theme="dark"]){--primary-soft:<?= e($shades['soft']) ?>;--primary-soft2:<?= e($shades['soft2']) ?>}
</style>
<?= \App\Core\View::stack('styles') ?>
<script>(function(){try{var t=localStorage.getItem('tn-theme');if(!t&&window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches)t='dark';if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
