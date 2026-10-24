<?php
declare(strict_types=1);
/**
 * View: app/Views/alimentacao.php
 * Variáveis do AlimentacaoController::index():
 *   $dispositivos, $atual (array|null), $horarios (array), $erro, $sucesso
 */
$titulo    = 'Alimentação';
$menuAtivo = 'alimentacao';
require __DIR__ . '/partials/app_head.php';
?>
        <?php if (!empty($erro)): ?><div class="alerta" role="alert"><?= $e($erro) ?></div><?php endif; ?>
        <?php if (!empty($sucesso)): ?><div class="alerta ok" role="status"><?= $e($sucesso) ?></div><?php endif; ?>

        <?php if ($atual === null): ?>
            <div class="vazio">
                <p>Cadastre um comedouro para programar os horários de alimentação.</p>
                <a class="btn" href="/dispositivos/novo"><svg aria-hidden="true"><use href="#i-mais"/></svg>Cadastrar comedouro</a>
            </div>
        <?php else: ?>

            <?php if (count($dispositivos) > 1): ?>
                <nav class="chips" aria-label="Escolher comedouro">
                    <?php foreach ($dispositivos as $d): ?>
                        <a class="chip" href="/alimentacao?dispositivo=<?= (int) $d['id'] ?>"<?= (int) $d['id'] === (int) $atual['id'] ? ' aria-current="true"' : '' ?>><?= $e($d['nome']) ?></a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>

            <section class="caixa" aria-labelledby="t-horarios">
                <h2 id="t-horarios">Alimentação</h2>

                <div class="tab-linha tab-cab">
                    <span>Horário</span><span>Quantidade</span><span class="tab-acoes">Ações</span>
                </div>

                <?php if (empty($horarios)): ?>
                    <p class="vazio-lista">Nenhum horário programado para <?= $e($atual['nome']) ?>.</p>
                <?php endif; ?>

                <?php foreach ($horarios as $h): ?>
                    <?php $hora = substr((string) $h['horario'], 0, 5); ?>
                    <div class="tab-linha tab-item">
                        <span><?= $e($hora) ?></span>
                        <span><?= (int) $h['porcao_g'] ?> g</span>
                        <span class="tab-acoes">
                            <a class="icon-btn" href="/alimentacao/editar?id=<?= (int) $h['id'] ?>" aria-label="Editar horário das <?= $e($hora) ?>"><svg aria-hidden="true"><use href="#i-lapis"/></svg></a>
                            <form action="/alimentacao/excluir" method="POST" data-confirmar="Excluir o horário das <?= $e($hora) ?>?">
                                <input type="hidden" name="id" value="<?= (int) $h['id'] ?>">
                                <button type="submit" class="icon-btn" aria-label="Excluir horário das <?= $e($hora) ?>"><svg aria-hidden="true"><use href="#i-lixeira"/></svg></button>
                            </form>
                        </span>
                    </div>
                <?php endforeach; ?>

                <a class="btn" href="/alimentacao/novo?dispositivo=<?= (int) $atual['id'] ?>"><svg aria-hidden="true"><use href="#i-mais"/></svg>Adicionar horário</a>
            </section>

            <section class="caixa manual" aria-labelledby="t-manual">
                <h3 id="t-manual">Alimentação manual</h3>
                <p>Libere uma refeição fora do horário programado.</p>
                <form action="/alimentacao/liberar" method="POST">
                    <input type="hidden" name="dispositivo_id" value="<?= (int) $atual['id'] ?>">
                    <button type="submit" class="btn"><svg aria-hidden="true"><use href="#i-mais"/></svg>Liberar agora</button>
                </form>
            </section>
        <?php endif; ?>
<?php require __DIR__ . '/partials/app_foot.php'; ?>
