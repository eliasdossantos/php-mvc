/**
 * PHP MVC Boilerplate — app.js
 * JavaScript genérico do boilerplate.
 * Adicione o JS específico do seu projeto aqui ou crie arquivos separados.
 */

document.addEventListener('DOMContentLoaded', () => {

    // ── Sidebar toggle (mobile) ───────────────────────────────────────────────
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar       = document.getElementById('sidebar');

    sidebarToggle?.addEventListener('click', () => {
        sidebar?.classList.toggle('open');
    });

    // Fecha sidebar ao clicar fora (mobile)
    document.addEventListener('click', (e) => {
        if (sidebar?.classList.contains('open') &&
            !sidebar.contains(e.target) &&
            e.target !== sidebarToggle) {
            sidebar.classList.remove('open');
        }
    });

    // ── CSRF token para fetch/AJAX ────────────────────────────────────────────
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    window.apiFetch = (url, options = {}) => {
        return fetch(url, {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                ...options.headers,
            },
            ...options,
        }).then(res => res.json());
    };

    // ── Auto-dismiss de alertas ───────────────────────────────────────────────
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => alert.remove(), 5000);
    });

    // ── Confirmação de ações destrutivas ─────────────────────────────────────
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', (e) => {
            const msg = el.dataset.confirm || 'Tem certeza?';
            if (!confirm(msg)) e.preventDefault();
        });
    });

    // ── Submit do form pai ao clicar em botão com data-submit-form ───────────
    document.querySelectorAll('[data-submit-form]').forEach(btn => {
        btn.addEventListener('click', () => {
            btn.closest('form')?.submit();
        });
    });

});
