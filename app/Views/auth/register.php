<div class="auth-title">Criar sua conta</div>
<p class="auth-subtitle">Preencha os dados abaixo para come&#231;ar</p>

<form method="POST" action="<?= url('auth/register') ?>" novalidate>
    <?= csrf_field() ?>

    <div class="form-group">
        <label class="form-label" for="name">Nome completo</label>
        <input
            type="text"
            id="name"
            name="name"
            value="<?= old('name') ?>"
            class="form-control <?= hasError('name') ? 'is-invalid' : '' ?>"
            placeholder="Seu nome completo"
            required
            autofocus
            autocomplete="name"
        >
        <?php if (hasError('name')): ?>
            <span class="form-error"><?= error('name') ?></span>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label class="form-label" for="email">E-mail</label>
        <input
            type="email"
            id="email"
            name="email"
            value="<?= old('email') ?>"
            class="form-control <?= hasError('email') ? 'is-invalid' : '' ?>"
            placeholder="seu@email.com"
            required
            autocomplete="email"
        >
        <?php if (hasError('email')): ?>
            <span class="form-error"><?= error('email') ?></span>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label class="form-label" for="password">Senha</label>
        <div class="input-password-wrap">
            <input
                type="password"
                id="password"
                name="password"
                class="form-control <?= hasError('password') ? 'is-invalid' : '' ?>"
                placeholder="M&#237;nimo 8 caracteres"
                required
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
        <label class="form-label" for="password_confirmation">Confirmar senha</label>
        <div class="input-password-wrap">
            <input
                type="password"
                id="password_confirmation"
                name="password_confirmation"
                class="form-control"
                placeholder="Repita a senha"
                required
                autocomplete="new-password"
            >
            <button type="button" class="password-toggle" aria-label="Mostrar senha">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
        </div>
        <span class="form-hint">As senhas devem ser id&#234;nticas.</span>
    </div>

    <button type="submit" class="btn btn-primary btn-block btn-lg">
        <span class="btn-text">Criar minha conta</span>
    </button>

    <div class="auth-links">
        J&#225; tem uma conta?
        <a href="<?= url('auth/login') ?>">Fazer login</a>
    </div>

</form>
