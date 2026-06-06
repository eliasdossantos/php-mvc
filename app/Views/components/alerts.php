<?php
// Exibe flash messages. Suporta: success, error, warning, info
$alertTypes = ['success', 'error', 'warning', 'info'];
foreach ($alertTypes as $alertType):
    $alertMsg = \Core\Session::getFlash($alertType);
    if (!$alertMsg) continue;
?>
<div class="alert alert-<?= $alertType ?>" role="alert" aria-live="polite">
    <div class="alert-body"><?= e($alertMsg) ?></div>
    <button
        class="alert-close"
        onclick="var el=this.parentElement;el.style.transition='opacity .3s ease';el.style.opacity='0';setTimeout(function(){el.remove()},300)"
        aria-label="Fechar"
    >&#x2715;</button>
</div>
<?php endforeach; ?>
