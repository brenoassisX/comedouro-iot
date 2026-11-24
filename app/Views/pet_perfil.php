<?php
declare(strict_types=1);
/**
 * View: app/Views/pet_perfil.php  (Perfil alimentar)
 * Variáveis do PetController::perfil(): $pet (array), $calc (array|null - cálculo energético)
 */
$titulo    = 'Perfil alimentar';
$menuAtivo = 'pets';
require __DIR__ . '/partials/app_head.php';

// Formatadores (só para exibição)
$idadeTexto = static function (?int $m): string {
    if ($m === null) { return '—'; }
    if ($m < 12)     { return $m . ($m === 1 ? ' mês' : ' meses'); }
    $a = intdiv($m, 12);
    $r = $m % 12;
    $t = $a . ($a === 1 ? ' ano' : ' anos');
    return $r > 0 ? $t . ' e ' . $r . ($r === 1 ? ' mês' : ' meses') : $t;
};
$pesoTexto = static fn ($kg): string =>
    $kg === null ? '—' : rtrim(rtrim(number_format((float) $kg, 2, ',', ''), '0'), ',') . ' kg';

$portes    = ['pequeno' => 'Pequeno', 'medio' => 'Médio', 'grande' => 'Grande'];
$subtitulo = $pet['raca'] ?: ($pet['especie'] === 'gato' ? 'Gato' : 'Cão');
$meses     = $pet['idade_meses'] !== null ? (int) $pet['idade_meses'] : null;
?>
        <header class="cab">
            <a class="seta" href="/pets" aria-label="Voltar para Meus Pets"><svg aria-hidden="true"><use href="#i-seta"/></svg></a>
            <h1>Perfil alimentar</h1>
            <div class="cab-acoes">
                <a class="btn btn-sec btn-peq" href="/pets/editar?id=<?= (int) $pet['id'] ?>"><svg aria-hidden="true"><use href="#i-lapis"/></svg>Editar</a>

                <!-- Excluir é POST (nunca link): o JavaScript abaixo pede confirmação antes de enviar -->
                <form action="/pets/excluir" method="POST" id="formExcluir"
                      data-confirmar="Excluir <?= $e($pet['nome']) ?>? Esta ação não pode ser desfeita.">
                    <input type="hidden" name="id" value="<?= (int) $pet['id'] ?>">
                    <button type="submit" class="btn btn-perigo btn-peq">Excluir</button>
                </form>
            </div>
        </header>

        <section class="painel-pet" aria-label="Dados do pet">
            <div class="foto-box">
                <?php if (!empty($pet['foto'])): ?>
                    <img src="/uploads/pets/<?= $e($pet['foto']) ?>" alt="Foto de <?= $e($pet['nome']) ?>">
                <?php else: ?>
                    <svg aria-hidden="true"><use href="#i-pets"/></svg>
                <?php endif; ?>
            </div>
            <div>
                <h2><?= $e($pet['nome']) ?></h2>
                <p class="raca"><?= $e($subtitulo) ?></p>
                <ul class="meta">
                    <li><svg aria-hidden="true"><use href="#i-calendario"/></svg><?= $e($idadeTexto($meses)) ?></li>
                    <li><svg aria-hidden="true"><use href="#i-balanca"/></svg><?= $e($pesoTexto($pet['peso_kg'])) ?></li>
                    <li><svg aria-hidden="true"><use href="#i-tag"/></svg><?= $e($portes[$pet['porte']] ?? '—') ?></li>
                </ul>
            </div>
        </section>

        <?php if (!empty($pet['observacoes'])): ?>
            <p class="obs"><strong>Observações:</strong> <?= $e($pet['observacoes']) ?></p>
        <?php endif; ?>

        <section class="nutri" aria-labelledby="t-nutri">
            <h2 id="t-nutri">Dados nutricionais</h2>

            <?php if ($calc === null): ?>
                <div class="alerta" role="status">Este pet não tem peso cadastrado, por isso não foi possível calcular. Clique em Editar e informe o peso.</div>
            <?php else: ?>
                <div class="nutri-grade">
                    <div class="nutri-card">
                        <small class="rotulo">Necessidade energética diária</small>
                        <span class="valor"><?= number_format($calc['kcal_dia'], 0, ',', '.') ?> Kcal</span>
                        <span class="obs-valor">(Estimativa)</span>
                    </div>
                    <div class="nutri-card">
                        <small class="rotulo">Frequência sugerida</small>
                        <span class="valor"><?= (int) $calc['refeicoes'] ?> vezes ao dia</span>
                    </div>
                    <div class="nutri-card">
                        <small class="rotulo">Quantidade por refeição</small>
                        <span class="valor"><?= (int) $calc['g_refeicao'] ?>g</span>
                        <span class="obs-valor">(Aproximadamente)</span>
                    </div>
                </div>

                <p class="nota">
                    Estimativa para <?= $pet['especie'] === 'gato' ? 'gato' : 'cão' ?> na fase <strong><?= $e($calc['fase']) ?></strong>:
                    energia em repouso de <?= (int) $calc['rer'] ?> kcal × fator <?= $e(number_format($calc['fator'], 1, ',', '')) ?>,
                    considerando ração de <?= $e(number_format($calc['kcal_por_grama'], 1, ',', '')) ?> kcal/g
                    (≈ <?= (int) $calc['g_dia'] ?>g de ração por dia). Não considera castração, atividade física
                    nem condições de saúde; consulte um veterinário.
                </p>
            <?php endif; ?>
        </section>
<?php require __DIR__ . '/partials/app_foot.php'; ?>
