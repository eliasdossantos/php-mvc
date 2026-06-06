<div class="auth-title">Esqueceu a senha?</div>
<p class="auth-subtitle">
    Informe seu e-mail e enviaremos um link para criar uma nova senha.
</p>

<form method="POST" action="<?= url('auth/forgot-password') ?>" novalidate>
    <?= csrf_field() ?>

    <div class="auth-description">
        O link de recupera&#231;&#227;o expira em <strong>1 hora</strong>. Verifique tamb&#233;m a pasta de spam.
    </div>

    <div class="form-group">
        <label class="form-label" for="email">E-mail cadastrado</label>
        <input
            type="email"
            id="email"
            name="email"
            value="<?= old('email') ?>"
            class="form-control <?= hasError('email') ? 'is-invalid' : '' ?>"
            placeholder="seu@email.com"
            required
            autofocus
            autocomplete="email"
        >
        <?php if (hasError('email')): ?>
            <span class="form-error"><?= error('email') ?></span>
        <?php endif; ?>
    </div>

    <button type="submit" class="btn btn-primary btn-block btn-lg">
        <span class="btn-text">Enviar link de recupera&#231;&#227;o</span>
    </button>

    <div class="auth-links">
        <a href="<?= url('auth/login') ?>" class="auth-back-link">
            &#8592; Voltar ao login
        </a>
    </div>

</form>
