<form method="POST" action="<?= url('auth/register') ?>">
    <?= csrf_field() ?>

    <div class="form-group">
        <label for="name">Nome</label>
        <input type="text" id="name" name="name"
               value="<?= old('name') ?>"
               class="form-control <?= hasError('name') ? 'is-invalid' : '' ?>"
               required autofocus>
        <?php if (hasError('name')): ?>
            <span class="form-error"><?= error('name') ?></span>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email"
               value="<?= old('email') ?>"
               class="form-control <?= hasError('email') ? 'is-invalid' : '' ?>"
               required>
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

    <div class="form-group">
        <label for="password_confirmation">Confirmar Senha</label>
        <input type="password" id="password_confirmation" name="password_confirmation"
               class="form-control"
               required>
    </div>

    <button type="submit" class="btn btn-primary btn-block">Criar Conta</button>

    <div class="auth-links">
        <a href="<?= url('auth/login') ?>">Já tenho uma conta</a>
    </div>
</form>
