<style>
    .alert-close {
        background: none;
        border: none;
        padding: 0;
        margin-left: 12px;
        font-size: 16px;
        line-height: 1;
        color: inherit;
        opacity: 0.6;
        cursor: pointer;
    }

    .alert-close:hover {
        opacity: 1;
    }

    .alert-close:focus {
        outline: none;
        opacity: 1;
    }
</style>

<?php
// Exibe flash messages + erros de validação acumulados
$alertTypes = ['success', 'error', 'warning', 'info'];

$bootstrapClass = [
    'success' => 'success',
    'error'   => 'danger',
    'warning' => 'warning',
    'info'    => 'info',
];

foreach ($alertTypes as $alertType):
    // Pega a mensagem simples do flash
    $alertMsg = \Core\Session::getFlash($alertType);

    // Se for tipo 'error', junta com os erros vindos do Validator ($_SESSION['_errors'])
    $validationErrors = ($alertType === 'error') ? (\Core\Session::get('_errors', [])) : [];

    // Se não tiver mensagem flash nem erros de validação, ignora
    if (!$alertMsg && empty($validationErrors)) {
        continue;
    }

    // Monta o array final de mensagens deste tipo
    $messages = [];

    if ($alertMsg) {
        if (is_array($alertMsg)) {
            $messages = array_merge($messages, $alertMsg);
        } else {
            $messages[] = $alertMsg;
        }
    }

    if (!empty($validationErrors)) {
        foreach ($validationErrors as $fieldErrors) {
            if (is_array($fieldErrors)) {
                foreach ($fieldErrors as $err) {
                    $messages[] = $err;
                }
            } else {
                $messages[] = $fieldErrors;
            }
        }
    }

    // Evita duplicados idênticos
    $messages = array_unique($messages);

    $cssClass = $bootstrapClass[$alertType] ?? $alertType;
?>
    <div class="alert alert-<?= $cssClass ?> alert-dismissible fade show d-flex justify-content-between align-items-start mb-3"
        role="alert" aria-live="polite">
        <div class="alert-body mb-0 w-100">
            <?php if (count($messages) > 1): ?>
                <ul class="mb-0 ps-3">
                    <?php foreach ($messages as $msg): ?>
                        <li><?= e($msg) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <?= e(reset($messages)) ?>
            <?php endif; ?>
        </div>
        <button type="button" class="alert-close mt-1"
            onclick="var el=this.parentElement;el.style.transition='opacity .3s ease';el.style.opacity='0';setTimeout(function(){el.remove()},300)"
            aria-label="Fechar">&#x2715;</button>
    </div>
<?php endforeach; ?>