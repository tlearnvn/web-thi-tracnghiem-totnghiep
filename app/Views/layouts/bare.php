<!doctype html>
<html lang="vi">
<head>
<?= \App\Core\View::partial('partials/head', ['title' => $title ?? '']) ?>
</head>
<body>
<?= $content ?>
<?= \App\Core\View::partial('partials/scripts') ?>
</body>
</html>
