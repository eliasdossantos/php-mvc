<header class="topbar">
    <button class="topbar-toggle" id="sidebarToggle">☰</button>

    <h2 class="topbar-title"><?= e($title ?? '') ?></h2>

    <div class="topbar-user">
        <span class="topbar-name"><?= e(user()?->name ?? '') ?></span>
    </div>
</header>
