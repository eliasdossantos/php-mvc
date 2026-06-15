<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Autenticação') ?> — <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <style>
        .auth-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.25rem;
        }

        .remember-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: var(--text-muted, #64748b);
            cursor: pointer;
            user-select: none;
        }

        .remember-label input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--primary, #6366f1);
            cursor: pointer;
        }

        .auth-forgot-link {
            font-size: 0.875rem;
            color: var(--primary, #6366f1);
            text-decoration: none;
        }

        .auth-forgot-link:hover {
            text-decoration: underline;
        }
    </style>

</head>

<body class="auth-body">

    <!-- Painel esquerdo: formulário -->
    <div class="auth-panel">
        <div class="auth-panel-inner">

            <a href="<?= url('/') ?>" class="auth-brand">
                <div class="auth-brand-icon">&#9889;</div>
                <span class="auth-brand-name"><?= e(APP_NAME) ?></span>
            </a>

            <?php \Core\View::render('components.alerts'); ?>

            <?= $content ?>

        </div>
    </div>

    <!-- Painel direito: hero decorativo -->
    <div class="auth-hero">
        <div class="auth-hero-content">

            <div class="auth-hero-badge">
                <span class="dot"></span>
                Plataforma segura e confi&#225;vel
            </div>

            <h2 class="auth-hero-title">
                Tudo o que voc&#234; precisa<br>em <span>um s&#243; lugar</span>
            </h2>

            <p class="auth-hero-desc">
                Uma plataforma moderna, r&#225;pida e segura para gerenciar
                seu neg&#243;cio com efici&#234;ncia e escalabilidade.
            </p>

            <div class="auth-features">
                <div class="auth-feature">
                    <div class="auth-feature-icon">&#128274;</div>
                    <div>
                        <div class="auth-feature-title">Seguran&#231;a avan&#231;ada</div>
                        <div class="auth-feature-desc">Autentica&#231;&#227;o robusta com prote&#231;&#227;o CSRF e
                            hashing bcrypt.</div>
                    </div>
                </div>
                <div class="auth-feature">
                    <div class="auth-feature-icon">&#9889;</div>
                    <div>
                        <div class="auth-feature-title">Alta performance</div>
                        <div class="auth-feature-desc">Arquitetura MVC otimizada para respostas ultra-r&#225;pidas.
                        </div>
                    </div>
                </div>
                <div class="auth-feature">
                    <div class="auth-feature-icon">&#127912;</div>
                    <div>
                        <div class="auth-feature-title">Interface moderna</div>
                        <div class="auth-feature-desc">Design responsivo e acess&#237;vel para qualquer dispositivo.
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="<?= asset('js/app.js') ?>"></script>
</body>

</html>