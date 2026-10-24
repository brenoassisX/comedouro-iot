<?php
declare(strict_types=1);
/**
 * View: app/Views/reenviar_confirmacao.php
 * Variáveis do Controller: $erro, $sucesso (string|null), $csrf (string)
 */
$titulo = 'Confirmar e-mail';
require __DIR__ . '/partials/auth_head.php';
?>
    <h1>Confirme seu e-mail</h1>
    <p class="sub">Digite o e-mail cadastrado e enviaremos um novo link de confirmação (válido por 24 horas).</p>

    <?php if (!empty($erro)): ?><div class="alerta" role="alert"><?= $e($erro) ?></div><?php endif; ?>
    <?php if (!empty($sucesso)): ?><div class="alerta ok" role="status"><?= $e($sucesso) ?></div><?php endif; ?>

    <form action="/reenviar-confirmacao" method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $e($csrf ?? '') ?>">

        <div class="grupo">
            <label for="email">E-mail</label>
            <div class="campo">
                <svg class="icone" aria-hidden="true"><use href="#i-mail"/></svg>
                <input type="email" id="email" name="email" placeholder="seu@email.com" autocomplete="email" required>
            </div>
        </div>

        <button type="submit" class="btn btn-verde">Reenviar confirmação</button>
    </form>

    <a class="voltar" href="/login">Voltar para o login</a>
<?php require __DIR__ . '/partials/auth_foot.php'; ?>
