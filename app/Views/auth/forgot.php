<form method="POST" action="<?= url('auth/forgot-password') ?>">
    <?= csrf_field() ?>

    <p class="auth-description">Informe seu e-mail para receber as instruções de recuperação de senha.</p>

    <div class="form-group">
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email"
               value="<?= old('email') ?>"
               class="form-control"
               required autofocus>
    </div>

    <button type="submit" class="btn btn-primary btn-block">Enviar Instruções</button>

    <div class="auth-links">
        <a href="<?= url('auth/login') ?>">← Voltar ao login</a>
    </div>
</form>
