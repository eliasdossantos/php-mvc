<?php
/**
 * Componente de Paginação
 * Uso: \Core\View::render('components.pagination', ['data' => $paginatedResult]);
 * $data deve ter: page, last_page, total, from, to
 */
if (empty($data) || ($data['last_page'] ?? 1) <= 1) return;
$page     = (int) ($data['page']      ?? 1);
$lastPage = (int) ($data['last_page'] ?? 1);
$total    = (int) ($data['total']     ?? 0);
$from     = (int) ($data['from']      ?? 0);
$to       = (int) ($data['to']        ?? 0);
$qs       = $_GET;
?>
<div class="pagination">
    <span class="pagination-info">
        Exibindo <?= $from ?>–<?= $to ?> de <?= $total ?> registros
    </span>
    <div class="pagination-links">
        <?php if ($page > 1): $qs['page'] = $page - 1; ?>
            <a href="?<?= http_build_query($qs) ?>" class="pagination-btn">← Anterior</a>
        <?php endif; ?>

        <?php
        $start = max(1, $page - 2);
        $end   = min($lastPage, $page + 2);
        for ($i = $start; $i <= $end; $i++):
            $qs['page'] = $i;
        ?>
            <a href="?<?= http_build_query($qs) ?>"
               class="pagination-btn <?= $i === $page ? 'pagination-btn--active' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>

        <?php if ($page < $lastPage): $qs['page'] = $page + 1; ?>
            <a href="?<?= http_build_query($qs) ?>" class="pagination-btn">Próxima →</a>
        <?php endif; ?>
    </div>
</div>
