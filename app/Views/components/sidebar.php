<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="<?= url('dashboard') ?>" class="sidebar-brand">
            <?= e(APP_NAME) ?>
        </a>
    </div>

    <ul class="sidebar-nav">
        <li class="sidebar-item <?= isActive('dashboard') ?>">
            <a href="<?= url('dashboard') ?>" class="sidebar-link">
                <span class="sidebar-icon">⊞</span>
                Dashboard
            </a>
        </li>

        <!-- Adicione itens de menu do seu projeto aqui -->
        <!-- Exemplo:
        <li class="sidebar-item <?= isActive('dashboard/posts') ?>">
            <a href="<?= url('dashboard/posts') ?>" class="sidebar-link">
                <span class="sidebar-icon">✏</span>
                Posts
            </a>
        </li>
        -->
    </ul>

    <div class="sidebar-footer">
        <a href="<?= url('auth/logout') ?>" class="sidebar-link sidebar-logout">
            <span class="sidebar-icon">↩</span>
            Sair
        </a>
    </div>
</nav>
