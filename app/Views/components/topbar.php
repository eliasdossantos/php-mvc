<?php
$topbarUser = function_exists('user') ? user() : null;
$topbarName = $topbarUser->name ?? 'Usuário';
$topbarParts = explode(' ', trim($topbarName));
$topbarInitials = count($topbarParts) >= 2
    ? strtoupper($topbarParts[0][0] . $topbarParts[count($topbarParts)-1][0])
    : strtoupper(substr($topbarParts[0] ?? '?', 0, 1));
$topbarFirst = $topbarParts[0] ?? 'Usuário';
?>
<header class="topbar" role="banner">

    <button class="topbar-toggle" id="sidebarToggle" aria-label="Abrir menu">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>

    <div class="topbar-breadcrumb">
        <h1 class="topbar-title"><?= e($title ?? 'Dashboard') ?></h1>
    </div>

    <div class="topbar-actions">
        <div class="topbar-divider"></div>
        <div class="topbar-user">
            <div class="topbar-avatar"><?= e($topbarInitials) ?></div>
            <span class="topbar-name"><?= e($topbarFirst) ?></span>
        </div>
    </div>

</header>
