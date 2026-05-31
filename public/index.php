<?php

/**
 * Ponto de entrada único da aplicação
 * ─────────────────────────────────────────────────────────────────────────────
 * TODAS as requisições HTTP chegam aqui via .htaccess (mod_rewrite).
 *
 * Este arquivo é intencionalmente mínimo:
 *   - Não contém lógica de negócio
 *   - Apenas inicializa o bootstrap e dispara a aplicação
 */

$app = require __DIR__ . '/../bootstrap/app.php';
$app->run();
