<?php
declare(strict_types=1);
/**
 * View: app/Views/alimentacao.php
 * Variáveis do AlimentacaoController::index():
 *   $dispositivos, $atual (array|null), $horarios (array), $erro, $sucesso
 */
$titulo    = 'Alimentação';
$menuAtivo = 'alimentacao';
// Enquanto o pedido espera o comedouro (pendente/enviado), a página se atualiza sozinha a cada 10 s
$extraHead = ($ultima !== null && in_array($ultima['status'], ['pendente', 'enviado'], true))
    ? '<meta http-equiv="refresh" content="10">' : '';
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
                        <?php $nomeP = \App\Models\Porcao::nomePorGramas($porcoes, (int) $h['porcao_g']); ?>
                        <span><?= $nomeP !== null ? $e($nomeP) . ' · ' : '' ?><?= (int) $h['porcao_g'] ?> g</span>
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

            <?php
                $statusTextos = [
                    'pendente'   => 'Aguardando o comedouro',
                    'enviado'    => 'Pedido recebido pelo comedouro',
                    'concluido'  => 'Concluída',
                    'dispensado' => 'Dispensada',
                    'falha'      => 'Falhou',
                ];
                $nomeUltima = $ultima !== null ? \App\Models\Porcao::nomePorGramas($porcoes, (int) $ultima['porcao_g']) : null;
            ?>
            <section class="caixa manual" aria-labelledby="t-manual">
                <h3 id="t-manual">Alimentação manual</h3>
                <p>Libere uma refeição fora do horário programado.</p>

                <form class="manual-form" action="/alimentacao/liberar" method="POST">
                    <input type="hidden" name="dispositivo_id" value="<?= (int) $atual['id'] ?>">
                    <div class="manual-campo">
                        <label for="porcao_manual">Porção</label>
                        <select class="input-cfg" id="porcao_manual" name="porcao">
                            <?php foreach ($porcoes as $chave => $p): ?>
                                <option value="<?= $e($chave) ?>"<?= $chave === 'media' ? ' selected' : '' ?>><?= $e($p['nome']) ?> (<?= (int) $p['gramas'] ?> g)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn"><svg aria-hidden="true"><use href="#i-mais"/></svg>Liberar agora</button>
                </form>
                <p class="ajuda">O comedouro abre a comporta o suficiente para liberar a quantidade da porção escolhida e fecha em seguida.</p>

                <?php if ($ultima !== null): ?>
                    <div class="ultima" aria-live="polite">
                        <div class="ultima-cab">
                            <strong>Última liberação <span class="num">#<?= (int) $ultima['id'] ?></span></strong>
                            <span class="ultima-status<?= $ultima['status'] === 'concluido' ? ' ok' : '' ?>"><?= $e($statusTextos[$ultima['status']] ?? $ultima['status']) ?></span>
                        </div>
                        <div class="ultima-grade">
                            <div><small>Porção</small><strong><?= $nomeUltima !== null ? $e($nomeUltima) : '—' ?></strong></div>
                            <div><small>Quantidade</small><strong><?= (int) $ultima['porcao_g'] ?> g</strong></div>
                            <?php if ($ultima['angulo_graus'] !== null): ?>
                                <div><small>Comporta</small><strong><?= (int) $ultima['pulsos'] ?> × <?= (int) $ultima['angulo_graus'] ?>°</strong></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </section>

            <section class="caixa" aria-labelledby="t-porcoes">
                <h3 id="t-porcoes">Tamanhos das porções</h3>
                <p>Troque o nome e a quantidade de cada tamanho. Eles aparecem na alimentação manual e nos horários.</p>
                <form action="/alimentacao/porcoes" method="POST" novalidate>
                    <input type="hidden" name="dispositivo_id" value="<?= (int) $atual['id'] ?>">
                    <div class="tab-linha tab-cab"><span>Nome</span><span>Gramas</span><span class="tab-acoes">Comporta</span></div>
                    <?php foreach ($porcoes as $chave => $p): ?>
                        <div class="tab-linha tab-item">
                            <span><input class="input-cfg" type="text" name="nome_<?= $e($chave) ?>" maxlength="30" required value="<?= $e($p['nome']) ?>" aria-label="Nome do tamanho <?= $e($chave) ?>"></span>
                            <span><input class="input-cfg" type="number" name="gramas_<?= $e($chave) ?>" min="1" max="500" step="1" inputmode="numeric" required value="<?= (int) $p['gramas'] ?>" aria-label="Gramas do tamanho <?= $e($chave) ?>"></span>
                            <span class="tab-acoes">≈ <?= $e(\App\Models\Porcao::anguloTexto((int) $p['gramas'], $atual)) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <p class="ajuda">Cada abertura é de 30° e libera cerca de 20 g; porções maiores repetem a abertura, com 6 s de intervalo entre elas. Horários já criados guardam a quantidade de quando foram salvos.</p>
                    <button type="submit" class="btn">Salvar porções</button>
                </form>
            </section>
        <?php endif; ?>
<?php require __DIR__ . '/partials/app_foot.php'; ?>
