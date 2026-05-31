<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Página não encontrada | TaskFlow</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ============================================
           CSS Variables / Design Tokens - Light Theme
           ============================================ */
        :root {
            --primary: #5e3fb8;
            --primary-light: #7c5dd9;
            --primary-hover: #4a2f8f;
            --primary-light-bg: rgba(93, 63, 184, 0.22);
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
            --bg-dark: #7c5dd9;
            --bg-darker: #4a2f8f;
            --bg-card: #ffffff;
            --bg-card-hover: #f8fafc;
            --bg-input: #f3f4f6;
            --text-primary: #1e293b;
            --text-secondary: #475569;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --border-light: #cbd5e1;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.12);
            --shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.15);
            --radius-sm: 4px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            --radius-full: 9999px;
            --transition-fast: 0.15s ease;
            --transition-normal: 0.25s ease;
            --transition-slow: 0.35s ease;
            --header-height: 70px;
        }

        /* ============================================
           Dark Theme Variables
           ============================================ */
        [data-theme="dark"] {
            --primary: #8b5cf6;
            --primary-light: #a78bfa;
            --primary-hover: #7c3aed;
            --primary-light-bg: rgba(139, 92, 246, 0.15);
            --bg-dark: #0f172a;
            --bg-darker: #020617;
            --bg-card: #1e293b;
            --bg-card-hover: #334155;
            --bg-input: #1e293b;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --border-color: #334155;
            --border-light: #475569;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.5);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.5);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.6);
            --shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.7);
        }

        /* ============================================
           Reset & Base Styles
           ============================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, var(--bg-dark) 0%, var(--bg-darker) 100%);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            transition: background-color var(--transition-normal), color var(--transition-normal);
        }

        /* ============================================
           Error Container
           ============================================ */
        .error-container {
            max-width: 550px;
            width: 100%;
            background-color: var(--bg-card);
            border-radius: var(--radius-xl);
            padding: 56px 48px;
            text-align: center;
            box-shadow: var(--shadow-lg);
            animation: fadeInUp 0.6s ease-out;
            border: 1px solid var(--border-color);
            transition: all var(--transition-normal);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        /* ============================================
           Error Icon
           ============================================ */
        .error-icon {
            font-size: 5rem;
            margin-bottom: 24px;
            animation: float 3s ease-in-out infinite;
            color: var(--primary);
        }

        .error-icon i {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        /* ============================================
           Error Code
           ============================================ */
        .error-code {
            font-size: 7rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            font-family: 'JetBrains Mono', monospace;
            line-height: 1;
            margin-bottom: 16px;
            letter-spacing: -4px;
        }

        /* ============================================
           Typography
           ============================================ */
        h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 12px;
        }

        .error-message {
            color: var(--text-secondary);
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 32px;
        }

        /* ============================================
           Suggestions Box
           ============================================ */
        .suggestions-box {
            background: var(--primary-light-bg);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 32px;
            text-align: left;
            border: 1px solid var(--border-color);
            transition: all var(--transition-normal);
        }

        .suggestions-box:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .suggestions-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 16px;
        }

        .suggestions-title i {
            color: var(--primary);
            font-size: 1rem;
        }

        .suggestions-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .suggestions-list li {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            font-size: 0.875rem;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--border-color);
        }

        .suggestions-list li:last-child {
            border-bottom: none;
        }

        .suggestions-list li i {
            color: var(--primary);
            font-size: 0.875rem;
            flex-shrink: 0;
        }

        .suggestions-list li a {
            color: var(--primary);
            text-decoration: none;
            transition: color var(--transition-fast);
        }

        .suggestions-list li a:hover {
            color: var(--primary-hover);
            text-decoration: underline;
        }

        /* ============================================
           Button Group
           ============================================ */
        .button-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            border-radius: var(--radius-md);
            transition: all var(--transition-fast);
            cursor: pointer;
            border: none;
            font-family: inherit;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
            box-shadow: var(--shadow-sm);
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-outline {
            background-color: transparent;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-outline:hover {
            background-color: var(--bg-card-hover);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
            border-color: var(--primary);
        }

        .btn:active {
            transform: translateY(0);
        }

        /* ============================================
           Support Footer
           ============================================ */
        .support-footer {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid var(--border-color);
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .support-footer i {
            margin-right: 4px;
        }

        /* ============================================
           Responsive Design — Tablet (≤ 768px)
           ============================================ */
        @media (max-width: 768px) {
            .error-container {
                padding: 40px 32px;
            }

            .error-code {
                font-size: 5rem;
                letter-spacing: -2px;
            }

            .error-icon {
                font-size: 4rem;
            }

            h1 {
                font-size: 1.5rem;
            }

            .error-message {
                font-size: 0.95rem;
            }
        }

        /* ============================================
           Responsive Design — Mobile (≤ 576px)
           ============================================ */
        @media (max-width: 576px) {
            .error-container {
                padding: 32px 24px;
            }

            .error-code {
                font-size: 4rem;
            }

            .error-icon {
                font-size: 3rem;
                margin-bottom: 20px;
            }

            h1 {
                font-size: 1.25rem;
            }

            .error-message {
                font-size: 0.875rem;
                margin-bottom: 24px;
            }

            .btn {
                padding: 0.5rem 1rem;
                font-size: 0.8125rem;
            }

            .button-group {
                gap: 10px;
            }

            .suggestions-box {
                padding: 16px;
                margin-bottom: 24px;
            }

            .suggestions-title {
                font-size: 0.8125rem;
                margin-bottom: 12px;
            }

            .suggestions-list li {
                padding: 8px 0;
                font-size: 0.8125rem;
            }
        }

        /* ============================================
           Responsive Design — Extra Small (≤ 480px)
           ============================================ */
        @media (max-width: 480px) {
            .error-container {
                padding: 24px 20px;
            }

            .button-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .error-code {
                font-size: 3.5rem;
                margin-bottom: 12px;
            }

            .support-footer {
                font-size: 0.7rem;
                margin-top: 24px;
                padding-top: 20px;
            }
        }

        /* ============================================
           Animations
           ============================================ */
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <div class="error-container" role="alert" aria-labelledby="error-title">
        <div class="error-icon">
            <i class="fas fa-compass"></i>
        </div>
        <div class="error-code">404</div>
        <h1 id="error-title">Página não encontrada</h1>
        <div class="error-message">
            A página que você está procurando não existe, foi removida ou o endereço está incorreto.
        </div>

        <!-- Sugestões úteis -->
        <div class="suggestions-box">
            <div class="suggestions-title">
                <i class="fas fa-lightbulb"></i>
                <span>O que você pode fazer:</span>
            </div>
            <ul class="suggestions-list">
                <li>
                    <i class="fas fa-check-circle"></i>
                    <span>Verifique se o endereço digitado está correto</span>
                </li>
                <li>
                    <i class="fas fa-home"></i>
                    <span>Volte para a <a href="<?= url('/') ?>">página inicial</a> e navegue pelo menu</span>
                </li>
                <li>
                    <i class="fas fa-search"></i>
                    <span>Utilize a busca para encontrar o que procura</span>
                </li>
                <li>
                    <i class="fas fa-envelope"></i>
                    <span>Entre em contato com o <a href="mailto:suporte@taskflow.com">suporte</a> se precisar de
                        ajuda</span>
                </li>
            </ul>
        </div>

        <div class="button-group">
            <a href="<?= url('/') ?>" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Ir para o início
            </a>
            <button onclick="goBack()" class="btn btn-outline">
                <i class="fas fa-undo-alt"></i> Voltar
            </button>
        </div>

        <div class="support-footer">
            <i class="fas fa-info-circle"></i> Erro 404 • Verifique o endereço e tente novamente
        </div>
    </div>

    <script>
        // Função segura para voltar à página anterior
        function goBack() {
            try {
                // Verificar se há histórico para voltar
                if (document.referrer && document.referrer !== '') {
                    window.history.back();
                } else {
                    // Se não houver histórico, ir para o início
                    window.location.href = '<?= url('/') ?>';
                }
            } catch (error) {
                console.error('Erro ao voltar:', error);
                window.location.href = '<?= url('/') ?>';
            }
        }

        // Prevenir loop de redirecionamento em páginas de erro
        if (window.sessionStorage) {
            const errorCount = parseInt(sessionStorage.getItem('404ErrorCount') || '0');
            if (errorCount > 2) {
                // Se muitos erros 404, sugerir ir para o início
                console.log('Múltiplos erros 404 detectados');
            }
            sessionStorage.setItem('404ErrorCount', (errorCount + 1).toString());

            // Resetar contador após 30 segundos
            setTimeout(() => {
                sessionStorage.setItem('404ErrorCount', '0');
            }, 30000);
        }

        // Log para debug (opcional)
        console.log('TaskFlow - Página de erro 404 carregada');
        console.log('URL não encontrada:', window.location.href);
    </script>

    <?php if (function_exists('url')): ?>
        <script>
            // Configurar base URL se disponível
            window.baseUrl = '<?= rtrim(url('/'), '/') ?>';
        </script>
    <?php endif; ?>
</body>

</html>