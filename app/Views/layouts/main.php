<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= e($title ?? 'Dashboard') ?> — <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="layout">

    <?php \Core\View::render('components.sidebar', ['title' => $title ?? '']); ?>

    <div class="layout-main">

        <?php \Core\View::render('components.topbar', ['title' => $title ?? '']); ?>

        <main class="layout-content">
            <?php \Core\View::render('components.alerts'); ?>
            <?= $content ?>
        </main>

        <?php \Core\View::render('components.footer'); ?>

    </div>
</div>

<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
