<?php
// Exibe flash messages de sessão. Suporta: success, error, warning, info
$types = ['success', 'error', 'warning', 'info'];
foreach ($types as $type):
    $msg = \Core\Session::getFlash($type);
    if (!$msg) continue;
?>
<div class="alert alert-<?= $type ?>" role="alert">
    <?= e($msg) ?>
    <button class="alert-close" onclick="this.parentElement.remove()">×</button>
</div>
<?php endforeach; ?>
