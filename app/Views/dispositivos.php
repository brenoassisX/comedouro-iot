<?php
declare(strict_types=1);
/**
 * View: app/Views/dispositivos.php
 * Variáveis do DispositivoController::index():
 *   $dispositivos (array), $atual (array|null), $status (array|null), $erro, $sucesso
 */
$titulo    = 'Dispositivos';
$menuAtivo = 'dispositivos';
$extraHead = $atual !== null ? '<meta http-equiv="refresh" content="15">' : ''; // atualiza nível e status sozinho
require __DIR__ . '/partials/app_head.php';
?>
        <header class="cab">
            <h1>Dispositivos</h1>
            <div class="cab-acoes">
                <a class="btn btn-peq" href="/dispositivos/novo"><svg aria-hidden="true"><use href="#i-mais"/></svg>Adicionar comedouro</a>
            </div>
        </header>

        <?php if (!empty($erro)): ?><div class="alerta" role="alert"><?= $e($erro) ?></div><?php endif; ?>
        <?php if (!empty($sucesso)): ?><div class="alerta ok" role="status"><?= $e($sucesso) ?></div><?php endif; ?>

        <?php if ($atual === null): ?>
            <div class="vazio">
                <p>Você ainda não cadastrou nenhum comedouro.</p>
                <a class="btn" href="/dispositivos/novo"><svg aria-hidden="true"><use href="#i-mais"/></svg>Cadastrar meu primeiro comedouro</a>
            </div>
        <?php else: ?>

            <?php if (count($dispositivos) > 1): ?>
                <nav class="chips" aria-label="Escolher comedouro">
                    <?php foreach ($dispositivos as $d): ?>
                        <a class="chip" href="/dispositivos?id=<?= (int) $d['id'] ?>"<?= (int) $d['id'] === (int) $atual['id'] ? ' aria-current="true"' : '' ?>><?= $e($d['nome']) ?></a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>

            <div class="disp-linha">
                <section class="disp-card" aria-label="Comedouro">
                    <div class="disp-foto">
                        <?php if (!empty($atual['pet_foto'])): ?>
                            <img src="/uploads/pets/<?= $e($atual['pet_foto']) ?>" alt="Foto de <?= $e($atual['pet_nome']) ?>">
                        <?php else: ?>
                            <svg aria-hidden="true"><use href="#i-pets"/></svg>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h2 class="disp-nome"><?= $e($atual['nome']) ?></h2>
                        <span class="online<?= $status['online'] ? '' : ' off' ?>"><?= $status['online'] ? 'Online' : 'Offline' ?></span>
                        <div class="disp-meta">
                            <span><?= $e($status['codigo']) ?> · <?= $e($atual['codigo_mac_esp']) ?></span>
                            <span>Pet: <?= $atual['pet_nome'] !== null ? $e($atual['pet_nome']) : 'sem pet vinculado' ?></span>
                            <span>Comporta: ≈ <?= (int) round(((float) $atual['servo_g90_vazio'] + (float) $atual['servo_g90_cheio']) / 2 * 40 / 90) ?> g a cada 40° de abertura</span>
                            <span>Atualizado: <?= $e($status['atualizado']) ?></span>
                        </div>
                    </div>
                </section>

                <section class="caixa nivel-card" aria-label="Nível de ração">
                    <h2>Nível de ração</h2>
                    <div class="barra-trilho" role="img" aria-label="<?= $e($status['nivel_texto']) ?>">
                        <div class="barra-preench nivel-<?= $e($status['nivel_classe']) ?>" style="width: <?= (int) $status['nivel_barra'] ?>%"></div>
                    </div>
                    <p class="nivel-pct"><?= $e($status['nivel_texto']) ?></p>
                </section>
            </div>

            <section class="caixa" aria-label="Status atual">
                <h2>Status atual</h2>
                <div class="status-grid">
                    <div><small>Funcionamento</small><strong><?= $e($status['funcionamento']) ?></strong></div>
                    <div><small>Reservatório</small><strong><?= $e($status['reservatorio']) ?></strong></div>
                    <div><small>Conexão</small><strong><?= $e($status['conexao']) ?></strong></div>
                </div>
            </section>

            <div class="acoes-grandes">
                <a class="acao-grande" href="/relatorios<?= $atual['pet_id'] !== null ? '?pet=' . (int) $atual['pet_id'] : '' ?>">
                    <svg aria-hidden="true"><use href="#i-doc"/></svg>Ver relatórios
                </a>
                <a class="acao-grande" href="/dispositivos/editar?id=<?= (int) $atual['id'] ?>">
                    <svg aria-hidden="true"><use href="#i-config"/></svg>Configurações
                </a>
            </div>
        <?php endif; ?>
<?php require __DIR__ . '/partials/app_foot.php'; ?>
