<div class="auth-title">Bem-vindo de volta</div>
<p class="auth-subtitle">Entre com sua conta para continuar</p>

<form method="POST" action="<?= url('auth/login') ?>" novalidate>
    <?= csrf_field() ?>

    <div class="form-group">
        <label class="form-label" for="email">E-mail</label>
        <div class="form-input-wrap">
            <span class="form-input-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                    <polyline points="22,6 12,12 2,6" />
                </svg>
            </span>
            <input type="email" id="email" name="email" value="<?= old('email') ?>"
                class="form-control has-icon <?= hasError('email') ? 'is-invalid' : '' ?>" placeholder="seu@email.com"
                required autofocus autocomplete="email">
        </div>
        <?php if (hasError('email')): ?>
            <span class="form-error"><?= error('email') ?></span>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label class="form-label" for="password">Senha</label>
        <div class="input-password-wrap">
            <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required
                autocomplete="current-password">
            <button type="button" class="password-toggle" aria-label="Mostrar senha">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                    <circle cx="12" cy="12" r="3" />
                </svg>
            </button>
        </div>
    </div>

    <div class="auth-row">
        <label class="remember-label">
            <input type="checkbox" name="remember" value="1">
            <span>Lembrar de mim</span>
        </label>
        <a href="<?= url('auth/forgot-password') ?>" class="auth-forgot-link">Esqueci minha senha</a>
    </div>

    <button type="submit" class="btn btn-primary btn-block btn-lg">
        <span class="btn-text">Entrar na conta</span>
    </button>

    <div class="auth-divider">ou</div>

    <div class="auth-links">
        Não tem uma conta?
        <a href="<?= url('auth/register') ?>">Criar conta grátis</a>
    </div>

</form>