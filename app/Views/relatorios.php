<?php
declare(strict_types=1);
/**
 * View: app/Views/relatorios.php
 * Variáveis do RelatorioController (via extract): $erro, $pets, $petAtual, $petId, $periodo, $inicio, $fim,
 *   $rotuloPeriodo, $dias, $total, $kgLiberado, $kgConsumido, $temConsumo, $preco, $temPreco, $custoTotal, $media, $topo, $ticks, $consulta
 */
$titulo    = 'Relatórios';
$menuAtivo = 'relatorios';
require __DIR__ . '/partials/app_head.php';

$fmtMoeda = static fn (float $v): string => 'R$ ' . number_format($v, 2, ',', '.');
$fmtPeso = static fn (float $g): string => $g >= 1000 ? rtrim(rtrim(number_format($g / 1000, 2, ',', ''), '0'), ',') . ' kg' : (int) round($g) . ' g';
$aba    = static fn (string $p): string => '/relatorios?' . http_build_query(['pet' => $petId, 'periodo' => $p]);
?>
        <header class="cab">
            <a class="seta" href="/painel" aria-label="Voltar"><svg aria-hidden="true"><use href="#i-seta"/></svg></a>
            <div>
                <h1>Relatório</h1>
                <p class="sub">Histórico de alimentação do seu pet.</p>
            </div>
        </header>

        <?php if (!empty($erro)): ?><div class="alerta" role="alert"><?= $e($erro) ?></div><?php endif; ?>

        <?php if ($petAtual === null): ?>
            <div class="vazio">
                <p>Cadastre um pet para acompanhar o histórico de alimentação.</p>
                <a class="btn" href="/pets/novo"><svg aria-hidden="true"><use href="#i-mais"/></svg>Cadastrar pet</a>
            </div>
        <?php else: ?>

            <nav class="abas" aria-label="Período">
                <a href="<?= $e($aba('semanal')) ?>" class="<?= $periodo === 'semanal' ? 'ativa' : '' ?>"<?= $periodo === 'semanal' ? ' aria-current="true"' : '' ?>>Semanal</a>
                <a href="<?= $e($aba('mensal')) ?>" class="<?= $periodo === 'mensal' ? 'ativa' : '' ?>"<?= $periodo === 'mensal' ? ' aria-current="true"' : '' ?>>Mensal</a>
                <a href="<?= $e($aba('personalizado')) ?>" class="<?= $periodo === 'personalizado' ? 'ativa' : '' ?>"<?= $periodo === 'personalizado' ? ' aria-current="true"' : '' ?>>Personalizado</a>
            </nav>

            <!-- Mudar a data ou o pet recarrega o relatório sozinho (JavaScript no fim da página) -->
            <form id="filtros" action="/relatorios" method="GET">
                <input type="hidden" name="periodo" value="<?= $e($periodo) ?>">

                <div class="faixa">
                    <strong><?= $e($rotuloPeriodo) ?></strong>
                    <span>
                        <?php if ($periodo === 'mensal'): ?>
                            <input type="month" name="mes" value="<?= $e($inicio->format('Y-m')) ?>" aria-label="Mês">
                        <?php elseif ($periodo === 'personalizado'): ?>
                            <input type="date" name="de" value="<?= $e($inicio->format('Y-m-d')) ?>" aria-label="Data inicial">
                            <span class="ate">até</span>
                            <input type="date" name="ate" value="<?= $e($fim->format('Y-m-d')) ?>" aria-label="Data final">
                        <?php else: ?>
                            <input type="date" name="data" value="<?= $e($inicio->format('Y-m-d')) ?>" aria-label="Escolher semana (qualquer dia dela)">
                        <?php endif; ?>
                    </span>
                </div>

                <div class="linha-pet" style="margin-top: 12px">
                    <div class="select-simples">
                        <select name="pet" aria-label="Pet">
                            <?php foreach ($pets as $p): ?>
                                <option value="<?= (int) $p['id'] ?>"<?= (int) $p['id'] === $petId ? ' selected' : '' ?>><?= $e($p['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <a class="btn" href="/relatorios/pdf?<?= $e(http_build_query($consulta)) ?>" target="_blank" rel="noopener">Gerar PDF</a>
                </div>
            </form>

            <div class="rel-corpo">
                <div class="stats">
                    <div class="stat"><small>Total de refeições</small><strong><?= (int) $total ?></strong></div>
                    <div class="stat"><small>Ração liberada</small><strong><?= $e($fmtPeso($liberado)) ?></strong></div>
                    <div class="stat" title="<?= $temPreco ? 'Kg liberados x preço do kg (' . $e($fmtMoeda($preco)) . ')' : 'Informe o preço do kg da ração no cadastro do pet' ?>">
                        <small>Gasto com ração</small><strong><?= $temPreco ? $e($fmtMoeda($custoTotal)) : '—' ?></strong>
                    </div>
                    <div class="stat"><small>Média/refeição</small><strong><?= (int) $media ?> g</strong></div>
                </div>

                <section class="caixa grafico-caixa" aria-labelledby="t-grafico">
                    <h2 id="t-grafico">Frequência de Alimentação</h2>

                    <div class="grafico" role="img" aria-label="Gráfico de barras com o número de refeições por dia">
                        <div class="g-eixo">
                            <?php foreach ($ticks as $t): ?><span><?= (int) $t ?></span><?php endforeach; ?>
                        </div>
                        <div class="g-plot" style="--n: <?= max(1, count($ticks) - 1) ?>">
                            <?php foreach ($dias as $d): ?>
                                <div class="g-col" title="<?= $e($d['data']) ?>: <?= (int) $d['refeicoes'] ?> refeição(ões)">
                                    <div class="g-barra" style="height: <?= round($d['refeicoes'] / $topo * 100, 1) ?>%"></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="g-rotulos">
                            <?php foreach ($dias as $d): ?><span><?= $e($d['rotulo']) ?></span><?php endforeach; ?>
                        </div>
                    </div>

                    <?php if ($total === 0): ?>
                        <p class="sem-dados">Nenhuma refeição registrada para <?= $e($petAtual['nome']) ?> neste período.</p>
                    <?php endif; ?>
                </section>
            </div>

            <script>
                document.getElementById('filtros').addEventListener('change', function () { this.submit(); });
            </script>
        <?php endif; ?>
<?php require __DIR__ . '/partials/app_foot.php'; ?>
