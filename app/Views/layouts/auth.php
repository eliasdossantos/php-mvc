<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Autenticação') ?> — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="auth-body">

<div class="auth-wrapper">
    <div class="auth-card">

        <div class="auth-logo">
            <h1><?= e(APP_NAME) ?></h1>
        </div>

        <?php \Core\View::render('components.alerts'); ?>

        <?= $content ?>

    </div>
</div>

<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
