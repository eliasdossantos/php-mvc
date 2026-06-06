<?php
$sidebarUser = function_exists('user') ? user() : null;
$sidebarName = $sidebarUser->name ?? 'Usuário';
$sidebarEmail = $sidebarUser->email ?? '';
$nameParts = explode(' ', trim($sidebarName));
$sidebarInitials = count($nameParts) >= 2
    ? strtoupper($nameParts[0][0] . $nameParts[count($nameParts)-1][0])
    : strtoupper(substr($nameParts[0] ?? '?', 0, 1));
?>
<nav class="sidebar" id="sidebar" aria-label="Menu principal">

    <div class="sidebar-header">
        <a href="<?= url('dashboard') ?>" class="sidebar-brand">
            <div class="brand-icon">&#9889;</div>
            <?= e(APP_NAME) ?>
        </a>
    </div>

    <div class="sidebar-section-label">Menu</div>

    <ul class="sidebar-nav">
        <li class="sidebar-item <?= isActive('dashboard') ?>">
            <a href="<?= url('dashboard') ?>" class="sidebar-link">
                <span class="sidebar-icon">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                </span>
                Dashboard
            </a>
        </li>
        <?php /* Adicione seus itens de menu aqui */ ?>
    </ul>

    <div class="sidebar-footer">
        <?php if ($sidebarUser): ?>
        <div class="sidebar-user">
            <div class="sidebar-user-avatar"><?= e($sidebarInitials) ?></div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= e($sidebarName) ?></div>
                <div class="sidebar-user-role"><?= e($sidebarEmail) ?></div>
            </div>
        </div>
        <?php endif; ?>
        <a href="<?= url('auth/logout') ?>" class="sidebar-logout" data-confirm="Deseja sair?">
            <span class="sidebar-icon">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
            </span>
            Sair
        </a>
    </div>

</nav>
