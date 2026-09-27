<div class="auth-title">Criar nova senha</div>
<p class="auth-subtitle">
    Escolha uma senha forte e diferente das anteriores.
</p>

<form method="POST" action="<?= url('auth/reset-password') ?>" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token ?? '') ?>">

    <div class="form-group">
        <label class="form-label" for="password">Nova senha</label>
        <div class="input-password-wrap">
            <input
                type="password"
                id="password"
                name="password"
                class="form-control <?= hasError('password') ? 'is-invalid' : '' ?>"
                placeholder="M&#237;nimo 8 caracteres"
                required
                autofocus
                minlength="8"
                autocomplete="new-password"
            >
            <button type="button" class="password-toggle" aria-label="Mostrar senha">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
        </div>
        <div class="password-strength">
            <div class="strength-bar"><div class="strength-fill"></div></div>
            <span class="strength-text"></span>
        </div>
        <?php if (hasError('password')): ?>
            <span class="form-error"><?= error('password') ?></span>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label class="form-label" for="confirm">Confirmar nova senha</label>
        <div class="input-password-wrap">
            <input
                type="password"
                id="confirm"
                name="confirm"
                class="form-control"
                placeholder="Repita a nova senha"
                required
                minlength="8"
                autocomplete="new-password"
            >
            <button type="button" class="password-toggle" aria-label="Mostrar senha">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
        </div>
        <span class="form-hint">As senhas devem ser id&#234;nticas.</span>
    </div>

    <button type="submit" class="btn btn-primary btn-block btn-lg">
        <span class="btn-text">Redefinir senha</span>
    </button>

    <div class="auth-links">
        <a href="<?= url('auth/login') ?>" class="auth-back-link">
            &#8592; Voltar ao login
        </a>
    </div>

</form>
