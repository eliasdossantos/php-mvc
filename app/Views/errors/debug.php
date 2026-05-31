<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $httpCode ?> — <?= e($httpMessage) ?> | Debug</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#0f172a;color:#e2e8f0;font-size:14px;line-height:1.6}
.header{background:linear-gradient(135deg,#7c3aed,#db2777);padding:24px 32px;display:flex;align-items:center;gap:16px}
.badge{background:rgba(255,255,255,.2);border-radius:8px;padding:6px 14px;font-size:28px;font-weight:900;font-family:monospace;color:#fff}
.header-text h1{color:#fff;font-size:20px;font-weight:700}
.header-text p{color:rgba(255,255,255,.75);font-size:13px;margin-top:2px}
.body{padding:28px 32px;max-width:1200px;margin:0 auto}
.card{background:#1e293b;border:1px solid #334155;border-radius:12px;margin-bottom:20px;overflow:hidden}
.card-header{background:#334155;padding:12px 20px;font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;display:flex;align-items:center;gap:8px}
.card-body{padding:20px}
.message{font-size:18px;font-weight:600;color:#f87171;padding:16px 20px;background:#1e293b;border-left:4px solid #ef4444;border-radius:0 8px 8px 0}
.meta-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px}
.meta-item{background:#0f172a;border-radius:8px;padding:12px}
.meta-label{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin-bottom:4px}
.meta-value{color:#e2e8f0;font-family:monospace;font-size:13px;word-break:break-all}
.source-wrap{overflow:auto;background:#0f172a;border-radius:8px}
table.source{width:100%;border-collapse:collapse;font-family:monospace;font-size:13px}
table.source tr.active{background:#422006}
table.source tr.active td.code{color:#fbbf24}
table.source td.ln{padding:4px 12px 4px 16px;text-align:right;color:#475569;user-select:none;min-width:52px;border-right:1px solid #1e293b}
table.source tr.active td.ln{color:#f59e0b}
table.source td.code{padding:4px 16px;white-space:pre;color:#94a3b8}
.trace-item{padding:14px 20px;border-bottom:1px solid #334155}
.trace-item:last-child{border-bottom:none}
.trace-file{color:#7dd3fc;font-family:monospace;font-size:13px}
.trace-fn{color:#86efac;font-family:monospace;font-size:12px;margin-top:3px}
.trace-num{display:inline-block;background:#334155;border-radius:4px;padding:2px 7px;font-size:11px;color:#94a3b8;margin-right:8px;font-family:monospace}
.env-table{width:100%;border-collapse:collapse}
.env-table tr:nth-child(even) td{background:#1a2942}
.env-table td{padding:7px 12px;font-family:monospace;font-size:12px;border-bottom:1px solid #1e293b}
.env-table td:first-child{color:#7dd3fc;min-width:240px}
.env-table td:last-child{color:#a5f3fc;word-break:break-all}
.tag{display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600}
.tag-red{background:#7f1d1d;color:#fca5a5}
.tag-blue{background:#1e3a5f;color:#93c5fd}
</style>
</head>
<body>

<div class="header">
    <div class="badge"><?= $httpCode ?></div>
    <div class="header-text">
        <h1><?= e(get_class($exception)) ?></h1>
        <p><?= e($httpMessage) ?> — <?= e($context['file'] ?? '') ?>:<?= (int)($context['line'] ?? 0) ?></p>
    </div>
</div>

<div class="body">

    <!-- Mensagem -->
    <div class="card">
        <div class="card-header">💥 Mensagem</div>
        <div class="card-body">
            <div class="message"><?= e($exception->getMessage()) ?></div>
        </div>
    </div>

    <!-- Metadados -->
    <div class="card">
        <div class="card-header">📋 Contexto</div>
        <div class="card-body">
            <div class="meta-grid">
                <div class="meta-item"><div class="meta-label">HTTP Code</div><div class="meta-value"><span class="tag tag-red"><?= $httpCode ?></span></div></div>
                <div class="meta-item"><div class="meta-label">Method</div><div class="meta-value"><?= e($context['method'] ?? '-') ?></div></div>
                <div class="meta-item"><div class="meta-label">URI</div><div class="meta-value"><?= e($context['uri'] ?? '-') ?></div></div>
                <div class="meta-item"><div class="meta-label">IP</div><div class="meta-value"><?= e($context['ip'] ?? '-') ?></div></div>
                <div class="meta-item"><div class="meta-label">Memory</div><div class="meta-value"><?= e($context['memory'] ?? '-') ?></div></div>
                <div class="meta-item"><div class="meta-label">Time</div><div class="meta-value"><?= e($context['time_ms'] ?? '-') ?> ms</div></div>
                <div class="meta-item"><div class="meta-label">PHP</div><div class="meta-value"><?= PHP_VERSION ?></div></div>
                <div class="meta-item"><div class="meta-label">Hora</div><div class="meta-value"><?= date('H:i:s') ?></div></div>
            </div>
        </div>
    </div>

    <!-- Código-fonte -->
    <?php if (!empty($source)): ?>
    <div class="card">
        <div class="card-header">📄 <?= e(str_replace(ROOT_PATH, '', $context['file'] ?? '')) ?>  <span class="tag tag-blue">linha <?= (int)($context['line'] ?? 0) ?></span></div>
        <div class="card-body" style="padding:0">
            <div class="source-wrap">
                <table class="source">
                    <?php foreach ($source as $ln => $code): ?>
                    <tr class="<?= $ln === (int)($context['line'] ?? 0) ? 'active' : '' ?>">
                        <td class="ln"><?= $ln ?></td>
                        <td class="code"><?= htmlspecialchars($code, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stack trace -->
    <div class="card">
        <div class="card-header">🔍 Stack Trace</div>
        <div>
            <?php foreach ($exception->getTrace() as $i => $frame): ?>
            <div class="trace-item">
                <span class="trace-num">#<?= $i ?></span>
                <span class="trace-file"><?= e(str_replace(ROOT_PATH, '', $frame['file'] ?? '[internal]')) ?>:<?= (int)($frame['line'] ?? 0) ?></span>
                <div class="trace-fn">→ <?= e(($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '')) ?>()</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- $_SERVER resumido -->
    <div class="card">
        <div class="card-header">🌐 $_SERVER (resumo)</div>
        <div class="card-body" style="padding:0">
            <table class="env-table">
                <?php
                $show = ['REQUEST_METHOD','REQUEST_URI','HTTP_HOST','REMOTE_ADDR','SERVER_SOFTWARE','PHP_SELF','CONTENT_TYPE','HTTP_USER_AGENT'];
                foreach ($show as $k): if (!isset($_SERVER[$k])) continue; ?>
                <tr><td><?= e($k) ?></td><td><?= e($_SERVER[$k]) ?></td></tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

</div>
</body>
</html>
