<?php
declare(strict_types=1);
/**
 * View: app/Views/redefinir_senha.php
 * Variáveis do Controller: $token (string), $erro (string|null), $csrf (string)
 */
$titulo = 'Nova senha';
require __DIR__ . '/partials/auth_head.php';
?>
    <h1>Criar nova senha</h1>
    <p class="sub">Escolha uma senha com pelo menos 8 caracteres. O link só pode ser usado uma vez.</p>

    <?php if (!empty($erro)): ?><div class="alerta" role="alert"><?= $e($erro) ?></div><?php endif; ?>

    <form action="/redefinir-senha" method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $e($csrf ?? '') ?>">
        <input type="hidden" name="token" value="<?= $e($token ?? '') ?>">

        <div class="grupo">
            <label for="senha">Nova senha</label>
            <div class="campo">
                <svg class="icone" aria-hidden="true"><use href="#i-lock"/></svg>
                <input type="password" id="senha" name="senha" placeholder="Pelo menos 8 caracteres"
                       autocomplete="new-password" minlength="8" required>
            </div>
        </div>

        <div class="grupo">
            <label for="confirmar_senha">Confirmar nova senha</label>
            <div class="campo">
                <svg class="icone" aria-hidden="true"><use href="#i-lock"/></svg>
                <input type="password" id="confirmar_senha" name="confirmar_senha" placeholder="Repita a senha"
                       autocomplete="new-password" minlength="8" required>
            </div>
        </div>

        <button type="submit" class="btn btn-verde">Salvar nova senha</button>
    </form>

    <a class="voltar" href="/login">Cancelar e voltar ao login</a>
<?php require __DIR__ . '/partials/auth_foot.php'; ?>
