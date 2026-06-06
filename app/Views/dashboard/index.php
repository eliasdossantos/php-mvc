<?php
$dashUser = function_exists('user') ? user() : null;
$dashName = $dashUser->name ?? 'Usu&#225;rio';
$dashFirst = explode(' ', trim($dashName))[0];
?>

<!-- Banner de boas-vindas -->
<div class="welcome-banner">
    <div class="welcome-text">
        <div class="welcome-greeting">Painel de Controle</div>
        <div class="welcome-name">Ol&#225;, <?= e($dashFirst) ?>!</div>
        <div class="welcome-desc">Bem-vindo de volta. Aqui est&#225; um resumo da sua conta.</div>
    </div>
    <div class="welcome-emoji">&#128640;</div>
</div>

<!-- Cards de estatísticas -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon indigo">&#128202;</div>
        <div>
            <div class="stat-label">Total de Registros</div>
            <div class="stat-value">0</div>
            <div class="stat-change up">&#8593; Come&#231;ando agora</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">&#9989;</div>
        <div>
            <div class="stat-label">Tarefas Conclu&#237;das</div>
            <div class="stat-value">0</div>
            <div class="stat-change up">&#8593; &#211;timo in&#237;cio</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber">&#9201;</div>
        <div>
            <div class="stat-label">Em Andamento</div>
            <div class="stat-value">0</div>
            <div class="stat-change" style="color:var(--text-muted)">&#8594; Sem pend&#234;ncias</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon rose">&#128276;</div>
        <div>
            <div class="stat-label">Notifica&#231;&#245;es</div>
            <div class="stat-value">0</div>
            <div class="stat-change" style="color:var(--text-muted)">&#8594; Tudo em dia</div>
        </div>
    </div>
</div>

<!-- Card de primeiros passos -->
<div class="card">
    <div class="card-header">
        <span class="card-title">&#127959; Primeiros Passos</span>
        <span class="badge badge-primary">Guia r&#225;pido</span>
    </div>
    <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px">

            <div style="padding:18px;background:var(--slate-50);border-radius:var(--radius);border:1px solid var(--border)">
                <div style="font-size:24px;margin-bottom:10px">&#128193;</div>
                <div style="font-weight:600;margin-bottom:6px;font-size:14px">Controllers</div>
                <p style="font-size:13px;color:var(--text-muted);line-height:1.5">
                    Adicione seus controllers em <code>app/Controllers/</code>.
                </p>
            </div>

            <div style="padding:18px;background:var(--slate-50);border-radius:var(--radius);border:1px solid var(--border)">
                <div style="font-size:24px;margin-bottom:10px">&#128452;</div>
                <div style="font-weight:600;margin-bottom:6px;font-size:14px">Models</div>
                <p style="font-size:13px;color:var(--text-muted);line-height:1.5">
                    Crie seus models em <code>app/Models/</code>.
                </p>
            </div>

            <div style="padding:18px;background:var(--slate-50);border-radius:var(--radius);border:1px solid var(--border)">
                <div style="font-size:24px;margin-bottom:10px">&#128739;</div>
                <div style="font-weight:600;margin-bottom:6px;font-size:14px">Rotas</div>
                <p style="font-size:13px;color:var(--text-muted);line-height:1.5">
                    Registre suas rotas em <code>routes/web.php</code>.
                </p>
            </div>

        </div>

        <div style="margin-top:20px;padding-top:20px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
            <p style="font-size:13.5px;color:var(--text-muted)">
                Autenticado como <strong><?= e($dashUser->name ?? 'Usu&#225;rio') ?></strong>
                &mdash; <code><?= e($dashUser->email ?? '') ?></code>
            </p>
            <a href="<?= url('auth/logout') ?>" class="btn btn-secondary btn-sm" data-confirm="Deseja sair?">
                Sair da conta
            </a>
        </div>
    </div>
</div>
