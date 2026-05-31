<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erro <?= $httpCode ?? 500 ?> — <?= e($httpMessage ?? 'Erro interno') ?></title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f5f6fa; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .box { background: #fff; border-radius: 16px; padding: 48px 40px; text-align: center; max-width: 480px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,.1); }
        .code { font-size: 80px; font-weight: 900; background: linear-gradient(135deg,#6366f1,#8b5cf6); -webkit-background-clip: text; color: transparent; line-height: 1; }
        h1 { color: #1e293b; font-size: 22px; margin: 12px 0 8px; }
        p { color: #64748b; font-size: 14px; line-height: 1.6; margin: 0 0 28px; }
        a { display: inline-block; background: #6366f1; color: #fff; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; }
        a:hover { background: #4f46e5; }
    </style>
</head>
<body>
    <div class="box">
        <div class="code"><?= $httpCode ?? 500 ?></div>
        <h1><?= e($httpMessage ?? 'Erro interno do servidor') ?></h1>
        <p>Algo deu errado. Se o problema persistir, entre em contato com o suporte.</p>
        <a href="<?= defined('APP_URL') ? APP_URL : '/' ?>">← Voltar ao início</a>
    </div>
</body>
</html>
