<?php
declare(strict_types=1);
/**
 * View: app/Views/painel.php  (Início / dashboard)
 * Variáveis do PainelController: $primeiroNome, $naoLidas, $erro, $pets, $pet (array|null), $posicao, $totalPets,
 *   $anteriorId, $seguinteId, $dispositivo (array|null), $status (array|null), $proxima (array|null), $calc (array|null)
 */
$titulo    = 'Início';
$menuAtivo = 'inicio';
require __DIR__ . '/partials/app_head.php';

// Formatadores (só para exibição)
$idadeTexto = static function (?int $m): string {
    if ($m === null) { return '—'; }
    if ($m < 12)     { return $m . ($m === 1 ? ' mês' : ' meses'); }
    $a = intdiv($m, 12);
    return $a . ($a === 1 ? ' ano' : ' anos');
};
$pesoTexto = static fn ($kg): string =>
    $kg === null ? '—' : rtrim(rtrim(number_format((float) $kg, 2, ',', ''), '0'), ',') . ' kg';
$portes = ['pequeno' => 'Pequeno', 'medio' => 'Médio', 'grande' => 'Grande'];
?>
        <div class="dash-topo">
            <header class="saudacao">
                <h1>Olá, <?= $e($primeiroNome) ?>!</h1>
                <p>Seja bem-vindo ao seu comedouro inteligente.</p>
            </header>
            <a class="sino" href="/notificacoes" aria-label="Notificações<?= $naoLidas > 0 ? ' (' . (int) $naoLidas . ' novas)' : '' ?>">
                <svg aria-hidden="true"><use href="#i-sino"/></svg>
                <?php if ($naoLidas > 0): ?><span class="sino-badge" aria-hidden="true"><?= $naoLidas > 9 ? '9+' : (int) $naoLidas ?></span><?php endif; ?>
            </a>
        </div>

        <?php if (!empty($erro)): ?><div class="alerta" role="alert"><?= $e($erro) ?></div><?php endif; ?>

        <?php if ($pet === null && empty($erro)): ?>
            <div class="vazio">
                <p>Cadastre seu primeiro pet para ver o resumo da alimentação aqui.</p>
                <a class="btn" href="/pets/novo"><svg aria-hidden="true"><use href="#i-mais"/></svg>Cadastrar pet</a>
            </div>
        <?php elseif ($pet !== null): ?>

            <div class="dash-2">
                <!-- Pet (com setas para alternar quando há mais de um) -->
                <section class="caixa dash-pet" aria-label="Pet">
                    <div class="foto-box">
                        <?php if (!empty($pet['foto'])): ?>
                            <img src="/uploads/pets/<?= $e($pet['foto']) ?>" alt="Foto de <?= $e($pet['nome']) ?>">
                        <?php else: ?>
                            <svg aria-hidden="true"><use href="#i-pets"/></svg>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h2><?= $e($pet['nome']) ?></h2>
                        <p class="raca"><?= $e($pet['raca'] ?: ($pet['especie'] === 'gato' ? 'Gato' : 'Cão')) ?></p>
                        <p class="mini-meta">
                            <?= $e($idadeTexto($pet['idade_meses'] !== null ? (int) $pet['idade_meses'] : null)) ?>
                            | <?= $e($pesoTexto($pet['peso_kg'])) ?>
                            | <?= $e($portes[$pet['porte']] ?? '—') ?>
                        </p>
                        <a class="btn-outline" href="/pets/perfil?id=<?= (int) $pet['id'] ?>">Ver perfil</a>
                    </div>

                    <?php if ($totalPets > 1): ?>
                        <nav class="pet-nav" aria-label="Alternar entre os pets">
                            <a class="seta-pet" href="/painel?pet=<?= (int) $anteriorId ?>" aria-label="Pet anterior"><svg aria-hidden="true"><use href="#i-chevron-esq"/></svg></a>
                            <span aria-live="polite"><?= (int) $posicao ?>/<?= (int) $totalPets ?></span>
                            <a class="seta-pet" href="/painel?pet=<?= (int) $seguinteId ?>" aria-label="Próximo pet"><svg aria-hidden="true"><use href="#i-chevron-dir"/></svg></a>
                        </nav>
                    <?php endif; ?>
                </section>

                <!-- Status do comedouro deste pet -->
                <section class="caixa dash-status" aria-label="Status do dispositivo">
                    <h2>Status do dispositivo</h2>
                    <?php if ($dispositivo === null): ?>
                        <p class="sem-info"><?= $e($pet['nome']) ?> ainda não tem um comedouro vinculado.</p>
                        <a class="btn-outline" href="/dispositivos/novo">Vincular comedouro</a>
                    <?php else: ?>
                        <span class="online<?= $status['online'] ? '' : ' off' ?>"><?= $status['online'] ? 'Online' : 'Offline' ?></span>
                        <p class="sub-linha">
                            Última atualização:
                            <?= !empty($dispositivo['ultimo_ping']) ? $e(date('d/m/Y - H:i', strtotime((string) $dispositivo['ultimo_ping']))) : 'nunca conectou' ?>
                        </p>
                        <a class="btn-outline" href="/dispositivos?id=<?= (int) $dispositivo['id'] ?>">Ver detalhes</a>
                    <?php endif; ?>
                </section>
            </div>

            <div class="dash-3">
                <!-- Próxima alimentação -->
                <section class="caixa mini-card" aria-label="Próxima alimentação">
                    <h3>Próxima alimentação</h3>
                    <?php if ($proxima !== null): ?>
                        <p class="mini-valor"><?= $e($proxima['horario']) ?><?= $proxima['amanha'] ? '<small>amanhã</small>' : '' ?></p>
                        <p class="mini-sub">Quantidade: <?= (int) $proxima['gramas'] ?> g</p>
                    <?php else: ?>
                        <p class="mini-valor">—</p>
                        <p class="mini-sub"><?= $dispositivo === null ? 'Sem comedouro vinculado' : 'Nenhum horário programado' ?></p>
                    <?php endif; ?>
                    <svg class="mini-icone" aria-hidden="true"><use href="#i-calendario-seta"/></svg>
                </section>

                <!-- Ração restante (vem do sensor ultrassônico) -->
                <section class="caixa mini-card" aria-label="Ração restante">
                    <h3>Ração restante</h3>
                    <?php if ($status !== null && $status['nivel'] !== null): ?>
                        <p class="mini-valor"><?= $e($status['nivel_texto']) ?></p>
                        <p class="mini-sub">No reservatório</p>
                    <?php else: ?>
                        <p class="mini-valor">—</p>
                        <p class="mini-sub"><?= $dispositivo === null ? 'Sem comedouro vinculado' : 'Sem leitura do sensor' ?></p>
                    <?php endif; ?>
                    <svg class="mini-icone" aria-hidden="true"><use href="#i-reservatorio"/></svg>
                </section>

                <!-- Necessidade energética -->
                <section class="caixa mini-card destaque" aria-label="Necessidade energética diária">
                    <h3>Necessidade energética diária</h3>
                    <?php if ($calc !== null): ?>
                        <p class="mini-valor"><?= number_format($calc['kcal_dia'], 0, ',', '.') ?><small>kcal</small></p>
                        <p class="mini-sub">(estimativa)</p>
                    <?php else: ?>
                        <p class="mini-valor">—</p>
                        <p class="mini-sub">Informe o peso do pet</p>
                    <?php endif; ?>
                    <svg class="mini-icone" aria-hidden="true"><use href="#i-raio"/></svg>
                </section>
            </div>
        <?php endif; ?>
<?php require __DIR__ . '/partials/app_foot.php'; ?>
