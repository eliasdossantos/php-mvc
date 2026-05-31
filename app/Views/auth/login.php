<form method="POST" action="<?= url('auth/login') ?>">
    <?= csrf_field() ?>

    <div class="form-group">
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email"
               value="<?= old('email') ?>"
               class="form-control <?= hasError('email') ? 'is-invalid' : '' ?>"
               required autofocus>
        <?php if (hasError('email')): ?>
            <span class="form-error"><?= error('email') ?></span>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="password">Senha</label>
        <input type="password" id="password" name="password"
               class="form-control"
               required>
    </div>

    <button type="submit" class="btn btn-primary btn-block">Entrar</button>

    <div class="auth-links">
        <a href="<?= url('auth/forgot-password') ?>">Esqueci minha senha</a>
        &nbsp;·&nbsp;
        <a href="<?= url('auth/register') ?>">Criar conta</a>
    </div>
</form>
