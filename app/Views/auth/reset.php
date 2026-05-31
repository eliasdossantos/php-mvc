<form method="POST" action="<?= url('auth/reset-password') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">

    <div class="form-group">
        <label for="password">Nova Senha</label>
        <input type="password" id="password" name="password"
               class="form-control" required autofocus minlength="6">
    </div>

    <div class="form-group">
        <label for="confirm">Confirmar Nova Senha</label>
        <input type="password" id="confirm" name="confirm"
               class="form-control" required minlength="6">
    </div>

    <button type="submit" class="btn btn-primary btn-block">Redefinir Senha</button>
</form>
