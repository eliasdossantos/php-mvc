/**
 * PHP MVC — Redesign JS
 * Funcionalidades: sidebar mobile, password toggle, strength meter,
 * validação visual, auto-dismiss alerts, navbar scroll, animações.
 */

document.addEventListener('DOMContentLoaded', () => {

    // ── CSRF token global ─────────────────────────────────────────────────────
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    window.apiFetch = (url, options = {}) => fetch(url, {
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
            ...options.headers,
        },
        ...options,
    }).then(r => r.json());

    // ── Sidebar toggle (mobile) ───────────────────────────────────────────────
    const sidebarToggle  = document.getElementById('sidebarToggle');
    const sidebar        = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    function openSidebar() {
        sidebar?.classList.add('open');
        sidebarOverlay?.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar?.classList.remove('open');
        sidebarOverlay?.classList.remove('show');
        document.body.style.overflow = '';
    }

    sidebarToggle?.addEventListener('click', () => {
        sidebar?.classList.contains('open') ? closeSidebar() : openSidebar();
    });

    sidebarOverlay?.addEventListener('click', closeSidebar);

    // Fecha sidebar ao redimensionar para desktop
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) closeSidebar();
    });

    // ── Navbar scroll effect (home page) ─────────────────────────────────────
    const homeNav = document.getElementById('homeNav');
    if (homeNav) {
        const onScroll = () => {
            homeNav.classList.toggle('scrolled', window.scrollY > 40);
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    // ── Password toggle (mostrar/ocultar senha) ───────────────────────────────
    document.querySelectorAll('.password-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const wrap  = btn.closest('.input-password-wrap');
            const input = wrap?.querySelector('input');
            if (!input) return;

            const isText = input.type === 'text';
            input.type   = isText ? 'password' : 'text';
            btn.innerHTML = isText
                ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'
                : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
            btn.setAttribute('aria-label', isText ? 'Mostrar senha' : 'Ocultar senha');
        });
    });

    // ── Password strength meter ───────────────────────────────────────────────
    const passwordInput    = document.getElementById('password');
    const strengthWrap     = document.querySelector('.password-strength');
    const strengthFill     = document.querySelector('.strength-fill');
    const strengthText     = document.querySelector('.strength-text');
    const strengthMessages = ['', 'Muito fraca', 'Fraca', 'Razoável', 'Boa', 'Forte'];
    const strengthColors   = ['', '#ef4444', '#f97316', '#f59e0b', '#10b981', '#059669'];

    function calcStrength(pw) {
        let score = 0;
        if (pw.length >= 8)  score++;
        if (pw.length >= 12) score++;
        if (/[A-Z]/.test(pw)) score++;
        if (/[0-9]/.test(pw)) score++;
        if (/[^A-Za-z0-9]/.test(pw)) score++;
        return Math.min(score, 5);
    }

    if (passwordInput && strengthWrap) {
        passwordInput.addEventListener('input', () => {
            const pw = passwordInput.value;
            if (!pw) {
                strengthWrap.style.display = 'none';
                return;
            }
            strengthWrap.style.display = 'block';
            const score = calcStrength(pw);
            strengthFill.style.width      = (score / 5 * 100) + '%';
            strengthFill.style.background = strengthColors[score];
            strengthText.textContent      = strengthMessages[score];
            strengthText.style.color      = strengthColors[score];
        });
    }

    // ── Confirmação de senha em tempo real ────────────────────────────────────
    const confirmInput = document.getElementById('password_confirmation') || document.getElementById('confirm');
    if (confirmInput && passwordInput) {
        const checkMatch = () => {
            if (!confirmInput.value) {
                confirmInput.classList.remove('is-invalid', 'is-valid');
                return;
            }
            const match = confirmInput.value === passwordInput.value;
            confirmInput.classList.toggle('is-valid',   match);
            confirmInput.classList.toggle('is-invalid', !match);
        };
        confirmInput.addEventListener('input', checkMatch);
        passwordInput.addEventListener('input', () => {
            if (confirmInput.value) checkMatch();
        });
    }

    // ── Auto-dismiss de alertas ───────────────────────────────────────────────
    document.querySelectorAll('.alert').forEach(alert => {
        // Progress bar de auto-dismiss
        const duration = 5000;
        setTimeout(() => {
            alert.style.transition = 'opacity .4s ease, transform .4s ease';
            alert.style.opacity    = '0';
            alert.style.transform  = 'translateY(-6px)';
            setTimeout(() => alert.remove(), 400);
        }, duration);
    });

    // ── Confirmação de ações destrutivas ─────────────────────────────────────
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', e => {
            if (!confirm(el.dataset.confirm || 'Tem certeza?')) e.preventDefault();
        });
    });

    // ── Submit do form pai via data-submit-form ───────────────────────────────
    document.querySelectorAll('[data-submit-form]').forEach(btn => {
        btn.addEventListener('click', () => btn.closest('form')?.submit());
    });

    // ── Loading state em botões de submit ────────────────────────────────────
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', () => {
            const submitBtn = form.querySelector('[type="submit"]');
            if (submitBtn && !submitBtn.dataset.noLoader) {
                submitBtn.classList.add('btn-loading');
                submitBtn.disabled = true;
                // Safety: remove loading após 8s para evitar travamento
                setTimeout(() => {
                    submitBtn.classList.remove('btn-loading');
                    submitBtn.disabled = false;
                }, 8000);
            }
        });
    });

    // ── Scroll reveal (Intersection Observer) ────────────────────────────────
    const revealEls = document.querySelectorAll('[data-reveal]');
    if (revealEls.length) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('revealed');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

        revealEls.forEach(el => observer.observe(el));
    }

    // ── Smooth scroll para âncoras ────────────────────────────────────────────
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', e => {
            const target = document.querySelector(anchor.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // ── Highlight menu ativo na sidebar ──────────────────────────────────────
    const currentPath = window.location.pathname;
    document.querySelectorAll('.sidebar-link').forEach(link => {
        const href = link.getAttribute('href');
        if (href && href !== '/' && currentPath.startsWith(href)) {
            link.closest('.sidebar-item')?.classList.add('active');
        }
    });

    // ── Inicializa avatar com iniciais do nome ────────────────────────────────
    document.querySelectorAll('[data-initials]').forEach(el => {
        const name = el.dataset.initials || '';
        const parts = name.trim().split(' ').filter(Boolean);
        el.textContent = parts.length >= 2
            ? (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
            : (parts[0]?.[0] || '?').toUpperCase();
    });

});
