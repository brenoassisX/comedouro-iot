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

            <?php
                $statusTextos = [
                    'pendente'   => 'Aguardando o comedouro',
                    'enviado'    => 'Pedido recebido pelo comedouro',
                    'concluido'  => 'Concluída',
                    'dispensado' => 'Dispensada: a tigela já estava cheia',
                    'falha'      => 'Falhou',
                ];
                $fmtG  = static fn ($v): string => $v === null ? '—' : (int) round((float) $v) . ' g';
                // Sobra na tigela = o que havia depois de liberar menos o que foi consumido (só com leitura da balança)
                $sobra = ($ultima !== null && $ultima['peso_depois_g'] !== null && $ultima['consumido_g'] !== null)
                    ? max(0.0, (float) $ultima['peso_depois_g'] - (float) $ultima['consumido_g']) : null;
                // Já havia na tigela quando o pedido chegou (leituras negativas = ruído da balança)
                $jaNaTigela = ($ultima !== null && $ultima['peso_antes_g'] !== null) ? max(0.0, (float) $ultima['peso_antes_g']) : null;
            ?>
            <section class="caixa manual" aria-labelledby="t-manual">
                <h3 id="t-manual">Alimentação manual</h3>
                <p>Libere uma refeição fora do horário programado.</p>

                <form class="manual-form" action="/alimentacao/liberar" method="POST">
                    <input type="hidden" name="dispositivo_id" value="<?= (int) $atual['id'] ?>">
                    <div class="manual-campo">
                        <label for="quantidade_manual">Quantidade (g)</label>
                        <input class="input-cfg" type="number" id="quantidade_manual" name="quantidade" min="1" max="2000" step="1" inputmode="numeric"
                               value="<?= (int) ($atual['porcao_padrao_g'] ?? 50) ?>">
                    </div>
                    <button type="submit" class="btn"><svg aria-hidden="true"><use href="#i-mais"/></svg>Liberar agora</button>
                </form>
                <p class="ajuda">A tigela é <strong>completada</strong> até a quantidade pedida: se já houver ração, só a diferença é liberada (margem de erro da balança: 5 g). Se a tigela já estiver cheia, nada é liberado.</p>

                <?php if ($ultima !== null): ?>
                    <div class="ultima" aria-live="polite">
                        <div class="ultima-cab">
                            <strong>Última liberação <span class="num">#<?= (int) $ultima['id'] ?></span></strong>
                            <span class="ultima-status<?= $ultima['status'] === 'concluido' ? ' ok' : '' ?>"><?= $e($statusTextos[$ultima['status']] ?? $ultima['status']) ?></span>
                        </div>
                        <div class="ultima-grade">
                            <div><small>Pedida (alvo)</small><strong><?= (int) $ultima['porcao_g'] ?> g</strong></div>
                            <div><small>Já na tigela</small><strong><?= $e($fmtG($jaNaTigela)) ?></strong></div>
                            <div><small>Enviada ao motor</small><strong><?= $e($fmtG($ultima['enviado_g'])) ?></strong></div>
                            <div><small>Liberada (medida)</small><strong><?= $e($fmtG($ultima['liberado_g'])) ?></strong></div>
                            <div><small>Consumida</small><strong><?= $e($fmtG($ultima['consumido_g'])) ?></strong></div>
                            <div><small>Sobra na tigela</small><strong><?= $e($fmtG($sobra)) ?></strong></div>
                        </div>
                        <p class="ajuda">Liberada, consumida e sobra vêm da balança da tigela (por até 60 min depois de liberar). Sem leitura de peso aparece "—".</p>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
<?php require __DIR__ . '/partials/app_foot.php'; ?>
