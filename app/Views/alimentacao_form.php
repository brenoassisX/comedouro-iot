<?php
declare(strict_types=1);
/**
 * View: app/Views/alimentacao_form.php  (adicionar e editar horário)
 * Variáveis do AlimentacaoController: $modo, $erro, $old, $id (int|null), $dispositivo (array)
 */
$titulo    = $modo === 'editar' ? 'Editar horário' : 'Adicionar horário';
$menuAtivo = 'alimentacao';
require __DIR__ . '/partials/app_head.php';

$old    = $old ?? [];
$voltar = '/alimentacao?dispositivo=' . (int) $dispositivo['id'];
?>
        <header class="cab">
            <a class="seta" href="<?= $e($voltar) ?>" aria-label="Voltar"><svg aria-hidden="true"><use href="#i-seta"/></svg></a>
            <div>
                <h1><?= $e($titulo) ?></h1>
                <p class="sub">Comedouro: <?= $e($dispositivo['nome']) ?></p>
            </div>
        </header>

        <?php if (!empty($erro)): ?><div class="alerta" role="alert"><?= $e($erro) ?></div><?php endif; ?>

        <form class="caixa" action="<?= $modo === 'editar' ? '/alimentacao/atualizar' : '/alimentacao' ?>" method="POST" novalidate>
            <?php if ($modo === 'editar'): ?>
                <input type="hidden" name="id" value="<?= (int) $id ?>">
            <?php else: ?>
                <input type="hidden" name="dispositivo_id" value="<?= (int) $dispositivo['id'] ?>">
            <?php endif; ?>

            <div class="campos">
                <div class="c6">
                    <label for="horario">Horário</label>
                    <div class="campo">
                        <svg class="icone" aria-hidden="true"><use href="#i-relogio"/></svg>
                        <input type="time" id="horario" name="horario" required value="<?= $e($old['horario'] ?? '') ?>">
                    </div>
                </div>

                <div class="c6">
                    <label for="quantidade">Quantidade (g)</label>
                    <div class="campo">
                        <svg class="icone" aria-hidden="true"><use href="#i-balanca"/></svg>
                        <input type="number" id="quantidade" name="quantidade" min="1" max="2000" step="1" inputmode="numeric" placeholder="Ex: 50" required value="<?= $e($old['quantidade'] ?? '') ?>"
                               data-plano="estimativa"
                               data-nivel="<?= $dispositivo['nivel_racao'] !== null ? (int) $dispositivo['nivel_racao'] : '' ?>"
                               data-g90-vazio="<?= (float) $dispositivo['servo_g90_vazio'] ?>"
                               data-g90-cheio="<?= (float) $dispositivo['servo_g90_cheio'] ?>">
                    </div>
                    <p class="ajuda">É o quanto deve haver na tigela: se já tiver ração, o comedouro libera só o que falta. Sugestão: a porção calculada no perfil do pet.</p>
                    <p class="ajuda" id="estimativa" aria-live="polite"></p>
                </div>
            </div>

            <div class="acoes-form" style="margin-top: 20px">
                <button type="submit" class="btn">Salvar</button>
                <a class="btn btn-sec" href="<?= $e($voltar) ?>">Cancelar</a>
            </div>
        </form>
<script src="/assets/plano.js"></script>
<?php require __DIR__ . '/partials/app_foot.php'; ?>
