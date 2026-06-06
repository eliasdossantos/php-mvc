<!-- ── Navbar ─────────────────────────────────────────────────────────────── -->
<nav class="home-nav" id="homeNav">
    <div class="nav-inner">
        <a href="<?= url('/') ?>" class="nav-brand">
            <div class="nav-brand-icon">&#9889;</div>
            <?= e(APP_NAME) ?>
        </a>
        <div class="nav-actions">
            <a href="<?= url('auth/login') ?>" class="nav-btn-ghost">Entrar</a>
            <a href="<?= url('auth/register') ?>" class="nav-btn-primary">
                Criar Conta
            </a>
        </div>
    </div>
</nav>

<!-- ── Hero ───────────────────────────────────────────────────────────────── -->
<section class="home-hero">
    <div class="hero-content">

        <div class="hero-badge">
            <span class="dot"></span>
            Novo &middot; Vers&#227;o 2.0 dispon&#237;vel agora
        </div>

        <h1 class="hero-title">
            Construa aplica&#231;&#245;es<br>
            <span class="gradient-text">r&#225;pidas e seguras</span>
        </h1>

        <p class="hero-desc">
            Uma base s&#243;lida em PHP MVC para criar produtos digitais
            com qualidade profissional, sem perder tempo com configura&#231;&#227;o.
        </p>

        <div class="hero-cta">
            <a href="<?= url('auth/register') ?>" class="btn-hero-primary">
                Come&#231;ar gratuitamente &#8594;
            </a>
            <a href="<?= url('auth/login') ?>" class="btn-hero-ghost">
                J&#225; tenho conta
            </a>
        </div>

        <div class="hero-stats">
            <div class="hero-stat">
                <div class="hero-stat-value">99.9%</div>
                <div class="hero-stat-label">Uptime garantido</div>
            </div>
            <div class="hero-stat">
                <div class="hero-stat-value">&lt;50ms</div>
                <div class="hero-stat-label">Tempo de resposta</div>
            </div>
            <div class="hero-stat">
                <div class="hero-stat-value">100%</div>
                <div class="hero-stat-label">C&#243;digo aberto</div>
            </div>
            <div class="hero-stat">
                <div class="hero-stat-value">A+</div>
                <div class="hero-stat-label">Nota de seguran&#231;a</div>
            </div>
        </div>

    </div>
</section>

<!-- ── Features ───────────────────────────────────────────────────────────── -->
<section class="home-features">
    <div class="section-inner">

        <div class="section-label">Recursos</div>
        <h2 class="section-title">Tudo pronto para usar</h2>
        <p class="section-desc">
            Um conjunto completo de ferramentas para criar
            aplica&#231;&#245;es profissionais sem retrabalho.
        </p>

        <div class="features-grid">

            <div class="feature-card" data-reveal>
                <div class="feature-icon">&#128272;</div>
                <div class="feature-title">Autentica&#231;&#227;o completa</div>
                <p class="feature-desc">Login, cadastro, recupera&#231;&#227;o de senha e CSRF prontos para produ&#231;&#227;o.</p>
            </div>

            <div class="feature-card" data-reveal>
                <div class="feature-icon">&#127959;</div>
                <div class="feature-title">Arquitetura MVC</div>
                <p class="feature-desc">Separa&#231;&#227;o clara entre Models, Views e Controllers com roteamento flex&#237;vel.</p>
            </div>

            <div class="feature-card" data-reveal>
                <div class="feature-icon">&#128737;</div>
                <div class="feature-title">Seguran&#231;a por padr&#227;o</div>
                <p class="feature-desc">Prote&#231;&#227;o contra CSRF, XSS e SQL Injection com valida&#231;&#227;o robusta.</p>
            </div>

            <div class="feature-card" data-reveal>
                <div class="feature-icon">&#128230;</div>
                <div class="feature-title">ORM e Migrations</div>
                <p class="feature-desc">Abstra&#231;&#227;o de banco de dados com migrations e seeders.</p>
            </div>

            <div class="feature-card" data-reveal>
                <div class="feature-icon">&#128231;</div>
                <div class="feature-title">Envio de e-mails</div>
                <p class="feature-desc">Integra&#231;&#227;o com PHPMailer para e-mails transacionais responsivos.</p>
            </div>

            <div class="feature-card" data-reveal>
                <div class="feature-icon">&#128241;</div>
                <div class="feature-title">100% Responsivo</div>
                <p class="feature-desc">Interface adaptada para qualquer tela, do celular ao desktop.</p>
            </div>

        </div>
    </div>
</section>

<!-- ── CTA ────────────────────────────────────────────────────────────────── -->
<section class="home-cta-section">
    <div class="section-inner">
        <div class="section-label" style="text-align:center">Comece agora</div>
        <h2 class="section-title" style="text-align:center;margin-bottom:14px">Pronto para come&#231;ar?</h2>
        <p class="section-desc" style="text-align:center;margin:0 auto 36px">
            Crie sua conta gratuitamente e come&#231;e a construir em minutos.
        </p>
        <div style="display:flex;justify-content:center;gap:14px;flex-wrap:wrap">
            <a href="<?= url('auth/register') ?>" class="btn-hero-primary">Criar conta gr&#225;tis &#8594;</a>
            <a href="<?= url('auth/login') ?>" class="btn-hero-ghost">Fazer login</a>
        </div>
    </div>
</section>

<!-- ── Footer ─────────────────────────────────────────────────────────────── -->
<footer class="home-footer">
    <p>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. Feito com &#10084;&#65039; em PHP.</p>
</footer>
