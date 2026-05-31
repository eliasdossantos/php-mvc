<div class="page-header">
    <h1>Olá, <?= e($user?->name ?? 'Usuário') ?>!</h1>
    <p class="page-subtitle">Bem-vindo ao dashboard.</p>
</div>

<div class="card-grid">
    <div class="card">
        <div class="card-body">
            <h3 class="card-title">Primeiros Passos</h3>
            <p>Adicione seus controllers em <code>app/Controllers/</code>,
               seus models em <code>app/Models/</code>
               e registre as rotas em <code>routes/web.php</code>.</p>
        </div>
    </div>
</div>
